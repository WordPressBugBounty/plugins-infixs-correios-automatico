<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;
use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Template;
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

	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;

		add_action( 'woocommerce_before_order_itemmeta', [ $this, 'before_order_itemmeta' ], 10, 2 );
		add_action( 'woocommerce_before_save_order_items', [ $this, 'before_save_order_items' ], 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_itemmeta' ] );
		add_filter( 'woocommerce_order_item_display_meta_key', [ $this, 'order_item_display_meta_key' ], 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_value', [ $this, 'order_item_display_meta_value' ], 10, 3 );


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
			'shipping_product_code'
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

		if ( $item->get_method_id() !== 'infixs-correios-automatico' ) {
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

		if ( $item->get_method_id() !== 'infixs-correios-automatico' ) {
			return $display_value;
		}

		if ( 'shipping_product_code' === $meta->key ) {
			$method = \WC_Shipping_Zones::get_shipping_method( $item->get_instance_id() );
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

	public function before_save_order_items( $order_id, $items ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( isset( $items['shipping_method_id'] ) ) {
			foreach ( $items['shipping_method_id'] as $item_id ) {
				$item = \WC_Order_Factory::get_order_item( absint( $item_id ) );
				if ( ! $item ) {
					continue;
				}
				if ( $item instanceof \WC_Order_Item_Shipping ) {
					if ( isset( $items['instance_id'][ $item_id ] ) ) {
						//$method = new CorreiosShippingMethod( $item->get_instance_id() );
						$item->set_instance_id( $items['instance_id'][ $item_id ] );
						$item->save();
					}

				}
			}
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

		if ( Config::boolean( 'general.active_returning' ) ) {
			$returning_label = Config::string( 'general.status_returning', 'Em devolução' );
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

		if ( Config::boolean( 'general.active_returning' ) ) {
			$returning_label = Config::string( 'general.status_returning', 'Em devolução' );
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
			//$this->trackingService->sendDeliveredNotification( $order_id );
		}
	}
}