<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the preposts of the Contrato pré-pago through the Infixs Ship.
 *
 * A new prepost goes through it while the store is on the Contrato pré-pago.
 * A prepost that already exists goes back to the contract that created it,
 * whatever the mode is now: one paid from the balance is cancelled, and
 * refunded, through the Infixs Ship even after the store moved to its own
 * contract.
 *
 * Runs after the marketplace layer, and leaves alone a client it swapped in:
 * a vendor with an own contract posts with it.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidClientResolver {

	/**
	 * Priority of the filters, after the one of the marketplace layer.
	 *
	 * @since 1.14.0
	 *
	 * @var int
	 */
	const PRIORITY = 20;

	/**
	 * Which contract the store posts with.
	 *
	 * @var ContractMode
	 */
	private $contractMode;

	/**
	 * Client of the Contrato pré-pago.
	 *
	 * @var PrepaidCorreiosApi
	 */
	private $prepaidClient;

	/**
	 * Client of the own contract of the store.
	 *
	 * @var CorreiosApi
	 */
	private $storeClient;

	/**
	 * Contract of each object already looked up in this request.
	 *
	 * @var array<string, string>
	 */
	private $sources = [];

	/**
	 * @param ContractMode       $contractMode  Which contract the store posts with.
	 * @param PrepaidCorreiosApi $prepaidClient Client of the Contrato pré-pago.
	 * @param CorreiosApi        $storeClient   Client of the own contract of the store.
	 */
	public function __construct( ContractMode $contractMode, PrepaidCorreiosApi $prepaidClient, CorreiosApi $storeClient ) {
		$this->contractMode = $contractMode;
		$this->prepaidClient = $prepaidClient;
		$this->storeClient = $storeClient;
	}

	/**
	 * Hook the choice of the client.
	 *
	 * @since 1.14.0
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'infixs_correios_automatico_prepost_correios_api', [ $this, 'prepostApi' ], self::PRIORITY, 2 );
		add_filter( 'infixs_correios_automatico_object_correios_api', [ $this, 'objectApi' ], self::PRIORITY, 3 );
	}

	/**
	 * Client of a new prepost.
	 *
	 * @since 1.14.0
	 *
	 * @param CorreiosApi                                                        $client  Client chosen so far.
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost|null $prepost Prepost.
	 *
	 * @return CorreiosApi
	 */
	public function prepostApi( $client, $prepost = null ) {
		if ( $client !== $this->storeClient || ! $this->contractMode->isPrepaid() ) {
			return $client;
		}

		return $this->prepaidClient->forOrder( $prepost && method_exists( $prepost, 'getOrderId' ) ? (int) $prepost->getOrderId() : 0 );
	}

	/**
	 * Client of an object that already exists.
	 *
	 * @since 1.14.0
	 *
	 * @param CorreiosApi $client      Client chosen so far.
	 * @param string      $object_code Object code, or the id of the prepost when cancelling.
	 * @param mixed       $source      Prepost or tracking code the code came from.
	 *
	 * @return CorreiosApi
	 */
	public function objectApi( $client, $object_code, $source = null ) {
		if ( $client !== $this->storeClient ) {
			return $client;
		}

		return ContractMode::PREPAID === $this->sourceOf( (string) $object_code, $source ) ? $this->prepaidClient : $client;
	}

	/**
	 * Contract that created an object.
	 *
	 * @since 1.14.0
	 *
	 * @param string $object_code Object code, or the id of the prepost.
	 * @param mixed  $source      Prepost or tracking code the code came from.
	 *
	 * @return string ContractMode::OWN or ContractMode::PREPAID.
	 */
	private function sourceOf( $object_code, $source ) {
		if ( $source instanceof Prepost ) {
			return self::sourceOfPrepost( $source );
		}

		$code = $source instanceof TrackingCode ? (string) $source->code : $object_code;

		if ( '' === $code ) {
			return ContractMode::OWN;
		}

		if ( ! isset( $this->sources[ $code ] ) ) {
			$prepost = Prepost::where( 'object_code', $code )->orderBy( 'id', 'desc' )->limit( 1 )->first();

			if ( ! $prepost && ! $source instanceof TrackingCode ) {
				$prepost = Prepost::where( 'external_id', $code )->orderBy( 'id', 'desc' )->limit( 1 )->first();
			}

			$this->sources[ $code ] = $prepost ? self::sourceOfPrepost( $prepost ) : ContractMode::OWN;
		}

		return $this->sources[ $code ];
	}

	/**
	 * Contract that created a prepost: the own one for a row older than the column.
	 *
	 * @since 1.14.0
	 *
	 * @param Prepost $prepost Prepost.
	 *
	 * @return string
	 */
	private static function sourceOfPrepost( Prepost $prepost ) {
		return isset( $prepost->contract_source ) && ContractMode::PREPAID === $prepost->contract_source ? ContractMode::PREPAID : ContractMode::OWN;
	}
}
