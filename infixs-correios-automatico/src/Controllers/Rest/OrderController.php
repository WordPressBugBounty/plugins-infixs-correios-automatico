<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\OrderService;
use Infixs\CorreiosAutomatico\Utils\OrderFilterSanitizer;
use Infixs\CorreiosAutomatico\Utils\ShipmentCodesFormatter;
use Infixs\CorreiosAutomatico\Utils\ShippingLineName;

defined( 'ABSPATH' ) || exit;
class OrderController {

	/**
	 * Order controller instance.
	 * 
	 * @since 1.0.0
	 * 
	 * @var OrderService
	 */
	private $orderService;

	/**
	 * Filters of the listing, and the preference each one falls back to.
	 *
	 * @since 1.11.0
	 *
	 * @var array<string, string>
	 */
	private static $filters = [ 
		'status' => 'statuses',
		'carrier' => 'carriers',
		'pending' => 'pending',
	];

	public function __construct( OrderService $orderService ) {
		$this->orderService = $orderService;
	}

	/**
	 * List orders.
	 * 
	 * @since 1.0.0
	 * @since 1.11.0 Filters by carrier and by pendency, each one falling back to the saved preference.
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function list( $request ) {
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$search = $request->get_param( 'search' );

		$orders = $this->orderService->getOrders( [
			'page' => $page,
			'per_page' => $per_page,
			'search' => $search,
			'status' => $this->filter( $request, 'status' ),
			'carrier' => $this->filter( $request, 'carrier' ),
			'pending' => $this->filter( $request, 'pending' ),
		] );

		return rest_ensure_response(
			array_merge( [
				"status" => "success",
			],
				$orders
			)
		);
	}

	/**
	 * Read one filter of the listing.
	 *
	 * A filter the request does not carry falls back to the preference saved by
	 * the operator; a filter that is there is used as it came, empty included —
	 * which is how the dashboard says "no filter of this kind", instead of
	 * silently applying an old preference.
	 *
	 * @since 1.11.0
	 *
	 * @param \WP_REST_Request $request Request being answered.
	 * @param string           $filter  Filter name.
	 *
	 * @return string[]
	 */
	private function filter( $request, $filter ) {
		$sanitizer = [ OrderFilterSanitizer::class, self::$filters[ $filter ] ];

		if ( $request->has_param( $filter ) ) {
			return call_user_func( $sanitizer, $request->get_param( $filter ) );
		}

		return call_user_func( $sanitizer, Config::get( "preferences.order.{$filter}", [] ) );
	}

