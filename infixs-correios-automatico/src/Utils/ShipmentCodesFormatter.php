<?php

namespace Infixs\CorreiosAutomatico\Utils;

use Infixs\CorreiosAutomatico\Entities\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Writes the codes of an order one per line, naming the shipment of each.
 *
 * A customer who gets several codes at once has to know which box each one
 * follows, whether it is a tracking code or the code of a return.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class ShipmentCodesFormatter {

	/**
	 * Build one line per code, in the order of the shipping lines.
	 *
	 * A code of a volume reads "Volume i/N: CODE". A code of another carrier
	 * is preceded by the name of its freight. A code of a single Correios
	 * shipment, or bound to no line, is written alone after the others.
	 *
	 * @since 1.11.0
	 *
	 * @param Order $order Order the codes belong to, without a scope.
	 * @param array $codes Codes, each with `code`, `shipping_item_id` and `carrier`.
	 *
	 * @return string[]
	 */
	public static function lines( $order, $codes ) {
		$lines = [];

		foreach ( $order->getShippingItemsPayload() as $shipping_item ) {
			foreach ( $codes as $index => $code ) {
				if ( (int) $code['shipping_item_id'] !== $shipping_item['item_id'] ) {
					continue;
				}

				$lines[] = self::line( $code, $shipping_item );
				unset( $codes[ $index ] );
			}
		}

		foreach ( $codes as $code ) {
			$lines[] = self::line( $code, null );
		}

		return $lines;
	}

	/**
	 * Write a single code with the name of its shipment.
	 *
	 * @since 1.11.0
	 *
	 * @param array      $code          Code, with `code` and `carrier`.
	 * @param array|null $shipping_item Shipping line of the code, as in Order::getShippingItemsPayload().
	 *
	 * @return string
	 */
	private static function line( $code, $shipping_item ) {
		$names = [];

		if ( $shipping_item && $shipping_item['volume_total'] > 1 ) {
			$names[] = sprintf( 'Volume %d/%d', $shipping_item['volume_index'], $shipping_item['volume_total'] );
		}

		$carrier = ! empty( $code['carrier'] ) ? $code['carrier'] : 'correios';

		if ( 'correios' !== $carrier ) {
			$names[] = $shipping_item ? $shipping_item['method_title'] : ucfirst( $carrier );
		}

		if ( ! $names ) {
			return $code['code'];
		}

		return implode( ' · ', $names ) . ': ' . $code['code'];
	}
}
