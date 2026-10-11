<?php

namespace Infixs\CorreiosAutomatico\Utils;

use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;

defined( 'ABSPATH' ) || exit;

/**
 * Names the shipping lines of an order.
 *
 * The name is what the store and the customer read on the order: the freight
 * and, when the shipment was split, which volume the line carries. The delivery
 * time stays out of it: it belongs to the quote and is stale by the time anyone
 * reads the order again.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class ShippingLineName {

	/**
	 * Name a shipping line after the title of its freight.
	 *
	 * A volume of a split shipment keeps saying which volume it is, so a line
	 * renamed from the dashboard or recalculated on the order screen does not
	 * pass for a freight of its own. A title that already names the volume is
	 * not named twice.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $item  Shipping line being named, with its volume metas.
	 * @param string                  $title Title of the freight, with or without the delivery time.
	 *
	 * @return string
	 */
	public static function build( $item, $title ) {
		$name = TextHelper::removeShippingTime( (string) $title );
		$index = absint( $item->get_meta( CorreiosShippingMethod::VOLUME_INDEX_META_KEY ) );
		$total = absint( $item->get_meta( CorreiosShippingMethod::VOLUME_TOTAL_META_KEY ) );

		if ( ! $index || $total < 2 ) {
			return $name;
		}

		$suffix = self::nameVolume( '', $index, $total );

		if ( '' !== $suffix && substr( $name, -strlen( $suffix ) ) === $suffix ) {
			$name = substr( $name, 0, -strlen( $suffix ) );
		}

		return self::nameVolume( $name, $index, $total );
	}

	/**
	 * Add the position of a volume to a name.
	 *
	 * @since 1.11.0
	 *
	 * @param string $name  Name of the freight.
	 * @param int    $index Position of the volume, from 1.
	 * @param int    $total Number of volumes of the shipment.
	 *
	 * @return string
	 */
	private static function nameVolume( $name, $index, $total ) {
		return sprintf(
			/* translators: 1: shipping method name, 2: volume number, 3: number of volumes */
			__( '%1$s (volume %2$d de %3$d)', 'infixs-correios-automatico' ),
			$name,
			$index,
			$total
		);
	}
}
