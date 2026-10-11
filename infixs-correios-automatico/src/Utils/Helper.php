<?php

namespace Infixs\CorreiosAutomatico\Utils;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\APIServiceCode;

defined( 'ABSPATH' ) || exit;
class Helper {

	/**
	 * Contract Has Service
	 *
	 * A contract whose granted APIs were never recorded grants none, instead of
	 * handing `in_array()` a null list.
	 *
	 * @param string $service_code Infixs\CorreiosAutomatico\Services\Correios\Enums\APIServiceCode
	 *
	 * @return bool
	 */
	public static function contractHasService( $service_code ) {
		return in_array( $service_code, (array) Config::get( 'auth.allowed_services', [] ) );
	}
	public static function extractNumberFromTrackingCode( $code, $without_digit = false ) {
		if ( preg_match( '/\d+/', $code, $matches ) ) {
			if ( $without_digit ) {
				return substr( $matches[0], 0, -1 );
			}
			return $matches[0];
		}
		return false;
	}

	public static function isValidPostcode( $postcode ) {
		return preg_match( '/^\d{5}-?\d{3}$/', $postcode );
	}

	public static function generateHashFromArray( $array ) {
		return md5( maybe_serialize( $array ) );
	}
}