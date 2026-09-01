<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;

use Infixs\CorreiosAutomatico\Services\TrackingService;
use Infixs\CorreiosAutomatico\Entities\Order as CAOrder;

defined( 'ABSPATH' ) || exit;

class Order {
	/**
	 * Tracking Service
	 * 
	 * @var TrackingService
	 */
	private $trackingService;

	/**
	 * Constructor
	 * 
	 * @param TrackingService $trackingService
	 */
	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;

		add_filter( 'woocommerce_my_account_my_orders_columns', [ $this, 'add_tracking_order_column' ] );
		add_action( 'woocommerce_my_account_my_orders_column_infixs-correios-automatico-tracking-column', [ $this, 'add_tracking_order_column_content' ] );
		add_action( 'woocommerce_view_order', [ $this, 'add_content_above_order_details' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_order_details_script' ] );
	}

	public function add_tracking_order_column( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			if ( 'order-status' === $key ) {
				$new_columns['infixs-correios-automatico-tracking-column'] = __( 'Rastreio', 'infixs-correios-automatico' );
			}
		}
		return $new_columns;
	}

	/**
	 * Add tracking order column content
	 * 
	 * @param \WC_Order $order
	 */
	public function add_tracking_order_column_content( $order ) {

		$tracking = $this->trackingService->get_last_tracking_order( $order->get_id() );
		$last_event_text = "Não Disponível";
		$tracking_code = '';

		if ( $tracking ) {
			$tracking_code = sprintf(
				'<strong>%s</strong><br/>',
				esc_html( $tracking->code )
			);
			$lastEvent = $tracking->events->sortByDesc( 'event_date' )->first();
			$last_event_text = $lastEvent ? $lastEvent->description : "Preparando para envio";
		}

		echo sprintf(
			'%s%s',
			wp_kses( $tracking_code, [ 'br' => [], 'strong' => [] ] ),
			esc_html( $last_event_text )
		);
	}

	public function add_content_above_order_details( $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! ( $order instanceof \WC_Order ) ) {
			return;
		}

		$order = new CAOrder( $order );

		$objects = $this->trackingService->get_order_tracking_history( $order_id, true );

		$template = 'order/tracking-order.php';

		wc_get_template(
			$template,
			[
				'objects' => $objects,
				'order' => $order->getOrder(),
				'show_tracking' => $this->show_tracking( $order, $objects ),
			],
			'infixs-correios-automatico/',
			\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'templates/'
		);
	}

	/**
	 * Whether the tracking section belongs on this order page.
	 *
	 * The section follows a shipment this plugin handles, so it only shows for
	 * an order shipped by one of our methods — Correios Automático, a method
	 * linked to it, or a carrier extension such as PEX and JadLog. An order
	 * already carrying a tracking object keeps the section whatever the method
	 * is: the store registered that code by hand and the customer has to see
	 * it.
	 *
	 * @since 1.10.4
	 *
	 * @param CAOrder $order   Order being rendered.
	 * @param array   $objects Tracking objects of the order.
	 *
	 * @return bool
	 */
	private function show_tracking( $order, $objects ) {
		$show_tracking = ! empty( $objects ) || $order->hasInfixsShippingMethod();

		/**
		 * Filters whether the tracking section shows on the customer order page.
		 *
		 * @since 1.10.4
		 *
		 * @param bool      $show_tracking Whether the section is rendered.
		 * @param \WC_Order $order         Order being rendered.
		 * @param array     $objects       Tracking objects of the order.
		 */
		return (bool) apply_filters( 'infixs_correios_automatico_show_order_tracking', $show_tracking, $order->getOrder(), $objects );
	}

	public function enqueue_order_details_script() {
		if ( is_account_page() && is_wc_endpoint_url( 'view-order' ) ) {
			wp_enqueue_script(
				'infixs-correios-automatico-tracking-component',
				\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/components/tracking/tracking.js',
				[ 'jquery' ],
				filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/components/tracking/tracking.js' ),
				true
			);

			wp_enqueue_style(
				'infixs-correios-automatico-tracking-component',
				\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/components/tracking/tracking.css',
				[],
				filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/components/tracking/tracking.css' ),
			);

			wp_localize_script(
				'infixs-correios-automatico-tracking-component',
				'infixsCorreiosAutomaticoTracking',
				[
					'restUrl' => esc_url_raw( rest_url( 'infixs-correios-automatico/v1/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				]
			);
		}
	}
}