<?php

namespace Infixs\CorreiosAutomatico\Utils;

use Infixs\CorreiosAutomatico\Container;

defined( 'ABSPATH' ) || exit;
class Currency {

	/**
	 * Get the currency value in USD
	 * 
	 * @param float $value
	 * @param string $from_currency
	 * @return string
	 * 
	 * @return float|bool
	 */
	public static function toCurrency( $value, $to_currency = 'USD', $from_currency = null ) {

		if ( $from_currency == null ) {
			$from_currency = get_woocommerce_currency();
		}

		if ( $from_currency == $to_currency ) {
			return $value;
		}

		$rate = self::getRate( $from_currency, $to_currency );

		if ( is_wp_error( $rate ) ) {
			return false;
		}

		return round( $value * $rate, 2 );
	}

	/**
	 * Get the currency value in current currency
	 * 
	 * @param float $value
	 * @param string $from_currency
	 * 
	 * @return float|bool
	 */
	public static function toCurrentCurrency( $value, $from_currency = 'USD' ) {
		return self::toCurrency( $value, get_woocommerce_currency(), $from_currency );
	}

	/**
	 * Get the currency rate
	 * 
	 * @param string $from_currency
	 * @param string $to_currency
	 * 
	 * @return float|\WP_Error
	 */
	public static function getRate( $from_currency, $to_currency ) {
		$transient_name = "infixs_currency_rate_{$from_currency}_{$to_currency}";

		$transient_data = get_transient( $transient_name );
		$current_timestamp = strtotime( current_time( 'mysql' ) );

		if ( false === $transient_data || gmdate( 'Ymd', $transient_data['timestamp'] ) !== gmdate( 'Ymd', $current_timestamp ) ) {
			$rate = Container::infixsApi()->getCurrencyRate( $from_currency, $to_currency );

			if ( is_wp_error( $rate ) ) {
				return $rate;
			}

			$rate = $rate['bid'];

			set_transient( $transient_name, [ 
				'rate' => $rate,
				'timestamp' => $current_timestamp
			], \DAY_IN_SECONDS );

			return $rate;
		} else {
			return $transient_data['rate'];
		}
	}
}