	/**
	 * Save the filters the listing opens with.
	 *
	 * Only the filters present in the body are saved, so the dashboard can keep
	 * a preference it is not showing.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 Saves the carrier and the pendency filters too.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function save_preferences( $request ) {
		$preferences = $request->get_json_params();
		$preferences = is_array( $preferences ) ? $preferences : [];

		$saved = [];

		foreach ( self::$filters as $filter => $sanitizer ) {
			if ( ! isset( $preferences[ $filter ] ) ) {
				continue;
			}

			$saved[ $filter ] = call_user_func( [ OrderFilterSanitizer::class, $sanitizer ], $preferences[ $filter ] );

			Config::update( "preferences.order.{$filter}", $saved[ $filter ] );
		}

		return rest_ensure_response( [
			"status" => "success",
			"preferences" => $saved,
		] );
	}

	/**
	 * Get a single order, with the same payload of the listing.
	 *
	 * @since 1.9.1
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function retrieve( $request ) {
		$order = $this->orderService->getOrder( absint( $request['id'] ) );

		if ( null === $order ) {
			return new \WP_Error( 'invalid_order', __( 'Pedido inválido.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( [
			'status' => 'success',
			'order' => $order,
		] );
	}

	/**
	 * Patch order by ID.
	 *
	 * @since 1.3.8
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function update( $request ) {
		$order_id = $request->get_param( 'id' );
		$params = $request->get_json_params();

		if ( ! $order_id ) {
			return new \WP_Error( 'missing_order_id', 'Order ID is required.', [ 'status' => 400 ] );
		}

		$this->orderService->updateOrder( $order_id, $params );

		return rest_ensure_response( [
			'status' => 'success',
			'order_id' => $order_id,
		] );
	}

	/**
	 * Patch batch order by ID.
	 * 
	 * @since 1.3.8
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function batch_update( $request ) {

		$params = $request->get_json_params();

		if ( empty( $params['orders'] ) ) {
			return new \WP_Error( 'empty_order_id', 'Order ID is not empty.', [ 'status' => 400 ] );
		}

		$updated_orders = [];

		foreach ( $params['orders'] as $order_id ) {
			$created = $this->orderService->updateOrder( $order_id, $params );
			if ( ! is_wp_error( $created ) ) {
				$updated_orders[] = $order_id;
			}
		}

		return rest_ensure_response( [
			'status' => 'success',
			'updated_orders' => $updated_orders,
		] );
	}

	/**
	 * Calculate shipping from order.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function calculate_shipping( $request ) {
		$order_id = $request->get_param( 'id' );
		$params = $request->get_json_params();

		if ( ! isset( $params['instance_id'] ) && ! $params['instance_id'] ) {
			return new \WP_Error( 'missing_instance_id', 'Instance ID is required.', [ 'status' => 400 ] );
		}

		$shipping_item_id = isset( $params['shipping_item_id'] ) ? absint( $params['shipping_item_id'] ) : 0;

		$result = $this->orderService->calculateShipping( $params['instance_id'], $order_id, $shipping_item_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Update Shipping
	 *
	 * @since 1.0.0
	 * @since 1.11.0 A volume keeps saying which volume it is in the name of the line.
	 *
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function update_shipping( $request ) {
		$order_id = $request->get_param( 'id' );
		$params = $request->get_json_params();

		if ( ! isset( $params['instance_id'] ) || ! $params['instance_id'] ) {
			return new \WP_Error( 'missing_instance_id', 'Instance ID is required.', [ 'status' => 400 ] );
		}

		$shipping_method = \WC_Shipping_Zones::get_shipping_method( $params['instance_id'] );

		if ( ! $shipping_method ) {
			return new \WP_Error( 'invalid_instance_id', 'Invalid instance ID.', [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'invalid_order', 'Invalid order.', [ 'status' => 404 ] );
		}

		/** @var \WC_Order_Item_Shipping $shipping_item */
		$shipping_items = $order->get_items( 'shipping' );

		/**
		 * An order can carry one shipping line per vendor, so the dashboard names
		 * which one it is editing. Without it the first line is used, as before.
		 */
		if ( ! empty( $params['shipping_item_id'] ) ) {
			$shipping_item = $order->get_item( absint( $params['shipping_item_id'] ) );

			if ( ! $shipping_item instanceof \WC_Order_Item_Shipping ) {
				return new \WP_Error( 'invalid_shipping_item', 'Invalid shipping item for this order.', [ 'status' => 400 ] );
			}
		} else {
			$shipping_item = reset( $shipping_items );
		}

		/**
		 * An order that came in with no delivery at all — placed with another
		 * plugin, or created by hand — gets the line here, so the store can
		 * ship it without editing the order in WooCommerce first. The freight
		 * charged stays zero: the order was closed without it, and changing the
		 * totals from this screen would part the order from what was paid.
		 */
		if ( ! $shipping_item ) {
			$shipping_item = new \WC_Order_Item_Shipping();
			$shipping_item->set_total( 0 );
			$order->add_item( $shipping_item );
		}

		$shipping_item->set_instance_id( $params['instance_id'] );
		$shipping_item->set_method_id( $shipping_method->id );

		$method_name = $shipping_method->get_title();

		// Converted orders (native Frete Grátis / Preço Fixo linked to a Correios method) carry a
		// combined title like "PAC (Frete grátis)". Rebuild it here so recalculating does not reset
		// it to the bare service name. If the operator switched the Correios service, the new title
		// is combined with the same native suffix ("SEDEX (Frete grátis)").
		if ( $shipping_method instanceof CorreiosShippingMethod ) {
			$original_title = $shipping_item->get_meta( '_infixs_original_method_title' );

			// Fallback for orders converted before this meta existed: reuse the suffix already on
			// the item name (still combined at this point, before set_name below overwrites it).
			if ( empty( $original_title ) && $shipping_item->get_meta( '_infixs_original_method_id' )
				&& preg_match( '/\(([^)]+)\)\s*$/u', (string) $shipping_item->get_name(), $matches ) ) {
				$original_title = $matches[1];
			}

			if ( ! empty( $original_title ) ) {
				$method_name = sprintf( '%s (%s)', $method_name, $original_title );
			}
		}

