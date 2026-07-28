<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;
use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Shipping\LinkedShippingMethod;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Core\Support\Template;
use Infixs\CorreiosAutomatico\Entities\Order as OrderEntity;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Order Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Order {

	/**
	 * Tracking Service
	 * 
	 * @var TrackingService
	 */
	protected $trackingService;

	/**
	 * Notices collected while recalculating the shipping lines, rendered in the items box.
	 *
	 * @var array<int, array{title: string, message: string}>
	 */
	protected $recalculate_notices = [];

	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;

		add_action( 'woocommerce_before_order_itemmeta', [ $this, 'before_order_itemmeta' ], 10, 2 );
		add_action( 'woocommerce_before_save_order_items', [ $this, 'before_save_order_items' ], 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_itemmeta' ] );
		add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'order_item_display_meta_key' ], 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_value', [ $this, 'order_item_display_meta_value' ], 10, 3 );
		add_action( 'woocommerce_order_before_calculate_taxes', [ $this, 'recalculate_shipping_items' ], 10, 2 );
		add_action( 'woocommerce_admin_order_items_after_shipping', [ $this, 'render_recalculate_notices' ] );


		add_action( 'init', [ $this, 'register_order_status' ] );
		add_filter( 'wc_order_statuses', [ $this, 'add_order_status' ] );
		add_action( 'woocommerce_order_status_changed', [ $this, 'order_status_changed' ], 10, 3 );
	}

	/**
	 * Add shipping service in edit shipping order
	 * 
	 * @param string $item_id
	 * @param \WC_Order_Item_Shipping $item
	 * 
	 * @return void
	 */
	public function before_order_itemmeta( $item_id, $item ) {
		if ( ! $item instanceof \WC_Order_Item_Shipping ) {
			return;
		}

		$shipping_services = DeliveryServiceCode::getAll();

		$order = wc_get_order( $item->get_order_id() );

		$correios_shipping_methods = Container::shippingService()->getAvailableZoneCorreiosMethods( [
			'country' => $order->get_shipping_country() ?: 'BR',
			'state' => $order->get_shipping_state(),
			'postcode' => $order->get_shipping_postcode(),
			'city' => $order->get_shipping_city(),
			'address' => $order->get_shipping_address_1(),
		] );

		$current_method = \WC_Shipping_Zones::get_shipping_method( $item->get_instance_id() );

		if ( $current_method instanceof CorreiosShippingMethod &&
			! in_array( $current_method->get_instance_id(), wp_list_pluck( $correios_shipping_methods, 'instance_id' ), false ) ) {
			array_unshift( $correios_shipping_methods, $current_method );
		}

		$instances = [];

		foreach ( $correios_shipping_methods as $method ) {
			$instances[ $method->get_instance_id()] = [
				'title' => $method->get_title(),
				'description' => DeliveryServiceCode::getDescription( $method->get_product_code(), true )
			];
		}

		$is_selected = $item->get_method_id() === 'infixs-correios-automatico';

		Template::adminView( 'html-order-edit-shipping.php', [
			'shipping_services' => $shipping_services,
			'shipping_methods' => $correios_shipping_methods,
			'item_id' => $item_id,
			'item' => $item,
			'is_selected' => $is_selected,
			'instances' => $instances
		] );
	}

	/**
	 * Hidden order item meta
	 * 
	 * @param array $hidden_order_itemmeta
	 * 
	 * @return array
	 */
	public function hidden_order_itemmeta( $hidden_order_itemmeta ) {
		$hidden_order_itemmeta = array_merge( $hidden_order_itemmeta, [
			'_weight',
			'_length',
			'_width',
			'_height',
			'shipping_product_code',
			LinkedShippingMethod::ITEM_META_KEY,
			'_infixs_original_method_id',
			'_infixs_original_method_title',
		] );
		return $hidden_order_itemmeta;
	}

	/**
	 * Order item display meta key
	 * 
	 * @param string $display_key
	 * @param array $meta
	 * @param \WC_Order_Item_Shipping $item
	 * 
	 * @return string
	 */
	public function order_item_display_meta_key( $display_key, $meta, $item ) {

		if ( ! $item instanceof \WC_Order_Item_Shipping ) {
			return $display_key;
		}

		if ( $item->get_method_id() !== 'infixs-correios-automatico' && ! LinkedShippingMethod::resolve_from_item( $item ) ) {
			return $display_key;
		}

		$display_keys = [
			'_weight' => __( 'Peso', 'infixs-correios-automatico' ),
			'_length' => __( 'Comprimento', 'infixs-correios-automatico' ),
			'_width' => __( 'Largura', 'infixs-correios-automatico' ),
			'_height' => __( 'Altura', 'infixs-correios-automatico' ),
			'_original_cost' => __( 'Valor do Frete Original', 'infixs-correios-automatico' ),
			'_insurance_cost' => __( 'Custo do Seguro', 'infixs-correios-automatico' ),
			'delivery_time' => __( 'Prazo de Entrega', 'infixs-correios-automatico' ),
			'shipping_product_code' => __( 'Serviço dos Correios', 'infixs-correios-automatico' ),
		];

		if ( array_key_exists( $display_key, $display_keys ) ) {
			return $display_keys[ $display_key ];
		}

		return $display_key;
	}

	/**
	 * Order item display meta value
	 * 
	 * @param string $display_value
	 * @param object $meta
	 * @param \WC_Order_Item_Shipping $item
	 * 
	 * @return string
	 */
	public function order_item_display_meta_value( $display_value, $meta, $item ) {

		if ( ! $item instanceof \WC_Order_Item_Shipping ) {
			return $display_value;
		}

		$linked_method = LinkedShippingMethod::resolve_from_item( $item );

		if ( $item->get_method_id() !== 'infixs-correios-automatico' && ! $linked_method ) {
			return $display_value;
		}

		if ( 'shipping_product_code' === $meta->key ) {
			$method = $linked_method ?: \WC_Shipping_Zones::get_shipping_method( $item->get_instance_id() );
			if ( $method instanceof CorreiosShippingMethod ) {
				$display_value = DeliveryServiceCode::getDescription( $method->get_product_code(), true );
			} else {
				$display_value = DeliveryServiceCode::getDescription( $meta->value, true );
			}
		}

		if ( '_insurance_cost' === $meta->key ) {
			$display_value = wc_price( $meta->value );
		}

		if ( '_original_cost' === $meta->key ) {
			$display_value = wc_price( $meta->value );
		}

		return $display_value;
	}

	/**
	 * Recalculate Correios shipping lines when the WooCommerce "Recalculate" button is used.
	 *
	 * Runs inside \WC_Abstract_Order::calculate_taxes(), which the admin recalculate request
	 * fires right after saving the order items, so the service and the package dimensions
	 * chosen in the shipping line are already persisted at this point. Taxes and order totals
	 * are calculated by WooCommerce after this hook, using the costs applied here.
	 *
	 * @since 1.8.0
	 *
	 * @param array           $args  Taxable address sent by the recalculate request.
	 * @param \WC_Order|mixed $order Order being recalculated.
	 *
	 * @return void
	 */
	public function recalculate_shipping_items( $args, $order ) {
		if ( ! wp_doing_ajax() || ! $order instanceof \WC_Order ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 'woocommerce_calc_line_taxes' !== $action || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$shipping_items = $order->get_items( 'shipping' );

		if ( empty( $shipping_items ) ) {
			return;
		}

		$package = $this->get_recalculate_package( $order, $args );

		if ( empty( $package ) ) {
			return;
		}

		$this->recalculate_notices = [];

		$correios_items = [];

		/** @var \WC_Order_Item_Shipping $item */
		foreach ( $shipping_items as $item ) {
			if ( 'infixs-correios-automatico' === $item->get_method_id() ) {
				$correios_items[] = $item;
			} elseif ( LinkedShippingMethod::resolve_from_item( $item ) ) {
				$this->add_recalculate_notice(
					$this->get_item_title( $item ),
					__( 'Esta linha usa um método nativo do WooCommerce vinculado a um serviço dos Correios, então o valor cobrado é o do método nativo e não é recalculado.', 'infixs-correios-automatico' ),
					'info'
				);
			}
		}

		if ( count( $correios_items ) > 1 ) {
			$this->add_recalculate_notice(
				__( 'Entrega', 'infixs-correios-automatico' ),
				__( 'O pedido tem mais de uma linha de entrega dos Correios. Como não é possível saber quais itens vão em cada volume, os valores não foram recalculados — informe o valor de cada linha manualmente.', 'infixs-correios-automatico' )
			);
			return;
		}

		foreach ( $correios_items as $item ) {
			$item_title = $this->get_item_title( $item );

			$method = \WC_Shipping_Zones::get_shipping_method( $item->get_instance_id() );

			if ( ! $method instanceof CorreiosShippingMethod ) {
				$this->add_recalculate_notice( $item_title, $this->get_missing_method_message( $order ) );
				continue;
			}

			$dimensions = $this->get_item_dimensions( $item );

			$result = $this->get_admin_rates( $method, $package, $dimensions );

			if ( ! empty( $result['rates'] ) && ! empty( $dimensions ) ) {
				$this->add_recalculate_notice(
					$item_title,
					sprintf(
						/* translators: 1: weight, 2: length, 3: width, 4: height */
						__( 'O cálculo usou o peso e as dimensões informados nesta linha (%1$s kg, %2$s x %3$s x %4$s cm). Para calcular a partir dos produtos do pedido, apague esses campos na linha de entrega e recalcule.', 'infixs-correios-automatico' ),
						$dimensions['setWeight'],
						$dimensions['setLength'],
						$dimensions['setWidth'],
						$dimensions['setHeight']
					),
					'info'
				);
			}

			if ( empty( $result['rates'] ) ) {
				Log::info( "Não foi possível recalcular o frete do pedido pelo botão do WooCommerce.", [
					'order_id' => $order->get_id(),
					'instance_id' => $item->get_instance_id(),
					'errors' => $result['errors'],
				] );

				$messages = ! empty( $result['errors'] ) ? $result['errors'] : [
					__( 'Não foi possível calcular o frete deste serviço. Ative os logs em Correios Automático > Configurações > Depuração para ver o motivo detalhado.', 'infixs-correios-automatico' ),
				];

				foreach ( $messages as $message ) {
					$this->add_recalculate_notice( $item_title, $message );
				}

				continue;
			}

			$this->apply_rate_to_shipping_item( $item, reset( $result['rates'] ) );

			if ( $item->get_meta( '_infixs_original_method_id' ) ) {
				$this->add_recalculate_notice(
					$item_title,
					__( 'Peso, dimensões e prazo foram atualizados, mas o valor foi mantido porque esta linha veio de um método nativo do WooCommerce (Frete Grátis ou Preço Fixo).', 'infixs-correios-automatico' ),
					'info'
				);
			}
		}
	}

	/**
	 * Get the title used to identify a shipping line in the notices.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order_Item_Shipping $item Shipping item.
	 *
	 * @return string
	 */
	protected function get_item_title( $item ) {
		return $item->get_name() ? $item->get_name() : __( 'Entrega', 'infixs-correios-automatico' );
	}

	/**
	 * Get the message shown when the shipping line has no Correios service attached.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order $order Order being recalculated.
	 *
	 * @return string
	 */
	protected function get_missing_method_message( $order ) {
		$zone_methods = Container::shippingService()->getAvailableZoneCorreiosMethods( [
			'country' => $order->get_shipping_country() ?: 'BR',
			'state' => $order->get_shipping_state(),
			'postcode' => $order->get_shipping_postcode(),
			'city' => $order->get_shipping_city(),
			'address' => $order->get_shipping_address_1(),
		] );

		if ( empty( $zone_methods ) ) {
			return __( 'A zona de entrega deste endereço não tem nenhum método Correios Automático. Adicione um em WooCommerce > Configurações > Entrega e depois selecione o serviço na linha de entrega.', 'infixs-correios-automatico' );
		}

		return __( 'Nenhum serviço dos Correios está selecionado nesta linha de entrega. Clique no lápis para editar a linha, escolha o método de entrega e salve o pedido antes de recalcular.', 'infixs-correios-automatico' );
	}

	/**
	 * Add a notice to be rendered in the order items box.
	 *
	 * @since 1.8.0
	 *
	 * @param string $title   Shipping line title.
	 * @param string $message Message describing what has to be done.
	 * @param string $type    Notice type, 'warning' or 'info'.
	 *
	 * @return void
	 */
	protected function add_recalculate_notice( $title, $message, $type = 'warning' ) {
		foreach ( $this->recalculate_notices as $notice ) {
			if ( $notice['title'] === $title && $notice['message'] === $message ) {
				return;
			}
		}

		$this->recalculate_notices[] = [
			'title' => $title,
			'message' => $message,
			'type' => $type,
		];
	}

	/**
	 * Render the notices collected while recalculating, inside the order items box.
	 *
	 * Hooked to an action that runs inside the shipping lines of the items table, which is
	 * the markup returned by the recalculate request, so the message shows up right after
	 * the button is used, with no page reload. Nothing is rendered on a regular page load
	 * because the notices only exist during the request that recalculated the order.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function render_recalculate_notices() {
		if ( empty( $this->recalculate_notices ) ) {
			return;
		}

		Template::adminView( 'html-order-items-notice.php', [
			'notices' => $this->recalculate_notices,
		] );
	}

	/**
	 * Get the rates of a Correios method for the admin recalculation.
	 *
	 * Availability is forced because the method was explicitly chosen in the shipping line:
	 * a service disabled in the shipping zone (therefore not offered at checkout) can still
	 * be used on a manual order, and would otherwise return no rate at all.
	 *
	 * @since 1.8.0
	 *
	 * The reasons of a calculation that produced no rate are captured from the shipping
	 * method, so they can be shown to the operator instead of only reaching the log.
	 *
	 * @param CorreiosShippingMethod $method     Shipping method.
	 * @param array                  $package    Shipping package.
	 * @param array                  $dimensions Package data informed in the shipping line.
	 *
	 * @return array{rates: \WC_Shipping_Rate[], errors: string[]}
	 */
	protected function get_admin_rates( $method, $package, $dimensions = [] ) {
		$filter = "woocommerce_shipping_{$method->id}_is_available";

		$force_available = function () {
			return true;
		};

		$apply_dimensions = function ( $shipping_cost ) use ( $dimensions ) {
			foreach ( $dimensions as $setter => $value ) {
				$shipping_cost->{$setter}( $value );
			}

			return $shipping_cost;
		};

		$errors = [];
		$api_error = '';

		$capture_failure = function ( $code, $message, $failed_package, $failed_method ) use ( &$errors, $method ) {
			if ( $failed_method !== $method ) {
				return;
			}

			$errors[ $code ] = $message;
		};

		$capture_api_error = function ( $error ) use ( &$api_error ) {
			if ( is_wp_error( $error ) ) {
				$api_error = $error->get_error_message();
			}
		};

		add_filter( $filter, $force_available, PHP_INT_MAX );
		add_action( CorreiosShippingMethod::CALCULATION_FAILED_HOOK, $capture_failure, 10, 4 );
		add_action( 'infixs_correios_automatico_shipping_cost_failed', $capture_api_error, 10 );

		if ( ! empty( $dimensions ) ) {
			add_filter( 'infixs_correios_automatico_shipping_cost_data', $apply_dimensions, PHP_INT_MAX );
		}

		try {
			$rates = $method->get_rates_for_package( $package );
		} finally {
			if ( ! empty( $dimensions ) ) {
				remove_filter( 'infixs_correios_automatico_shipping_cost_data', $apply_dimensions, PHP_INT_MAX );
			}

			remove_action( 'infixs_correios_automatico_shipping_cost_failed', $capture_api_error, 10 );
			remove_action( CorreiosShippingMethod::CALCULATION_FAILED_HOOK, $capture_failure, 10 );
			remove_filter( $filter, $force_available, PHP_INT_MAX );
		}

		if ( isset( $errors['api_error'] ) && '' !== $api_error ) {
			$errors['api_error'] .= ' ' . sprintf(
				/* translators: %s: error message returned by the shipping cost API */
				__( 'Retorno da API: %s', 'infixs-correios-automatico' ),
				$api_error
			);
		}

		return [
			'rates' => $rates,
			'errors' => array_values( $errors ),
		];
	}

	/**
	 * Get the package data informed manually in the shipping line.
	 *
	 * The operator can correct the weight and the dimensions directly in the shipping line,
	 * and those values are the ones used in the pré-postagem, so the quote has to use them
	 * instead of the ones derived from the products.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order_Item_Shipping $item Shipping item.
	 *
	 * @return array<string, float> Setter name of ShippingCost => value.
	 */
	protected function get_item_dimensions( $item ) {
		$meta_setters = [
			'_weight' => 'setWeight',
			'_length' => 'setLength',
			'_width' => 'setWidth',
			'_height' => 'setHeight',
		];

		$dimensions = [];

		foreach ( $meta_setters as $meta_key => $setter ) {
			$value = $item->get_meta( $meta_key );

			if ( ! is_scalar( $value ) || '' === $value ) {
				return [];
			}

			$value = (float) str_replace( ',', '.', (string) $value );

			if ( $value <= 0 ) {
				return [];
			}

			$dimensions[ $setter ] = $value;
		}

		return $dimensions;
	}

	/**
	 * Build the shipping package used on the admin recalculation.
	 *
	 * The address typed on the screen is preferred over the saved one, so a manual order can
	 * be quoted before the changes are saved. The shipping address sent by the plugin script
	 * comes first; the taxable address sent by WooCommerce (which may be the billing one) is
	 * only used as a fallback.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order $order Order being recalculated.
	 * @param array     $args  Taxable address sent by the recalculate request.
	 *
	 * @return array
	 */
	protected function get_recalculate_package( $order, $args ) {
		$order_entity = OrderEntity::fromId( $order );

		if ( ! $order_entity ) {
			return [];
		}

		$package = $order_entity->getPackageData();

		$saved_address = [
			'country' => $order->get_shipping_country() ?: $order->get_billing_country(),
			'state' => $order->get_shipping_state() ?: $order->get_billing_state(),
			'postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
			'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
		];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce checked by WooCommerce before this hook.
		foreach ( $saved_address as $field => $saved_value ) {
			$posted_field = "infixs_shipping_{$field}";
			$posted_value = isset( $_POST[ $posted_field ] ) ? wp_unslash( $_POST[ $posted_field ] ) : '';

			if ( is_scalar( $posted_value ) && '' !== $posted_value ) {
				$package['destination'][ $field ] = wc_clean( $posted_value );
				continue;
			}

			if ( '' !== $saved_value && null !== $saved_value ) {
				$package['destination'][ $field ] = $saved_value;
				continue;
			}

			if ( ! empty( $args[ $field ] ) && is_scalar( $args[ $field ] ) ) {
				$package['destination'][ $field ] = wc_clean( $args[ $field ] );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( empty( $package['destination']['country'] ) ) {
			$package['destination']['country'] = 'BR';
		}

		return $package;
	}

	/**
	 * Apply a calculated rate to a shipping order item.
	 *
	 * @since 1.8.0
	 *
	 * Orders converted from a native method (Frete Grátis / Preço Fixo linked to a Correios
	 * method) keep their original cost and combined title: only the package data, the service
	 * and the delivery time are refreshed, so recalculating does not charge shipping that the
	 * customer was not charged for.
	 *
	 * @param \WC_Order_Item_Shipping $item Shipping item.
	 * @param \WC_Shipping_Rate       $rate Calculated rate.
	 *
	 * @return void
	 */
	protected function apply_rate_to_shipping_item( $item, $rate ) {
		$original_method_id = $item->get_meta( '_infixs_original_method_id' );
		$is_converted = ! empty( $original_method_id );
		$stale_meta_keys = [
			'_original_cost',
			'_final_cost',
			'_insurance_cost',
			'_show_original_shipping_discount_price',
			'_hide_others_rates',
			'delivery_time',
			'_weight',
			'_length',
			'_width',
			'_height',
			'shipping_product_code',
		];

		foreach ( $stale_meta_keys as $meta_key ) {
			$item->delete_meta_data( $meta_key );
		}

		foreach ( $rate->get_meta_data() as $meta_key => $meta_value ) {
			$item->update_meta_data( $meta_key, $meta_value );
		}

		if ( ! $is_converted ) {
			$item->set_name( $rate->get_label() );
			$item->set_total( wc_format_decimal( $rate->get_cost() ) );
		}

		$item->save();
	}

	/**
	 * Apply the Correios service chosen in the shipping line before WooCommerce saves the items.
	 *
	 * The service selector is rendered (hidden) on every shipping line, so its value is always
	 * submitted. It is only applied when the line is, or is being changed to, a Correios
	 * Automático method; when a line leaves the Correios method, the instance id is cleared so
	 * it does not keep pointing to a Correios service.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $items    Submitted order items.
	 *
	 * @return void
	 */
	public function before_save_order_items( $order_id, $items ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( ! isset( $items['shipping_method_id'] ) ) {
			return;
		}

		foreach ( $items['shipping_method_id'] as $item_id ) {
			$item = \WC_Order_Factory::get_order_item( absint( $item_id ) );

			if ( ! $item instanceof \WC_Order_Item_Shipping ) {
				continue;
			}

			$method_id = isset( $items['shipping_method'][ $item_id ] ) ?
				wc_clean( wp_unslash( $items['shipping_method'][ $item_id ] ) ) :
				$item->get_method_id();

			if ( 'infixs-correios-automatico' !== $method_id ) {
				if ( 'infixs-correios-automatico' === $item->get_method_id() ) {
					$item->set_instance_id( '' );
					$item->save();
				}

				continue;
			}

			if ( ! isset( $items['instance_id'][ $item_id ] ) ) {
				continue;
			}

			$instance_id = absint( $items['instance_id'][ $item_id ] );
			$method = \WC_Shipping_Zones::get_shipping_method( $instance_id );

			if ( ! $method instanceof CorreiosShippingMethod ) {
				continue;
			}

			$item->set_instance_id( $instance_id );
			$item->save();
		}
	}

	/**
	 * TODO: Save order meta data remove?
	 * 
	 * @param mixed|\WC_Order $order
	 * 
	 * @deprecated 1.2.7
	 * 
	 * @return void
	 */
	public function save_order_meta_data( $order ) {

		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
		}

		$meta_data = [];

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$delivery_time = $item->get_meta( 'delivery_time' );
			$shipping_product_code = $item->get_meta( 'shipping_product_code' );
			$width = $item->get_meta( '_width' );
			$height = $item->get_meta( '_height' );
			$lenght = $item->get_meta( '_length' );
			$weight = $item->get_meta( '_weight' );

			if ( ! isset( $meta_data['width'] ) && ! empty( $width ) ) {
				$meta_data['width'] = $width;
			}

			if ( ! isset( $meta_data['height'] ) && ! empty( $height ) ) {
				$meta_data['height'] = $height;
			}

			if ( ! isset( $meta_data['lenght'] ) && ! empty( $lenght ) ) {
				$meta_data['lenght'] = $lenght;
			}

			if ( ! isset( $meta_data['weight'] ) && ! empty( $weight ) ) {
				$meta_data['weight'] = $weight;
			}

			if ( ! isset( $meta_data['delivery_time'] ) && ! empty( $delivery_time ) ) {
				$meta_data['delivery_time'] = $delivery_time;
			}

			if ( ! isset( $meta_data['shipping_product_code'] ) && ! empty( $shipping_product_code ) ) {
				$meta_data['shipping_product_code'] = $shipping_product_code;
			}
		}

		$order->update_meta_data( '_infixs_correios_automatico_data', $meta_data );

		$order->save();
	}

	public function register_order_status() {

		if ( Config::boolean( 'general.active_preparing_to_ship' ) ) {
			$preparing_to_ship_label = Config::string( 'general.status_preparing_to_ship', 'Preparando para envio' );
			register_post_status( 'wc-preparing-to-ship', [
				'label' => $preparing_to_ship_label,
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of items */
				'label_count' => _n_noop( "Preparando para envio (%s)", "Preparando para envio (%s)", "infixs-correios-automatico" ),
			] );
		}

		if ( Config::boolean( 'general.active_in_transit' ) ) {
			$in_transit_label = Config::string( 'general.status_in_transit', 'Em transporte' );
			register_post_status( 'wc-in-transit', [
				'label' => $in_transit_label,
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of items */
				'label_count' => _n_noop( "Em transporte (%s)", "Em transporte (%s)", "infixs-correios-automatico" ),
			] );
		}

		if ( Config::boolean( 'general.active_waiting_pickup' ) ) {
			$waiting_pickup_label = Config::string( 'general.status_waiting_pickup', 'Aguardando retirada' );
			register_post_status( 'wc-waiting-pickup', [
				'label' => $waiting_pickup_label,
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of items */
				'label_count' => _n_noop( "Aguardando retirada (%s)", "Aguardando retirada (%s)", "infixs-correios-automatico" ),
			] );
		}

		if ( Config::boolean( 'return.active' ) ) {
			$returning_label = Config::string( 'return.status', 'Em devolução' );
			register_post_status( 'wc-returning', [
				'label' => $returning_label,
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of items */
				'label_count' => _n_noop( "Em devolução (%s)", "Em devolução (%s)", "infixs-correios-automatico" ),
			] );
		}

		if ( Config::boolean( 'general.active_delivered' ) ) {
			$delivered_label = Config::string( 'general.status_delivered', 'Entregue' );
			register_post_status( 'wc-delivered', [
				'label' => $delivered_label,
				'public' => true,
				'exclude_from_search' => false,
				'show_in_admin_all_list' => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count of items */
				'label_count' => _n_noop( "Entregue (%s)", "Entregue (%s)", "infixs-correios-automatico" ),
			] );
		}
	}

	public function add_order_status( $order_statuses ) {
		if ( Config::boolean( 'general.active_preparing_to_ship' ) ) {
			$preparing_to_ship_label = Config::string( 'general.status_preparing_to_ship', 'Preparando para envio' );
			$order_statuses['wc-preparing-to-ship'] = $preparing_to_ship_label;
		}

		if ( Config::boolean( 'general.active_in_transit' ) ) {
			$in_transit_label = Config::string( 'general.status_in_transit', 'Em transporte' );
			$order_statuses['wc-in-transit'] = $in_transit_label;
		}

		if ( Config::boolean( 'general.active_waiting_pickup' ) ) {
			$waiting_pickup_label = Config::string( 'general.status_waiting_pickup', 'Aguardando retirada' );
			$order_statuses['wc-waiting-pickup'] = $waiting_pickup_label;
		}

		if ( Config::boolean( 'return.active' ) ) {
			$returning_label = Config::string( 'return.status', 'Em devolução' );
			$order_statuses['wc-returning'] = $returning_label;
		}

		if ( Config::boolean( 'general.active_delivered' ) ) {
			$delivered_label = Config::string( 'general.status_delivered', 'Entregue' );
			$order_statuses['wc-delivered'] = $delivered_label;
		}

		return $order_statuses;
	}

	public function order_status_changed( $order_id, $old_status, $new_status ) {
		if ( 'preparing-to-ship' === $new_status &&
			Config::boolean( 'general.active_preparing_to_ship' ) &&
			Config::boolean( 'general.email_preparing_to_ship' ) ) {
			$this->trackingService->sendPreparingToShipNotification( $order_id );
		}

		if ( 'in-transit' === $new_status &&
			Config::boolean( 'general.active_in_transit' ) &&
			Config::boolean( 'general.email_in_transit' ) ) {
			$trackings = $this->trackingService->getTrackings( $order_id );
			$codes = $trackings->pluck( 'code' )->toArray();
			if ( ! empty( $codes ) ) {
				$this->trackingService->sendTrackingNotification( $order_id, $codes );
			}
		}

		if ( 'waiting-pickup' === $new_status &&
			Config::boolean( 'general.active_waiting_pickup' ) &&
			Config::boolean( 'general.email_waiting_pickup' ) ) {
			$this->trackingService->sendWaitingPickupNotification( $order_id );
		}

		if ( 'returning' === $new_status &&
			Config::boolean( 'general.active_returning' ) &&
			Config::boolean( 'general.email_returning' ) ) {
			//$this->trackingService->sendReturningNotification( $order_id );
		}

		if ( 'delivered' === $new_status &&
			Config::boolean( 'general.active_delivered' ) &&
			Config::boolean( 'general.email_delivered' ) ) {
			$this->trackingService->sendDeliveredNotification( $order_id );
		}
	}
}