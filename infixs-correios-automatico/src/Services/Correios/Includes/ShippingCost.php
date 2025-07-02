<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

use Infixs\CorreiosAutomatico\Services\Correios\Enums\AddicionalServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Utils\NumberHelper;

defined( 'ABSPATH' ) || exit;

class ShippingCost {

	/**
	 * Own hands
	 * 
	 * @var bool
	 */
	private $own_hands = false;

	/**
	 * Receipt notice
	 * 
	 * @var bool
	 */
	private $receipt_notice = false;


	/**
	 * Registro Modico
	 * 
	 * @var bool
	 */
	private $modico = false;

	/**
	 * Product code
	 * 
	 * Use DeliveryServiceCode constants
	 * 
	 * @var string
	 */
	private $product_code;

	/**
	 * Origin postcode
	 * 
	 * @var string
	 */
	private $origin_postcode;

	/**
	 * Destination postcode
	 * 
	 * @var string
	 */
	private $destination_postcode;

	/**
	 * Country
	 * 
	 * @var string
	 */
	private $destination_country;

	/**
	 * Package
	 * 
	 * @var Package
	 */
	private $package = null;

	/**
	 * Width in cm
	 * 
	 * @var int
	 */
	private $width;

	/**
	 * Height in cm
	 * 
	 * @var int
	 */
	private $height;

	/**
	 * Length in cm
	 * 
	 * @var int
	 */
	private $length;

	/**
	 * Weight in kg
	 * 
	 * @var float|int
	 */
	private $weight;

	/**
	 * Insurance declaration value
	 * 
	 * @var float
	 */
	private $insurance_declaration_value = null;

	/**
	 * Object type
	 * 
	 * @var string $object_type "package"|"label"
	 */
	private $object_type = 'package';

	public function __construct( $product_code, $origin_postcode, $destination_postcode, $destination_country = "BR" ) {
		$this->product_code = $product_code;
		$this->origin_postcode = $origin_postcode;
		$this->destination_postcode = $destination_postcode;
		$this->destination_country = $destination_country;
	}

	public function getOwnHands() {
		return $this->own_hands;
	}

	/**
	 * Set Package
	 * 
	 * @param Package $package
	 * @return void
	 */
	public function setPackage( $package ) {
		$this->package = $package;
		$data = $package->get_data();
		$this->setHeight( $data['height'] );
		$this->setWidth( $data['width'] );
		$this->setLength( $data['length'] );
		$this->setWeight( $data['weight'] );
	}

	/**
	 * Get Package
	 * 
	 * @return Package|null
	 */
	public function getPackage() {
		return $this->package;
	}

	/**
	 * Set Own Hands
	 * 
	 * @since 1.0.0
	 * 
	 * @param bool $own_hands
	 * 
	 * @return void
	 */
	public function setOwnHands( $own_hands ) {
		$this->own_hands = $own_hands;
	}

	public function getReceiptNotice() {
		return $this->receipt_notice;
	}

	public function setReceiptNotice( $receipt_notice ) {
		$this->receipt_notice = $receipt_notice;
	}

	public function getModico() {
		return $this->modico;
	}

	public function setModico( $modico ) {
		$this->modico = $modico;
	}

	/**
	 * Set Insurance Declaration Value
	 * 
	 * @param float $insurance_declaration_value
	 * 
	 * @return void
	 */
	public function setInsuranceDeclarationValue( $insurance_declaration_value ) {
		$this->insurance_declaration_value = $insurance_declaration_value;
	}

	/**
	 * Get Package
	 * 
	 * @return float|null
	 */
	public function getInsuranceDeclarationValue() {
		return $this->insurance_declaration_value;
	}

	public function getProductCode() {
		return $this->product_code;
	}

	public function getOriginPostcode() {
		return $this->origin_postcode;
	}

	public function getDestinationPostcode() {
		return $this->destination_postcode;
	}

	public function getDestinationCountry() {
		return $this->destination_country;
	}

	/**
	 * Get the weight
	 * 
	 * Default is KG
	 * 
	 * @param string $unit  'g', 'kg', 'lbs', 'oz'.
	 * 
	 * @return float
	 */
	public function getWeight( $unit = 'kg' ) {
		$weight = $this->weight > 0 ? $this->weight : 0.1;
		return NumberHelper::parseNumber( wc_get_weight( $weight, $unit, 'kg' ), 3 );
	}

