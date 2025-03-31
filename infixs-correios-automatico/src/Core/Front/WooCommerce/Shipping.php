<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\ShippingService;
use Infixs\CorreiosAutomatico\Utils\Formatter;
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
			$template = 'shipping-calculator.php';

			wc_get_template(
				$template,
				[],
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

		if ( ! isset( $_POST['postcode'] ) || ! isset( $_POST['product_id'] ) ) {
			return wp_send_json_error( [ 'message' => 'CEP e o produto são obrigatórios' ] );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'infixs_correios_automatico_nonce' ) ) {
			return wp_send_json_error( [ 'message' => 'Nonce inválido' ] );
		}

		$postscode = sanitize_text_field( wp_unslash( $_POST['postcode'] ) );
		$product_id = sanitize_text_field( wp_unslash( $_POST['product_id'] ) );

		$variation_id = isset( $_POST['variation_id'] ) ? sanitize_text_field( wp_unslash( $_POST['variation_id'] ) ) : null;

		$product = wc_get_product( $variation_id ?: $product_id );

		$package_cost = $product->get_price();

		$address = Config::boolean( "general.show_full_address_calculate_product" ) ? $this->shippingService->getAddressByPostcode( $postscode ) : false;

		$state = $this->shippingService->getStateByPostcode( $postscode );

		WC()->shipping()->calculate_shipping(

			[ 
				[ 
					'contents' => [ 
						0 => [ 
							'data' => $product,
							'quantity' => 1,
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
						'city' => $address ? $address['city'] : '',
						'address' => $address ? $address['address'] : '',
					],
					'cart_subtotal' => $package_cost,
					'is_product_page' => true,
				],
			]
		);


		$packages = WC()->shipping()->get_packages();

		if ( ! WC()->customer->get_billing_first_name() ) {
			WC()->customer->set_billing_location( 'BR', $address ? $address['state'] : $state, $postscode, $address ? $address['city'] : '' );
			if ( $address )
				WC()->customer->set_billing_address( $address['address'] );
		}
		WC()->customer->set_shipping_location( 'BR', $address ? $address['state'] : $state, $postscode, $address ? $address['city'] : '' );
		if ( $address )
			WC()->customer->set_shipping_address( $address['address'] );
		WC()->customer->set_calculated_shipping( true );
		WC()->customer->save();


		wc_get_template(
			'shipping-calculator-results.php',
			[ 
				'address' => $address,
				'rates' => $packages[0]['rates'],
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
}