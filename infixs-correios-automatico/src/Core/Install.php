<?php

namespace Infixs\CorreiosAutomatico\Core;

use Infixs\CorreiosAutomatico\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Install the plugin.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Install {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		register_activation_hook( \INFIXS_CORREIOS_AUTOMATICO_FILE_NAME, [ $this, 'activate_plugin' ] );
		register_deactivation_hook( \INFIXS_CORREIOS_AUTOMATICO_FILE_NAME, [ $this, 'deactivate_plugin' ] );
		add_action( 'wp_loaded', [ $this, 'maybe_show_wizard' ] );
	}

	/**
	 * Activate plugin.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function activate_plugin() {
		if ( $this->is_new_install() ) {
			add_option( '_infixs_correios_automatico_activate', true );
		}
	}

	/**
	 * Deactivate plugin.
	 * 
	 * @since 1.0.0
	 * @return void
	 */
	public function deactivate_plugin() {
		// Do something
	}

	public function maybe_show_wizard() {
		if ( get_option( '_infixs_correios_automatico_activate' ) && class_exists( 'WooCommerce' ) ) {
			delete_option( '_infixs_correios_automatico_activate' );
			if ( ! headers_sent() ) {

				$shippingService = Container::shippingService();

				if ( $shippingService->hasMethodsToImport() || ! $shippingService->hasCorreiosAutomaticoActiveMethods() ) {
					wp_safe_redirect( admin_url( 'admin.php?page=infixs-correios-automatico&path=/starter' ) );
				} else {
					wp_safe_redirect( admin_url( 'admin.php?page=infixs-correios-automatico&path=/config/general' ) );
				}

			}
		}
	}

	/**
	 * Is this a brand new install
	 *
	 * A brand new install has no version yet. Also treat empty installs as 'new'.
	 *
	 * @since  3.2.0
	 * @return boolean
	 */
	public static function is_new_install() {
		return is_null( get_option( '_infixs_correios_automatico_version', null ) );
	}
}