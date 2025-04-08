<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\ContractType;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\APIServiceCode;
use Infixs\CorreiosAutomatico\Utils\Formatter;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;
class SettingsAuthController {

	/**
	 * Auth settings save
	 * 
	 * @since 1.0.0
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function save( $request ) {
		$data = $request->get_json_params();

		$is_active = rest_sanitize_boolean( $data['active'] );
		$allowed_services = [];

		$contract_settings = [ 
			'token' => '',
			'allowed_services' => [],
			'contract_type' => '',
			'contract_document' => '',
		];

		if ( $is_active ) {
			if ( ! isset( $data['user_name'] ) || empty( $data['user_name'] ) ) {
				return new \WP_Error( 'missing_user_name', 'User name is required', [ 'status' => 400 ] );
			}
			if ( ! isset( $data['access_code'] ) || empty( $data['access_code'] ) ) {
				return new \WP_Error( 'missing_access_code', 'Access code is required', [ 'status' => 400 ] );
			}
			if ( ! isset( $data['environment'] ) || empty( $data['environment'] ) ) {
				return new \WP_Error( 'missing_environment', 'Environment is required', [ 'status' => 400 ] );
			}
			if ( ! isset( $data['postcard'] ) || empty( $data['postcard'] ) ) {
				return new \WP_Error( 'missing_postcard', 'Postcard is required', [ 'status' => 400 ] );
			}

			$environment = sanitize_text_field( $data['environment'] );
			$user_name = trim( sanitize_text_field( $data['user_name'] ) );
			$access_code = trim( sanitize_text_field( $data['access_code'] ) );
			$postcard = trim( sanitize_text_field( $data['postcard'] ) );

			$postcard_response = Container::correiosService()->auth_postcard( $user_name, $access_code, $postcard, $environment );
			if ( is_wp_error( $postcard_response ) ) {
				return $postcard_response;
			}

			if ( ! isset( $postcard_response, $postcard_response['token'] ) ) {
				return new \WP_Error( 'auth_error', 'Authentication error', [ 'status' => 400 ] );
			}

			$allowed_services = [];

			if ( isset( $postcard_response['cartaoPostagem'], $postcard_response['cartaoPostagem']['apis'] ) ) {
				$allowed_services = array_column( $postcard_response['cartaoPostagem']['apis'], 'api' );
			}

			$contract_settings = [ 
				'environment' => $environment,
				'user_name' => $user_name,
				'access_code' => $access_code,
				'postcard' => $postcard,
				'token' => $postcard_response['token'],
				'allowed_services' => $allowed_services ?? [],
				'contract_number' => sanitize_text_field( isset( $postcard_response['cartaoPostagem']['contrato'] ) ? $postcard_response['cartaoPostagem']['contrato'] : '' ),
				'contract_type' => sanitize_text_field( $postcard_response['perfil'] ?? 'PF' ),
				'contract_document' => sanitize_text_field( isset( $postcard_response['perfil'] ) && $postcard_response['perfil'] === 'PJ' ? ( $postcard_response['cnpj'] ?? '' ) : ( $postcard_response['cpf'] ?? '' ) ),
			];
		}

		$updated_settings = array_merge( $contract_settings, [ 
			'active' => rest_sanitize_boolean( $data['active'] ),
		] );

		Config::update( 'auth', $updated_settings );

		$response_data = $this->prepare_data();

		$response = [ 
			'status' => 'success',
			'auth' => $response_data,
		];
		return rest_ensure_response( $response );
	}

	public function retrieve() {
		$settings = Config::get( 'auth' );

		$sanitized_settings = $this->prepare_data();

		return rest_ensure_response( $sanitized_settings );
	}

	/**
	 * Prepare the data
	 *
	 * @since 1.0.0
	 * @param array $settings
	 * @return array
	 */
	public function prepare_data() {
		$sanitized_settings = [ 
			'active' => Config::boolean( 'auth.active' ),
			'environment' => sanitize_text_field( Config::get( 'auth.environment' ) ),
			'user_name' => sanitize_text_field( Config::get( 'auth.user_name' ) ),
			'access_code' => sanitize_text_field( Config::get( 'auth.access_code' ) ),
			'postcard' => sanitize_text_field( Config::get( 'auth.postcard' ) ),
			'token' => sanitize_text_field( Config::get( 'auth.token' ) ),
			'contract_number' => sanitize_text_field( Config::get( 'auth.contract_number' ) ),
			'contract_type' => sanitize_text_field( ContractType::getDescription( Config::get( 'auth.contract_type' ) ) ),
			'contract_document' => sanitize_text_field( Formatter::format_document( Config::get( 'auth.contract_document' ) ) ),
			'allowed_services' => array_map( [ $this, 'map_services' ], Config::get( 'auth.allowed_services' ) ?? [] ),
		];

		return $sanitized_settings;
	}




	/**
	 * Map the services
	 *
	 * @param int $service
	 * @return string
	 */
	private function map_services( $service ) {
		return APIServiceCode::getDescription( $service );
	}

}