<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Services\Correios\VendorApiFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Acts on an existing Correios object through the contract that issued it.
 *
 * Cancelling, syncing, printing the DCe or tracking an object created on a
 * vendor contract only works when the request is signed with that same
 * contract, so the vendor is resolved back from the object itself — no
 * marketplace lookup involved, only the rows the base plugin already wrote.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class VendorObjectApi {

	/**
	 * Vendor already resolved per object code in this request.
	 *
	 * @var array<string, int>
	 */
	private $resolved = [];

	public function __construct() {
		add_filter( 'infixs_correios_automatico_object_correios_api', [ $this, 'object_api' ], 10, 3 );
		add_filter( 'infixs_correios_automatico_object_trackings_batches', [ $this, 'batches' ], 10, 2 );
	}

	/**
	 * Swap in the client of the vendor that owns the object.
	 *
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi $correios_api Store wide client.
	 * @param string                                                   $object_code  Correios object code.
	 * @param mixed                                                    $source       Model the code came from.
	 *
	 * @return \Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi
	 */
	public function object_api( $correios_api, $object_code, $source = null ) {
		if ( ! MarketplaceRegistry::hasAny() ) {
			return $correios_api;
		}

		return VendorApiFactory::forVendor( $this->resolveVendor( $object_code, $source ), $correios_api );
	}

	/**
	 * Group tracking codes by the contract that has to query them.
	 *
	 * @param array    $batches        Store wide single batch.
	 * @param string[] $tracking_codes Codes being queried.
	 *
	 * @return array
	 */
	public function batches( $batches, $tracking_codes ) {
		if ( ! MarketplaceRegistry::hasAny() ) {
			return $batches;
		}

		$grouped = [];

		foreach ( $tracking_codes as $code ) {
			$grouped[ $this->resolveVendor( $code ) ][] = $code;
		}

		if ( count( $grouped ) <= 1 ) {
			return $batches;
		}

		$result = [];

		foreach ( $grouped as $vendor_id => $codes ) {
			$result[] = [
				'api' => $vendor_id ? VendorApiFactory::fromVendor( $vendor_id ) : null,
				'codes' => $codes,
			];
		}

		return $result;
	}

	/**
	 * Resolve the vendor an object belongs to.
	 *
	 * Prefers what the caller already has in hand, then the prepost that issued
	 * the object, then the tracking code row.
	 *
	 * @param string $object_code Correios object code.
	 * @param mixed  $source      Model the code came from.
	 *
	 * @return int Vendor ID, zero when it belongs to the store.
	 */
	private function resolveVendor( $object_code, $source = null ) {
		if ( $source && ! empty( $source->vendor_id ) ) {
			return (int) $source->vendor_id;
		}

		$object_code = (string) $object_code;

		if ( $object_code === '' ) {
			return 0;
		}

		if ( isset( $this->resolved[ $object_code ] ) ) {
			return $this->resolved[ $object_code ];
		}

		$vendor_id = 0;

		$prepost = Prepost::where( 'object_code', $object_code )->first();

		if ( $prepost && ! empty( $prepost->vendor_id ) ) {
			$vendor_id = (int) $prepost->vendor_id;
		}

		if ( ! $vendor_id ) {
			$tracking = TrackingCode::where( 'code', $object_code )->first();

			if ( $tracking && ! empty( $tracking->vendor_id ) ) {
				$vendor_id = (int) $tracking->vendor_id;
			}
		}

		$this->resolved[ $object_code ] = $vendor_id;

		return $vendor_id;
	}
}
