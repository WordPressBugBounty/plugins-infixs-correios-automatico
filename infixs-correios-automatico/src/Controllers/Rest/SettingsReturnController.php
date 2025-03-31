<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Core\Support\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Return Controller
 * 
 * @since 1.2.1
 * 
 * @package Infixs\CorreiosAutomatico\Controllers\Rest
 */
class SettingsReturnController {

	/**
	 * Return settings save
	 * 
	 * @since 1.2.1
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function save( $request ) {
		$data = $request->get_json_params();

		$updated_settings = [ 
			'active' => rest_sanitize_boolean( $data['active'] ),
			'auto_return' => rest_sanitize_boolean( $data['auto_return'] ),
			'days' => sanitize_text_field( $data['days'] ),
			'same_service' => rest_sanitize_boolean( $data['same_service'] )
		];

		Config::update( 'return', $updated_settings );

		$response_data = $this->prepare_data();

		$response = [ 
			'status' => 'success',
			'return' => $response_data,
		];
		return rest_ensure_response( $response );
	}

	public function retrieve() {

		$response = [ 
			'status' => 'success',
			'return' => $this->prepare_data(),
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare the data
	 *
	 * @since 1.2.1
	 * 
	 * @param array $settings
	 * 
	 * @return array
	 */
	public function prepare_data() {
		$sanitized_settings = [ 
			'active' => Config::boolean( 'return.active' ),
			'days' => Config::string( 'return.days' ),
			'auto_return' => Config::boolean( 'return.auto_return' ),
			'same_service' => Config::boolean( 'return.same_service' )
		];

		return $sanitized_settings;
	}
}