<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

class DeliveryServiceCode {
	public const PAC = '04510';
	public const SEDEX = '04014';
	public const SEDEX_10 = '04790';
	public const SEDEX_12 = '04782';
	public const SEDEX_HOJE = '04804';
	public const PAC_CONTRATO_AG = '03298';
	public const PAC_CONTRATO_AG_CC = '03085';
	public const PAC_CONTRATO_AG_TA = '04596';
	public const PAC_CONTRATO_AG_VAREJO = '04669';
	public const SEDEX_CONTRATO_AG = '03220';
	public const SEDEX_CONTRATO_AG_TA = '04553';
	public const SEDEX_CONTRATO_AG_CC = '03050';
	public const SEDEX_CONTRATO_AG_VAREJO = '04162';
	public const SEDEX_10_CONTRATO_AG = '03158';
	public const SEDEX_12_CONTRATO_AG = '03140';
	public const SEDEX_HOJE_CONTRATO_AG = '03204';
	public const SEDEX_CONTRATO_GRANDE_FORMATO = '03212';
	public const SEDEX_CONTRATO_PGTO_ENTREGA = '03271';
	public const PAC_CONTRATO_PGTO_ENTREGA = '03310';
	public const PAC_CONTRATO_GRANDE_FORMATO = '03328';
	public const SEDEX_KIT = '03352';
	public const SEDEX_KIT_ISENCAO = '04219';
	public const SEDEX_HOJE_EMPRESARIAL = '03662';
	public const CORREIOS_MINI_ENVIOS_CTR_AG = '04227';

	public const IMPRESSO_NORMAL = '20010';

	public const IMPRESSO_NORMAL_2 = '20133';
	public const IMPRESSO_NORMAL_20KG_NP = '20060';
	public const IMPRESSO_NORMAL_NAC_FAT_CHANC_NP = '20117';

	public const IMPRESSO_MODICO = '20192';

	public const PACKET_STANDARD = '33162'; // IMPORTAÇÃO
	public const PACKET_EXPRESS = '33170'; // IMPORTAÇÃO

	public const EXPORTA_FACIL_STANDARD = '45128';
	public const EXPORTA_FACIL_PREMIUM = '45195';
	public const EXPORTA_FACIL_ECNOMICO = '45209';

	public const EXPORTA_FACIL_EXPRESSO = '45110';

	public const PAC_LOG = '39870';

	public const SEDEX_LOG = '39888';
	public const SEDEX_10_LOG = '06580';

	public const SEDEX_12_LOG = '06599';

	public const SEDEX_HOJE_LOG = '06602';

	public const CARTA_COML_REG_B1_CHANC_ETIQ = '80250';



