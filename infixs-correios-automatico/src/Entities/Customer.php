<?php

namespace Infixs\CorreiosAutomatico\Entities;

defined( 'ABSPATH' ) || exit;

/**
 * Customer class.
 * 
 * @since 1.0.0
 */
class Customer {
	/**
	 * Name
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $name;

	/**
	 * Phone number
	 * 
	 * Min 8 characters or empty
	 * 
	 * @since 1.0.0
	 * 
	 * @var string|null
	 */
	private $phone_number;

	/**
	 * Email
	 * 
	 * @since 1.0.0
	 * 
	 * @var string|null
	 */
	private $email;

	/**
	 * Document
	 * 
	 * @since 1.0.0
	 * 
	 * @var string|null
	 */
	private $document;

	/**
	 * Constructor
	 * 
	 * @param string $name
	 * @param string|null $phone_number
	 * @param string|null $email
	 * @param string|null $document
	 */
	public function __construct(
		$name,
		$email = null,
		$phone_number = null,
		$document = null
	) {
		$this->name = $name;
		$this->email = $email;
		$this->phone_number = $phone_number;
		$this->document = $document;
	}

	public function toArray() {
		return [ 
			'name' => $this->name,
			'email' => $this->email,
			'phone_number' => $this->phone_number,
			'document' => $this->document,
		];
	}

	/**
	 * Get the name
	 * 
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Get the phone number
	 * 
	 * @return string
	 */
	public function getPhoneNumber() {
		return $this->phone_number;
	}

	/**
	 * Get the email
	 * 
	 * @return string
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * Get the CPF or CNPJ
	 * 
	 * @return string
	 */
	public function getDocument() {
		return $this->document;
	}
}
