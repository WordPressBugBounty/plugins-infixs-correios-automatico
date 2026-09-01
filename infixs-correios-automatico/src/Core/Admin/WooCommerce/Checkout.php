<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Support\Log;
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
		add_action( 'woocommerce_checkout_order_created', [ $this, 'verify_shipping_line_persisted' ] );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'verify_shipping_line_persisted' ] );
	}

	/**
	 * Remove the delivery time from the Correios shipping line names.
	 *
	 * The items must not be saved here: the order has no id yet, so a save
	 * would insert the shipping line with `order_id = 0` and leave it an
	 * orphan until the following `$order->save()` adopts it with a single
	 * unchecked UPDATE. Any failure or cleanup inside that window loses the
	 * line while the totals, taken from the cart, keep the freight — the
	 * "charged with no shipping method" order. WooCommerce persists these
	 * items right after this hook, in the same save as the product lines.
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
			}
		}
	}

	/**
	 * Accuse an order persisted with freight charged but no shipping line.
	 *
	 * WooCommerce's own silent-failure guard in `create_order` only counts
	 * product lines, so a shipping line lost to an unchecked insert passes
	 * it. The order is re-read from the data store because the in-memory
	 * instance always carries the line, persisted or not.
	 *
	 * @since 1.10.4
	 *
	 * @param \WC_Order|int $order Order created by the checkout.
	 *
	 * @return bool Whether the persisted order is consistent.
	 */
	public function verify_shipping_line_persisted( $order ) {
		$order_id = $order instanceof \WC_Order ? $order->get_id() : absint( $order );

		if ( ! $order_id ) {
			return true;
		}

		$persisted = wc_get_order( $order_id );

		if ( ! $persisted instanceof \WC_Order ) {
			return true;
		}

		if ( (float) $persisted->get_shipping_total() <= 0 || count( $persisted->get_items( 'shipping' ) ) > 0 ) {
			return true;
		}

		Log::error( "O pedido #{$order_id} foi salvo com frete cobrado e sem a linha do método de entrega.", [
			'order_id' => $order_id,
			'shipping_total' => $persisted->get_shipping_total(),
			'total' => $persisted->get_total(),
		] );

		return false;
	}
}
