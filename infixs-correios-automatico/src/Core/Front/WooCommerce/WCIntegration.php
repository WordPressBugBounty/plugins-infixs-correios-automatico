<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Front\Front;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático WooCommerce
 * 
 * Settup functions for woocommerce
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class WCIntegration {

	/**
	 * Shipping instance.
	 *
	 * @since 1.0.0
	 * @var Shipping
	 */
	public $shipping;

	/**
	 * Order instance.
	 *
	 * @since 1.0.0
	 * @var Order
	 */
	public $order;

	/**
	 * Checkout instance.
	 *
	 * @since 1.2.9
	 * @var Checkout
	 */
	public $checkout;


	public $front;


	/**
	 * Cart instance.
	 *
	 * @since 1.2.9
	 * @var Cart
	 */
	public $cart;
	public function __construct( Front $front ) {
		$this->front = $front;
		add_action( 'woocommerce_loaded', [ $this, 'init' ] );
	}

	public function init() {
		$this->shipping = new Shipping( Container::shippingService() );
		$this->order = new Order( Container::trackingService() );
		$this->checkout = new Checkout();
		$this->cart = new Cart();
		new TrackingView( Container::trackingService() );

		$this->filters();
		$this->actions();
		$this->shortcodes();
	}

	public function actions() {
		add_action( 'wp_ajax_infixs_correios_automatico_calculate_shipping', [ $this->shipping, 'calculate_shipping' ] );
		add_action( 'wp_ajax_nopriv_infixs_correios_automatico_calculate_shipping', [ $this->shipping, 'calculate_shipping' ] );
		add_action( 'wp_enqueue_scripts', [ $this->front, 'enqueue_scripts' ] );
	}

	public function filters() {
		add_filter( 'woocommerce_cart_shipping_method_full_label', [ $this->shipping, 'shipping_method_label' ], 10, 2 );
	}

	public function shortcodes() {
		add_shortcode( 'infixs_correios_automatico_calculator', [ $this->shipping, 'shipping_calculator_shortcode' ] );
	}
}