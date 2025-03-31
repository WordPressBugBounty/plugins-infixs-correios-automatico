<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

class PrepostStatusCode {
	public const PREATENDIDO = 1;
	public const PREPOSTADO = 2;
	public const POSTADO = 3;
	public const EXPRIRADO = 4;
	public const CANCELADO = 5;
	public const ESTORNADO = 6;

	/**
	 * Get status
	 * 
	 * @param int $code
	 * 
	 * @return string|null
	 */
	public static function getStatus( $code ) {
		$statuses = [ 
			self::PREATENDIDO => 'Preatendido',
			self::PREPOSTADO => 'Pré-postado',
			self::POSTADO => 'Postado',
			self::EXPRIRADO => 'Expirado',
			self::CANCELADO => 'Cancelado',
			self::ESTORNADO => 'Estornado',
		];

		return isset( $statuses[ $code ] ) ? $statuses[ $code ] : null;
	}
}