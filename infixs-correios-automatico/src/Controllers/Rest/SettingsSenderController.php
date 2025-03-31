<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;
use Infixs\CorreiosAutomatico\Validators\SettingsSenderValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Sender Controller
 * 
 * @since 1.0.0
 * 
 * @package Infixs\CorreiosAutomatico\Controllers\Rest
 */
class SettingsSenderController {

	/**
	 * Sender settings save
	 * 
	 * @since 1.0.0
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function save( $request ) {
		$validator = new SettingsSenderValidator( $request );
		$validator->validate();

		if ( $validator->hasErrors() ) {
			return new \WP_Error( 'invalid_data', 'Invalid data', [ 'errors' => $validator->errors() ] );
		}

		$data = $validator->all();

		$updated_settings = [ 
			'name' => sanitize_text_field( $data['name'] ),
			'legal_name' => sanitize_text_field( $data['legal_name'] ),
			'email' => sanitize_text_field( $data['email'] ),
			'phone' => Sanitizer::numeric_text( $data['phone'] ),
			'celphone' => Sanitizer::numeric_text( $data['celphone'] ),
			'document' => Sanitizer::numeric_text( $data['document'] ),
			'address_postalcode' => Sanitizer::numeric_text( $data['address_postalcode'] ),
			'address_street' => sanitize_text_field( $data['address_street'] ),
			'address_complement' => sanitize_text_field( $data['address_complement'] ),
			'address_number' => sanitize_text_field( $data['address_number'] ),
			'address_neighborhood' => sanitize_text_field( $data['address_neighborhood'] ),
			'address_city' => sanitize_text_field( $data['address_city'] ),
			'address_state' => sanitize_text_field( $data['address_state'] ),
			'address_country' => sanitize_text_field( $data['address_country'] ),
		];

		Config::update( 'sender', $updated_settings );

		$response_data = $this->prepare_data();

		$response = [ 
			'status' => 'success',
			'sender' => $response_data,
		];
		return rest_ensure_response( $response );
	}

	public function retrieve() {
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
			'name' => Config::string( 'sender.name' ),
			'legal_name' => Config::string( 'sender.legal_name' ),
			'email' => Config::string( 'sender.email' ),
			'phone' => Config::string( 'sender.phone' ),
			'celphone' => Config::string( 'sender.celphone' ),
			'document' => Config::string( 'sender.document' ),
			'address_postalcode' => Config::string( 'sender.address_postalcode' ),
			'address_street' => Config::string( 'sender.address_street' ),
			'address_complement' => Config::string( 'sender.address_complement' ),
			'address_number' => Config::string( 'sender.address_number' ),
			'address_neighborhood' => Config::string( 'sender.address_neighborhood' ),
			'address_city' => Config::string( 'sender.address_city' ),
			'address_state' => Config::string( 'sender.address_state' ),
			'address_country' => Config::string( 'sender.address_country' ),
		];

		return $sanitized_settings;
	}
}