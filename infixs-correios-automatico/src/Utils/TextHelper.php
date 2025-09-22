<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;
class TextHelper {
	public static function extractAddressNumber( $address ) {
		preg_match( '/\b\d+(\.\d+)?\b/', $address, $matches );
		return Sanitizer::numeric_text( $matches[0] ?? '' );
	}
	public static function removeAddressNumber( $address ) {
		$number = self::extractAddressNumber( $address );
		if ( $number === '' ) {
			return rtrim( $address, ', ' );
		}
		$address = preg_replace( '/\b' . preg_quote( $number, '/' ) . '\b/', '', $address, 1 );
		$address = trim( $address );
		$address = rtrim( $address, ', ' );
		return $address;
	}


	public static function removeShippingTime( $name ) {
		return trim( preg_replace( '/ \(\s*\d+(?: a \d+)? dia[s]? út(eis|il)\s*\)/', '', $name ) );
	}
}