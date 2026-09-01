<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

use Infixs\CorreiosAutomatico\Models\Prepost;

defined( 'ABSPATH' ) || exit;

/**
 * Records which vendor a tracking code belongs to.
 *
 * On a shared order every vendor would otherwise see the codes of the others,
 * and a sync would query them all on the store contract.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class VendorTrackingService {

	public function __construct() {
		add_filter( 'infixs_correios_automatico_tracking_vendor_id', [ $this, 'vendor_id' ], 10, 4 );
	}

	/**
	 * Resolve the vendor of a tracking code being recorded.
	 *
	 * Prefers the prepost that issued the object, then falls back to a single
	 * vendor order. A code added by hand to a shared order stays unassigned and
	 * therefore visible to everyone on it, which is the safe default.
	 *
	 * @param int|null $vendor_id Vendor already resolved by the caller.
	 * @param int      $order_id  Order ID.
	 * @param string   $code      Tracking code.
	 * @param array    $data      Extra data given to the tracking service.
	 *
	 * @return int|null
	 */
	public function vendor_id( $vendor_id, $order_id, $code, $data = [] ) {
		if ( $vendor_id ) {
			return $vendor_id;
		}

		$order = $order_id ? wc_get_order( $order_id ) : false;
		$adapter = $order ? MarketplaceRegistry::forOrder( $order ) : null;

		if ( ! $adapter ) {
			return $vendor_id;
		}

		if ( ! empty( $data['shipping_item_id'] ) ) {
			$line_vendor_id = $adapter->getShippingItemVendorId( $order, $data['shipping_item_id'] );

			if ( $line_vendor_id ) {
				return $line_vendor_id;
			}
		}

		$prepost = Prepost::where( 'object_code', $code )->first();

		if ( $prepost && ! empty( $prepost->vendor_id ) ) {
			return (int) $prepost->vendor_id;
		}

		$resolved = $adapter->resolveOrderVendorId( $order_id );

		return $resolved ? $resolved : null;
	}
}
