<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

class AddicionalServiceCode {
	public const RECEIPT_NOTICE = '001';
	public const OWN_HANDS = '002';

	public const MODICO = '004';

	public const INSURANCE_DECLARATION_SEDEX = '019';
	public const INSURANCE_DECLARATION_PAC = '064';

	public const INSURANCE_DECLARATION_MINI_ENVIOS = '065';

	private static $descriptions = [ 
		self::RECEIPT_NOTICE => 'Aviso de Recebimento',
		self::OWN_HANDS => 'Mão Própria',
		self::MODICO => 'Registro Módico',
		self::INSURANCE_DECLARATION_SEDEX => 'Declaração de Valor Sedex',
		self::INSURANCE_DECLARATION_PAC => 'Declaração de Valor PAC',
		self::INSURANCE_DECLARATION_MINI_ENVIOS => 'Declaração de Valor Mini Envios',
	];

	/**
	 * Get the description of the additional service.
	 * 
	 * @param string $item Additional service code.
	 * 
	 * @return string
	 */
	public static function getDescription( $item ) {
		return self::$descriptions[ $item ] ?? 'Serviço desconhecido';
	}
}