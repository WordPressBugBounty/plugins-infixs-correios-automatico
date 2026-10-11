<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Reads what an access key of the SEFAZ says about itself.
 *
 * The forty four digits are not an opaque id: they carry, in fixed positions,
 * the model of the document, its series and its number. That is what tells a
 * DC-e (model `99`, issued by the Correios for the package) from the NF-e of
 * the store (model `55`) — the same column of a prepost holds whichever of the
 * two the Correios answered in `chaveNFe`, so nothing but the key itself says
 * which document it belongs to.
 *
 * Layout of the 44 digits:
 *
 *     UF(2) AAMM(4) CNPJ(14) model(2) series(3) number(9) tpEmis(1) code(8) DV(1)
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class AccessKey {

	/**
	 * How many digits an access key of the SEFAZ has.
	 *
	 * @since 1.11.0
	 *
	 * @var int
	 */
	const LENGTH = 44;

	/**
	 * Model of the DC-e, the electronic declaration the Correios issue.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const MODEL_DCE = '99';

	/**
	 * Model of the NF-e, the invoice of the store.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const MODEL_INVOICE = '55';

	/**
	 * Keep only the digits of a key and take it when it has the exact length.
	 *
	 * The key is printed in groups of four, and stored with whatever the
	 * Correios answered, so the separators are dropped here. Anything that is
	 * not a whole key is refused: a truncated run of digits read from a text
	 * would point the QR Code at a document that does not exist.
	 *
	 * @since 1.11.0
	 *
	 * @param string|null $key Key as it was read or stored.
	 *
	 * @return string|null Forty four digits, null when the value is not a key.
	 */
	public static function normalize( $key ) {
		if ( ! is_scalar( $key ) ) {
			return null;
		}

		$digits = preg_replace( '/[^0-9]/', '', (string) $key );

		return strlen( $digits ) === self::LENGTH ? $digits : null;
	}

	/**
	 * Model of the document the key belongs to, printed on digits 21 and 22.
	 *
	 * @since 1.11.0
	 *
	 * @param string|null $key Access key.
	 *
	 * @return string|null Two digits, null when the value is not a key.
	 */
	public static function model( $key ) {
		$digits = self::normalize( $key );

		return null === $digits ? null : substr( $digits, 20, 2 );
	}

	/**
	 * Whether the key is of a DC-e.
	 *
	 * @since 1.11.0
	 *
	 * @param string|null $key Access key.
	 *
	 * @return bool
	 */
	public static function isDce( $key ) {
		return self::MODEL_DCE === self::model( $key );
	}

	/**
	 * Whether the key is of an NF-e of the store.
	 *
	 * @since 1.11.0
	 *
	 * @param string|null $key Access key.
	 *
	 * @return bool
	 */
	public static function isInvoice( $key ) {
		return self::MODEL_INVOICE === self::model( $key );
	}

	/**
	 * Number of the document, printed on digits 26 to 34 of the key.
	 *
	 * The zeros that pad the field are dropped: the document is printed as
	 * `Nº: 57406860`, not as `057406860`.
	 *
	 * @since 1.11.0
	 *
	 * @param string|null $key Access key.
	 *
	 * @return string|null Number of the document, null when the value is not a key.
	 */
	public static function number( $key ) {
		$digits = self::normalize( $key );

		if ( null === $digits ) {
			return null;
		}

		$number = ltrim( substr( $digits, 25, 9 ), '0' );

		return '' === $number ? '0' : $number;
	}

	/**
	 * Series of the document, printed on digits 23 to 25 of the key.
	 *
	 * The zeros are kept: the series is printed as `SERIE: 001`.
	 *
	 * @since 1.11.0
	 *
	 * @param string|null $key Access key.
	 *
	 * @return string|null Series of the document, null when the value is not a key.
	 */
	public static function series( $key ) {
		$digits = self::normalize( $key );

		return null === $digits ? null : substr( $digits, 22, 3 );
	}
}