	/**
	 * Set the weight
	 * 
	 * @param float $weight
	 * 
	 * @return void
	 */
	public function setWeight( $weight ) {
		$this->weight = $weight;
	}

	/**
	 * Get the height
	 * 
	 * @return float
	 */
	public function getHeight() {
		return NumberHelper::parseNumber( $this->height );
	}

	/**
	 * Set the height
	 * 
	 * @param float $height
	 * 
	 * @return void
	 */
	public function setHeight( $height ) {
		$this->height = $height;
	}

	/**
	 * Get the width
	 * 
	 * @return float
	 */
	public function getWidth() {
		return NumberHelper::parseNumber( $this->width );
	}

	/**
	 * Set the width
	 * 
	 * @param float $width
	 * 
	 * @return void
	 */
	public function setWidth( $width ) {
		$this->width = $width;
	}

	/**
	 * Get the length
	 * 
	 * @return float
	 */
	public function getLength() {
		return NumberHelper::parseNumber( $this->length );
	}

	/**
	 * Set the length
	 * 
	 * @param float $length
	 * 
	 * @return void
	 */
	public function setLength( $length ) {
		$this->length = $length;
	}

	public function getObjectType() {
		return $this->object_type;
	}

	/**
	 * Check if the declaration is within the limits
	 * 
	 * @return bool
	 */
	/**
	 * Check if the declaration is within the limits.
	 *
	 * @param string $check 'both' (default), 'min', or 'max'
	 * @return bool
	 */
	public function areDeclarationWithinLimits( $check = 'both' ) {
		$limits = DeliveryServiceCode::getDeclarationLimits( $this->getProductCode() );
		$declaration_value = $this->getInsuranceDeclarationValue();

		if ( ! $limits || $declaration_value === null ) {
			return false;
		}

		switch ( $check ) {
			case 'min':
				return $declaration_value >= $limits['min'];
			case 'max':
				return $declaration_value <= $limits['max'];
			case 'both':
			default:
				return $declaration_value >= $limits['min'] && $declaration_value <= $limits['max'];
		}
	}

	public function getData() {
		$data = [ 
			"cepOrigem" => $this->getOriginPostcode(),
			"cepDestino" => $this->getDestinationPostcode(),
			"psObjeto" => $this->getWeight( 'g' ),
			"comprimento" => $this->getLength(),
			"altura" => $this->getHeight(),
			"largura" => $this->getWidth(),
			"tpObjeto" => $this->getObjectType() === 'label' || $this->getModico() ? 1 : 2,
		];

		if ( in_array( $this->getProductCode(),
			[ 
				DeliveryServiceCode::IMPRESSO_NORMAL,
				DeliveryServiceCode::IMPRESSO_NORMAL_NAC_FAT_CHANC_NP,
				DeliveryServiceCode::IMPRESSO_NORMAL_2,
				DeliveryServiceCode::IMPRESSO_NORMAL_20KG_NP,
			]
		) ) {
			$data['tpObjeto'] = 1;
		}

		if ( $this->getOwnHands() ) {
			$data['servicosAdicionais'][] = AddicionalServiceCode::OWN_HANDS;
		}

		if ( $this->getReceiptNotice() ) {
			$data['servicosAdicionais'][] = AddicionalServiceCode::RECEIPT_NOTICE;
		}

		if ( $this->getModico() ) {
			$data['servicosAdicionais'][] = AddicionalServiceCode::MODICO;
		}

		if ( $this->getProductCode() === DeliveryServiceCode::PAC_CONTRATO_GRANDE_FORMATO
			|| $this->getProductCode() === DeliveryServiceCode::SEDEX_CONTRATO_GRANDE_FORMATO ) {
			$data['servicosAdicionais'][] = AddicionalServiceCode::BIG_FORMATS;
		}

		$declaration_value = $this->getInsuranceDeclarationValue();

		if ( $declaration_value && $this->areDeclarationWithinLimits() ) {
			$insurance_service_code = AddicionalServiceCode::getInsuranceCode( $this->getProductCode() );
			if ( $insurance_service_code ) {
				$data['servicosAdicionais'][] = $insurance_service_code;
				$data['vlDeclarado'] = $declaration_value;
			}
		}

		return $data;
	}
}