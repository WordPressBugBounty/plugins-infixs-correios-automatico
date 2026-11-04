<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;
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
	 * Settings service instance.
	 * 
	 * @since 1.6.43
	 * 
	 * @var \Infixs\CorreiosAutomatico\Services\SettingsService
	 */
	private $settingsService;

	public function __construct() {
		$this->settingsService = Container::settingsService();
	}

	/**
	 * Sender settings save
	 * 
	 * @since 1.0.0
	 * @since 1.11.2 Drops the shipping rates the carts keep, since carriers quote from the sender postcode.
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
		$document = Sanitizer::numeric_text( $data['document'] );
		$person_type = $this->personType( $data, $document );

		$updated_settings = [
			'name' => sanitize_text_field( $data['name'] ),
			// A person has no legal name to print.
			'legal_name' => 'person' === $person_type || ! isset( $data['legal_name'] ) ? '' : sanitize_text_field( $data['legal_name'] ),
			'email' => sanitize_text_field( $data['email'] ),
			'phone' => Sanitizer::numeric_text( $data['phone'] ),
			'celphone' => Sanitizer::numeric_text( $data['celphone'] ),
			'document' => $document,
			'person_type' => $person_type,
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

		\WC_Cache_Helper::get_transient_version( 'shipping', true );

		$response_data = $this->prepare_data();

		$response = [
			'status' => 'success',
			'sender' => $response_data,
		];
		return rest_ensure_response( $response );
	}

	/**
	 * Person type to store: the one chosen, or the one the document tells.
	 *
	 * @since 1.14.0
	 *
	 * @param array  $data     Request data.
	 * @param string $document Document, digits only.
	 *
	 * @return string `company` or `person`.
	 */
	private function personType( $data, $document ) {
		if ( isset( $data['person_type'] ) && in_array( $data['person_type'], [ 'company', 'person' ], true ) ) {
			return $data['person_type'];
		}

		return 11 === strlen( $document ) ? 'person' : 'company';
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
		return $this->settingsService->getSenderData();
	}
}