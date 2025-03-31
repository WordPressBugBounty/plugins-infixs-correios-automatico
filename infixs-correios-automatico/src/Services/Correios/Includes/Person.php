<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Address class.
 * 
 * @since 1.0.0
 */
class Person {
	/**
	 * Name
	 * 
	 * Max 255 characters
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $name;

	/**
	 * Phone area code
	 * 
	 * Max 2 characters
	 * 
	 * @since 1.0.0
	 * 
	 * @var string|null
	 */
	private $phone_area_code;

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
	 * Cell area code
	 * 
	 * Max 2 characters
	 * 
	 * @var string|null
	 */
	private $cell_area_code;

	/**
	 * Cell number
	 * 
	 * Min 9 characters or empty
	 * 
	 * @var string|null
	 */
	private $cell_number;

	/**
	 * Email
	 * 
	 *  max 255 characters
	 * 
	 * @var string|null
	 */
	private $email;

	/**
	 * CPF or CNPJ
	 * 
	 * For Recipent: Required if the delivery is to a 'locker'. If provided, it must be valid.
	 * For Sender: CPF or CNPJ of the sender. It is mandatory if no foreign document is provided, must be valid, and have a maximum of 14 numeric characters.
	 * 
	 * Max 16 characters
	 * 
	 * @var string|null
	 */
	private $cpf_cnpj;

	/**
	 * Foregin Document
	 * 
	 * Passport or RNM of the recipient at the time of pre-posting. Max 30 characters.
	 * 
	 * Max 30 characters
	 * 
	 * @var string|null
	 */
	private $foregin_document;

	/**
	 * Notes
	 * 
	 * Recipient's note filled at the customer's discretion.
	 * 
	 * Max 100 characters
	 * 
	 * @var string|null
	 */
	private $notes;

	/**
	 * Address
	 * 
	 * @var Address
	 */
	private $address;

	/**
	 * Constructor
	 * 
	 * @param string $name
	 * @param Address $address
	 * @param string|null $phone_area_code
	 * @param string|null $phone_number
	 * @param string|null $cell_area_code
	 * @param string|null $cell_number
	 * @param string|null $email
	 * @param string|null $cpf_cnpj
	 * @param string|null $foregin_document
	 * @param string|null $notes
	 */
	public function __construct(
		$name,
		$address,
		$cpf_cnpj = null,
		$phone_number = null,
		$cell_number = null,
		$email = null,
		$foregin_document = null,
		$notes = null
	) {
		$this->name = $name;
		$this->address = $address;
		$this->phone_area_code = substr( $phone_number, 0, 2 );
		$this->phone_number = strlen( $phone_number ) >= 2 ? substr( $phone_number, 2 ) : '';
		$this->cell_area_code = substr( $cell_number, 0, 2 );
		$this->cell_number = substr( $cell_number, 2 );
		$this->email = $email;
		$this->cpf_cnpj = $cpf_cnpj;
		$this->foregin_document = $foregin_document;
		$this->notes = $notes;
	}

	public function getData() {
		$data = [];

		if ( ! empty( $this->phone_area_code ) && ! empty( $this->phone_number ) ) {
			$data['dddTelefone'] = $this->phone_area_code;
			$data['telefone'] = $this->phone_number;
		}

		if ( ! empty( $this->cell_area_code ) && ! empty( $this->cell_number ) ) {
			$data['dddCelular'] = $this->cell_area_code;
			$data['celular'] = $this->cell_number;
		}

		if ( ! empty( $this->email ) ) {
			$data['email'] = $this->email;
		}

		if ( ! empty( $this->cpf_cnpj ) ) {
			$data['cpfCnpj'] = $this->cpf_cnpj;
		}

		if ( ! empty( $this->foregin_document ) ) {
			$data['documentoEstrangeiro'] = $this->foregin_document;
		}

		if ( ! empty( $this->notes ) ) {
			$data['obs'] = $this->notes;
		}

		return array_merge( $data, [ 
			"nome" => $this->name,
			"endereco" => $this->address->getData(),
		] );
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
	 * Get the phone area code
	 * 
	 * @return string
	 */
	public function getPhoneAreaCode() {
		return substr( $this->phone_area_code, 0, 2 );
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
	 * Get the cell area code with 2 characters
	 * 
	 * @return string
	 */
	public function getCellAreaCode() {
		return substr( $this->cell_area_code, 0, 2 );
	}

	/**
	 * Get the cell number
	 * 
	 * @return string
	 */
	public function getCellNumber() {
		return $this->cell_number;
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
	 * Get the CPF or CNPJ sanitized without punctuation
	 * 
	 * @return string
	 */
	public function getCpfCnpj() {
		return Sanitizer::numeric_text( $this->cpf_cnpj );
	}


	public function getDocumentType() {
		return strlen( $this->getCpfCnpj() ) > 11 ? 'CNPJ' : 'CPF';
	}

	/**
	 * Get the foregin document
	 * 
	 * @return string
	 */
	public function getForeginDocument() {
		return $this->foregin_document;
	}

	/**
	 * Get the notes
	 * 
	 * @return string
	 */
	public function getNotes() {
		return $this->notes;
	}

	/**
	 * Get the address
	 * 
	 * @return Address
	 */
	public function getAddress() {
		return $this->address;
	}

	/**
	 * Get the cellphone or phone Exactly 10 or 11 numeric digits
	 * 
	 * Ex: 3199998888 or 31999998888
	 * 
	 * @return string
	 */
	public function getAlwaysPhone() {
		if ( $this->getCellAreaCode() && $this->getCellNumber() ) {
			return $this->getCellAreaCode() . $this->getCellNumber();
		} else {
			return $this->getPhoneAreaCode() . $this->getPhoneNumber();
		}
	}
}
