<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Utils\TextHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.5.1
 */
class Checkout {
	public function __construct() {
		add_action( 'woocommerce_checkout_create_order', [ $this, 'woocommerce_checkout_create_order' ], 100 );
	}

	/**
	 * Create order
	 * 
	 * @param \WC_Order $order
	 * 
	 * @return void
	 */
	public function woocommerce_checkout_create_order( $order ) {
		/** @var \WC_Order_Item_Shipping $line_item */
		foreach ( $order->get_items( 'shipping' ) as $line_item ) {
			if ( $line_item->get_method_id() === 'infixs-correios-automatico' ) {
				$current_name = $line_item->get_name();
				$line_item->set_name( TextHelper::removeShippingTime( $current_name ) );
				$line_item->save();
			}
		}
	}
}