	private static $descriptions = [ 
		self::PAC => 'PAC (Sem Contrato)',
		self::PAC_CONTRATO_AG => 'PAC (Contrato Agência)',
		self::PAC_CONTRATO_AG_CC => 'PAC CC (Contrato Agência CC)',
		self::PAC_CONTRATO_AG_TA => 'PAC TA (Contrato Agência TA)',
		self::PAC_CONTRATO_AG_VAREJO => 'PAC (Contrato Agência Varejo)',
		self::SEDEX => 'SEDEX (Sem Contrato)',
		self::SEDEX_CONTRATO_AG => 'SEDEX (Contrato Agência)',
		self::SEDEX_CONTRATO_AG_CC => 'SEDEX CC (Contrato Agência CC)',
		self::SEDEX_CONTRATO_AG_TA => 'SEDEX TA (Contrato Agência TA)',
		self::SEDEX_CONTRATO_AG_VAREJO => 'SEDEX (Contrato Agência Varejo)',
		self::SEDEX_10 => 'SEDEX 10 (Sem Contrato)',
		self::SEDEX_10_CONTRATO_AG => 'SEDEX 10 (Contrato Agência)',
		self::SEDEX_12 => 'SEDEX 12 (Sem Contrato)',
		self::SEDEX_12_CONTRATO_AG => 'SEDEX 12 (Contrato Agência)',
		self::SEDEX_HOJE => 'SEDEX Hoje (Sem Contrato)',
		self::SEDEX_HOJE_CONTRATO_AG => 'SEDEX Hoje (Contrato Agência)',
		self::SEDEX_CONTRATO_GRANDE_FORMATO => 'SEDEX (Contrato Grande Formato)',
		self::SEDEX_CONTRATO_PGTO_ENTREGA => 'SEDEX (Contrato Pagamento na Entrega)',
		self::PAC_CONTRATO_PGTO_ENTREGA => 'PAC (Contrato Pagamento na Entrega)',
		self::PAC_CONTRATO_GRANDE_FORMATO => 'PAC (Contrato Grande Formato)',
		self::SEDEX_KIT => 'SEDEX KIT',
		self::SEDEX_KIT_ISENCAO => 'SEDEX KIT ISENÇÃO',
		self::SEDEX_HOJE_EMPRESARIAL => 'SEDEX HOJE EMPRESARIAL',
		self::CORREIOS_MINI_ENVIOS_CTR_AG => 'Correios Mini Envios (Contrato Agência)',
		self::IMPRESSO_NORMAL => 'Impresso Normal (Com ou sem Contrato)',
		self::IMPRESSO_NORMAL_2 => 'Impresso Normal G (Com ou sem Contrato)',
		self::IMPRESSO_MODICO => 'Impresso Módico (Com ou sem Contrato)',
		self::PACKET_STANDARD => 'PACKET STANDART (Contrato)',
		self::PACKET_EXPRESS => 'PACKET EXPRESS (Contrato)',
		self::EXPORTA_FACIL_STANDARD => 'EXPORTA FÁCIL STANDART',
		self::EXPORTA_FACIL_PREMIUM => 'EXPORTA FÁCIL PREMIUM',
		self::EXPORTA_FACIL_ECNOMICO => 'EXPORTA FÁCIL ECONÔMICO',
		self::EXPORTA_FACIL_EXPRESSO => 'EXPORTA FÁCIL EXPRESSO',
		self::SEDEX_10_LOG => 'SEDEX 10 LOG+',
		self::SEDEX_12_LOG => 'SEDEX 12 LOG+',
		self::SEDEX_HOJE_LOG => 'SEDEX HOJE LOG+',
		self::PAC_LOG => 'PAC LOG+',
		self::SEDEX_LOG => 'SEDEX LOG+',
		self::CARTA_COML_REG_B1_CHANC_ETIQ => 'CARTA COMERCIAL REGISTRADA B1 CHANCELADA ETIQUETA',
		self::IMPRESSO_NORMAL_NAC_FAT_CHANC_NP => 'Impresso Normal NAC FAT CHANC NP',
		self::IMPRESSO_NORMAL_20KG_NP => 'Impresso Normal Até 20KG NP',
	];

	/**
	 * Check parameter
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $param
	 * 
	 * @return string
	 */
	public static function checkParameter( $param ) {
		return defined( "self::$param" ) ? constant( "self::$param" ) : $param;
	}

	/**
	 * Get description
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $item
	 * @param bool $with_code
	 * 
	 * @return string
	 */
	public static function getDescription( $item, $with_code = false ) {
		if ( ! isset( self::$descriptions[ $item ] ) ) {
			return '';
		}
		return $with_code ? self::checkParameter( $item ) . ' - ' . self::$descriptions[ $item ] : self::$descriptions[ $item ];
	}

	public static function getShortDescription( $item ) {
		return preg_replace( '/\s*\(.*?\)/', '', self::getDescription( $item ) );
	}

	public static function getInternationals() {
		return [ 
			self::EXPORTA_FACIL_ECNOMICO,
			self::EXPORTA_FACIL_EXPRESSO,
			self::EXPORTA_FACIL_PREMIUM,
			self::EXPORTA_FACIL_STANDARD,
		];
	}

	public static function getDefaultLabel() {
		return [ 
			self::PAC,
			self::SEDEX,
			self::SEDEX_10,
			self::SEDEX_12,
			self::SEDEX_HOJE,
			self::PAC_CONTRATO_AG,
			self::SEDEX_CONTRATO_AG,
			self::SEDEX_10_CONTRATO_AG,
			self::SEDEX_12_CONTRATO_AG,
			self::SEDEX_HOJE_CONTRATO_AG,
			self::CORREIOS_MINI_ENVIOS_CTR_AG,
			self::SEDEX_10_LOG,
			self::SEDEX_12_LOG,
			self::SEDEX_HOJE_LOG,
		];
	}

