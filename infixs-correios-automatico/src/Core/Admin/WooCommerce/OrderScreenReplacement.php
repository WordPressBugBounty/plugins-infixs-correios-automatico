<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Order Screen Replacement
 *
 * Renders the plugin order screen in place of the WooCommerce order list,
 * keeping the same menu item and the same URL. Only the list is replaced,
 * the single order screens stay untouched.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.9.0
 */
class OrderScreenReplacement {

	/**
	 * WooCommerce order list page hook when HPOS is enabled.
	 *
	 * @since 1.9.0
	 * @var string
	 */
	const HPOS_PAGE_HOOK = 'woocommerce_page_wc-orders';

	/**
	 * WooCommerce order list page slug when HPOS is enabled.
	 *
	 * @since 1.9.0
	 * @var string
	 */
	const HPOS_PAGE = 'wc-orders';

	/**
	 * WooCommerce order post type, used by the legacy order list.
	 *
	 * @since 1.9.0
	 * @var string
	 */
	const LEGACY_POST_TYPE = 'shop_order';

	/**
	 * Constructor.
	 *
	 * @since 1.9.0
	 */
	public function __construct() {
		add_action( 'load-' . self::HPOS_PAGE_HOOK, [ $this, 'replace_hpos_screen' ] );
		add_action( 'load-edit.php', [ $this, 'replace_legacy_screen' ] );
	}

	/**
	 * Whether the replacement is enabled in the settings.
	 *
	 * @since 1.9.0
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return Config::boolean( 'general.replace_orders_screen' );
	}

	/**
	 * Whether the current request renders the plugin order screen in place of
	 * the WooCommerce order list.
	 *
	 * @since 1.9.0
	 *
	 * @return bool
	 */
	public static function is_replacing_current_screen() {
		if ( ! self::is_enabled() ) {
			return false;
		}

		return self::is_hpos_list_request() || self::is_legacy_list_request();
	}

	/**
	 * Whether the current request is the WooCommerce order list with HPOS enabled.
	 *
	 * The order edit and creation screens share the same page slug and are told
	 * apart by the "action" argument.
	 *
	 * @since 1.9.0
	 *
	 * @return bool
	 */
	private static function is_hpos_list_request() {
		global $pagenow;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( 'admin.php' !== $pagenow || ! isset( $_GET['page'] ) ) {
			return false;
		}

		if ( self::HPOS_PAGE !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return false;
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'list' === $action;
	}

	/**
	 * Whether the current request is the WooCommerce order list with the legacy
	 * post storage.
	 *
	 * @since 1.9.0
	 *
	 * @return bool
	 */
	private static function is_legacy_list_request() {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'edit.php' !== $pagenow || ! isset( $_GET['post_type'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return self::LEGACY_POST_TYPE === sanitize_key( wp_unslash( $_GET['post_type'] ) );
	}

	/**
	 * Replaces the output of the WooCommerce order list when HPOS is enabled.
	 *
	 * WooCommerce registers the page callback as an action on the page hook,
	 * which WordPress only fires after "load-{page_hook}", so replacing the
	 * callback here swaps the rendered content and keeps everything else
	 * (menu item, url, screen setup) as it is.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	public function replace_hpos_screen() {
		if ( ! self::is_replacing_current_screen() ) {
			return;
		}

		remove_all_actions( self::HPOS_PAGE_HOOK );
		add_action( self::HPOS_PAGE_HOOK, [ $this, 'render' ] );
		add_filter( 'screen_options_show_screen', '__return_false' );
	}

	/**
	 * Replaces the output of the WooCommerce order list with the legacy post storage.
	 *
	 * The legacy list is rendered by "wp-admin/edit.php" itself, so there is no
	 * callback to replace. This hook runs before any output, which allows the
	 * plugin screen to be rendered on the same url.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	public function replace_legacy_screen() {
		if ( ! self::is_replacing_current_screen() ) {
			return;
		}

		global $title, $parent_file, $submenu_file;

		$title = __( 'Pedidos', 'infixs-correios-automatico' );
		$parent_file = 'woocommerce';
		$submenu_file = 'edit.php?post_type=' . self::LEGACY_POST_TYPE;

		add_filter( 'screen_options_show_screen', '__return_false' );

		require_once ABSPATH . 'wp-admin/admin-header.php';
		$this->render();
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}

	/**
	 * Renders the plugin order screen container.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	public function render() {
		Template::adminView( 'html-orders-screen.php' );
	}
}
