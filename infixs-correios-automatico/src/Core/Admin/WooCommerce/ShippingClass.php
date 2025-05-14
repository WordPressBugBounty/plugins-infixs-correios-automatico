<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Support\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Shipping Classes Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.3.1
 */

class ShippingClass {
	public function __construct() {
		add_filter( 'woocommerce_shipping_classes_columns', [ $this, 'shipping_classes_columns' ] );
		add_action( 'woocommerce_shipping_classes_column_infixs-shipping-class-additional-days', [ $this, 'render_shipping_class_input' ] );
		add_action( 'woocommerce_shipping_classes_save_class', [ $this, 'save_shipping_class' ], 10, 2 );
		add_filter( 'woocommerce_get_shipping_classes', [ $this, 'get_shipping_classes' ] );
	}

	/**
	 * Get Shipping Classes
	 *
	 * @param \WP_Term[] $classes
	 * 
	 * @return \WP_Term[]
	 */
	public function get_shipping_classes( $classes ) {
		foreach ( $classes as $key => $class ) {
			$value = get_term_meta( $class->term_id, 'infixs_additional_days', true );
			$meta_key = 'infixs_additional_days';
			$classes[ $key ]->$meta_key = intval( $value );
		}
		return $classes;
	}

	public function shipping_classes_columns( $classes ) {
		$classes['infixs-shipping-class-additional-days'] = __( 'Dias Adicionais', 'infixs-correios-automatico' );
		return $classes;
	}

	public function save_shipping_class( $term_id, $data ) {
		if ( isset( $data['infixs_additional_days'] ) ) {
			update_term_meta( $term_id, 'infixs_additional_days', sanitize_text_field( $data['infixs_additional_days'] ) );
		}
	}

	public function render_shipping_class_input() {
		Template::adminView( "/settings/html-setting-shipping-class.php" );
	}
}