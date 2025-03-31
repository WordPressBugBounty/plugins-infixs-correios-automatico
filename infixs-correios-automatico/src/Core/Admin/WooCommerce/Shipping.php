<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Shipping Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Shipping {

	public function shipping_settings_page( $settings ) {
		global $current_section, $hide_save_button, $current_tab;
	}


	/**
	 * Include shipping methods filter.
	 *
	 * @since 1.0.0
	 */
	public function include_methods( $methods ) {
		$methods['infixs-correios-automatico'] = 'Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod';
		return $methods;
	}


}