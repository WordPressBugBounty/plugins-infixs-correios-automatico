<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Auth;
use Infixs\CorreiosAutomatico\Services\InfixsApi;

defined( 'ABSPATH' ) || exit;

/**
 * Correios client of the Contrato pré-pago.
 *
 * Creates, cancels, reads and prints the DACE of the preposts through the
 * Infixs Ship, which posts them with the contract of the Infixs and charges
 * the balance of the account. The answers are the ones of the Correios, so
 * the code that reads them does not change. The tracking goes through the API
 * of the Infixs, as for a store without a contract. Anything else would need
 * credentials of the Correios this client does not have, and is refused here
 * instead of reaching the Correios.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidCorreiosApi extends CorreiosApi {

	/**
	 * Action fired after a prepost was paid from the balance, not when the API answered one already paid.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const CHARGED_ACTION = 'infixs_correios_automatico_prepaid_prepost_charged';

	/**
	 * Action fired after a cancelled prepost went back to the balance.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const REFUNDED_ACTION = 'infixs_correios_automatico_prepaid_prepost_refunded';

	/**
	 * Client of the Infixs Ship.
	 *
	 * @var PrepaidApi
	 */
	private $prepaidApi;

	/**
	 * Client of the API of the Infixs, for the tracking.
	 *
	 * @var InfixsApi
	 */
	private $infixsApi;

	/**
	 * Connection to the Infixs Ship.
	 *
	 * @var PrepaidConnection
	 */
	private $connection;

	/**
	 * Order the next prepost belongs to, zero for none.
	 *
	 * @var int
	 */
	private $orderId = 0;

	/**
	 * @param PrepaidApi        $prepaidApi Client of the Infixs Ship.
	 * @param InfixsApi         $infixsApi  Client of the API of the Infixs, for the tracking.
	 * @param PrepaidConnection $connection Connection to the Infixs Ship.
	 */
	public function __construct( PrepaidApi $prepaidApi, InfixsApi $infixsApi, PrepaidConnection $connection ) {
		parent::__construct( new Auth( [] ) );

		$this->prepaidApi = $prepaidApi;
		$this->infixsApi = $infixsApi;
		$this->connection = $connection;
	}

	/**
	 * Copy of the client for the prepost of an order, shown on the statement.
	 *
	 * @since 1.14.0
	 *
	 * @param int $order_id Order.
	 *
	 * @return self
	 */
	public function forOrder( $order_id ) {
		$client = clone $this;
		$client->orderId = absint( $order_id );

		return $client;
	}

	/**
	 * Create a prepost, paid from the balance.
	 *
	 * A retry of the same attempt, which the API may have finished after the
	 * plugin gave up waiting, gets the prepost already paid back instead of
	 * paying a second one; it is stored like any other, without noting the
	 * charge again.
	 *
	 * @since 1.14.0
	 *
	 * @param array $data Prepost, as the Correios take it.
	 *
	 * @return array|\WP_Error Answer of the Correios, with `infixs_ship` holding what was charged.
	 */
	public function prepostagens( $data ) {
		if ( isset( $data['logisticaReversa'] ) && 'S' === $data['logisticaReversa'] ) {
			return ContractMode::reverseNotSupportedError();
		}

		$response = $this->prepaidApi->createPrepost( (array) $data, $this->orderId, $this->idempotencyKey( (array) $data ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$charge = isset( $response[ ContractMode::PREPAID_RESPONSE_KEY ] ) && is_array( $response[ ContractMode::PREPAID_RESPONSE_KEY ] ) ? $response[ ContractMode::PREPAID_RESPONSE_KEY ] : [];
		$response[ ContractMode::PREPAID_RESPONSE_KEY ] = [
			'charged_cents' => isset( $charge['charged_cents'] ) ? (int) $charge['charged_cents'] : null,
			'balance_cents' => isset( $charge['balance_cents'] ) ? (int) $charge['balance_cents'] : null,
			'replayed' => ! empty( $charge['replayed'] ),
		];

		if ( null !== $response[ ContractMode::PREPAID_RESPONSE_KEY ]['balance_cents'] ) {
			$this->connection->updateBalance( $response[ ContractMode::PREPAID_RESPONSE_KEY ]['balance_cents'] );
		}

		/**
		 * Fires after a prepost was paid from the balance of the Infixs Ship account.
		 *
		 * Also fires when the API hands back a label it had already made and charged
		 * (`infixs_ship.replayed`), as when the first answer was lost to a timeout;
		 * listeners tell it apart by the object code.
		 *
		 * @since 1.14.0
		 *
		 * @param int      $order_id      Order of the prepost, zero when unknown.
		 * @param int|null $charged_cents What was charged, null when the API did not say.
		 * @param int|null $balance_cents Balance after the charge, null when the API did not say.
		 * @param array    $response      Answer of the Correios.
		 */
		do_action( self::CHARGED_ACTION, $this->orderId, $response[ ContractMode::PREPAID_RESPONSE_KEY ]['charged_cents'], $response[ ContractMode::PREPAID_RESPONSE_KEY ]['balance_cents'], $response );

		return $response;
	}

	/**
	 * Key that makes a retry of the same attempt replay the prepost the API
	 * already created, instead of paying a second one.
	 *
	 * The same order, with the same preposts stored and the same body, is the
	 * same attempt: a prepost stored for the order, even one cancelled later,
	 * makes the next one a new attempt.
	 *
	 * @since 1.14.0
	 *
	 * @param array $data Prepost, as the Correios take it.
	 *
	 * @return string SHA-256 in hex, empty without an order.
	 */
	public function idempotencyKey( array $data ) {
		if ( ! $this->orderId ) {
			return '';
		}

		return hash( 'sha256', implode( '|', [
			home_url(),
			$this->orderId,
			Prepost::where( 'order_id', $this->orderId )->count(),
			wp_json_encode( self::canonical( $data ) ),
		] ) );
	}

	/**
	 * Value with the keys of every map sorted, so the same body always encodes the same way.
	 *
	 * @since 1.14.0
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	private static function canonical( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( array_values( $value ) !== $value ) {
			ksort( $value, SORT_STRING );
		}

		return array_map( [ self::class, 'canonical' ], $value );
	}

	/**
	 * Cancel a prepost; the API refunds it.
	 *
	 * @since 1.14.0
	 *
	 * @param string $prepost_id Id of the prepost at the Correios.
	 *
	 * @return array|\WP_Error
	 */
	public function cancelarPrepostagem( $prepost_id ) {
		$response = $this->prepaidApi->cancelPrepost( $prepost_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$refund = isset( $response[ ContractMode::PREPAID_RESPONSE_KEY ] ) && is_array( $response[ ContractMode::PREPAID_RESPONSE_KEY ] ) ? $response[ ContractMode::PREPAID_RESPONSE_KEY ] : [];
		$balance_cents = isset( $refund['balance_cents'] ) ? (int) $refund['balance_cents'] : null;

		if ( null !== $balance_cents ) {
			$this->connection->updateBalance( $balance_cents );
		}

		if ( ! empty( $refund['refunded_cents'] ) ) {
			do_action( self::REFUNDED_ACTION, (string) $prepost_id, (int) $refund['refunded_cents'], $balance_cents );
		}

		return $response;
	}

	/**
	 * Read the preposts of an object of the account.
	 *
	 * @since 1.14.0
	 *
	 * @param array $params Filters, as the Correios take them.
	 *
	 * @return array|\WP_Error
	 */
	public function getPrepostagens( $params = [] ) {
		return $this->prepaidApi->findPreposts( (array) $params );
	}

	/**
	 * Print the DACE of objects of the account.
	 *
	 * @since 1.14.0
	 *
	 * @param array $data `codigosObjetos` and `tipoDace`.
	 *
	 * @return array|\WP_Error
	 */
	public function printDce( $data ) {
		return $this->prepaidApi->printDace( (array) $data );
	}

	/**
	 * Track an object through the API of the Infixs.
	 *
	 * @since 1.14.0
	 *
	 * @param string $code Object code.
	 *
	 * @return array|\WP_Error
	 */
	public function rastroObjeto( $code ) {
		return $this->infixsApi->getTrackingHistory( $code );
	}

	/**
	 * International preposts have no prepaid contract yet.
	 *
	 * @since 1.14.0
	 *
	 * @param array $data Packets.
	 *
	 * @return \WP_Error
	 */
	public function packages( $data ) {
		return ContractMode::packetNotSupportedError();
	}

	/**
	 * Suspending a delivery needs the contract that posted it.
	 *
	 * @since 1.14.0
	 *
	 * @param string $object_code Object code.
	 *
	 * @return \WP_Error
	 */
	public function suspenderEntrega( $object_code ) {
		return $this->unsupportedError();
	}

	/**
	 * Calls of the Correios not routed through the Infixs Ship are refused.
	 *
	 * @since 1.14.0
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $params   Query.
	 * @param array  $headers  Headers.
	 * @param bool   $retry    Retry on an expired token.
	 *
	 * @return \WP_Error
	 */
	public function authenticated_get( $endpoint, $params = [], $headers = [], $retry = true ) {
		return $this->unsupportedError();
	}

	/**
	 * Calls of the Correios not routed through the Infixs Ship are refused.
	 *
	 * @since 1.14.0
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $data     Body.
	 * @param array  $headers  Headers.
	 * @param bool   $retry    Retry on an expired token.
	 *
	 * @return \WP_Error
	 */
	protected function authenticated_post( $endpoint, $data, $headers = [], $retry = true ) {
		return $this->unsupportedError();
	}

	/**
	 * Calls of the Correios not routed through the Infixs Ship are refused.
	 *
	 * @since 1.14.0
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $params   Request arguments.
	 * @param array  $headers  Headers.
	 * @param bool   $retry    Retry on an expired token.
	 *
	 * @return \WP_Error
	 */
	protected function authenticated_delete( $endpoint, $params = [], $headers = [], $retry = true ) {
		return $this->unsupportedError();
	}

	/**
	 * The prepaid contract has no credentials of the Correios to authenticate with.
	 *
	 * @since 1.14.0
	 *
	 * @param string      $user_name   User.
	 * @param string      $access_code Access code.
	 * @param string      $postcard    Postcard.
	 * @param string|null $enviroment  Environment.
	 *
	 * @return \WP_Error
	 */
	public function auth_postcard( $user_name, $access_code, $postcard, $enviroment = null ) {
		return $this->unsupportedError();
	}

	/**
	 * Error of an operation the Contrato pré-pago does not offer.
	 *
	 * @since 1.14.0
	 *
	 * @return \WP_Error
	 */
	private function unsupportedError() {
		return new \WP_Error(
			'prepaid_not_supported',
			__( 'Esta operação não está disponível para as pré-postagens do Contrato pré-pago.', 'infixs-correios-automatico' ),
			[ 'status' => 422 ]
		);
	}
}
