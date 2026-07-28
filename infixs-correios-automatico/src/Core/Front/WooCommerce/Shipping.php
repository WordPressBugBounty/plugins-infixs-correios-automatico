<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;
use Infixs\CorreiosAutomatico\Core\Shipping\LinkedShippingMethod;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Services\ShippingService;
use Infixs\CorreiosAutomatico\Utils\Formatter;
use Infixs\CorreiosAutomatico\Utils\Helper;
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

		if ( Config::boolean( "general.show_additional_time" ) ) {
			add_action( 'woocommerce_single_product_summary', [ $this, 'add_additional_time' ], 100 );
		}

		add_filter( 'woocommerce_package_rates', [ $this, 'filter_rates' ], 10, 2 );

		add_filter( 'woocommerce_package_rates', [ $this, 'attach_linked_correios_meta' ], 20, 2 );

		add_action( 'woocommerce_checkout_create_order_shipping_item', [ $this, 'convert_linked_shipping_item' ], 10, 4 );

		if ( Config::boolean( "general.force_shipping_cost" ) ) {
			add_filter( 'woocommerce_package_rates', [ $this, 'force_shipping_cost' ], 999, 2 );
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
		if ( ! $method instanceof \WC_Shipping_Rate ) {
			return $label;
		}

		$meta_data = $method->get_meta_data();
		$show_original_shipping_discount_price = isset( $meta_data['_show_original_shipping_discount_price'] )
			? Sanitizer::boolean( $meta_data['_show_original_shipping_discount_price'] )
			: false;

		if ( ! $show_original_shipping_discount_price ) {
			return $label;
		}

		$current_cost = (float) $method->cost;
		$original_cost = isset( $meta_data['_original_cost'] ) ? (float) $meta_data['_original_cost'] : null;

		if ( null === $original_cost || $original_cost <= $current_cost ) {
			return $label;
		}

		if ( false !== strpos( $label, 'infixs-correios-automatico-original-cost' ) ) {
			return $label;
		}

		$discount_html = sprintf(
			'<del class="infixs-correios-automatico-original-cost">%s</del> ',
			esc_html( Formatter::format_currency( $original_cost ) )
		);

		if ( preg_match( '/(<span class="woocommerce-Price-amount amount">.*?<\/span>)/', $label, $matches ) ) {
			return str_replace( $matches[1], $discount_html . $matches[1], $label );
		}

		return $discount_html . $label;
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

		return $this->minify_calculator_html( ob_get_clean() );
	}

	/**
	 * Minify the calculator markup into a single line.
	 *
	 * Some themes render the product summary through the "the_content"
	 * filter, which runs wpautop/wptexturize. Those functions inject
	 * <p> and <br> tags based on line breaks, breaking the calculator
	 * layout and mangling its inline <style> block. Removing line breaks
	 * and the whitespace between tags makes the markup immune to it.
	 *
	 * @since 1.8.0
	 *
	 * @param string $html Calculator HTML.
	 * 
	 * @return string
	 */
	private function minify_calculator_html( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return '';
		}

		$html = str_replace( [ "\r\n", "\r", "\n" ], '', $html );
		$html = preg_replace( '/>[\t ]+</', '><', $html );

		return $html;
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

		$package_cost = (float) $product->get_price() * $quantity;

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
					'formatted_data' => (object) [
						'id' => $product->get_id(),
						'name' => $product->get_name(),
						'is_virtual' => $product->get_virtual(),
						'unitary_value' => (float) $product->get_price(),
						'quantity' => $quantity,
						'width' => $product->get_width(),
						'height' => $product->get_height(),
						'length' => $product->get_length(),
						'weight' => $product->get_weight(),
					],
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

		//TODO: Change priority dinamically by config
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

		Log::debug( 'Filtering rates', [ 'rates' => $rates, 'package' => $package ] );

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

	/**
	 * Attach the Correios package meta to rates of linked native shipping methods.
	 *
	 * Native methods like "Frete Grátis" produce a rate without any Correios data, so
	 * the order line item ends up without the service code and the package dimensions
	 * the pre-post needs. When the merchant links a Correios method to the native one,
	 * the linked method's settings answer for those values here. WooCommerce copies the
	 * rate meta into the shipping line item when the order is created.
	 *
	 * No Correios API call happens here: the package is computed locally.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Shipping_Rate[] $rates Shipping rates.
	 * @param array $package Package data.
	 *
	 * @return array
	 */
	public function attach_linked_correios_meta( $rates, $package ) {
		foreach ( $rates as $rate_id => $rate ) {
			if ( ! LinkedShippingMethod::is_supported( $rate->get_method_id() ) ) {
				continue;
			}

			$linked_method = LinkedShippingMethod::get_linked_method( $rate->get_method_id(), $rate->get_instance_id() );

			if ( ! $linked_method ) {
				continue;
			}

			$package_data = $linked_method->get_package( $package )->get_data();

			$rate->add_meta_data( '_weight', $package_data['weight'] );
			$rate->add_meta_data( '_length', $package_data['length'] );
			$rate->add_meta_data( '_width', $package_data['width'] );
			$rate->add_meta_data( '_height', $package_data['height'] );
			$rate->add_meta_data( 'shipping_product_code', $linked_method->get_product_code() );
			$rate->add_meta_data( LinkedShippingMethod::ITEM_META_KEY, $linked_method->get_instance_id() );

			$rates[ $rate_id ] = $rate;

			Log::debug( 'Correios meta attached to linked rate', [
				'rate_id' => $rate_id,
				'linked_instance_id' => $linked_method->get_instance_id(),
				'package_data' => $package_data,
			] );
		}

		return $rates;
	}

	/**
	 * Convert a native shipping line item into the linked Correios method.
	 *
	 * When the order goes out with a native method (Frete Grátis / Preço Fixo) that the
	 * merchant linked to a Correios method, the whole plugin — which keys off
	 * method_id === 'infixs-correios-automatico' — must see it as a native Correios order
	 * (pré-postagem, etiqueta, rastreio, contagens do PRO). So the shipping item is
	 * rewritten to the Correios method here, keeping the original cost (0 on free shipping)
	 * and combining the titles into "SEDEX (Frete grátis)".
	 *
	 * Runs during WC_Checkout::create_order_shipping_lines(), which serves both the classic
	 * and the block/Store API checkout. The item is not saved yet — WooCommerce persists it
	 * on the following $order->save(), so no $item->save() is needed here.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order_Item_Shipping $item         Shipping line item being built.
	 * @param string $package_key Package key.
	 * @param array $package Shipping package.
	 * @param \WC_Order $order Order being created.
	 *
	 * @return void
	 */
	public function convert_linked_shipping_item( $item, $package_key, $package, $order ) {
		if ( ! $item instanceof \WC_Order_Item_Shipping ) {
			return;
		}

		$original_method_id = $item->get_method_id();

		if ( $original_method_id === 'infixs-correios-automatico' ) {
			return;
		}

		$linked_method = LinkedShippingMethod::resolve_from_item( $item );

		if ( ! $linked_method ) {
			return;
		}

		$original_title = $item->get_method_title();

		$item->set_method_id( 'infixs-correios-automatico' );
		$item->set_instance_id( $linked_method->get_instance_id() );
		$item->set_method_title( sprintf( '%s (%s)', $linked_method->get_title(), $original_title ) );
		$item->update_meta_data( '_infixs_original_method_id', $original_method_id );
		// Keep the native title ("Frete grátis") so the admin "recalcular frete" can rebuild the
		// combined title ("SEDEX (Frete grátis)") instead of resetting it to the bare service name.
		$item->update_meta_data( '_infixs_original_method_title', $original_title );

		// Price the linked Correios method for this package — the same calculation the normal
		// Correios checkout and the admin "recalcular frete" use. This freezes the real freight
		// cost (_original_cost), delivery time and insurance on the item so the merchant sees how
		// much the "free" shipment actually costs. The customer keeps paying the native total:
		// the item total is never touched, so free shipping stays free.
		$rate = null;
		$rates = $linked_method->get_rates_for_package( $package );
		if ( is_array( $rates ) ) {
			$rate = reset( $rates );
		}

		if ( $rate instanceof \WC_Shipping_Rate ) {
			$metas = $rate->get_meta_data();

			foreach ( [ '_weight', '_length', '_width', '_height', 'shipping_product_code' ] as $meta_key ) {
				if ( isset( $metas[ $meta_key ] ) ) {
					$item->update_meta_data( $meta_key, $metas[ $meta_key ] );
				}
			}

			$item->update_meta_data( LinkedShippingMethod::ITEM_META_KEY, $linked_method->get_instance_id() );

			if ( isset( $metas['_original_cost'] ) ) {
				$item->update_meta_data( '_original_cost', $metas['_original_cost'] );
			}

			if ( ! empty( $metas['_insurance_cost'] ) ) {
				$item->update_meta_data( '_insurance_cost', $metas['_insurance_cost'] );
			}

			if ( isset( $metas['delivery_time'] ) ) {
				$item->update_meta_data( 'delivery_time', $metas['delivery_time'] );
			}
		} elseif ( ! $item->get_meta( 'shipping_product_code' ) ) {
			// The linked method produced no rate (unavailable / no price). Keep at least the
			// dimensions and the service, computed locally, so the pré-postagem still works —
			// only the freight cost is missing.
			$package_data = $linked_method->get_package( $package )->get_data();

			$item->update_meta_data( '_weight', $package_data['weight'] );
			$item->update_meta_data( '_length', $package_data['length'] );
			$item->update_meta_data( '_width', $package_data['width'] );
			$item->update_meta_data( '_height', $package_data['height'] );
			$item->update_meta_data( 'shipping_product_code', $linked_method->get_product_code() );
			$item->update_meta_data( LinkedShippingMethod::ITEM_META_KEY, $linked_method->get_instance_id() );
		}

		Log::debug( 'Linked shipping item converted to Correios', [
			'order_id' => $order instanceof \WC_Order ? $order->get_id() : null,
			'original_method_id' => $original_method_id,
			'linked_instance_id' => $linked_method->get_instance_id(),
			'product_code' => $linked_method->get_product_code(),
			'original_cost' => $rate instanceof \WC_Shipping_Rate ? ( $rate->get_meta_data()['_original_cost'] ?? null ) : null,
		] );
	}

	/**
	 * Prevent other plugins to change shipping cost.
	 *
	 * @param \WC_Shipping_Rate[] $rates Shipping rates.
	 * @param array $package Package data.
	 *
	 * @return array
	 */
	public function force_shipping_cost( $rates, $package ) {
		Log::debug( 'Forcing shipping cost, initial', [ 'rates' => $rates, 'package' => $package ] );

		foreach ( $rates as $rate_id => $rate ) {
			if ( $rate->get_method_id() !== 'infixs-correios-automatico' ) {
				continue;
			}

			$meta_data = $rate->get_meta_data();
			if ( $meta_data ) {
				foreach ( $meta_data as $meta_key => $meta_value ) {
					if ( $meta_key === '_final_cost' ) {
						$rate->set_cost( (float) $meta_value );
						$rates[ $rate_id ] = $rate;
					}
				}
			}
		}

		Log::debug( 'Forcing shipping cost, final', [ 'rates' => $rates, 'package' => $package ] );

		return $rates;
	}

	public function add_additional_time() {
		if ( is_product() ) {
			global $product;
			if ( $product->needs_shipping() ) {
				echo $this->additional_time_shortcode();
			}
		}
	}

	public function additional_time_shortcode() {
		ob_start();


		if ( is_product() ) {
			global $product;

			$additional_days = intval( $product->get_meta( '_infixs_correios_automatico_additional_days', true ) );

			if ( $additional_days > 0 ) {
				echo sprintf( __( 'Prazo de produção: %d dias úteis', 'infixs-correios-automatico' ), $additional_days );
			}
		}

		return ob_get_clean();
	}
}