<?php

namespace Infixs\CorreiosAutomatico\Services;
use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\CeintCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Utils\NumberHelper;
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
	 * 
	 * @param array{
	 * 			page: int,
	 * 			per_page: int
	 * 			search: string
	 * } $query Query parameters.
	 * 
	 * @return array
	 */
	public function getOrders( $query ) {

		$page = $query['page'] ?? 1;
		$per_page = $query['per_page'] ?? 10;
		$search = $query['search'] ?? null;
		$status = isset( $query['status'] ) ? explode( ",", $query['status'] ) : Config::get( 'preferences.order.status' );

		$order_query_args = [ 
			'limit' => $per_page,
			'page' => $page,
			'paginate' => true,
			'type' => "shop_order",
			'status' => $status,
			'order_by' => 'date',
			'order' => 'DESC'
		];

		if ( ! empty( $search ) ) {
			if ( function_exists( 'wc_get_container' ) &&
				class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) &&
				wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ) {
				$order_query_args['s'] = $search;
				$order_query_args['search_filter'] = 'all';
			} else {
				$order_query_args['meta_query'] = [ 
					'relation' => 'OR',
					[ 
						'key' => '_billing_first_name',
						'value' => $search,
						'compare' => 'LIKE'
					],
					[ 
						'key' => '_billing_last_name',
						'value' => $search,
						'compare' => 'LIKE'
					],
					[ 
						'key' => '_billing_email',
						'value' => $search,
						'compare' => 'LIKE'
					],
					[ 
						'key' => '_billing_address_1',
						'value' => $search,
						'compare' => 'LIKE'
					],
				];

				if ( is_numeric( $search ) ) {
					$order_query_args['post__in'] = [ absint( $search ) ];
				}
			}
		}

		$orders = wc_get_orders( $order_query_args );

		$data = $this->transformOrders( $orders->orders );

		$max_num_pages = $orders->max_num_pages;

		return [ 
			'page' => $page,
			'per_page' => $per_page,
			'total_results' => count( $data ),
			'total' => $orders->total ?? 0,
			'orders' => $data,
		];
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
	 * 
	 * @return array
	 */
	public function calculateShipping( $instance_id, $order_id ) {
		$ca_order = Order::fromId( $order_id );

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
			wp_schedule_single_event( time() + 10, 'infixs_correios_automatico_update_status_schedule', [ 
				$order_id
			] );
		}
	}

	public function update_status_schedule( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		Log::debug( "Schedule: Mudando status do pedido para 'preparando para envio' order_id: $order_id." );
		$order->update_status( 'preparing-to-ship', 'Correios Automático:' );
	}
}