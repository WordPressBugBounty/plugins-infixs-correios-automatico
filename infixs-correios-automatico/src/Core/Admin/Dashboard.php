<?php

namespace Infixs\CorreiosAutomatico\Core\Admin;

use Infixs\CorreiosAutomatico\Core\Admin\Notices\BuyProNotice;
use Infixs\CorreiosAutomatico\Core\Admin\Notices\PluginDeactivation;
use Infixs\CorreiosAutomatico\Core\Admin\Notices\RequiredPluginNotice;
use Infixs\CorreiosAutomatico\Core\Admin\Notices\ShippingMethod;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Admin Hooks
 * 
 * Settup all hooks for admin area, actions and filters.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Dashboard {

	/**
	 * Dashboard constructor.
	 * 
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_infixs_correios_automatico_dismiss_notice', [ $this, 'dismiss_notice' ] );
		add_filter( 'infixs_correios_automatico_dashboard_notices', [ $this, 'add_notices' ] );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Correios Automático', 'infixs-correios-automatico' ),
			esc_html__( 'Correios Automático', 'infixs-correios-automatico' ),
			'manage_woocommerce',
			'infixs-correios-automatico',
			[ $this, 'admin_page' ],
			6
		);
	}

	/**
	 * Admin page.
	 *
	 * @since 1.0.0
	 */
	public function admin_page() {
		include_once INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-dashboard.php';
	}

	/**
	 * Add notices.
	 *
	 * @since 1.0.5
	 */
	public function add_notices( $notices ) {
		$notices[] = new ShippingMethod();
		$notices[] = new RequiredPluginNotice();
		$notices[] = new BuyProNotice();
		$notices[] = new PluginDeactivation();
		return $notices;
	}

	public function dismiss_notice() {
		if ( ! isset( $_POST['_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'infixs_correios_automatico_dismiss_notice' ) ) {
			wp_send_json_error( [ 'message' => __( 'Ação inválida.', 'infixs-correios-automatico' ) ] );
		}

		if ( ! isset( $_POST['notice_id'] ) ) {
			wp_send_json_error( [ 'message' => __( 'ID do aviso não informado.', 'infixs-correios-automatico' ) ] );
		}

		$notice_id = sanitize_text_field( wp_unslash( $_POST['notice_id'] ) );

		$notices = apply_filters( 'infixs_correios_automatico_dashboard_notices', [] );

		foreach ( $notices as $notice ) {
			if ( $notice->getId() === $notice_id ) {
				$notice->dismiss();
				wp_send_json_success( [ 'message' => __( 'Aviso ocultado.', 'infixs-correios-automatico' ) ] );
			}
		}

		wp_send_json_error( [ 'message' => __( 'Aviso não encontrado.', 'infixs-correios-automatico' ) ] );
	}


	public function display_notices() {
		$notices = apply_filters( 'infixs_correios_automatico_dashboard_notices', [] );
		$displayed = [];

		foreach ( $notices as $notice ) {
			if ( ! $notice->isDismissed() && $notice->shouldDisplay() ) {
				$displayed[] = [ 
					'id' => $notice->getId(),
					'title' => $notice->getTitle(),
					'message' => $notice->getMessage(),
					'type' => $notice->getType(),
					'buttons' => $notice->getButtons(),
				];
			}
		}

		return $displayed;
	}
}