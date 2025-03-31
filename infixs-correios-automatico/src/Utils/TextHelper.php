<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;
class TextHelper {
	public static function extractAddressNumber( $address ) {
		preg_match( '/\b\d+(\.\d+)?\b/', $address, $matches );
		return Sanitizer::numeric_text( $matches[0] ?? '' );
	}

	public static function removeShippingTime( $name ) {
		return preg_replace( '/ \(\d+ dia[s]? út(eis|il)\)/', '', $name );
	}
}