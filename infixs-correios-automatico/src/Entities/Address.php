<?php

namespace Infixs\CorreiosAutomatico\Entities;

defined( 'ABSPATH' ) || exit;

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
	 * Max 50 characters
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
	 * Max 2 characters
	 * 
	 * @var string
	 */
	private $country;

	/**
	 * Constructor
	 * 
	 * @param string $postal_code
	 * @param string $street
	 * @param string $number
	 * @param string $complement
	 * @param string $neighborhood
	 * @param string $city
	 * @param string $state
	 * @param string $country
	 */
	public function __construct( $postal_code, $street, $number, $neighborhood, $city, $state, $complement = '', $country = 'BR' ) {
		$this->postal_code = $postal_code;
		$this->street = $street;
		$this->number = $number;
		$this->complement = $complement;
		$this->neighborhood = $neighborhood;
		$this->city = $city;
		$this->state = $state;
		$this->country = $country;
	}

	/**
	 * Convert to array
	 * 
	 * @return array
	 */
	public function toArray() {
		return [ 
			'postal_code' => $this->postal_code,
			'street' => $this->street,
			'number' => $this->number,
			'complement' => $this->complement,
			'neighborhood' => $this->neighborhood,
			'city' => $this->city,
			'state' => $this->state,
			'country' => $this->country,
		];
	}

	/**
	 * Set postal code
	 * 
	 * @param string $postal_code
	 * 
	 * @return void
	 */
	public function setPostCode( $postal_code ) {
		$this->postal_code = $postal_code;
	}

	/**
	 * Get postal code
	 * 
	 * @return string
	 */
	public function getPostCode() {
		return $this->postal_code;
	}

	/**
	 * Set street
	 * 
	 * @param string $street
	 * 
	 * @return void
	 */
	public function setStreet( $street ) {
		$this->street = $street;
	}

	/**
	 * Get street
	 * 
	 * @return string
	 */
	public function getStreet() {
		return $this->street;
	}

	/**
	 * Set number
	 * 
	 * @param string $number
	 * 
	 * @return void
	 */
	public function setNumber( $number ) {
		$this->number = $number;
	}

	/**
	 * Get number
	 * 
	 * @return string
	 */
	public function getNumber() {
		return $this->number;
	}

	/**
	 * Set complement
	 * 
	 * @param string $complement
	 * 
	 * @return void
	 */
	public function setComplement( $complement ) {
		$this->complement = $complement;
	}

	/**
	 * Get complement
	 * 
	 * @return string
	 */
	public function getComplement() {
		return $this->complement;
	}

	/**
	 * Set neighborhood
	 * 
	 * @param string $neighborhood
	 * 
	 * @return void
	 */
	public function setNeighborhood( $neighborhood ) {
		$this->neighborhood = $neighborhood;
	}

	/**
	 * Get neighborhood
	 * 
	 * @return string
	 */
	public function getNeighborhood() {
		return $this->neighborhood;
	}

	/**
	 * Set city
	 * 
	 * @param string $city
	 * 
	 * @return void
	 */
	public function setCity( $city ) {
		$this->city = $city;
	}

	/**
	 * Get city
	 * 
	 * @return string
	 */
	public function getCity() {
		return $this->city;
	}

	/**
	 * Set state
	 * 
	 * @param string $state
	 * 
	 * @return void
	 */
	public function setState( $state ) {
		$this->state = $state;
	}

	/**
	 * Get state
	 * 
	 * @return string
	 */
	public function getState() {
		return $this->state;
	}

	/**
	 * Set country
	 * 
	 * @param string $country
	 * 
	 * @return void
	 */
	public function setCountry( $country ) {
		$this->country = $country;
	}

	/**
	 * Get country
	 * 
	 * @return string
	 */
	public function getCountry() {
		return $this->country;
	}
}