<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

class PaymentTypeCode {
	public const A_VISTA = 1;
	public const A_FATURAR = 2;
	public const AMBOS = 3;

	private static $descriptions = [ 
		self::A_VISTA => 'À vista',
		self::A_FATURAR => 'A faturar',
		self::AMBOS => 'Á Vista/A faturar',
	];

	/**
	 * Get the description of the payment type.
	 * 
	 * @param number $item Payment type code.
	 * 
	 * @return string
	 */
	public static function getDescription( $item ) {
		return self::$descriptions[ $item ] ?? null;
	}
}