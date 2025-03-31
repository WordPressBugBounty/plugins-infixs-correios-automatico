<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Services\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Order Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class OrderTrackingColumn {

	/**
	 * TrackingService instance.
	 *
	 * @since 1.0.0
	 * @var TrackingService
	 */
	protected $trackingService;

	/**
	 * OrderTrackingColumn constructor.
	 *
	 * @param TrackingService $trackingService
	 */
	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;

		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'display_wc_order_tracking_custom_column_content' ], 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_wc_order_tracking_custom_column' ], 100 );
		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_wc_order_tracking_custom_column' ], 20 );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'display_admin_order_list_custom_column_content' ], 20, 2 );
	}

	function display_admin_order_list_custom_column_content( $column, $post_id ) {
		$order = wc_get_order( $post_id );

		if ( $column == 'infixs-correios-automatico-actions-column' ) {
			$this->generate_action_field( $order );
		}
		if ( $column == 'infixs-correios-automatico-tracking-column' ) {
			$this->generate_tracking_field( $order );
		}
	}

	function add_wc_order_tracking_custom_column( $columns ) {
		$columns['infixs-correios-automatico-tracking-column'] = __( 'Rastreio', 'infixs-correios-automatico' );
		$columns['infixs-correios-automatico-actions-column'] = __( 'Correios', 'infixs-correios-automatico' );
		return $columns;
	}


	function display_wc_order_tracking_custom_column_content( $column, $order ) {
		if ( $column == 'infixs-correios-automatico-actions-column' ) {
			$this->generate_action_field( $order );
		}
		if ( $column == 'infixs-correios-automatico-tracking-column' ) {
			$this->generate_tracking_field( $order );
		}
	}

	/**
	 * Display order action column item
	 * 
	 * @param \WC_Order $order
	 * @return void
	 */
	public function generate_action_field( $order ) {
		$print_url = add_query_arg( [ 
			'page' => 'infixs-correios-automatico',
			'path' => '/print',
			'orders' => $order->get_id(),
		], admin_url( 'admin.php' ) );

		$printed = $order->get_meta( '_infixs_correios_automatico_printed', true ) ?: null;

		include \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . '/src/Presentation/admin/views/html-order-action-column.php';
	}

	/**
	 * Display order tracking column item
	 * 
	 * @param \WC_Order $order
	 * @return void
	 */
	public function generate_tracking_field( $order ) {
		$codes = $this->trackingService->list( $order->get_id() );
		$tracking_codes =
			$codes->map( function ($code) {
				return [ 
					'id' => $code->id,
					'code' => $code->code,
				];
			} )
		;
		include \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . '/src/Presentation/admin/views/html-order-tracking-column.php';
	}
}