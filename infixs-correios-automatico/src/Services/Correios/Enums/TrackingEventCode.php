<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

/**
 * Events of the Correios tracking the plugin gives a meaning to.
 *
 * An event is named by its code and its type together, the way the tracking
 * API answers them in `codigo` and `tipo`.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class TrackingEventCode {

	/**
	 * Object handed to the postman to be delivered to the recipient.
	 *
	 * The types are listed because the code alone is not enough: the same
	 * `OEC` with another type is the object on its way back to the sender,
	 * and the customer must not be told it is about to arrive.
	 */
	public const OUT_FOR_DELIVERY = [ 'OEC00', 'OEC01', 'OEC03' ];

	/**
	 * Whether an event says the object is out for delivery to the recipient.
	 *
	 * @since 1.11.0
	 *
	 * @param string $code Event code, `codigo` on the tracking API.
	 * @param string $type Event type, `tipo` on the tracking API.
	 *
	 * @return bool
	 */
	public static function isOutForDelivery( $code, $type ) {
		/**
		 * Filters the events read as "out for delivery to the recipient".
		 *
		 * @since 1.11.0
		 *
		 * @param string[] $events Code and type of each event, joined.
		 */
		$events = apply_filters( 'infixs_correios_automatico_out_for_delivery_events', self::OUT_FOR_DELIVERY );

		return in_array( $code . $type, (array) $events, true );
	}
}
