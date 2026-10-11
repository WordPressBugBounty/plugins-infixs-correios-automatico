<?php

namespace Infixs\CorreiosAutomatico\Services;
use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Database\OrderFilterConditions;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\CeintCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Utils\NumberHelper;
use Infixs\CorreiosAutomatico\Utils\OrderQuery;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class OrderService {

	public function __construct() {
		add_action( 'infixs_correios_automatico_update_status_schedule', [ $this, 'update_status_schedule' ], 10 );
	}

	/**
	 * Get orders
	 * 
	 * @since 1.0.0
	 * @since 1.11.0 Narrows the listing by carrier and by pendency.
	 * 
	 * @param array{
	 * 			page: int,
	 * 			per_page: int,
	 * 			search: string,
	 * 			status: string[],
	 * 			carrier: string[],
	 * 			pending: string[]
	 * } $query Query parameters, with the filters already sanitized.
	 * 
	 * @return array
	 */
	public function getOrders( $query ) {

		$page = isset( $query['page'] ) ? max( 1, (int) $query['page'] ) : 1;
		$per_page = isset( $query['per_page'] ) ? max( 1, (int) $query['per_page'] ) : 10;
		$search = isset( $query['search'] ) ? $query['search'] : null;
		$statuses = isset( $query['status'] ) ? (array) $query['status'] : [];
		$carriers = isset( $query['carrier'] ) ? (array) $query['carrier'] : [];
		$pending = isset( $query['pending'] ) ? (array) $query['pending'] : [];

		$order_query_args = [ 
			'limit' => $per_page,
			'page' => $page,
			'paginate' => true,
			'type' => "shop_order",
			'orderby' => 'date',
			'order' => 'DESC'
		];

		$statuses = $statuses ? $statuses : $this->getUnfilteredStatuses();

		if ( $statuses ) {
			$order_query_args['status'] = $statuses;
		}

		$conditions = [];

		if ( $carriers ) {
			$conditions[] = OrderFilterConditions::carriers( $carriers );
		}

		if ( $pending ) {
			$conditions[] = OrderFilterConditions::pending( $pending );
		}

		if ( ! empty( $search ) ) {
			if ( OrderQuery::usesOrdersTable() ) {
				$order_query_args['s'] = $search;
				$order_query_args['search_filter'] = 'all';
			} else {
				$conditions[] = OrderFilterConditions::search( $search );
			}
		}

		$orders = wc_get_orders( OrderQuery::withConditions( $order_query_args, $conditions ) );

		$data = $this->transformOrders( $orders->orders );

		return [ 
			'page' => $page,
			'per_page' => $per_page,
			'total_results' => count( $data ),
			'total' => isset( $orders->total ) ? (int) $orders->total : 0,
			'total_pages' => isset( $orders->max_num_pages ) ? (int) $orders->max_num_pages : 0,
			'orders' => $data,
		];
	}

	/**
	 * Get the statuses a listing without a status filter shows.
	 *
	 * Every order status of the store, but the ones kept out of searches: the
	 * checkout drafts of the block checkout are carts that were never placed,
	 * and the "Todos" list of the WooCommerce leaves them out too. On its own,
	 * `wc_get_orders()` would bring them in.
	 *
	 * @since 1.11.0
	 *
	 * @return string[]
	 */
	private function getUnfilteredStatuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return [];
		}

		$hidden = get_post_stati( [ 'exclude_from_search' => true ] );

		return array_values( array_diff( array_keys( wc_get_order_statuses() ), $hidden ) );
	}

	/**
	 * Get a single order with the same payload of the listing.
	 *
	 * Lets the dashboard refresh one row reactively instead of reloading the
	 * whole list after an action on that order.
	 *
	 * @since 1.9.1
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array|null Null when the order does not exist.
	 */
	public function getOrder( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$ca_order = new Order( $order );

		return $ca_order->toArray();
	}

	/**
	 * Transform a WC_Order object into the desired array format.
	 *
	 * @param \WC_Order[] $orders Orders.
	 * @return array
	 */
	private function transformOrders( $orders ) {
		$result = [];
		foreach ( $orders as $order ) {
			$ca_order = new Order( $order );
			$result[] = $ca_order->toArray();
		}

		return $result;
	}

	/**
	 * Calculate shipping
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $instance_id Shipping method instance ID.
	 * @param int $order_id Order ID.
	 * @param int $shipping_item_id Shipping line being quoted, zero for the whole order. @since 1.11.0
	 * 
	 * @return array
	 */
	public function calculateShipping( $instance_id, $order_id, $shipping_item_id = 0 ) {
		$ca_order = Order::fromId( $order_id );

		if ( $ca_order && $shipping_item_id ) {
			$ca_order->applyScope( Order::resolveScope( $ca_order, [ 
				'for' => 'calculate',
				'shipping_item_id' => $shipping_item_id,
			] ) );
		}

		$rates = Container::shippingService()->calculateShippingByMethod( $instance_id, $ca_order->getPackageData() );

		$response = [];

		foreach ( $rates as $rate ) {
			$metas = $rate->get_meta_data();

			$response[] =
				$rate->get_method_id() === 'infixs-correios-automatico' ?
				[
					'cost' => NumberHelper::to100( $metas['_original_cost'] ),
					'height' => (int) $metas['_height'],
					'width' => (int) $metas['_width'],
					'length' => (int) $metas['_length'],
					'weight' => Sanitizer::weight( $metas['_weight'] ),
					'insurance_cost' => isset( $metas['_insurance_cost'] ) ? NumberHelper::to100( $metas['_insurance_cost'] ) : 0,
					'shipping_product_code' => $metas['shipping_product_code'],
					'delivery_time' => $metas['delivery_time'],
				] : [
					'cost' => NumberHelper::to100( $rate->get_cost() ),
					'height' => 0,
					'width' => 0,
					'length' => 0,
					'weight' => 0,
					'shipping_product_code' => null,
					'insurance_cost' => 0,
					'delivery_time' => 0,
				];
		}

		return $response;
	}

	/**
	 * Update order
	 * 
	 * @since 1.3.8
	 * 
	 * @param int $order_id Order ID.
	 * @param array $params Order parameters.
	 * 
	 * @return \WP_Error|bool
	 */
	public function updateOrder( $order_id, $params ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'invalid_order_id', 'Invalid Order ID.', [ 'status' => 400 ] );
		}

		if ( isset( $params['printed'] ) ) {
			if ( $params['printed'] === true ) {
				$order->update_meta_data( '_infixs_correios_automatico_printed', current_time( 'mysql' ) );
			} else {
				$order->delete_meta_data( '_infixs_correios_automatico_printed' );
			}
		}

		$order->save();

		return true;
	}

	public function changeToPreparing( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( Config::boolean( 'general.active_preparing_to_ship' ) && $order->get_status() === 'processing' ) {
			Log::debug( "Adicionando um agendamento para mudança de status automática de 'processando' para 'preparando para envio', order_id: $order_id." );
			if ( function_exists( 'as_schedule_single_action' ) ) {
				Log::debug( "Usando agendador do WooCommerce" );
				as_schedule_single_action( time() + 10, 'infixs_correios_automatico_update_status_schedule', [ $order_id ] );
			} else {
				Log::debug( "Usando agendador do WordPress" );
				wp_schedule_single_event( time() + 10, 'infixs_correios_automatico_update_status_schedule', [ $order_id ] );
			}
		}
	}

	public function update_status_schedule( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			Log::alert( "Problema ao mudar o status para 'preparando para envio': $order_id." );
			return;
		}
		Log::debug( "Schedule: Mudando status do pedido para 'preparando para envio' order_id: $order_id." );
		$order->update_status( 'preparing-to-ship', 'Correios Automático:' );
	}
}