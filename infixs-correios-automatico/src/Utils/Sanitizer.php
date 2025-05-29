<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;
class Sanitizer {
	/**
	 * Sanitizer string to remove non-numeric characters.
	 *
	 * @since 1.0.0
	 * 
	 * @param string $value
	 * 
	 * @return string
	 */
	public static function numeric_text( $value ) {
		return preg_replace( '/\D/', '', $value );
	}


	/**
	 * Sanitizer integer
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $value
	 * 
	 * @return int
	 */
	public static function numeric( $value ) {
		return (int) preg_replace( '/\D/', '', $value );
	}

	/**
	 * Sanitizer money with multiple of 100
	 * 
	 * R$ 1.234,56 => 123456
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $value
	 * 
	 * @return int
	 */
	public static function money100( $value, $decimal = ',' ) {
		if ( $value === null )
			return 0;
		$sanitized_value = preg_replace( '/[^0-9' . preg_quote( $decimal, '/' ) . ']/', '', $value );
		$parts = explode( $decimal, $sanitized_value );
		$whole = $parts[0] ?? '0';
		$fraction = substr( str_pad( $parts[1] ?? '00', 2, '0' ), 0, 2 );

		return (int) ( $whole . $fraction );
	}

	/**
	 * Sanitizer float
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $value
	 * 
	 * @return string
	 */
	public static function float_text( $value, $precision = 3 ) {
		$value = str_replace( ',', '.', $value );
		$sanitized_value = preg_replace( '/[^0-9.]/', '', $value );
		$parts = explode( '.', $sanitized_value );
		$whole = $parts[0] ?? '0';
		$fraction = str_pad( $parts[1] ?? '00', $precision, '0' );

		return $whole . '.' . $fraction;
	}

	/**
	 * Sanitizer boolean
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $value
	 * 
	 * @return bool
	 */
	public static function boolean( $value ) {
		return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes';
	}

	/**
	 * Sanitizer array of string
	 * 
	 * @since 1.0.0
	 * 
	 * @param mixed $value
	 * 
	 * @return array
	 */
	public static function array_strings( $value ) {
		if ( ! is_array( $value ) ) {
			return [ sanitize_text_field( $value ) ];
		}
		return array_map( function ($item) {
			return sanitize_text_field( $item );
		}, $value );
	}


	/**
	 * Sanitize the array of numbers
	 *
	 * @param array $array
	 * @return bool|array
	 */
	public static function array_numbers( $array ) {
		return array_filter( $array, 'is_numeric' );
	}

	public static function post_checkbox( $value ) {
		return $value === 'yes' ? 'yes' : null;
	}

	/**
	 * Sanitizes a cellphone number.
	 *
	 * @param string $value The cellphone number to sanitize.
	 * 
	 * @return string The sanitized cellphone number
	 */
	public static function celphone( $value ) {
		$value = preg_replace( '/\D/', '', $value );

		if ( strlen( $value ) === 10 ) {
			return substr( $value, 0, 2 ) . '9' . substr( $value, 2 );
		}

		if ( strlen( $value ) > 11 ) {
			return substr( $value, -11 );
		}

		if ( strlen( $value ) < 10 ) {
			$value = str_pad( $value, 11, '0', STR_PAD_LEFT );
		}

		return $value;
	}

	/**
	 * Sanitizes a phone number.
	 *
	 * @param string $value The phone number to sanitize.
	 * 
	 * @return string The sanitized phone number
	 */
	public static function phone( $value ) {
		$value = preg_replace( '/\D/', '', $value );

		if ( strlen( $value ) === 0 ) {
			return '';
		}

		if ( strlen( $value ) < 10 ) {
			$value = str_pad( $value, 10, '0', STR_PAD_LEFT );
		}

		if ( strlen( $value ) === 10 ) {
			return $value;
		}

		return '';
	}


	public static function weight( $value, $precision = 3 ) {
		if ( is_numeric( $value ) ) {
			return round( floatval( $value ), $precision );
		}
		return round( 0, $precision );
	}


	public static function integer_text( $value ) {
		return number_format( $value, 0, '', '' );
	}
	public static function postcode( $value ) {
		$postcode = preg_replace( '/\D/', '', $value );
		$postcode = str_pad( $postcode, 8, '0', STR_PAD_LEFT );
		if ( strlen( $postcode ) > 8 ) {
			$postcode = substr( $postcode, 0, 8 );
		}
		return $postcode;
	}

	public static function multiselect( $value, $filter = 'sanitize_text_field' ) {
		if ( is_array( $value ) ) {
			return array_filter( array_map( $filter, $value ) );
		} else {
			return array_filter( array_map( $filter, explode( ',', $value ) ) );
		}
	}
}