	/**
	 * Get all
	 * 
	 * @since 1.0.0
	 * 
	 * @return array
	 */
	public static function getAll() {
		$keys = array_keys( self::$descriptions );
		$values = array_map( function ($key, $value) {
			return "$key - $value";
		}, $keys, self::$descriptions );

		return array_combine( $keys, $values );
	}

	public static function getGroups() {
		return [ 
			'most_used' => [ 
				self::PAC,
				self::PAC_CONTRATO_AG,
				self::SEDEX,
				self::SEDEX_CONTRATO_AG,
				self::SEDEX_10,
				self::SEDEX_10_CONTRATO_AG,
				self::SEDEX_12,
				self::SEDEX_12_CONTRATO_AG,
				self::SEDEX_HOJE,
				self::SEDEX_HOJE_CONTRATO_AG,
			],
			'with_contract' => [

			],
			'without_contract' => [

			],
		];
	}

	/**
	 * Get dimension limits
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $service_code
	 * @param string $object_type
	 * 
	 * @return array{
	 * 			min: array{
	 * 				length: int,
	 * 				width: int,
	 * 				height: int,
	 * 				weight: float
	 * 			},
	 * 			max: array{
	 * 				length: int,
	 * 				width: int,
	 * 				height: int,
	 * 				weight: float
	 * 			}
	 * }|null 
	 * 
	 * Weight in kg
	 * Height, width and length in cm
	 * 
	 */
	public static function getDimensionLimits( $service_code, $object_type = 'package' ) {
		switch ( $service_code ) {
			case self::SEDEX:
			case self::SEDEX_CONTRATO_AG:
			case self::PAC:
			case self::PAC_CONTRATO_AG:
				return [ 
					'min' => [ 'length' => 1, 'width' => 1, 'height' => 1, 'weight' => 0.1 ],
					'max' => [ 'length' => 100, 'width' => 100, 'height' => 100, 'weight' => 30, 'total_dimensions' => 200 ],
				];
			case self::CORREIOS_MINI_ENVIOS_CTR_AG:
				return [ 
					'min' => [ 'length' => 1, 'width' => 1, 'height' => 1, 'weight' => 0.001 ],
					'max' => [ 'length' => 24, 'width' => 16, 'height' => 4, 'weight' => 0.3 ],
				];
			case self::IMPRESSO_MODICO:
			case self::IMPRESSO_NORMAL:
			case self::IMPRESSO_NORMAL_NAC_FAT_CHANC_NP:
				return [ 
					'min' => [ 'length' => 1, 'width' => 1, 'height' => 1, 'weight' => 0.001 ],
					'max' => [ 'length' => 100, 'width' => 100, 'height' => 100, 'weight' => 2 ],
				];
			case self::CARTA_COML_REG_B1_CHANC_ETIQ:
				return [ 
					'min' => [ 'length' => 1, 'width' => 1, 'height' => 1, 'weight' => 0.001 ],
					'max' => [ 'length' => 100, 'width' => 100, 'height' => 100, 'weight' => 0.5 ],
				];
		}

		return null;
	}

	/**
	 * Get declaration limits
	 * 
	 * @since 1.2.9
	 * 
	 * @param string $service_code
	 * @param string $object_type
	 * 
	 * @return array{
	 * 			min: float,
	 * 			max: float
	 * }|null 
	 * 
	 * 
	 */
	public static function getDeclarationLimits( $service_code ) {
		switch ( $service_code ) {
			case self::SEDEX:
			case self::SEDEX_HOJE_CONTRATO_AG:
			case self::SEDEX_CONTRATO_AG:
			case self::SEDEX_10_CONTRATO_AG:
			case self::SEDEX_12_CONTRATO_AG:
				return [ 
					'min' => 25.63,
					'max' => 35571.17,
				];
			case self::PAC_CONTRATO_AG:
			case self::PAC:
				return [ 
					'min' => 25.63,
					'max' => 4184.84,
				];
			case self::CORREIOS_MINI_ENVIOS_CTR_AG:
			case self::IMPRESSO_MODICO:
			case self::IMPRESSO_NORMAL:
			case self::CARTA_COML_REG_B1_CHANC_ETIQ:
			case self::IMPRESSO_NORMAL_NAC_FAT_CHANC_NP:
			case self::IMPRESSO_NORMAL_20KG_NP:
				return [ 
					'min' => 12.82,
					'max' => 104.62,
				];
		}

		return null;
	}

