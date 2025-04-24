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

	public const BIG_FORMATS = '057';

	private static $descriptions = [ 
		self::RECEIPT_NOTICE => 'Aviso de Recebimento',
		self::OWN_HANDS => 'Mão Própria',
		self::MODICO => 'Registro Módico',
		self::INSURANCE_DECLARATION_SEDEX => 'Declaração de Valor Sedex',
		self::INSURANCE_DECLARATION_PAC => 'Declaração de Valor PAC',
		self::INSURANCE_DECLARATION_MINI_ENVIOS => 'Declaração de Valor Mini Envios',
		self::BIG_FORMATS => 'Grandes Formatos',
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

	public static function getInsuranceCode( $product_code ) {
		switch ( $product_code ) {
			case DeliveryServiceCode::PAC_CONTRATO_AG:
			case DeliveryServiceCode::PAC:
				return AddicionalServiceCode::INSURANCE_DECLARATION_PAC;
			case DeliveryServiceCode::SEDEX_HOJE_CONTRATO_AG:
			case DeliveryServiceCode::SEDEX_CONTRATO_AG:
			case DeliveryServiceCode::SEDEX_10_CONTRATO_AG:
			case DeliveryServiceCode::SEDEX_12_CONTRATO_AG:
				return AddicionalServiceCode::INSURANCE_DECLARATION_SEDEX;
			case DeliveryServiceCode::CORREIOS_MINI_ENVIOS_CTR_AG:
				return AddicionalServiceCode::INSURANCE_DECLARATION_MINI_ENVIOS;
			default:
				return null;
		}
	}
}