<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Services\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Tracking Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Tracking {

	/**
	 * Tracking service instance.
	 *
	 * @var TrackingService
	 */
	private $trackingService;

	/**
	 * Tracking constructor.
	 */
	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;
		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Compatibility with old plugins
		if ( Config::boolean( 'general.tracking_compatiblity' ) ) {
			add_action( 'woocommerce_order_get__correios_tracking_code', [ $this, 'get_tracking_code' ], 10, 2 );
		}

		if ( ! class_exists( 'WC_Correios' ) ) {
			add_action( 'woocommerce_order_get_correios_tracking_code', [ $this, 'get_tracking_code' ], 10, 2 );
		}

		add_action( 'infixs_correios_automatico_prepost_controller_created', [ $this, 'manual_prepost_created' ], 10, 2 );
	}

	public function manual_prepost_created( $order_id, Prepost $prepost ) {
		$this->trackingService->add( $order_id, $prepost->object_code );
	}


	public function get_tracking_code( $tracking_code, $order ) {
		return implode( ",", $this->trackingService->list( $order->get_id(), [ 
			'order' => [ 
				'column' => 'created_at',
				'order' => 'desc',
			],
		] )->pluck( 'code' )->toArray() );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( WCIntegration::is_edit_order_page() ) {
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

	/**
	 * Register meta boxes
	 * 
	 * @since 1.2.2
	 * 
	 * @return void
	 */
	public function register_meta_boxes() {
		add_meta_box(
			'infixs-correios-automatico-tracking-history',
			'Histórico de Rastreio',
			[ $this, 'render_tracking_history_meta_box' ],
			WCIntegration::get_shop_order_screen(),
			'normal',
			'low'
		);
	}


	/**
	 * Render the tracking history meta box.
	 * 
	 * @param \WP_Post|\WC_Order $post
	 * 
	 * @return void
	 */
	public function render_tracking_history_meta_box( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order->ID );
		}

		if ( ! $order )
			return;

		$objects = $this->trackingService->get_order_tracking_history( $order->get_id() );

		include_once INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-tracking-history-meta-box.php';
	}


	public function register_order_meta_box() {

		if ( ! Config::boolean( "general.show_order_tracking_form" ) )
			return;

		add_meta_box(
			'infixs-correios-automatico-tracking-code',
			'Código de Rastreio',
			[ $this, 'render_order_meta_box' ],
			WCIntegration::get_shop_order_screen(),
			'side',
			'high'
		);
	}

	/**
	 * Render the tracking code meta box.
	 * 
	 * @param \WP_Post|\WC_Order $post
	 * @return void
	 */
	public function render_order_meta_box( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order->ID );
		}

		if ( ! $order )
			return;

		$order_id = $order->get_id();

		$trackings = $this->trackingService->list( $order_id, [ 
			'order' => [ 
				'column' => 'created_at',
				'order' => 'desc',
			],
		] );

		include_once INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-tracking-meta-box.php';
		wp_nonce_field( 'infixs-correios-automatico-trakking-code', 'trakking_code_nonce' );
	}


	public function send_tracking_notification( $order, $tracking_code ) {

	}

	/**
	 * Trigger tracking code email notification.
	 *
	 * @param int $order_id         Order data.
	 * @param string|array   $tracking_codes The Correios tracking code string or array.
	 * 
	 * @return bool
	 */
	public static function trigger_tracking_code_email( $order_id, $tracking_codes ) {
		$mailer = WC()->mailer();

		/** @var \Infixs\CorreiosAutomatico\Core\Emails\TrackingCodeEmail $notification */
		$notification = $mailer->emails['Correios_Automatico_Tracking_Code_Email'];

		if ( 'yes' === $notification->enabled ) {
			return $notification->trigger( $order_id, $tracking_codes );
		}

		return false;
	}

	/**
	 * Preparing to ship notification.
	 *
	 * @param int $order_id         Order data.
	 * @param string|array   $tracking_codes The Correios tracking code string or array.
	 * 
	 * @return bool
	 */
	public static function trigger_preparing_to_ship_email( $order_id, $tracking_codes = [] ) {
		$mailer = WC()->mailer();

		/** @var \Infixs\CorreiosAutomatico\Core\Emails\PreparingToShipEmail $notification */
		$notification = $mailer->emails['Correios_Automatico_Preparing_To_Ship_Email'];

		if ( 'yes' === $notification->enabled ) {
			return $notification->trigger( $order_id, $tracking_codes );
		}

		return false;
	}

	/**
	 * Waiting Pickup notification.
	 *
	 * @param int $order_id         Order data.
	 * @param string|array   $tracking_code The Correios tracking code string.
	 * @param string $pickup_address The pickup address.
	 * 
	 * @return bool
	 */
	public static function trigger_waiting_pickup_email( $order_id, $tracking_code, $pickup_address ) {
		$mailer = WC()->mailer();

		/** @var \Infixs\CorreiosAutomatico\Core\Emails\WaitingPickupEmail $notification */
		$notification = $mailer->emails['Correios_Automatico_Waiting_Pickup_Email'];

		if ( 'yes' === $notification->enabled ) {
			return $notification->trigger( $order_id, $tracking_code, $pickup_address );
		}

		return false;
	}

	/**
	 * Returning notification.
	 *
	 * @param int $order_id         Order data.
	 * @param string|array   $tracking_code The Correios tracking code string.
	 * @param string $pickup_address The pickup address.
	 * 
	 * @return bool
	 */
	public static function trigger_returning_email( $order_id, $tracking_code, $pickup_address ) {
		$mailer = WC()->mailer();

		/** @var \Infixs\CorreiosAutomatico\Core\Emails\ReturningEmail $notification */
		$notification = $mailer->emails['Correios_Automatico_Returning_Email'];

		if ( 'yes' === $notification->enabled ) {
			return $notification->trigger( $order_id, $tracking_code );
		}

		return false;
	}
}