<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * Turns cart contents into the units being packed, and back.
 *
 * @since 1.11.0
 */
class PackingUnits {

	/**
	 * Break the contents down to one entry per unit being shipped.
	 *
	 * A line of three units is three units to pack: they travel in different
	 * boxes whenever they do not fit together. A product without a measure
	 * counts as zero on that axis.
	 *
	 * @since 1.11.0
	 *
	 * @param array $contents Cart package contents.
	 *
	 * @return array{units: array[], not_shipped: array} Units carry the cart key, the
	 *         cart values, the weight in kg, the volume in cm³ and the length, width
	 *         and height in cm. Items that ship on their own account are kept aside.
	 */
	public static function explode( $contents ) {
		$units = [];
		$not_shipped = [];

		foreach ( $contents as $key => $values ) {
			$product = isset( $values['data'] ) ? $values['data'] : null;
			$quantity = isset( $values['quantity'] ) ? (int) $values['quantity'] : 0;

			if ( ! $product || $quantity < 1 ) {
				continue;
			}

			if ( ! $product->needs_shipping() ) {
				$not_shipped[ $key ] = $values;
				continue;
			}

			$weight = wc_get_weight( (float) $product->get_weight(), 'kg' );
			$height = wc_get_dimension( (float) $product->get_height(), 'cm' );
			$width = wc_get_dimension( (float) $product->get_width(), 'cm' );
			$length = wc_get_dimension( (float) $product->get_length(), 'cm' );

			for ( $i = 0; $i < $quantity; $i++ ) {
				$units[] = [
					'key' => $key,
					'values' => $values,
					'weight' => $weight,
					'volume' => $height * $width * $length,
					'length' => $length,
					'width' => $width,
					'height' => $height,
				];
			}
		}

		return [
			'units' => $units,
			'not_shipped' => $not_shipped,
		];
	}

	/**
	 * Count the units of the contents that need shipping.
	 *
	 * @since 1.11.0
	 *
	 * @param array $contents Cart package contents.
	 *
	 * @return int
	 */
	public static function count( $contents ) {
		$count = 0;

		foreach ( $contents as $values ) {
			$product = isset( $values['data'] ) ? $values['data'] : null;
			$quantity = isset( $values['quantity'] ) ? (int) $values['quantity'] : 0;

			if ( $product && $quantity > 0 && $product->needs_shipping() ) {
				$count += $quantity;
			}
		}

		return $count;
	}

	/**
	 * Rebuild a contents array from the units of a single volume.
	 *
	 * The money of a line follows the units that stayed in the volume, so the
	 * declared value of a volume is the value of what is inside it.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units of the volume.
	 *
	 * @return array
	 */
	public static function toContents( $units ) {
		$quantities = [];
		$originals = [];

		foreach ( $units as $unit ) {
			$key = $unit['key'];
			$quantities[ $key ] = isset( $quantities[ $key ] ) ? $quantities[ $key ] + 1 : 1;
			$originals[ $key ] = $unit['values'];
		}

		$contents = [];

		foreach ( $quantities as $key => $quantity ) {
			$values = $originals[ $key ];
			$original_quantity = max( 1, (int) $values['quantity'] );
			$values['quantity'] = $quantity;

			foreach ( [ 'line_total', 'line_subtotal' ] as $field ) {
				if ( isset( $values[ $field ] ) && is_numeric( $values[ $field ] ) ) {
					$values[ $field ] = ( (float) $values[ $field ] / $original_quantity ) * $quantity;
				}
			}

			$contents[ $key ] = $values;
		}

		return $contents;
	}
}
