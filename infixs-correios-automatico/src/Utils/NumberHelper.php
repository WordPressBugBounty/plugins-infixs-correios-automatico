<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;
class NumberHelper {

	/**
	 * Convert a number to 100.
	 * 
	 * @param float $number Number to convert.
	 * 
	 * @return float
	 */
	public static function to100( $number ) {
		return floatval( number_format( $number, 2, '.', '' ) ) * 100;
	}

	/**
	 * Convert a number to decimal from 100.
	 * 
	 * @param float $number Number to convert.
	 * 
	 * @return float
	 */
	public static function from100( $number ) {
		return floatval( round( $number, 2 ) / 100 );
	}

	/**
	 * Format a number and return string formatted.
	 * 
	 * @param float|int|string $number Number to format.
	 * @param int $precision Number of decimal points.
	 * @param string $decimal_separator Decimal separator.
	 * @param string $thousand_separator Thousand separator.
	 * 
	 * @return string
	 */
	public static function formatNumber( $number, $precision = 2, $decimal_separator = '.', $thousand_separator = '' ) {
		$rounded = wc_format_decimal( trim( stripslashes( $number ) ), $precision ); //clean and sanitize number
		return number_format( floatval( $rounded ), $precision, $decimal_separator, $thousand_separator ); //format
	}

	/**
	 * Parse a formatted string foat number and return float without thousands.
	 * 
	 * Not use for non float strings.
	 * 
	 * @param string $formattedNumber Formatted number.
	 * @param int $precision Number of decimal points.
	 * 
	 * @return float
	 */
	public static function parseNumber( $formattedNumber, $precision = 2 ) {
		$number = wc_format_decimal( trim( stripslashes( $formattedNumber ) ), $precision );
		return floatval( $number );
	}
}