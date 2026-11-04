<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Traits\HttpTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Client of the Infixs Ship API (`/v1/ship`).
 *
 * Every route of the store carries the token of the connection in
 * `Authorization: Bearer` and the address of the site in `X-Site-Url`. Only
 * a token the API refuses (401 `invalid_token`) marks the connection to be
 * made again: any other 401 is an error like the others. The errors of the
 * Correios come back as the Correios sent them, and are read the way the
 * plugin always read them.
 *
 * Neither the token nor the bodies sent are ever logged.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidApi {

	use HttpTrait;

	/**
	 * Address of the API, unless INFIXS_CORREIOS_AUTOMATICO_SHIP_API_URL says otherwise.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const API_URL = 'https://api.infixs.io/v1/ship';

	/**
	 * Address of the site of the account, unless INFIXS_CORREIOS_AUTOMATICO_SHIP_APP_URL says otherwise.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const APP_URL = 'https://ship.infixs.com';

	/**
	 * Seconds a request waits for the API.
	 *
	 * @since 1.14.0
	 *
	 * @var int
	 */
	const TIMEOUT = 30;

	/**
	 * Seconds the creation of a prepost waits: the API waits for the Correios behind it.
	 *
	 * @since 1.14.0
	 *
	 * @var int
	 */
	const PREPOST_TIMEOUT = 60;

	/**
	 * Code of the error of a token the API refused.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const INVALID_TOKEN = 'invalid_token';

	/**
	 * Code of the error of an account the Infixs blocked.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const ACCOUNT_BLOCKED = 'account_blocked';

	/**
	 * Code of the error of a prepost whose first request, with the same idempotency key, is still running.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const PREPOST_IN_PROGRESS = 'prepost_in_progress';

	/**
	 * Connection to the Infixs Ship.
	 *
	 * @var PrepaidConnection
	 */
	private $connection;

	/**
	 * @param PrepaidConnection $connection Connection to the Infixs Ship.
	 */
	public function __construct( PrepaidConnection $connection ) {
		$this->connection = $connection;
	}

	/**
	 * Address of the API.
	 *
	 * @since 1.14.0
	 *
	 * @return string
	 */
	public static function apiUrl() {
		$url = defined( 'INFIXS_CORREIOS_AUTOMATICO_SHIP_API_URL' ) ? (string) constant( 'INFIXS_CORREIOS_AUTOMATICO_SHIP_API_URL' ) : self::API_URL;

		/**
		 * Filters the address of the Infixs Ship API.
		 *
		 * @since 1.14.0
		 *
		 * @param string $url Address, without the trailing slash.
		 */
		return untrailingslashit( (string) apply_filters( 'infixs_correios_automatico_prepaid_api_url', $url ) );
	}

	/**
	 * Address of the site where the merchant manages the account.
	 *
	 * @since 1.14.0
	 *
	 * @param string $path Path on the site.
	 *
	 * @return string
	 */
	public static function appUrl( $path = '' ) {
		$url = defined( 'INFIXS_CORREIOS_AUTOMATICO_SHIP_APP_URL' ) ? (string) constant( 'INFIXS_CORREIOS_AUTOMATICO_SHIP_APP_URL' ) : self::APP_URL;

		/**
		 * Filters the address of the Infixs Ship site.
		 *
		 * @since 1.14.0
		 *
		 * @param string $url Address, without the trailing slash.
		 */
		$url = untrailingslashit( (string) apply_filters( 'infixs_correios_automatico_prepaid_app_url', $url ) );

		return '' === $path ? $url : $url . '/' . ltrim( $path, '/' );
	}

	/**
	 * Exchange the authorization code for the token of the store (`POST /oauth/token`).
	 *
	 * @since 1.14.0
	 *
	 * @param string $code         Authorization code.
	 * @param string $verifier     PKCE verifier of the authorization.
	 * @param string $redirect_uri Redirect link the authorization was asked with.
	 * @param string $site_url     Address of the site the authorization was asked for.
	 *
	 * @return array{access_token: string, site_id: string, account_name: string}|\WP_Error
	 */
	public function exchangeCode( $code, $verifier, $redirect_uri, $site_url ) {
		$response = $this->request( 'POST', '/oauth/token', [
			'grant_type' => 'authorization_code',
			'code' => (string) $code,
			'code_verifier' => (string) $verifier,
			'redirect_uri' => (string) $redirect_uri,
			'site_url' => (string) $site_url,
		], [], false );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = $response['body'];

		if ( empty( $body['access_token'] ) || ! is_string( $body['access_token'] ) ) {
			return new \WP_Error( 'prepaid_token_failed', __( 'A Infixs Ship não devolveu o token da loja.', 'infixs-correios-automatico' ), [ 'status' => 502 ] );
		}

		return [
			'access_token' => $body['access_token'],
			'site_id' => isset( $body['site_id'] ) && is_scalar( $body['site_id'] ) ? sanitize_text_field( (string) $body['site_id'] ) : '',
			'account_name' => isset( $body['account']['name'] ) && is_string( $body['account']['name'] ) ? sanitize_text_field( $body['account']['name'] ) : '',
		];
	}

	/**
	 * Account of the store: name, balance, contract and the links of the site (`GET /account`).
	 *
	 * @since 1.14.0
	 *
	 * @return array{name: string, status: string, balance_cents: int, contract: array{number: string, postcard: string}, urls: array{topup: string, statement: string}}|\WP_Error
	 */
	public function account() {
		$response = $this->request( 'GET', '/account' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = $response['body'];
		$account = isset( $body['account'] ) && is_array( $body['account'] ) ? $body['account'] : [];
		$contract = isset( $body['contract'] ) && is_array( $body['contract'] ) ? $body['contract'] : [];
		$urls = isset( $body['urls'] ) && is_array( $body['urls'] ) ? $body['urls'] : [];

		return [
			'name' => self::text( $account, 'name' ),
			'status' => self::text( $account, 'status' ),
			'balance_cents' => isset( $account['balance_cents'] ) ? (int) $account['balance_cents'] : 0,
			'contract' => [
				'number' => self::text( $contract, 'number' ),
				'postcard' => self::text( $contract, 'postcard' ),
			],
			'urls' => [
				'topup' => self::link( $urls, 'topup', self::appUrl( 'recarregar' ) ),
				'statement' => self::link( $urls, 'statement', self::appUrl( 'painel' ) ),
			],
		];
	}

	/**
	 * Revoke the token of the store (`DELETE /site`).
	 *
	 * @since 1.14.0
	 *
	 * @return true|\WP_Error
	 */
	public function revokeSite() {
		$response = $this->request( 'DELETE', '/site' );

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Create a prepost, paid from the balance (`POST /preposts`).
	 *
	 * The same idempotency key never pays twice: the API answers the prepost
	 * it already created for it, with `infixs_ship.replayed`.
	 *
	 * @since 1.14.0
	 *
	 * @param array  $data            Prepost, as the Correios take it.
	 * @param int    $order_id        Order, shown on the statement of the account.
	 * @param string $idempotency_key Key of the attempt, empty for none.
	 *
	 * @return array|\WP_Error Answer of the Correios, with `infixs_ship` and what was charged.
	 */
	public function createPrepost( array $data, $order_id = 0, $idempotency_key = '' ) {
		$headers = $order_id ? [ 'X-Order-Ref' => (string) absint( $order_id ) ] : [];

		if ( '' !== (string) $idempotency_key ) {
			$headers['Idempotency-Key'] = (string) $idempotency_key;
		}

		$response = $this->request( 'POST', '/preposts', $data, $headers, true, self::PREPOST_TIMEOUT );

		return is_wp_error( $response ) ? $response : $response['body'];
	}

	/**
	 * Cancel a prepost and refund it (`DELETE /preposts/{id}`).
	 *
	 * @since 1.14.0
	 *
	 * @param string $prepost_id Id of the prepost at the Correios.
	 *
	 * @return array|\WP_Error Answer of the Correios, with `infixs_ship` and what was refunded.
	 */
	public function cancelPrepost( $prepost_id ) {
		$response = $this->existingPrepostRequest( 'DELETE', '/preposts/' . rawurlencode( (string) $prepost_id ) );

		return is_wp_error( $response ) ? $response : $response['body'];
	}

	/**
	 * Read the preposts of an object (`GET /preposts?codigoObjeto=`).
	 *
	 * @since 1.14.0
	 *
	 * @param array $params Filters, as the Correios take them.
	 *
	 * @return array|\WP_Error Answer of the Correios.
	 */
	public function findPreposts( array $params ) {
		$response = $this->existingPrepostRequest( 'GET', add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $params ) ), '/preposts' ) );

		return is_wp_error( $response ) ? $response : $response['body'];
	}

	/**
	 * Print the DACE of objects of the account (`POST /preposts/dace`).
	 *
	 * @since 1.14.0
	 *
	 * @param array $data `codigosObjetos` and `tipoDace`, as the Correios take them.
	 *
	 * @return array|\WP_Error Answer of the Correios.
	 */
	public function printDace( array $data ) {
		$response = $this->existingPrepostRequest( 'POST', '/preposts/dace', $data );

		return is_wp_error( $response ) ? $response : $response['body'];
	}

	/**
	 * Send a request about a prepost the account already created.
	 *
	 * The prepost goes back to the account that paid it whatever the contract
	 * is now, so a store that disconnected the account is told to connect it
	 * again instead of being pointed at the Contrato Próprio.
	 *
	 * @since 1.14.0
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path under the API, with the query.
	 * @param array|null $body   JSON body, null for none.
	 *
	 * @return array{status: int, body: array}|\WP_Error
	 */
	private function existingPrepostRequest( $method, $path, $body = null ) {
		if ( PrepaidConnection::STATUS_DISCONNECTED === $this->connection->status() ) {
			return ContractMode::prepaidPrepostDisconnectedError();
		}

		return $this->request( $method, $path, $body );
	}

	/**
	 * Whether an answer of the API refused the token of the store, which has to be connected again.
	 *
	 * @since 1.14.0
	 *
	 * @param int    $status HTTP status.
	 * @param string $code   Code of the error the API answered.
	 *
	 * @return bool
	 */
	public static function isInvalidToken( $status, $code ) {
		return 401 === (int) $status && self::INVALID_TOKEN === $code;
	}

	/**
	 * Whether an answer of the API refused an account the Infixs blocked.
	 *
	 * @since 1.14.0
	 *
	 * @param int    $status HTTP status.
	 * @param string $code   Code of the error the API answered.
	 *
	 * @return bool
	 */
	public static function isAccountBlocked( $status, $code ) {
		return 403 === (int) $status && self::ACCOUNT_BLOCKED === $code;
	}

	/**
	 * Error of a token the API refused.
	 *
	 * @since 1.14.0
	 *
	 * @return \WP_Error
	 */
	public static function reconnectError() {
		return new \WP_Error(
			'prepaid_reconnect',
			__( 'A conexão com a conta Infixs Ship expirou ou foi revogada. Reconecte a conta em Configurações > Contrato.', 'infixs-correios-automatico' ),
			[ 'status' => 401 ]
		);
	}

	/**
	 * Send a request to the API.
	 *
	 * @since 1.14.0
	 *
	 * @param string     $method        HTTP method.
	 * @param string     $path          Path under the API, with the query.
	 * @param array|null $body          JSON body, null for none.
	 * @param array      $headers       Extra headers.
	 * @param bool       $authenticated Whether the route takes the token of the store.
	 * @param int        $timeout       Seconds to wait for the API.
	 *
	 * @return array{status: int, body: array}|\WP_Error
	 */
	private function request( $method, $path, $body = null, $headers = [], $authenticated = true, $timeout = self::TIMEOUT ) {
		$connection = $this->connection->get();

		if ( $authenticated && PrepaidConnection::STATUS_RECONNECT === $connection['status'] ) {
			return self::reconnectError();
		}

		if ( $authenticated && PrepaidConnection::STATUS_CONNECTED !== $connection['status'] ) {
			return ContractMode::notConnectedError();
		}

		$headers = array_merge( [
			'Accept' => 'application/json',
			'Content-Type' => 'application/json',
			'X-Site-Url' => home_url(),
		], $headers );

		if ( $authenticated ) {
			$headers['Authorization'] = 'Bearer ' . $connection['token'];
		}

		$url = self::apiUrl();
		$args = [
			'method' => $method,
			'timeout' => (int) $timeout,
			'headers' => $headers,
			'body' => null === $body ? null : wp_json_encode( $body ),
		];

		/*
		 * An address set by the developer may be a local API, which the safe
		 * request refuses.
		 */
		$response = self::API_URL === $url ? wp_safe_remote_request( $url . $path, $args ) : wp_remote_request( $url . $path, $args );

		if ( is_wp_error( $response ) ) {
			Log::notice( 'Infixs Ship: a API não respondeu.', [ 'method' => $method, 'path' => strtok( $path, '?' ) ] );

			return new \WP_Error( 'prepaid_unreachable', __( 'A Infixs Ship não respondeu. Tente de novo em instantes.', 'infixs-correios-automatico' ), [ 'status' => 502 ] );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$decoded = is_array( $decoded ) ? $decoded : [];

		if ( $status >= 200 && $status < 300 ) {
			return [
				'status' => $status,
				'body' => $decoded,
			];
		}

		return $this->error( $status, $decoded, $method, $path, $authenticated );
	}

	/**
	 * Turn a refusal of the API into an error the merchant can read.
	 *
	 * @since 1.14.0
	 *
	 * @param int    $status        HTTP status.
	 * @param array  $body          Decoded body.
	 * @param string $method        HTTP method.
	 * @param string $path          Path under the API.
	 * @param bool   $authenticated Whether the route took the token of the store.
	 *
	 * @return \WP_Error
	 */
	private function error( $status, array $body, $method, $path, $authenticated ) {
		$error = isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : null;
		$code = $error && isset( $error['code'] ) && is_string( $error['code'] ) ? $error['code'] : '';
		$message = $error && isset( $error['message'] ) && $this->is_usable_message( $error['message'] ) ? trim( $error['message'] ) : '';

		Log::notice( 'Infixs Ship: a API recusou o pedido.', [
			'method' => $method,
			'path' => strtok( $path, '?' ),
			'status' => $status,
			'error' => $code,
		] );

		if ( $authenticated && self::isInvalidToken( $status, $code ) ) {
			$this->connection->markReconnect();

			return self::reconnectError();
		}

		if ( 402 === $status || 'insufficient_balance' === $code ) {
			return $this->insufficientBalanceError( $error ? $error : [] );
		}

		if ( 409 === $status && self::PREPOST_IN_PROGRESS === $code ) {
			return new \WP_Error( 'prepaid_prepost_in_progress', __( 'A etiqueta ainda está sendo gerada na Infixs Ship. Aguarde alguns segundos e tente de novo.', 'infixs-correios-automatico' ), [ 'status' => 409 ] );
		}

		if ( self::isAccountBlocked( $status, $code ) ) {
			return new \WP_Error( 'prepaid_account_blocked', '' !== $message ? $message : __( 'A conta Infixs Ship está bloqueada. Fale com o suporte da Infixs.', 'infixs-correios-automatico' ), [ 'status' => 403 ] );
		}

		if ( $error ) {
			return new \WP_Error( '' !== $code ? 'prepaid_' . $code : 'prepaid_error', '' !== $message ? $message : __( 'A Infixs Ship recusou o pedido.', 'infixs-correios-automatico' ), [ 'status' => $status ] );
		}

		return new \WP_Error( 'http_error', $this->extract_error_message( $body ), [ 'status' => $status ] );
	}

	/**
	 * Error of a prepost the balance does not cover, with what to do about it.
	 *
	 * @since 1.14.0
	 *
	 * @param array $error Error the API answered.
	 *
	 * @return \WP_Error Carries `balance_cents`, `price_cents` and `topup_url` in its data.
	 */
	private function insufficientBalanceError( array $error ) {
		$balance = isset( $error['balance_cents'] ) ? (int) $error['balance_cents'] : ( isset( $error['balance'] ) ? (int) $error['balance'] : null );
		$price = isset( $error['price_cents'] ) ? (int) $error['price_cents'] : ( isset( $error['price'] ) ? (int) $error['price'] : null );
		$topup_url = self::link( $error, 'topup_url', self::appUrl( 'recarregar' ) );

		if ( null !== $balance && null !== $price ) {
			/* translators: 1: price of the label, 2: balance of the account, 3: address to add balance. */
			$message = sprintf( __( 'Saldo insuficiente na conta Infixs Ship: a etiqueta custa %1$s e o saldo é %2$s. Adicione saldo em %3$s e tente de novo.', 'infixs-correios-automatico' ), PrepaidMoney::format( $price ), PrepaidMoney::format( $balance ), $topup_url );
		} else {
			/* translators: %s: address to add balance. */
			$message = sprintf( __( 'Saldo insuficiente na conta Infixs Ship. Adicione saldo em %s e tente de novo.', 'infixs-correios-automatico' ), $topup_url );
		}

		if ( null !== $balance ) {
			$this->connection->updateBalance( $balance );
		}

		return new \WP_Error( 'prepaid_insufficient_balance', $message, [
			'status' => 402,
			'balance_cents' => $balance,
			'price_cents' => $price,
			'topup_url' => $topup_url,
		] );
	}

	/**
	 * Text field of an answer.
	 *
	 * @since 1.14.0
	 *
	 * @param array  $data Answer.
	 * @param string $key  Field.
	 *
	 * @return string
	 */
	private static function text( array $data, $key ) {
		return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? sanitize_text_field( (string) $data[ $key ] ) : '';
	}

	/**
	 * Link of an answer, when it points at the site of the account.
	 *
	 * @since 1.14.0
	 *
	 * @param array  $data     Answer.
	 * @param string $key      Field.
	 * @param string $fallback Link used otherwise.
	 *
	 * @return string
	 */
	private static function link( array $data, $key, $fallback ) {
		$url = isset( $data[ $key ] ) && is_string( $data[ $key ] ) ? esc_url_raw( $data[ $key ] ) : '';

		return '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( self::appUrl(), PHP_URL_HOST ) ? $url : $fallback;
	}
}
