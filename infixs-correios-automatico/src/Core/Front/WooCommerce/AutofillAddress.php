<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;

use Infixs\CorreiosAutomatico\Models\Postcode;
use Infixs\CorreiosAutomatico\Services\ShippingService;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class AutofillAddress {

	/**
	 * Shipping service instance.
	 * 
	 * @var ShippingService
	 */
	private $shippingService;

	/**
	 * Constructor
	 * 
	 * @param ShippingService $shippingService
	 */
	public function __construct( ShippingService $shippingService ) {
		$this->shippingService = $shippingService;

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_ajax_infixs_correios_automatico_autofill_address', [ $this, 'autofill_address' ] );
		add_action( 'wp_ajax_nopriv_infixs_correios_automatico_autofill_address', [ $this, 'autofill_address' ] );
		add_filter( 'woocommerce_checkout_fields', [ $this, 'postcode_field_priority' ] );
		add_filter( 'wc_address_i18n_params', [ $this, 'postcode_param_priority' ] );
	}



	public function autofill_address() {
		if ( ! isset( $_POST['postcode'] ) ) {
			return wp_send_json_error( [ 'message' => 'O CEP é obrigatório' ] );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'infixs_correios_automatico_nonce' ) ) {
			return wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
		}

		$postcode = Sanitizer::postcode( sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) );

		$address = $this->get_address( $postcode );

		if ( $address ) {
			wp_send_json_success( [ 
				'postcode' => $address->postcode,
				'address' => $address->address,
				'neighborhood' => $address->neighborhood,
				'city' => $address->city,
				'state' => $address->state,
			] );
		} else {
			wp_send_json_error( __( 'CEP não encontrado.', 'infixs-correios-automatico' ) );
		}
	}

	/**
	 * Get address from database or fetch from API.
	 * 
	 * @param string $postcode
	 * 
	 * @return Postcode|false
	 */
	public function get_address( $postcode ) {
		$address = Postcode::where( 'postcode', $postcode )->first();

		if ( $address ) {
			return $address;
		}

		$address = $this->shippingService->getAddressByPostcode( $postcode );

		if ( $address && isset( $address['address'] ) && ! empty( $address['address'] ) ) {
			$created = Postcode::create( [ 
				'postcode' => $postcode,
				'address' => $address['address'],
				'neighborhood' => $address['neighborhood'],
				'city' => $address['city'],
				'state' => $address['state'],
				'created_at' => current_time( 'mysql' ),
			] );

			return $created ?: false;
		}

		return false;
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			'infixs-correios-automatico-autofill-address',
			\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/front/js/autofill-address.js',
			[ 'jquery', 'jquery-blockui', 'wp-util' ],
			filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/front/js/autofill-address.js' ),
			true
		);
	}

	public function postcode_field_priority( $fields ) {
		$fields['billing']['billing_postcode']['priority'] = 35;
		$fields['shipping']['shipping_postcode']['priority'] = 35;
		return $fields;
	}

	public function postcode_param_priority( $params ) {
		$locales = json_decode( $params['locale'], true );
		foreach ( $locales as &$locale ) {
			if ( isset( $locale['postcode'] ) ) {
				$locale['postcode']['priority'] = 35;
			}
		}
		$params['locale'] = wp_json_encode( $locales );
		return $params;
	}
}