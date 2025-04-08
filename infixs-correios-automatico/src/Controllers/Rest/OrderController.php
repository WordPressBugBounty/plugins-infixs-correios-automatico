<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\OrderService;

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

	public function __construct( OrderService $orderService ) {
		$this->orderService = $orderService;
	}

	/**
	 * List orders.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function list( $request ) {
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$search = $request->get_param( 'search' );
		$status = $request->get_param( 'status' );

		$orders = $this->orderService->getOrders( [ 
			'page' => $page,
			'per_page' => $per_page,
			'search' => $search,
			'status' => $status
		] );

		return rest_ensure_response(
			array_merge( [ 
				"status" => "success",
			],
				$orders
			)
		);
	}

	public function save_preferences( $request ) {
		$preferences = $request->get_json_params();

		$updated_preferences = [];

		if ( isset( $preferences['status'] ) ) {
			Config::update( 'preferences.order.status', $preferences['status'] );
		}

		return rest_ensure_response( [ 
			"status" => "success",
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

		$result = $this->orderService->calculateShipping( $params['instance_id'], $order_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Update Shipping
	 * 
	 * @since 1.0.0
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

		/** @var \WC_Order_Item_Shipping $shipping_item */
		$shipping_items = $order->get_items( 'shipping' );
		$shipping_item = reset( $shipping_items );

		$shipping_item->set_instance_id( $params['instance_id'] );
		$shipping_item->set_method_id( $shipping_method->id );
		$shipping_item->set_name( $shipping_method->get_title() );

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

		$response = Container::labelService()->attachRangeToOrder( $order_id, $params['service_code'] );

		if ( is_wp_error( $response ) ) {
			$response->add_data( [ 'status' => 400 ] );
			return $response;
		}

		return rest_ensure_response( [ 
			'success' => true,
		] );
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

		$updated_orders = [];

		foreach ( $params['orders'] as $order_id ) {
			$created = Container::unitService()->unitPacketByOrder( $order_id );
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

		$order->delete_meta_data( '_infixs_correios_automatico_prepost_id' );
		$order->delete_meta_data( '_infixs_correios_automatico_prepost_created' );
		$order->save();


		return rest_ensure_response( [ 
			'status' => 'success',
		] );
	}
}