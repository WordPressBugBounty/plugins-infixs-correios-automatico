<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Services\ShippingService;
use Infixs\CorreiosAutomatico\Utils\Formatter;
use Infixs\CorreiosAutomatico\Utils\Helper;
use Infixs\CorreiosAutomatico\Utils\NumberHelper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Shipping Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Shipping {

	/**
	 * Shipping service instance.
	 * 
	 * @var ShippingService
	 */
	private $shippingService;

	/**
	 * Constructor
	 * 
	 * @since 1.1.1
	 * 
	 * @param ShippingService $shippingService Shipping service instance.
	 */
	public function __construct( ShippingService $shippingService ) {
		$this->shippingService = $shippingService;
		$this->calculator_position_hook();
		if ( Config::boolean( "general.mask_postcode" ) ) {
			add_filter( 'woocommerce_customer_get_shipping_postcode', [ $this, 'get_shipping_postcode' ] );
		}

		add_filter( 'woocommerce_package_rates', [ $this, 'filter_rates' ], 10, 2 );
	}

	/**
	 * Get a masked shipping postcode.
	 *
	 * @param string $postcode Shipping postcode.
	 * 
	 * @since 1.2.9
	 */
	public function get_shipping_postcode( $postcode ) {
		return Formatter::format_postcode( $postcode );
	}


	/**
	 * Display estimated delivery time.
	 *
	 * @param string $label Shipping method label.
	 * @param \WC_Shipping_Rate $method Shipping method object.
	 * 
	 * @since 1.0.0
	 */
	public function shipping_method_label( $label, $method ) {
		return $label;
	}

	/**
	 * Display shipping calculator.
	 * 
	 * @since 1.0.1
	 */
	public function shipping_calculator_shortcode() {
		ob_start();

		if ( is_product() ) {
			$calculator_style_id = Config::string( "general.calculator_style_id" );
			$template = $calculator_style_id === 'custom' ? 'infixs-shipping-calculator-styles.php' : 'infixs-shipping-calculator.php';

			wc_get_template(
				$template,
				$calculator_style_id === 'custom' ? [ 
					'calculator_styles' => Config::get( 'general.calculator_styles', [] ),
				] : [],
				'infixs-correios-automatico/',
				\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'templates/'
			);
		}

		return ob_get_clean();
	}

	public function display_shipping_calculator() {
		if ( is_product() && Config::boolean( "general.calculate_shipping_product_page" ) ) {
			global $product;
			if ( $product->needs_shipping() ) {
				// phpcs:ignore
				echo $this->shipping_calculator_shortcode();
			}
		}

	}

	public function calculate_shipping() {
		WC()->shipping()->reset_shipping();

		// only read, ignore nonce for caching
		// phpcs:ignore
		if ( ! isset( $_POST['postcode'] ) || ! isset( $_POST['product_id'] ) ) {
			return wp_send_json_error( [ 'message' => 'CEP e o produto são obrigatórios' ] );
		}

		$postscode = sanitize_text_field( wp_unslash( $_POST['postcode'] ) );
		$product_id = sanitize_text_field( wp_unslash( $_POST['product_id'] ) );
		$quantity = Config::boolean( 'general.consider_quantity' ) && isset( $_POST['quantity'] ) ? (int) wp_unslash( $_POST['quantity'] ) : 1;

		$variation_id = isset( $_POST['variation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['variation_id'] ) ) : null;

		$product = wc_get_product( $variation_id ?: $product_id );

		$package_cost = $product->get_price() * $quantity;

		$state = $this->shippingService->getStateByPostcode( $postscode );

		$address = Config::boolean( "general.show_full_address_calculate_product" ) ? $this->shippingService->getAddressByPostcode( $postscode ) : false;

		if ( ! $address ) {
			$address = [ 
				'state' => $state,
				'postcode' => Sanitizer::numeric_text( $postscode ),
				'country' => 'BR',
			];
		}

		$package = apply_filters( 'infixs_correios_automatico_calculate_single_shipping_package', [ 
			'contents' => [ 
				0 => [ 
					'product_id' => $product->get_id(),
					'variation_id' => $variation_id,
					'data' => $product,
					'quantity' => $quantity,
				],
			],
			'contents_cost' => $package_cost,
			'applied_coupons' => false,
			'user' => [ 
				'ID' => get_current_user_id(),
			],
			'destination' => [ 
				'country' => 'BR',
				'state' => $state,
				'postcode' => Sanitizer::numeric_text( $postscode ),
				'city' => isset( $address['city'] ) ? $address['city'] : '',
				'address' => isset( $address['address'] ) ? $address['address'] : '',
			],
			'cart_subtotal' => $package_cost,
			'is_product_page' => true,
		], $product );

		if ( ! WC()->customer->get_billing_first_name() ) {
			WC()->customer->set_billing_location( 'BR', isset( $address['state'] ) ? $address['state'] : $state, $postscode, isset( $address['city'] ) ? $address['city'] : '' );
			if ( isset( $address['address'] ) )
				WC()->customer->set_billing_address( $address['address'] );
		}
		WC()->customer->set_shipping_location( 'BR', isset( $address['state'] ) ? $address['state'] : $state, $postscode, isset( $address['city'] ) ? $address['city'] : '' );
		if ( isset( $address['address'] ) )
			WC()->customer->set_shipping_address( $address['address'] );

		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();

		$hash = Helper::generateHashFromArray( $package );
		$packages[ $hash ] = $package;

		add_filter( 'option_woocommerce_shipping_cost_requires_address', '__return_false', 999 );
		$packages_result = WC()->shipping()->calculate_shipping( $packages );
		remove_filter( 'option_woocommerce_shipping_cost_requires_address', '__return_false', 999 );

		$current_package = reset( $packages_result );

		wc_get_template(
			Config::string( 'general.calculator_style_id' ) === 'custom' ? 'infixs-shipping-calculator-styles-results.php' : 'infixs-shipping-calculator-results.php',
			[ 
				'address' => $address,
				'rates' => $current_package['rates'],
				'calculator_styles' => Config::get( 'general.calculator_styles', [] ),
			],
			'infixs-correios-automatico/',
			\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'templates/'
		);

		wp_die();
	}

	public function calculator_position_hook() {
		$position = Config::string( "general.calculate_shipping_product_page_position" );

		$action_hook = 'woocommerce_product_meta_end';

		switch ( $position ) {
			case 'meta_start':
				$action_hook = 'woocommerce_product_meta_start';
				break;
			case 'meta_end':
				$action_hook = 'woocommerce_product_meta_end';
				break;
			case 'title_after':
				$action_hook = 'woocommerce_after_single_product';
				break;
			case 'description_before':
				$action_hook = 'woocommerce_single_product_summary';
				break;
			case 'buy_form_before':
				$action_hook = 'woocommerce_before_add_to_cart_form';
				break;
			case 'buy_form_after':
				$action_hook = 'woocommerce_after_add_to_cart_form';
				break;
			case 'options_before':
				$action_hook = 'woocommerce_before_variations_form';
				break;
			case 'buy_button_before':
				$action_hook = 'woocommerce_before_add_to_cart_button';
				break;
			case 'buy_button_after':
				$action_hook = 'woocommerce_after_add_to_cart_button';
				break;
			case 'variation_before':
				$action_hook = 'woocommerce_before_single_variation';
				break;
		}

		add_action( $action_hook, [ $this, 'display_shipping_calculator' ], 80 );
	}

	/**
	 * Filter rates.
	 *
	 * @param array $rates Shipping rates.
	 * @param array $package Package data.
	 * 
	 * @return array
	 */
	public function filter_rates( $rates, $package ) {

		foreach ( $rates as $rate_id => $rate ) {
			$meta_data = $rate->get_meta_data();
			if ( $meta_data ) {
				foreach ( $meta_data as $meta_key => $meta_value ) {
					if ( $meta_key === '_hide_others_rates' && $meta_value ) {
						return [ $rate_id => $rate ];
					}
				}
			}
		}

		return $rates;
	}
}