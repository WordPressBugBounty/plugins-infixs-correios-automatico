<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

use Infixs\CorreiosAutomatico\Services\Correios\Enums\Environment;

defined( 'ABSPATH' ) || exit;

class Auth {

	/**
	 * Environment for the contract
	 *
	 * @var Environment $environment "production" or "sandbox"
	 */
	private $environment;

	/**
	 * User name for the contract
	 *
	 * @var string $user_name
	 */
	private $user_name;

	/**
	 * Access code for the contract
	 *
	 * @var string $access_code
	 */
	private $access_code;

	/**
	 * Postcard for the contract
	 *
	 * @var string $postcard
	 */
	private $postcard;

	/**
	 * Token for the contract
	 *
	 * @var string $token
	 */
	private $token;


	/**
	 * Callback to update the token
	 *
	 * @var callable|null $update_token_callback
	 */
	private $update_token_callback = null;

	/**
	 * Constructor
	 *
	 * @param array $data Data to initialize the Auth object.
	 * @param callable|null $update_token_callback Optional callback to update the token.
	 */
	public function __construct( $data, $update_token_callback = null ) {
		$this->environment = isset( $data['environment'] ) && $data['environment'] === 'production' ? Environment::PRODUCTION : Environment::SANDBOX;
		$this->user_name = $data['user_name'] ?? '';
		$this->access_code = $data['access_code'] ?? '';
		$this->postcard = $data['postcard'] ?? '';
		$this->token = $data['token'] ?? '';
		if ( is_callable( $update_token_callback ) ) {
			$this->update_token_callback = $update_token_callback;
		}
	}

	/**
	 * Update the Auth data using the update token callback
	 */
	public function update_token( $token ) {
		$this->token = $token;
		if ( is_callable( $this->update_token_callback ) ) {
			call_user_func( $this->update_token_callback, $token );
		}
	}

	public function setUpdateTokenCallback( $callback ) {
		if ( is_callable( $callback ) ) {
			$this->update_token_callback = $callback;
		}
	}


	public function getEnvironment() {
		return $this->environment;
	}

	public function setEnvironment( $environment ) {
		$this->environment = $environment;
	}

	public function getUserName() {
		return $this->user_name;
	}

	public function setUserName( $user_name ) {
		$this->user_name = $user_name;
	}

	public function getAccessCode() {
		return $this->access_code;
	}

	public function setAccessCode( $access_code ) {
		$this->access_code = $access_code;
	}

	public function getPostcard() {
		return $this->postcard;
	}

	public function setPostcard( $postcard ) {
		$this->postcard = $postcard;
	}

	public function getToken() {
		return $this->token;
	}

	public function setToken( $token ) {
		$this->token = $token;
	}
}