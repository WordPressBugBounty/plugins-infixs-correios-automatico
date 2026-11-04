<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

defined( 'ABSPATH' ) || exit;

/**
 * Connection of the store to its Infixs Ship account.
 *
 * Kept in an option of its own, never autoloaded, apart from the settings of
 * the plugin: the settings go to the dashboard, and the token of the
 * connection never does. The contract of the Infixs printed on the labels
 * outlives a disconnection, so the labels of the preposts already paid keep
 * printing it.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidConnection {

	/**
	 * Option holding the connection.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const OPTION = 'infixs_correios_automatico_prepaid';

	/**
	 * Transient holding the account as the API last answered it.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const ACCOUNT_TRANSIENT = 'infixs_correios_automatico_prepaid_account';

	/**
	 * Seconds the account and its balance are reused before being read again.
	 *
	 * @since 1.14.0
	 *
	 * @var int
	 */
	const ACCOUNT_TTL = 60;

	/**
	 * The store has no account connected.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const STATUS_DISCONNECTED = 'disconnected';

	/**
	 * The store is connected and the token works.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const STATUS_CONNECTED = 'connected';

	/**
	 * The API refused the token: revoked in the account, or of another site.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const STATUS_RECONNECT = 'reconnect';

	/**
	 * Connection as stored, with every field.
	 *
	 * @since 1.14.0
	 *
	 * @return array{token: string, status: string, account_name: string, site_id: string, contract: array{number: string, postcard: string}, connected_at: int}
	 */
	public function get() {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		$contract = isset( $stored['contract'] ) && is_array( $stored['contract'] ) ? $stored['contract'] : [];
		$status = isset( $stored['status'] ) ? (string) $stored['status'] : '';
		$token = isset( $stored['token'] ) ? (string) $stored['token'] : '';

		if ( '' === $token || ! in_array( $status, [ self::STATUS_CONNECTED, self::STATUS_RECONNECT ], true ) ) {
			$status = self::STATUS_DISCONNECTED;
		}

		return [
			'token' => $token,
			'status' => $status,
			'account_name' => isset( $stored['account_name'] ) ? (string) $stored['account_name'] : '',
			'site_id' => isset( $stored['site_id'] ) ? (string) $stored['site_id'] : '',
			'contract' => [
				'number' => isset( $contract['number'] ) ? (string) $contract['number'] : '',
				'postcard' => isset( $contract['postcard'] ) ? (string) $contract['postcard'] : '',
			],
			'connected_at' => isset( $stored['connected_at'] ) ? (int) $stored['connected_at'] : 0,
		];
	}

	/**
	 * State of the connection: one of the STATUS_ constants.
	 *
	 * @since 1.14.0
	 *
	 * @return string
	 */
	public function status() {
		return $this->get()['status'];
	}

	/**
	 * Whether the store is connected with a token the API takes.
	 *
	 * @since 1.14.0
	 *
	 * @return bool
	 */
	public function isConnected() {
		return self::STATUS_CONNECTED === $this->status();
	}

	/**
	 * Token of the connection, empty when there is none.
	 *
	 * Only the client of the API reads it: no answer of the plugin carries it.
	 *
	 * @since 1.14.0
	 *
	 * @return string
	 */
	public function token() {
		return $this->get()['token'];
	}

	/**
	 * Contract of the Infixs the labels of the paid preposts print.
	 *
	 * @since 1.14.0
	 *
	 * @return array{number: string, postcard: string}
	 */
	public function contract() {
		return $this->get()['contract'];
	}

	/**
	 * Keep a new connection.
	 *
	 * @since 1.14.0
	 *
	 * @param string $token        Token the API issued to the store.
	 * @param string $site_id      Store, as the API knows it.
	 * @param string $account_name Name of the account.
	 *
	 * @return void
	 */
	public function connect( $token, $site_id, $account_name ) {
		$this->save( array_merge( $this->get(), [
			'token' => (string) $token,
			'status' => self::STATUS_CONNECTED,
			'site_id' => (string) $site_id,
			'account_name' => (string) $account_name,
			'connected_at' => time(),
		] ) );

		delete_transient( self::ACCOUNT_TRANSIENT );
	}

	/**
	 * Remember the account the API answered: its name and the contract, kept,
	 * and the balance, cached for a minute.
	 *
	 * @since 1.14.0
	 *
	 * @param array{name: string, status: string, balance_cents: int, contract: array{number: string, postcard: string}, urls: array{topup: string, statement: string}} $account Account.
	 *
	 * @return void
	 */
	public function rememberAccount( array $account ) {
		$connection = $this->get();

		if ( self::STATUS_DISCONNECTED === $connection['status'] ) {
			return;
		}

		$connection['account_name'] = '' !== $account['name'] ? $account['name'] : $connection['account_name'];

		if ( '' !== $account['contract']['number'] || '' !== $account['contract']['postcard'] ) {
			$connection['contract'] = $account['contract'];
		}

		$this->save( $connection );

		set_transient( self::ACCOUNT_TRANSIENT, $account, self::ACCOUNT_TTL );
	}

	/**
	 * Account as the API answered it in the last minute, null when it has to be read again.
	 *
	 * @since 1.14.0
	 *
	 * @return array|null
	 */
	public function cachedAccount() {
		$account = get_transient( self::ACCOUNT_TRANSIENT );

		return is_array( $account ) && isset( $account['balance_cents'] ) ? $account : null;
	}

	/**
	 * Update the balance of the cached account after the API debited or refunded it.
	 *
	 * @since 1.14.0
	 *
	 * @param int $balance_cents Balance after the operation.
	 *
	 * @return void
	 */
	public function updateBalance( $balance_cents ) {
		$account = $this->cachedAccount();

		if ( ! $account ) {
			return;
		}

		$account['balance_cents'] = (int) $balance_cents;

		set_transient( self::ACCOUNT_TRANSIENT, $account, self::ACCOUNT_TTL );
	}

	/**
	 * Tell the merchant to connect again, after the API refused the token.
	 *
	 * @since 1.14.0
	 *
	 * @return void
	 */
	public function markReconnect() {
		$connection = $this->get();

		if ( self::STATUS_CONNECTED !== $connection['status'] ) {
			return;
		}

		$connection['status'] = self::STATUS_RECONNECT;

		$this->save( $connection );

		delete_transient( self::ACCOUNT_TRANSIENT );
	}

	/**
	 * Forget the connection, keeping the contract the paid labels print.
	 *
	 * @since 1.14.0
	 *
	 * @return void
	 */
	public function clear() {
		$this->save( [ 'contract' => $this->contract() ] );

		delete_transient( self::ACCOUNT_TRANSIENT );
	}

	/**
	 * Write the option, never autoloaded.
	 *
	 * @since 1.14.0
	 *
	 * @param array $connection Connection.
	 *
	 * @return void
	 */
	private function save( array $connection ) {
		update_option( self::OPTION, $connection, false );
	}
}
