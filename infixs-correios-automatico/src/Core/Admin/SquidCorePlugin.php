<?php

namespace Infixs\CorreiosAutomatico\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Where the SquidCore plugin, which issues the NF-e of the orders, stands in the site.
 *
 * Read by the SquidCore tab of the dashboard: not installed, installed but
 * inactive, or active.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.13.0
 */
class SquidCorePlugin {

	/**
	 * Folders the plugin has shipped in: the wordpress.org slug and the first one.
	 *
	 * @since 1.13.0
	 *
	 * @var string[]
	 */
	const FOLDERS = [ 'infixs-squidcore-erp', 'infixs-squidcore' ];

	/**
	 * State of the plugin for the dashboard.
	 *
	 * The address that activates it carries the nonce of the WordPress and
	 * only comes for whoever may activate it.
	 *
	 * @since 1.13.0
	 *
	 * @return array{installed: bool, active: bool, activateUrl: string}
	 */
	public static function status() {
		if ( defined( 'INFIXS_SQUIDCORE_FILE_NAME' ) ) {
			return [
				'installed' => true,
				'active' => true,
				'activateUrl' => '',
			];
		}

		$file = self::installedFile();

		return [
			'installed' => '' !== $file,
			'active' => false,
			'activateUrl' => '' !== $file && current_user_can( 'activate_plugin', $file )
				? add_query_arg(
					[
						'action' => 'activate',
						'plugin' => rawurlencode( $file ),
						'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $file ),
					],
					self_admin_url( 'plugins.php' )
				)
				: '',
		];
	}

	/**
	 * Main file of the installed plugin, relative to the plugins folder, empty when it is not installed.
	 *
	 * @since 1.13.0
	 *
	 * @return string
	 */
	private static function installedFile() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_plugins() as $file => $data ) {
			$name = isset( $data['Name'] ) ? (string) $data['Name'] : '';

			if ( in_array( dirname( $file ), self::FOLDERS, true ) && false !== stripos( $name, 'SquidCore' ) ) {
				return (string) $file;
			}
		}

		return '';
	}
}
