<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Config\VendorAuth;
use Infixs\CorreiosAutomatico\Config\VendorSettings;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\APIServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\ContractType;
use Infixs\CorreiosAutomatico\Services\Correios\VendorApiFactory;
use Infixs\CorreiosAutomatico\Utils\Formatter;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and stores the Correios contract of a vendor.
 *
 * Shared by every marketplace extension so a vendor contract is validated the
 * same way whatever dashboard it was typed into.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.2
 */
class VendorContractService {

	/**
	 * Validate the credentials at Correios and persist them for the vendor.
	 *
	 * @param int   $vendor_id Vendor ID.
	 * @param array $input     Raw credentials with active, environment, user_name, access_code and postcard.
	 *
	 * @return array|\WP_Error Stored auth settings, or the API error.
	 */
	public function save( $vendor_id, $input ) {
		$settings = $this->validate( $input );

		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		$vendor_settings = VendorSettings::get( $vendor_id );
		$vendor_settings->auth = new VendorAuth( $settings );
		$vendor_settings->save();

		return $settings;
	}

	/**
	 * Validate the credentials against the Correios postcard endpoint.
	 *
	 * A deactivated contract skips the round trip and clears the stored data.
	 *
	 * @param array $input Raw credentials.
	 *
	 * @return array|\WP_Error
	 */
	public function validate( $input ) {
		$is_active = isset( $input['active'] ) ? rest_sanitize_boolean( $input['active'] ) : false;

		if ( ! $is_active ) {
			return [
				'active' => false,
				'environment' => isset( $input['environment'] ) ? sanitize_text_field( $input['environment'] ) : 'production',
				'user_name' => '',
				'access_code' => '',
				'postcard' => '',
				'token' => '',
				'contract_number' => '',
				'contract_type' => '',
				'contract_document' => '',
				'allowed_services' => [],
			];
		}

		$required = [
			'user_name' => __( 'O usuário dos Correios é obrigatório.', 'infixs-correios-automatico' ),
			'access_code' => __( 'O código de acesso dos Correios é obrigatório.', 'infixs-correios-automatico' ),
			'postcard' => __( 'O cartão de postagem é obrigatório.', 'infixs-correios-automatico' ),
			'environment' => __( 'O ambiente é obrigatório.', 'infixs-correios-automatico' ),
		];

		foreach ( $required as $field => $message ) {
			if ( empty( $input[ $field ] ) ) {
				return new \WP_Error( 'missing_' . $field, $message, [ 'status' => 400 ] );
			}
		}

		$environment = sanitize_text_field( $input['environment'] );
		$user_name = trim( sanitize_text_field( $input['user_name'] ) );
		$access_code = trim( sanitize_text_field( $input['access_code'] ) );
		$postcard = trim( sanitize_text_field( $input['postcard'] ) );

		$correios_api = VendorApiFactory::fromCredentials( [
			'user_name' => $user_name,
			'access_code' => $access_code,
			'postcard' => $postcard,
			'environment' => $environment,
		] );

		$postcard_response = $correios_api->auth_postcard( $user_name, $access_code, $postcard, $environment );

		if ( is_wp_error( $postcard_response ) ) {
			return $postcard_response;
		}

		if ( ! is_array( $postcard_response ) || empty( $postcard_response['token'] ) ) {
			return new \WP_Error(
				'auth_error',
				__( 'Não foi possível autenticar o contrato nos Correios. Confira as credenciais e tente novamente.', 'infixs-correios-automatico' ),
				[ 'status' => 400 ]
			);
		}

		$allowed_services = [];

		if ( isset( $postcard_response['cartaoPostagem']['apis'] ) && is_array( $postcard_response['cartaoPostagem']['apis'] ) ) {
			$allowed_services = array_column( $postcard_response['cartaoPostagem']['apis'], 'api' );
		}

		$profile = isset( $postcard_response['perfil'] ) ? sanitize_text_field( $postcard_response['perfil'] ) : 'PF';

		if ( $profile === 'PJ' ) {
			$document = isset( $postcard_response['cnpj'] ) ? $postcard_response['cnpj'] : '';
		} else {
			$document = isset( $postcard_response['cpf'] ) ? $postcard_response['cpf'] : '';
		}

		return [
			'active' => true,
			'environment' => $environment,
			'user_name' => $user_name,
			'access_code' => $access_code,
			'postcard' => $postcard,
			'token' => $postcard_response['token'],
			'allowed_services' => $allowed_services,
			'contract_number' => sanitize_text_field( isset( $postcard_response['cartaoPostagem']['contrato'] ) ? $postcard_response['cartaoPostagem']['contrato'] : '' ),
			'contract_type' => $profile,
			'contract_document' => sanitize_text_field( $document ),
		];
	}

	/**
	 * Format the stored settings for display.
	 *
	 * @param array $settings Stored auth settings.
	 *
	 * @return array
	 */
	public function prepareData( $settings ) {
		return [
			'active' => isset( $settings['active'] ) ? rest_sanitize_boolean( $settings['active'] ) : false,
			'environment' => isset( $settings['environment'] ) ? sanitize_text_field( $settings['environment'] ) : '',
			'user_name' => isset( $settings['user_name'] ) ? sanitize_text_field( $settings['user_name'] ) : '',
			'access_code' => isset( $settings['access_code'] ) ? sanitize_text_field( $settings['access_code'] ) : '',
			'postcard' => isset( $settings['postcard'] ) ? sanitize_text_field( $settings['postcard'] ) : '',
			'token' => isset( $settings['token'] ) ? sanitize_text_field( $settings['token'] ) : '',
			'contract_number' => isset( $settings['contract_number'] ) ? sanitize_text_field( $settings['contract_number'] ) : '',
			'contract_type' => ! empty( $settings['contract_type'] ) ? sanitize_text_field( ContractType::getDescription( $settings['contract_type'] ) ) : '',
			'contract_document' => ! empty( $settings['contract_document'] ) ? sanitize_text_field( Formatter::format_document( $settings['contract_document'] ) ) : '',
			'allowed_services' => isset( $settings['allowed_services'] ) && is_array( $settings['allowed_services'] )
				? array_map( [ APIServiceCode::class, 'getDescription' ], $settings['allowed_services'] )
				: [],
		];
	}
}
