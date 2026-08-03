<?php

namespace Infixs\CorreiosAutomatico\Services\Correios;

use Infixs\CorreiosAutomatico\Config\VendorSettings;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Correios API clients that speak for a single vendor.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.2
 */
class VendorApiFactory {

	/**
	 * Clients already built in this request, keyed by vendor.
	 *
	 * @var array<int, CorreiosApi|null>
	 */
	private static $clients = [];

	/**
	 * Get the client of a vendor, falling back to another one.
	 *
	 * @param int         $vendor_id Vendor ID.
	 * @param CorreiosApi $fallback  Client to use when the vendor has no contract.
	 *
	 * @return CorreiosApi
	 */
	public static function forVendor( $vendor_id, $fallback ) {
		$vendor_api = $vendor_id ? self::fromVendor( $vendor_id ) : null;

		return $vendor_api ? $vendor_api : $fallback;
	}

	/**
	 * Build a client using a vendor's own contract.
	 *
	 * The token refresh callback writes back to the vendor option, so a renewed
	 * token never leaks into the store wide configuration.
	 *
	 * @param int $vendor_id Vendor ID.
	 *
	 * @return CorreiosApi|null Null when the vendor has no usable contract.
	 */
	public static function fromVendor( $vendor_id ) {
		$vendor_id = (int) $vendor_id;

		if ( array_key_exists( $vendor_id, self::$clients ) ) {
			return self::$clients[ $vendor_id ];
		}

		$settings = VendorSettings::get( $vendor_id );

		if ( ! $settings->auth->isActive() || ! $settings->auth->hasValidCredentials() ) {
			self::$clients[ $vendor_id ] = null;

			return null;
		}

		$auth = new Auth( $settings->auth->toArray() );

		$auth->setUpdateTokenCallback( function ( $token ) use ( $vendor_id ) {
			$vendor_settings = VendorSettings::get( $vendor_id );
			$vendor_settings->auth->setToken( $token );
			$vendor_settings->save();
		} );

		self::$clients[ $vendor_id ] = new CorreiosApi( $auth );

		return self::$clients[ $vendor_id ];
	}

	/**
	 * Build a client from raw credentials, to validate them before saving.
	 *
	 * @param array $credentials Credentials with user_name, access_code, postcard and environment.
	 *
	 * @return CorreiosApi
	 */
	public static function fromCredentials( $credentials ) {
		return new CorreiosApi( new Auth( $credentials ) );
	}

	/**
	 * Drop the per request client cache.
	 *
	 * @param int|null $vendor_id Vendor to forget, null for every vendor.
	 *
	 * @return void
	 */
	public static function flush( $vendor_id = null ) {
		if ( $vendor_id === null ) {
			self::$clients = [];

			return;
		}

		unset( self::$clients[ (int) $vendor_id ] );
	}
}
