<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Core\Support\Router;
use Infixs\CorreiosAutomatico\Routes\RestRoutes;

defined( 'ABSPATH' ) || exit;

/**
 * Connects the store to its Infixs Ship account, reads it and ends the connection.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidConnector {

	/**
	 * Path of the Contrato page in the dashboard.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const DASHBOARD_PATH = '/config/contract';

	/**
	 * Route of the redirect link, under the namespace of the plugin.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const CALLBACK_ROUTE = '/prepaid/callback';

	/**
	 * Outcomes of an authorization, sent back to the dashboard as `prepaid_result`.
	 *
	 * @since 1.14.0
	 *
	 * @var string[]
	 */
	const RESULTS = [ 'connected', 'denied', 'invalid_state', 'error' ];

	/**
	 * Authorization of the store.
	 *
	 * @var PrepaidOAuth
	 */
	private $oauth;

	/**
	 * Client of the Infixs Ship.
	 *
	 * @var PrepaidApi
	 */
	private $api;

	/**
	 * Connection to the Infixs Ship.
	 *
	 * @var PrepaidConnection
	 */
	private $connection;

	/**
	 * Which contract the store posts with.
	 *
	 * @var ContractMode
	 */
	private $contractMode;

	/**
	 * REST routes of the plugin.
	 *
	 * @var RestRoutes
	 */
	private $routes;

	/**
	 * @param PrepaidOAuth      $oauth        Authorization of the store.
	 * @param PrepaidApi        $api          Client of the Infixs Ship.
	 * @param PrepaidConnection $connection   Connection to the Infixs Ship.
	 * @param ContractMode      $contractMode Which contract the store posts with.
	 * @param RestRoutes        $routes       REST routes of the plugin.
	 */
	public function __construct( PrepaidOAuth $oauth, PrepaidApi $api, PrepaidConnection $connection, ContractMode $contractMode, RestRoutes $routes ) {
		$this->oauth = $oauth;
		$this->api = $api;
		$this->connection = $connection;
		$this->contractMode = $contractMode;
		$this->routes = $routes;
	}

	/**
	 * Start an authorization.
	 *
	 * @since 1.14.0
	 *
	 * @param bool $use_rest_route Whether the site comes back through `?rest_route=`, for a
	 *                             site whose `/wp-json/` does not reach the WordPress.
	 *
	 * @return string Address of the authorization page of the Infixs Ship.
	 */
	public function start( $use_rest_route = false ) {
		$base = $use_rest_route ? $this->routes->get_rest_route_url() : $this->routes->get_rest_url();

		return $this->oauth->authorizeUrl(
			home_url(),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			untrailingslashit( $base ) . self::CALLBACK_ROUTE
		);
	}

	/**
	 * Finish an authorization the Infixs Ship sent back.
	 *
	 * The state is checked before anything else, so a link opened a second
	 * time never takes the code to the API again.
	 *
	 * @since 1.14.0
	 *
	 * @param string $code  Authorization code.
	 * @param string $state State sent back.
	 * @param string $error Error sent back, empty for none.
	 *
	 * @return string One of RESULTS.
	 */
	public function complete( $code, $state, $error ) {
		if ( '' !== (string) $error ) {
			$this->oauth->consumeState( $state );

			return 'denied';
		}

		$authorization = $this->oauth->consumeState( $state );

		if ( ! $authorization ) {
			return 'invalid_state';
		}

		if ( '' === (string) $code ) {
			return 'error';
		}

		$token = $this->api->exchangeCode( $code, $authorization['verifier'], $authorization['redirect_uri'], $authorization['site_url'] );

		if ( is_wp_error( $token ) ) {
			Log::notice( 'Infixs Ship: a troca do código de autorização falhou.', [ 'error' => $token->get_error_code() ] );

			return 'error';
		}

		$this->connection->connect( $token['access_token'], $token['site_id'], $token['account_name'] );
		$this->account();
		$this->flushShippingRates();

		Log::info( 'Infixs Ship: loja conectada.' );

		return 'connected';
	}

	/**
	 * State of the connection, as the dashboard reads it. Never carries the token.
	 *
	 * @since 1.14.0
	 *
	 * @param bool $refresh Whether to read the account again instead of the one cached.
	 *
	 * @return array
	 */
	public function status( $refresh = false ) {
		$connection = $this->connection->get();
		$account = PrepaidConnection::STATUS_CONNECTED === $connection['status'] ? $this->account( $refresh ) : null;
		$connection = $this->connection->get();
		$read = is_array( $account );

		return [
			'mode' => $this->contractMode->mode(),
			'connected' => PrepaidConnection::STATUS_CONNECTED === $connection['status'],
			'status' => $connection['status'],
			'account' => PrepaidConnection::STATUS_DISCONNECTED === $connection['status'] ? null : [
				'name' => $connection['account_name'],
				'status' => $read ? $account['status'] : '',
			],
			'balance_cents' => $read ? $account['balance_cents'] : null,
			'connected_at' => $connection['connected_at'] ? gmdate( 'c', $connection['connected_at'] ) : null,
			'urls' => [
				'app' => PrepaidApi::appUrl(),
				'signup' => PrepaidApi::appUrl(),
				'topup' => $read ? $account['urls']['topup'] : PrepaidApi::appUrl( 'recarregar' ),
				'statement' => $read ? $account['urls']['statement'] : PrepaidApi::appUrl( 'painel' ),
			],
		];
	}

	/**
	 * Contract of the Infixs for the labels, read from the account when not known yet.
	 *
	 * @since 1.14.0
	 *
	 * @return array{number: string, postcard: string}
	 */
	public function contract() {
		$contract = $this->connection->contract();

		if ( ( '' === $contract['number'] || '' === $contract['postcard'] ) && $this->connection->isConnected() ) {
			$this->account( true );
			$contract = $this->connection->contract();
		}

		return $contract;
	}

	/**
	 * End the connection.
	 *
	 * The token is revoked in the account as far as the API answers; the
	 * connection is forgotten here whatever it answers.
	 *
	 * @since 1.14.0
	 *
	 * @return void
	 */
	public function disconnect() {
		if ( PrepaidConnection::STATUS_CONNECTED === $this->connection->status() ) {
			$revoked = $this->api->revokeSite();

			if ( is_wp_error( $revoked ) ) {
				Log::notice( 'Infixs Ship: a revogação do token não foi confirmada.', [ 'error' => $revoked->get_error_code() ] );
			}
		}

		$this->connection->clear();
		$this->flushShippingRates();

		Log::info( 'Infixs Ship: loja desconectada.' );
	}

	/**
	 * Address of the Contrato page of the dashboard, with the outcome of an authorization.
	 *
	 * @since 1.14.0
	 *
	 * @param string $result One of RESULTS.
	 *
	 * @return string
	 */
	public function dashboardUrl( $result ) {
		return Router::resolve( self::DASHBOARD_PATH, [ 'prepaid_result' => in_array( $result, self::RESULTS, true ) ? $result : 'error' ] );
	}

	/**
	 * Account of the store, cached for a minute.
	 *
	 * @since 1.14.0
	 *
	 * @param bool $refresh Whether to read it again instead of the one cached.
	 *
	 * @return array|null Null when the API could not be read.
	 */
	private function account( $refresh = false ) {
		$cached = $refresh ? null : $this->connection->cachedAccount();

		if ( $cached ) {
			return $cached;
		}

		$account = $this->api->account();

		if ( is_wp_error( $account ) ) {
			return null;
		}

		$this->connection->rememberAccount( $account );

		return $account;
	}

	/**
	 * Drop the rates the carts keep: the services quoted change with the connection.
	 *
	 * @since 1.14.0
	 *
	 * @return void
	 */
	private function flushShippingRates() {
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			\WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}
}
