<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

use Infixs\CorreiosAutomatico\Repositories\ConfigRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Which contract the store posts with.
 *
 * - Contrato pré-pago, the default: connected to an Infixs Ship account, the
 *   preposts go through the contract of the Infixs and are paid from the
 *   balance of the account. Not connected, the store quotes and tracks
 *   through the API of the Infixs, without preposts, as a store without a
 *   contract always did.
 * - Contrato Próprio: the credentials of the store's own contract.
 *
 * `auth.active` keeps meaning "the own contract is on": the PRO, the
 * marketplace extensions and older code read it.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class ContractMode {

	/**
	 * Contrato Próprio.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const OWN = 'own';

	/**
	 * Contrato pré-pago, through the Infixs Ship.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const PREPAID = 'prepaid';

	/**
	 * Key of an answer to a prepost that only the Infixs Ship sends, with what was charged.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const PREPAID_RESPONSE_KEY = 'infixs_ship';

	/**
	 * Settings of the plugin.
	 *
	 * @var ConfigRepository
	 */
	private $config;

	/**
	 * Connection to the Infixs Ship.
	 *
	 * @var PrepaidConnection
	 */
	private $connection;

	/**
	 * @param ConfigRepository  $config     Settings of the plugin.
	 * @param PrepaidConnection $connection Connection to the Infixs Ship.
	 */
	public function __construct( ConfigRepository $config, PrepaidConnection $connection ) {
		$this->config = $config;
		$this->connection = $connection;
	}

	/**
	 * Mode chosen on the Contrato page.
	 *
	 * The own contract on is always the Contrato Próprio: choosing the
	 * Contrato pré-pago turns it off, and whatever turns it on — the Contrato
	 * page, a contract imported from another plugin — means the store posts
	 * with it. Otherwise the mode saved counts, and without one, the Contrato
	 * pré-pago, the default.
	 *
	 * @since 1.14.0
	 *
	 * @return string self::OWN or self::PREPAID.
	 */
	public function mode() {
		if ( $this->config->boolean( 'auth.active' ) ) {
			return self::OWN;
		}

		return self::OWN === $this->config->get( 'auth.mode' ) ? self::OWN : self::PREPAID;
	}

	/**
	 * Whether the store chose the Contrato Próprio.
	 *
	 * @since 1.14.0
	 *
	 * @return bool
	 */
	public function isOwn() {
		return self::OWN === $this->mode();
	}

	/**
	 * Whether the store chose the Contrato pré-pago.
	 *
	 * @since 1.14.0
	 *
	 * @return bool
	 */
	public function isPrepaid() {
		return self::PREPAID === $this->mode();
	}

	/**
	 * Whether the store is on the Contrato pré-pago with an account connected.
	 *
	 * @since 1.14.0
	 *
	 * @return bool
	 */
	public function prepaidConnected() {
		return $this->isPrepaid() && $this->connection->isConnected();
	}

	/**
	 * Whether the store can create a prepost now.
	 *
	 * @since 1.14.0
	 *
	 * @return bool
	 */
	public function canPrepost() {
		return $this->isOwn() ? $this->config->boolean( 'auth.active' ) : $this->prepaidConnected();
	}

	/**
	 * Whether the quotes use the services of a contract (03298, 03220…).
	 *
	 * @since 1.14.0
	 *
	 * @return bool
	 */
	public function quotesWithContract() {
		return $this->config->boolean( 'auth.active' ) || $this->prepaidConnected();
	}

	/**
	 * Why a prepost cannot be created now, null when it can.
	 *
	 * @since 1.14.0
	 *
	 * @param bool $reverse Whether it is a reverse prepost (devolução).
	 *
	 * @return \WP_Error|null
	 */
	public function prepostError( $reverse = false ) {
		if ( $this->isOwn() ) {
			return $this->config->boolean( 'auth.active' ) ? null : new \WP_Error(
				'auth_not_active',
				__( 'O Contrato Próprio não está ativo, acesse as configurações para ativá-lo. Menu WooCommerce -> Correios Automático -> Configurações -> Contrato.', 'infixs-correios-automatico' ),
				[ 'status' => 400 ]
			);
		}

		if ( $reverse ) {
			return self::reverseNotSupportedError();
		}

		if ( PrepaidConnection::STATUS_RECONNECT === $this->connection->status() ) {
			return PrepaidApi::reconnectError();
		}

		return $this->connection->isConnected() ? null : self::notConnectedError();
	}

	/**
	 * Contract an answer to a prepost came from.
	 *
	 * @since 1.14.0
	 *
	 * @param mixed $response Answer to the prepost.
	 *
	 * @return string self::PREPAID when the Infixs Ship answered it, self::OWN otherwise.
	 */
	public static function sourceOf( $response ) {
		return is_array( $response ) && isset( $response[ self::PREPAID_RESPONSE_KEY ] ) && is_array( $response[ self::PREPAID_RESPONSE_KEY ] ) ? self::PREPAID : self::OWN;
	}

	/**
	 * Error of a store on the Contrato pré-pago without an account connected.
	 *
	 * @since 1.14.0
	 *
	 * @return \WP_Error
	 */
	public static function notConnectedError() {
		return new \WP_Error(
			'prepaid_not_connected',
			__( 'Conecte sua conta Infixs Ship ou ative o Contrato Próprio.', 'infixs-correios-automatico' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Error of a prepost the Infixs Ship created, after the store disconnected the account.
	 *
	 * Only the account that paid the prepost cancels, syncs or prints it, so
	 * the store is told to connect it again, not to post with another contract.
	 *
	 * @since 1.14.0
	 *
	 * @return \WP_Error
	 */
	public static function prepaidPrepostDisconnectedError() {
		return new \WP_Error(
			'prepaid_not_connected',
			__( 'Esta pré-postagem saiu pelo Contrato pré-pago. Conecte a conta Infixs Ship de novo em Configurações > Contrato para cancelar, sincronizar ou imprimir a DACE dela.', 'infixs-correios-automatico' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Error of a reverse prepost on the Contrato pré-pago, which has none yet.
	 *
	 * @since 1.14.0
	 *
	 * @return \WP_Error
	 */
	public static function reverseNotSupportedError() {
		return new \WP_Error(
			'prepaid_reverse_not_supported',
			__( 'A devolução (logística reversa) ainda não está disponível no Contrato pré-pago. Para gerar devoluções, ative o Contrato Próprio.', 'infixs-correios-automatico' ),
			[ 'status' => 422 ]
		);
	}

	/**
	 * Error of an international prepost (Packet) on the Contrato pré-pago, which has none yet.
	 *
	 * @since 1.14.0
	 *
	 * @return \WP_Error
	 */
	public static function packetNotSupportedError() {
		return new \WP_Error(
			'prepaid_packet_not_supported',
			__( 'O envio internacional (Packet) ainda não está disponível no Contrato pré-pago. Para enviar ao exterior, ative o Contrato Próprio.', 'infixs-correios-automatico' ),
			[ 'status' => 422 ]
		);
	}
}
