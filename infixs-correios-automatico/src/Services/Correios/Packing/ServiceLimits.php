<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the measures of a box against the limits of a Correios service.
 *
 * @since 1.11.0
 */
class ServiceLimits {

	/**
	 * Whether the service accepts a box with these measures.
	 *
	 * Every measure is compared to three decimals, so the float left over by a
	 * sum, such as the contour of a stack, never refuses a box that sits right at
	 * the limit.
	 *
	 * @since 1.11.0
	 *
	 * @param array|null $limits Limits of the service, as DeliveryServiceCode::getDimensionLimits() returns.
	 * @param array      $data   Length, width and height in cm, weight in kg.
	 *
	 * @return bool
	 */
	public static function accepts( $limits, $data ) {
		if ( ! $limits ) {
			return true;
		}

		$max = $limits['max'];

		$within_sides = round( (float) $data['length'], 3 ) <= round( (float) $max['length'], 3 ) &&
			round( (float) $data['width'], 3 ) <= round( (float) $max['width'], 3 ) &&
			round( (float) $data['height'], 3 ) <= round( (float) $max['height'], 3 ) &&
			round( (float) $data['weight'], 3 ) <= round( (float) $max['weight'], 3 );

		if ( ! $within_sides || ! isset( $max['total_dimensions'] ) ) {
			return $within_sides;
		}

		return round( $data['length'] + $data['width'] + $data['height'], 3 ) <= round( (float) $max['total_dimensions'], 3 );
	}
}
