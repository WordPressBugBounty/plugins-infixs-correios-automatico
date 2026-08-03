<?php

namespace Infixs\CorreiosAutomatico\Config;

use Infixs\CorreiosAutomatico\Services\Correios\Enums\Environment;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Correios contract credentials of a single vendor.
 *
 * Mirrors the `auth` branch of the store wide configuration, so a vendor
 * contract can be handed to the same `Auth` and `CorreiosApi` classes the store
 * uses. Lives in the base plugin so every marketplace extension shares one
 * implementation and one stored shape.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.2
 */
class VendorAuth {

	/**
	 * Whether the vendor contract is active.
	 *
	 * @var bool
	 */
	private $active;

	/**
	 * Environment setting (production/sandbox).
	 *
	 * @var string
	 */
	public $environment;

	/**
	 * Correios API username.
	 *
	 * @var string
	 */
	public $user_name;

	/**
	 * Correios API access code.
	 *
	 * @var string
	 */
	public $access_code;

	/**
	 * Correios postcard number.
	 *
	 * @var string
	 */
	public $postcard;

	/**
	 * Correios API token.
	 *
	 * @var string
	 */
	public $token;

	/**
	 * Contract number with Correios.
	 *
	 * @var string
	 */
	public $contract_number;

	/**
	 * Type of contract (PF/PJ).
	 *
	 * @var string
	 */
	public $contract_type;

	/**
	 * Contract document (CNPJ/CPF).
	 *
	 * @var string
	 */
	public $contract_document;

	/**
	 * Allowed API services for the contract.
	 *
	 * @var array
	 */
	public $allowed_services;

	/**
	 * @param array $data Stored settings.
	 */
	public function __construct( $data = [] ) {
		$this->active = isset( $data['active'] ) ? Sanitizer::boolean( $data['active'] ) : false;
		$this->environment = isset( $data['environment'] ) ? $data['environment'] : 'production';
		$this->user_name = isset( $data['user_name'] ) ? $data['user_name'] : '';
		$this->access_code = isset( $data['access_code'] ) ? $data['access_code'] : '';
		$this->postcard = isset( $data['postcard'] ) ? $data['postcard'] : '';
		$this->token = isset( $data['token'] ) ? $data['token'] : '';
		$this->contract_number = isset( $data['contract_number'] ) ? $data['contract_number'] : '';
		$this->contract_type = isset( $data['contract_type'] ) ? $data['contract_type'] : '';
		$this->contract_document = isset( $data['contract_document'] ) ? $data['contract_document'] : '';
		$this->allowed_services = isset( $data['allowed_services'] ) && is_array( $data['allowed_services'] ) ? $data['allowed_services'] : [];
	}

	/**
	 * Check if the contract is active.
	 *
	 * @return bool
	 */
	public function isActive() {
		return $this->active;
	}

	/**
	 * Check if the contract points at the production environment.
	 *
	 * @return bool
	 */
	public function isProduction() {
		return $this->environment === Environment::PRODUCTION;
	}

	/**
	 * Check if the credentials are filled in.
	 *
	 * @return bool
	 */
	public function hasValidCredentials() {
		return ! empty( $this->user_name ) &&
			! empty( $this->access_code ) &&
			! empty( $this->postcard );
	}

	/**
	 * Check if the contract is allowed to use an API service.
	 *
	 * @param int $service_code API service code.
	 *
	 * @return bool
	 */
	public function contractHasService( $service_code ) {
		return in_array( $service_code, $this->allowed_services ); // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
	}

	/**
	 * Set the Correios API token.
	 *
	 * @param string $token Token.
	 *
	 * @return void
	 */
	public function setToken( $token ) {
		$this->token = $token;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function toArray() {
		return [
			'active' => $this->active,
			'environment' => $this->environment,
			'user_name' => $this->user_name,
			'access_code' => $this->access_code,
			'postcard' => $this->postcard,
			'token' => $this->token,
			'contract_number' => $this->contract_number,
			'contract_type' => $this->contract_type,
			'contract_document' => $this->contract_document,
			'allowed_services' => $this->allowed_services,
		];
	}
}
