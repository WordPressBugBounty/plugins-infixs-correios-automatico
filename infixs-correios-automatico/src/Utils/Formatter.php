<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;
class Formatter {
	/**
	 * Format the document.
	 *
	 * @param string $document
	 * @return string
	 */
	public static function format_document( $document ) {
		$document = preg_replace( '/\D/', '', $document );

		if ( strlen( $document ) === 11 ) {
			return preg_replace( '/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $document );
		} elseif ( strlen( $document ) === 14 ) {
			return preg_replace( '/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $document );
		} else {
			return $document;
		}
	}

	public static function format_datetime( $datetime ) {
		return \DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime )->format( 'd/m/Y H:i:s' );
	}

	public static function format_timestamp( $timestamp, $format = 'default' ) {
		if ( $format === 'default' ) {
			return date_i18n( get_option( 'date_format' ), $timestamp ) . ' às ' . date_i18n( get_option( 'time_format' ), $timestamp );
		} else {
			return date_i18n( $format, $timestamp );
		}
	}

	public static function format_postcode( $postcode ) {
		$postcode_numbers = preg_replace( '/\D/', '', $postcode );

		if ( strlen( $postcode_numbers ) === 8 ) {
			return preg_replace( '/(\d{5})(\d{3})/', '$1-$2', $postcode_numbers );
		} else {
			return $postcode;
		}
	}

	/**
	 * Format the currency.
	 *
	 * @param float $value
	 * @return string
	 */
	public static function format_currency( $value ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $value ) ) );
	}
}
