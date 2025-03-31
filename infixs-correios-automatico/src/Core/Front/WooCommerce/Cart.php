<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;

defined( 'ABSPATH' ) || exit;

class Cart {

	protected $shippingService;

	public function __construct() {
		$this->shippingService = Container::shippingService();

		if ( Config::boolean( 'general.simple_cart_shipping_calculator' ) ) {
			add_filter( 'woocommerce_shipping_calculator_enable_city', '__return_false' );
			add_filter( 'woocommerce_shipping_calculator_enable_country', '__return_false' );
			add_filter( 'woocommerce_shipping_calculator_enable_state', '__return_false' );

		}

		add_filter( 'woocommerce_cart_calculate_shipping_address', [ $this, 'calculate_shipping_address' ], 30 );
		add_action( 'woocommerce_calculated_shipping', [ $this, 'calculated_shipping' ] );

		if ( Config::boolean( 'general.cart_shipping_calculator_always_visible' ) ||
			Config::boolean( 'general.auto_calculate_cart_shipping_postcode' )
		) {
			add_filter( 'body_class', [ $this, 'add_body_class' ] );
		}
	}

	public function calculate_shipping_address( $address ) {
		$postcode = wc_format_postcode( $address['postcode'], 'BR' );
		$address['country'] = 'BR';
		$address['state'] = $this->shippingService->getStateByPostcode( $postcode );
		$address['city'] = $this->shippingService->getCityByPostcode( $postcode );

		return $address;
	}

	public function calculated_shipping() {
		if (
			WC()->customer->get_billing_postcode() &&
			WC()->customer->get_billing_city() &&
			WC()->customer->get_billing_state() &&
			! WC()->customer->get_billing_address()
		) {
			$address = $this->shippingService->getAddressByPostcode( WC()->customer->get_billing_postcode() );
			if ( $address ) {
				WC()->customer->set_shipping_address( $address['address'] );
				WC()->customer->set_billing_address( $address['address'] );
			}
		}
	}

	public function add_body_class( $classes ) {
		if ( Config::boolean( 'general.cart_shipping_calculator_always_visible' ) ) {
			$classes[] = 'infxs-correios-automatico-cart-shipping-calculator-visible';
		}

		if ( Config::boolean( 'general.auto_calculate_cart_shipping_postcode' ) &&
			Config::boolean( 'general.simple_cart_shipping_calculator' )
		) {
			$classes[] = 'infxs-correios-automatico-cart-shipping-calculator-hidden-button';
		}
		return $classes;
	}
}