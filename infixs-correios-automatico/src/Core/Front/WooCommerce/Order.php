<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;

use Infixs\CorreiosAutomatico\Services\Correios\CorreiosService;
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
		add_action( 'woocommerce_view_order', [ $this, 'add_content_above_order_details' ] );
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
			],
			'infixs-correios-automatico/',
			\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'templates/'
		);
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
		}
	}
}