<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Notices;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Notice;

defined( 'ABSPATH' ) || exit;

class RequiredPluginNotice extends Notice {
	public function __construct() {
		$url = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce-extra-checkout-fields-for-brazil' ),
			'install-plugin_woocommerce-extra-checkout-fields-for-brazil'
		);

		$this->id = 'infixs-correios-automatico-required-plugin';
		$this->title = __( 'Plugin recomendado', 'infixs-correios-automatico' );
		$this->message = sprintf( __( 'Para que os recursos do Correio Automático funcionem corretamente, instale o plugin de campos extras brasileiros.', 'infixs-correios-automatico' ) );
		$this->type = 'info';
		$this->dismissDuration = DAY_IN_SECONDS;
		$this->buttons = [ 
			[ 
				'text' => __( 'Instalar', 'infixs-correios-automatico' ),
				'url' => esc_url_raw( htmlspecialchars_decode( $url ) )
			],
		];
	}

	public function shouldDisplay() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin = 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php';

		if ( ! is_plugin_active( $plugin ) && Config::boolean( 'auth.active' ) ) {
			return true;
		}

		return false;
	}
}