		$shipping_item->set_name( ShippingLineName::build( $shipping_item, $method_name ) );

		$shipping_item->update_meta_data( '_length', $params['length'] );
		$shipping_item->update_meta_data( '_width', $params['width'] );
		$shipping_item->update_meta_data( '_height', $params['height'] );
		$shipping_item->update_meta_data( '_weight', $params['weight'] );
		$shipping_item->update_meta_data( 'delivery_time', $params['delivery_time'] );
		if ( $shipping_method instanceof CorreiosShippingMethod ) {
			$shipping_item->update_meta_data( 'shipping_product_code', $shipping_method->get_product_code() );
		}

		if ( isset( $params['cost'] ) )
			$shipping_item->update_meta_data( '_original_cost', $params['cost'] );
		// 	$shipping_item->set_total( $params['cost'] );

		$shipping_item->save();

		return rest_ensure_response( [] );
	}

	/**
	 * Attach range to order
	 *
	 * @since 1.3.7
	 * @since 1.11.0 Binds the code to the shipping line named by `shipping_item_id`.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function attach_range( $request ) {
		$order_id = $request->get_param( 'id' );
		$params = $request->get_json_params();

		if ( ! isset( $params['service_code'] ) || ! $params['service_code'] ) {
			return new \WP_Error( 'missing_service_code', 'Service code is required.', [ 'status' => 400 ] );
		}

		$shipping_item_id = isset( $params['shipping_item_id'] ) ? absint( $params['shipping_item_id'] ) : 0;

		if ( $shipping_item_id && ! $this->is_order_shipping_item( $order_id, $shipping_item_id ) ) {
			return new \WP_Error( 'invalid_shipping_item', 'Invalid shipping item for this order.', [ 'status' => 400 ] );
		}

		$response = Container::labelService()->attachRangeToOrder( $order_id, $params['service_code'], $shipping_item_id );

		if ( is_wp_error( $response ) ) {
			$response->add_data( [ 'status' => 400 ] );
			return $response;
		}

		return rest_ensure_response( [
			'success' => true,
		] );
	}

	/**
	 * Whether a shipping line belongs to an order.
	 *
	 * An order item is loaded by its id alone, so a line of another order would
	 * otherwise be accepted and the code would follow a shipment it is not on.
	 *
	 * @since 1.11.0
	 *
	 * @param int $order_id         Order ID.
	 * @param int $shipping_item_id Shipping line ID.
	 *
	 * @return bool
	 */
	private function is_order_shipping_item( $order_id, $shipping_item_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		return array_key_exists( $shipping_item_id, $order->get_items( 'shipping' ) );
	}


	/**
	 * Unitizer orders
	 * 
	 * @since 1.3.7
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function unit( $request ) {
		$params = $request->get_json_params();

		if ( empty( $params['orders'] ) ) {
			return new \WP_Error( 'empty_order_id', 'Order ID is not empty.', [ 'status' => 400 ] );
		}

		$ceint_id = isset( $params['ceint_id'] ) && is_numeric( $params['ceint_id'] ) ? (int) $params['ceint_id'] : null;

		$updated_orders = [];
		$error_orders = [];

		foreach ( $params['orders'] as $order_id ) {
			$created = Container::unitService()->unitPacketByOrder( $order_id, $ceint_id );
			if ( ! is_wp_error( $created ) ) {
				$updated_orders[] = $order_id;
			} else {
				$error_orders[] = [
					'order_id' => $order_id,
					'message' => $created->get_error_message(),
				];
			}
		}

		return rest_ensure_response( [
			'status' => 'success',
			'updated_orders' => $updated_orders,
			'error_orders' => $error_orders,
		] );
	}

	/**
	 * Delete prepost from order.
	 * 
	 * @since 1.5.1
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */

	public function delete_prepost( $request ) {
		$order_id = $request->get_param( 'id' );
		$prepost_id = $request->get_param( 'prepost_id' );

		if ( ! $order_id ) {
			return new \WP_Error( 'infixs_correios_automatico_invalid_order_id', __( 'Invalid order ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		if ( ! $prepost_id ) {
			return new \WP_Error( 'infixs_correios_automatico_invalid_prepost_id', __( 'Invalid prepost ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', 'Pedido não encontrado.', [ 'status' => 404 ] );
		}

		$prepost = Container::prepostService()->getPrepost( $prepost_id );

		if ( ! $prepost ) {
			return new \WP_Error( 'prepost_not_found', 'Pré-postagem não encontrada.', [ 'status' => 404 ] );
		}

		$response = Container::prepostService()->cancelPrepost( $prepost_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		Container::prepostService()->releaseShipmentPrepost( $order, $prepost );

		return rest_ensure_response( [
			'status' => 'success',
		] );
	}

	/**
	 * Dismiss a prepost error from an order.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function dismiss_prepost_error( $request ) {
		$order_id = $request->get_param( 'id' );
		$error_id = $request->get_param( 'error_id' );

		if ( ! $order_id ) {
			return new \WP_Error( 'infixs_correios_automatico_invalid_order_id', __( 'Invalid order ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		if ( ! $error_id ) {
			return new \WP_Error( 'infixs_correios_automatico_invalid_error_id', __( 'Invalid error ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$response = Container::prepostService()->dismissOrderPrepostError( $order_id, $error_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( [
			'status' => 'success',
		] );
	}

	/**
	 * Clear every prepost error from an order.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function clear_prepost_errors( $request ) {
		$order_id = $request->get_param( 'id' );

		if ( ! $order_id ) {
			return new \WP_Error( 'infixs_correios_automatico_invalid_order_id', __( 'Invalid order ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		Container::prepostService()->clearOrderPrepostErrors( $order_id );

		return rest_ensure_response( [
			'status' => 'success',
		] );
	}

	/**
	 * Send tracking whatsapp
	 *
	 * @since 1.6.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function send_tracking_whatsapp( $request ) {
		$order_id = $request->get_param( 'id' );
		$params = $request->get_json_params();
		$connection_id = $params['connection'] ?? null;

		if ( ! $order_id ) {
			return new \WP_Error( 'infixs_correios_automatico_missing_order_id', __( 'Order ID is required.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		if ( ! $connection_id ) {
			return new \WP_Error( 'infixs_correios_automatico_missing_connection_id', __( 'Connection ID is required.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$order = \Infixs\CorreiosAutomatico\Entities\Order::fromId( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'infixs_correios_automatico_order_not_found', __( 'Order not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		$customer_name = $order->getCustomerFullName();
		$phone = $order->getAlwaysPhone();

		if ( empty( $phone ) ) {
			return new \WP_Error( 'infixs_correios_automatico_missing_phone', __( 'Customer phone not found.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$message = [ 'text' => $this->tracking_whatsapp_text( $order, $customer_name ) ];
		$result = false;

		if ( function_exists( 'pingo_notify_send_message' ) ) {
			if ( function_exists( 'pingo_notify_normalize_phone' ) ) {
				$phone = pingo_notify_normalize_phone( $phone );
			}
			$result = pingo_notify_send_message( $connection_id, $phone, $message );
		}

		if ( $result ) {
			return rest_ensure_response( [ 'success' => true ] );
		} else {
			return new \WP_Error( 'infixs_correios_automatico_dispatch_failed', __( 'Failed to dispatch message, update a Pingo Notify plugin.', 'infixs-correios-automatico' ), [ 'status' => 500 ] );
		}
	}

	/**
	 * Build the WhatsApp text with the tracking codes of an order.
	 *
	 * An order shipped in a single object keeps the message it always had. One
	 * shipped in volumes lists the code of every box, one per line.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Entities\Order $order         Order being notified.
	 * @param string                                    $customer_name Name the message greets.
	 *
	 * @return string
	 */
	private function tracking_whatsapp_text( $order, $customer_name ) {
		$order_id = $order->get_id();
		$codes = Container::trackingService()->getShipmentCodes( $order_id );

		if ( ! $codes ) {
			return "Olá {$customer_name}, sobre o pedido #{$order_id}.";
		}

		if ( count( $codes ) === 1 ) {
			return "Olá {$customer_name}, segue o código de rastreio do seu pedido #{$order_id}: {$codes[0]['code']}";
		}

		return "Olá {$customer_name}, seguem os códigos de rastreio do seu pedido #{$order_id}:\n" . implode( "\n", ShipmentCodesFormatter::lines( $order, $codes ) );
	}
}