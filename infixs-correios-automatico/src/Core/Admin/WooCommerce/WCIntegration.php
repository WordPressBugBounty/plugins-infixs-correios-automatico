<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Admin\WooCommerce\Blocks\Blocks;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático WooCommerce
 * 
 * Settup all hooks for admin area, actions and filters.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class WCIntegration {

	/**
	 * Tracking instance.
	 *
	 * @var Tracking
	 */
	public $tracking;

	/**
	 * Shipping instance.
	 *
	 * @var Shipping
	 */
	public $shipping;

	public $order;

	public $prepost;

	public $label;

	public $product;

	public function __construct() {
		add_action( 'woocommerce_loaded', [ $this, 'init' ] );
	}

	public function init() {
		$trackingService = Container::trackingService();

		$this->tracking = new Tracking( $trackingService );
		$this->shipping = new Shipping();
		$this->order = new Order( $trackingService );
		$this->prepost = new Prepost( Container::prepostService() );
		$this->label = new Label( Container::labelService() );
		$this->product = new Product();

		new ShippingClass();
		new Blocks();
		new Email();
		new Rest( $trackingService );
		new OrderTrackingColumn( $trackingService );
		new Checkout();

		$this->actions();
		$this->filters();
	}

	public function actions() {
		add_action( 'add_meta_boxes', [ $this->tracking, 'register_order_meta_box' ] );
		add_action( 'add_meta_boxes', [ $this->prepost, 'register_order_meta_box' ] );
		add_action( 'add_meta_boxes', [ $this->label, 'register_order_meta_box' ] );
		add_action( 'before_woocommerce_init', [ $this, 'woocommerce_declare_compatibility' ] );
		add_action( 'woocommerce_order_list_table_extra_tablenav', [ $this, 'add_print_button_order_table' ] );
		add_action( 'woocommerce_settings_shipping', [ $this->shipping, 'shipping_settings_page' ] );
		add_action( 'woocommerce_checkout_update_order_meta', [ $this->order, 'save_order_meta_data' ] );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', [ $this->order, 'save_order_meta_data' ] );
	}

	public function filters() {
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'register_bulk_actions' ] );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_actions' ], 10, 3 );
		add_filter( 'woocommerce_shipping_methods', [ $this->shipping, 'include_methods' ] );
		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'register_bulk_actions' ] ); // Compatibility with old woocommerce
		add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_actions' ], 10, 3 );
	}

	public static function get_shop_order_screen() {
		return class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
			&& function_exists( 'wc_get_page_screen_id' )
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';
	}

	/**
	 * This method is used to check if the current page is the WooCommerce edit order page.
	 * 
	 * @since 1.0.0
	 */
	public static function is_edit_order_page() {
		$current_screen = get_current_screen();
		return $current_screen && $current_screen->id === self::get_shop_order_screen();
	}

	/**
	 * Is list order page
	 * 
	 * @since 1.3.7
	 */
	public static function is_list_order_page() {
		$current_screen = get_current_screen();
		return $current_screen && $current_screen->post_type === 'shop_order' && $current_screen->base === 'edit';
	}

	/**
	 * Register custom bulk actions.
	 *
	 * @since 1.0.0
	 */
	public function register_bulk_actions( $bulk_actions ) {
		if ( Config::boolean( 'general.active_preparing_to_ship' ) ) {
			$preparing_to_ship = strtolower( Config::get( 'general.status_preparing_to_ship' ) );
			$bulk_actions['infixs_correios_automatico_mark_preparing_to_ship'] = "Mudar para $preparing_to_ship";
		}

		if ( Config::boolean( 'general.active_in_transit' ) ) {
			$in_transit = strtolower( Config::get( 'general.status_in_transit' ) );
			$bulk_actions['infixs_correios_automatico_mark_in_transit'] = "Mudar para $in_transit";
		}

		if ( Config::boolean( 'general.active_waiting_pickup' ) ) {
			$waiting_pickup = strtolower( Config::get( 'general.status_waiting_pickup' ) );
			$bulk_actions['infixs_correios_automatico_mark_waiting_pickup'] = "Mudar para $waiting_pickup";
		}

		if ( Config::boolean( 'general.active_returning' ) ) {
			$returning = strtolower( Config::get( 'general.status_returning' ) );
			$bulk_actions['infixs_correios_automatico_mark_returning'] = "Mudar para $returning";
		}

		if ( Config::boolean( 'general.active_delivered' ) ) {
			$delivered = strtolower( Config::get( 'general.status_delivered' ) );
			$bulk_actions['infixs_correios_automatico_mark_delivered'] = "Mudar para $delivered";
		}


		$bulk_actions['infixs_correios_automatico_print_labels'] = __( 'Imprimir Etiquetas', 'infixs-correios-automatico' );

		return $bulk_actions;
	}

	/**
	 * Handle custom bulk actions.
	 *
	 * @since 1.0.0
	 */
	public function handle_bulk_actions( $redirect_to, $action, $post_ids ) {
		if ( $action === 'infixs_correios_automatico_print_labels' ) {
			$redirect_to = admin_url( sprintf( 'admin.php?page=infixs-correios-automatico&path=/print&orders=%s', implode( ',', $post_ids ) ) );
		}

		$status_actions = [ 
			'infixs_correios_automatico_mark_preparing_to_ship' => 'wc-preparing-to-ship',
			'infixs_correios_automatico_mark_in_transit' => 'wc-in-transit',
			'infixs_correios_automatico_mark_waiting_pickup' => 'wc-waiting-pickup',
			'infixs_correios_automatico_mark_returning' => 'wc-returning',
			'infixs_correios_automatico_mark_delivered' => 'wc-delivered',
		];

		if ( array_key_exists( $action, $status_actions ) ) {
			foreach ( $post_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$new_status = $status_actions[ $action ];
					$order->update_status( $new_status, __( 'Correios Automático: ', 'infixs-correios-automatico' ), true );
				}
			}
		}

		return $redirect_to;
	}

	/**
	 * Add custom bulk action to the order list.
	 *
	 * @since 1.0.0
	 */
	public function add_print_button_order_table( $post_type ) {
		include_once INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-order-list-action.php';
	}

	public function woocommerce_declare_compatibility() {
		if ( class_exists( FeaturesUtil::class) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', \INFIXS_CORREIOS_AUTOMATICO_FILE_NAME, true );
			FeaturesUtil::declare_compatibility( 'product_block_editor', \INFIXS_CORREIOS_AUTOMATICO_FILE_NAME, true );
		}
	}

}