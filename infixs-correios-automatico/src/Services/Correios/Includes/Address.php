<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

defined( 'ABSPATH' ) || exit;

/**
 * Address class.
 * 
 * @since 1.0.0
 */
class Address {

	/**
	 * Postal Code
	 * 
	 * Max 8 characters
	 * 
	 * @var string
	 */
	private $postal_code;

	/**
	 * Street
	 * 
	 * Max 50 characters
	 * 
	 * @var string
	 */
	private $street;

	/**
	 * Number
	 * 
	 * Max 6 characters
	 * 
	 * @var string
	 */
	private $number;

	/**
	 * Complement
	 * 
	 * Max 30 characters
	 * 
	 * @var string
	 */
	private $complement;

	/**
	 * Neighborhood
	 * 
	 * Max 30 characters
	 * 
	 * @var string
	 */
	private $neighborhood;

	/**
	 * City
	 * 
	 * Max 30 characters
	 * 
	 * @var string
	 */
	private $city;

	/**
	 * State
	 * 
	 * Max 2 characters
	 * 
	 * @var string
	 */
	private $state;

	/**
	 * Country
	 * 
	 * Max 50 characters
	 * 
	 * @var string|null
	 */
	private $country;

	/**
	 * Sets the address.
	 *
	 * @param string $postal_code Postal Code.
	 * @param string $street Street.
	 * @param string $number Number.
	 * @param string $complement Complement.
	 * @param string $neighborhood Neighborhood.
	 * @param string $city City.
	 * @param string $state State.
	 * @param string $country Country.
	 */
	public function __construct(
		$postal_code,
		$street,
		$number,
		$complement,
		$neighborhood,
		$city,
		$state,
		$country = null
	) {
		$this->postal_code = $postal_code;
		$this->street = $street;
		$this->number = $number;
		$this->complement = $complement;
		$this->neighborhood = $neighborhood;
		$this->city = $city;
		$this->state = $state;
		$this->country = $country;
	}


	public function getData() {
		$data = [ 
			"cep" => $this->postal_code,
			"logradouro" => $this->street,
			"numero" => $this->number,
			"complemento" => $this->complement,
			"bairro" => $this->neighborhood,
			"cidade" => $this->city,
			"uf" => $this->state,
		];

		if ( ! empty( $this->country ) && $this->country !== 'BR' ) {
			$data['pais'] = $this->country;
		}

		return $data;
	}

	/**
	 * Get postal code.
	 * 
	 * @since 1.0.0
	 * 
	 * @return string
	 */
	public function getPostalCode() {
		return $this->postal_code;
	}

	public function getStreet() {
		return $this->street;
	}

	public function getNumber() {
		return $this->number;
	}

	public function getComplement() {
		return $this->complement;
	}

	public function getNeighborhood() {
		return $this->neighborhood;
	}

	public function getCity() {
		return $this->city;
	}

	public function getState() {
		return $this->state;
	}

	public function getCountry() {
		return $this->country;
	}
}
