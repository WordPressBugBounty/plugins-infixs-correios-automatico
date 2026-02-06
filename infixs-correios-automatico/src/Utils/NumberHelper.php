<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;
class NumberHelper {

	/**
	 * Convert a number to multiply by 100.
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

	/**
	 * Convert monetary values to cents (int) without using float.
	 * Accepts: "R$ 1.234,56", "$1,234.56", "- 12,3", 12.5, 12, etc.
	 *
	 * @param mixed        $value    Input value (string/float/int/null)
	 * @param string|null  $decimal  Decimal separator; if null, auto-detects (last '.' or ',')
	 * 
	 * @return int
	 */
	public static function moneyToCents( $value, $decimal = null ) {
		if ( $value === null || $value === '' )
			return 0;

		if ( is_int( $value ) )
			return $value * 100;
		if ( is_float( $value ) )
			return (int) round( $value * 100 );

		$s = trim( (string) $value );
		if ( $s === '' )
			return 0;

		$sign = ( strpos( $s, '-' ) !== false ) ? -1 : 1;

		$s = preg_replace( '/[^\d.,-]+/u', '', $s );
		if ( $s === null )
			$s = '';

		if ( $decimal === null ) {
			$lastDot = strrpos( $s, '.' );
			$lastComma = strrpos( $s, ',' );
			if ( $lastDot === false && $lastComma === false ) {
				$decimal = '';
			} else {
				$decimal = ( $lastDot !== false && ( $lastComma === false || $lastDot > $lastComma ) ) ? '.' : ',';
			}
		}

		if ( $decimal !== '' ) {
			$s = preg_replace( '/[^0-9' . preg_quote( $decimal, '/' ) . ']/', '', $s );
			if ( $s === null )
				$s = '';
			$parts = explode( $decimal, $s, 2 );
		} else {
			$s = preg_replace( '/\D+/', '', $s );
			if ( $s === null )
				$s = '';
			$parts = array( $s, '' );
		}

		$whole = $parts[0] !== '' ? ltrim( $parts[0], '0' ) : '';
		if ( $whole === '' )
			$whole = '0';

		$fractionRaw = isset( $parts[1] ) ? $parts[1] : '';
		$fractionRaw = preg_replace( '/\D+/', '', $fractionRaw );
		if ( $fractionRaw === null )
			$fractionRaw = '';
		$fraction = substr( str_pad( $fractionRaw, 2, '0' ), 0, 2 );

		$centsStr = $whole . $fraction;
		if ( $centsStr === '' )
			$centsStr = '0';

		$cents = (int) $centsStr;
		return $sign * $cents;
	}


	/**
	 * Convert numeric string/float/int to cents (int) using WooCommerce helpers.
	 * 
	 * Use only if you are sure the input is a valid float/int number
	 * if you not sure use moneyToCents().
	 *
	 * @param string|float|int $value
	 * 
	 * @return int
	 */
	public static function numericToCents( $value ) {
		$decimal = wc_format_decimal( $value, 2 );
		return (int) round( $decimal * 100 );
	}
}