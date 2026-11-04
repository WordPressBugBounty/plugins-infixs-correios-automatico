<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

defined( 'ABSPATH' ) || exit;

/**
 * Amounts of the Infixs Ship account, in reais whatever the currency of the store.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidMoney {

	/**
	 * Format an amount in cents as `R$ 1.234,56`, a negative one as `-R$ 5,00`.
	 *
	 * @since 1.14.0
	 *
	 * @param int $cents Amount in cents.
	 *
	 * @return string
	 */
	public static function format( $cents ) {
		$cents = (int) $cents;
		$formatted = 'R$ ' . number_format( abs( $cents ) / 100, 2, ',', '.' );

		return $cents < 0 ? '-' . $formatted : $formatted;
	}
}