	/**
	 * Get common id
	 * 
	 * @since 1.1.5
	 * 
	 * @param string $product_code
	 * 
	 * @return string|null
	 */
	public static function getCommonId( $product_code ) {
		switch ( $product_code ) {
			case DeliveryServiceCode::PAC:
			case DeliveryServiceCode::PAC_CONTRATO_AG:
			case DeliveryServiceCode::PAC_CONTRATO_GRANDE_FORMATO:
			case DeliveryServiceCode::PAC_CONTRATO_PGTO_ENTREGA:
			case DeliveryServiceCode::PAC_LOG:
				return 'pac';
			case DeliveryServiceCode::SEDEX:
			case DeliveryServiceCode::SEDEX_CONTRATO_AG:
			case DeliveryServiceCode::SEDEX_CONTRATO_GRANDE_FORMATO:
			case DeliveryServiceCode::SEDEX_CONTRATO_PGTO_ENTREGA:
			case DeliveryServiceCode::SEDEX_LOG:
				return 'sedex';
			case DeliveryServiceCode::SEDEX_10:
			case DeliveryServiceCode::SEDEX_10_CONTRATO_AG:
			case DeliveryServiceCode::SEDEX_10_LOG:
				return 'sedex10';
			case DeliveryServiceCode::SEDEX_12:
			case DeliveryServiceCode::SEDEX_12_CONTRATO_AG:
			case DeliveryServiceCode::SEDEX_12_LOG:
				return 'sedex12';
			case DeliveryServiceCode::SEDEX_HOJE:
			case DeliveryServiceCode::SEDEX_HOJE_CONTRATO_AG:
			case DeliveryServiceCode::SEDEX_HOJE_EMPRESARIAL:
			case DeliveryServiceCode::SEDEX_HOJE_LOG:
				return 'sedexHoje';
			case DeliveryServiceCode::CORREIOS_MINI_ENVIOS_CTR_AG:
				return 'miniEnvios';
			case DeliveryServiceCode::IMPRESSO_MODICO:
				return 'impressoModico';
			case DeliveryServiceCode::IMPRESSO_NORMAL:
			case DeliveryServiceCode::IMPRESSO_NORMAL_2:
			case DeliveryServiceCode::IMPRESSO_NORMAL_NAC_FAT_CHANC_NP:
			case DeliveryServiceCode::IMPRESSO_NORMAL_20KG_NP:
				return 'impressoNormal';
			case DeliveryServiceCode::PACKET_EXPRESS:
				return 'packetExpress';
			case DeliveryServiceCode::PACKET_STANDARD:
				return 'packetStandart';
			case DeliveryServiceCode::CARTA_COML_REG_B1_CHANC_ETIQ:
				return 'cartaRegistrada';
			default:
				return null;
		}
	}

	public static function getObjectFormatByProductCode( $product_code ) {
		if ( self::isLetter( $product_code ) ) {
			return 1;
		}

		return 2;
	}

	public static function isLetter( $product_code ) {
		return in_array( $product_code, [ 
			self::CARTA_COML_REG_B1_CHANC_ETIQ,
			self::IMPRESSO_MODICO,
			self::IMPRESSO_NORMAL,
			self::IMPRESSO_NORMAL_2,
			self::IMPRESSO_NORMAL_NAC_FAT_CHANC_NP,
			self::IMPRESSO_NORMAL_20KG_NP,
		] );
	}

	public static function allowRange( $product_code ) {
		return in_array( $product_code, [ 
			self::IMPRESSO_MODICO,
			self::IMPRESSO_NORMAL,
			self::IMPRESSO_NORMAL_2,
			self::IMPRESSO_NORMAL_NAC_FAT_CHANC_NP,
			self::IMPRESSO_NORMAL_20KG_NP,
		] );
	}

}