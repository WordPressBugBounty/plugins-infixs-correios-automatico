<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Checks the links another plugin hands to the dashboard.
 *
 * An invoice provider answers with links of its own documents and screens —
 * the full DANFE, the order where an issue is solved — which the dashboard
 * opens as they came. Only an address on this site is taken: a link to
 * anywhere else, or one with a scheme other than http and https, is dropped.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.15.0
 */
class SiteUrl {

	/**
	 * The address, escaped, when it points to this site; empty otherwise.
	 *
	 * @since 1.15.0
	 *
	 * @param mixed $url Address as another plugin answered it.
	 *
	 * @return string
	 */
	public static function own( $url ) {
		$url = is_string( $url ) ? esc_url_raw( $url, [ 'http', 'https' ] ) : '';

		return self::isOwn( $url ) ? $url : '';
	}

	/**
	 * Whether an address points to this site, on the host of the store or of its admin.
	 *
	 * @since 1.15.0 Moved from OrderInvoiceStatus, where it was since 1.12.0.
	 *
	 * @param string $url Address, already escaped.
	 *
	 * @return bool
	 */
	public static function isOwn( $url ) {
		$host = is_string( $url ) && '' !== $url ? wp_parse_url( $url, PHP_URL_HOST ) : null;

		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$own = array_filter( [
			wp_parse_url( home_url(), PHP_URL_HOST ),
			wp_parse_url( admin_url(), PHP_URL_HOST ),
		] );

		return in_array( strtolower( $host ), array_map( 'strtolower', $own ), true );
	}
}
