<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\AddicionalServiceCode;

defined( 'ABSPATH' ) || exit;

class ShippingTime {

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

	public function __construct( $product_code, $origin_postcode, $destination_postcode ) {
		$this->product_code = $product_code;
		$this->origin_postcode = $origin_postcode;
		$this->destination_postcode = $destination_postcode;
	}


	/**
	 * Calculate shipping time
	 * 
	 * @return int|false
	 */
	public function calculate() {
		return Container::correiosService()->get_shipping_time( $this->product_code, [ 
			"cepOrigem" => $this->origin_postcode,
			"cepDestino" => $this->destination_postcode,
		] );
	}
}