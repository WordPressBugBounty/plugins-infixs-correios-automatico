<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

class ObjectFormatCode {
	public const ENVELOPE = 1;
	public const PACOTE = 2;
	public const CILINDRO = 3;

	private static $descriptions = [ 
		self::ENVELOPE => 'Envelope/Carta',
		self::PACOTE => 'Pacote',
		self::CILINDRO => 'Cilindro'
	];

	/**
	 * Get the description of the object format code.
	 * 
	 * @param number $item Object format code.
	 * 
	 * @return string
	 */
	public static function getDescription( $item ) {
		return self::$descriptions[ $item ] ?? null;
	}
}