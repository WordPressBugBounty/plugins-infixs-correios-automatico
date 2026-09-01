<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

defined( 'ABSPATH' ) || exit;

/**
 * Answers the questions about an order that only a marketplace can answer.
 *
 * Everything the base plugin does with a vendor — scoping an order, signing a
 * prepost, building a sender — is written once against this contract, so a new
 * marketplace only has to say who owns what and where its stores live.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
abstract class MarketplaceAdapter {

	/**
	 * Get the identifier of this marketplace.
	 *
	 * @return string
	 */
	abstract public function getSlug();

	/**
	 * Check whether this marketplace owns an order.
	 *
	 * Both extensions can be installed at once, so every operation asks this
	 * before using an adapter: handing a Dokan order to the WCFM adapter would
	 * resolve no vendor and quietly build one label out of everybody's items.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return bool
	 */
	abstract public function ownsOrder( $order );

	/**
	 * Get every vendor that has items on an order.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return int[]
	 */
	abstract public function getOrderVendorIds( $order );

	/**
	 * Get the vendor a shipping line belongs to.
	 *
	 * @param \WC_Order $order            Order.
	 * @param int       $shipping_item_id Shipping line item ID.
	 *
	 * @return int Vendor ID, zero when the line has no vendor.
	 */
	abstract public function getShippingItemVendorId( $order, $shipping_item_id );

	/**
	 * Get the shipping line of a vendor on an order.
	 *
	 * @param \WC_Order $order     Order.
	 * @param int       $vendor_id Vendor ID.
	 *
	 * @return \WC_Order_Item_Shipping|null
	 */
	abstract public function getOrderShippingItem( $order, $vendor_id );

	/**
	 * Get the order items belonging to a vendor.
	 *
	 * @param \WC_Order $order     Order.
	 * @param int       $vendor_id Vendor ID.
	 *
	 * @return \WC_Order_Item_Product[] Keyed by item ID.
	 */
	abstract public function getOrderItemsByVendor( $order, $vendor_id );

	/**
	 * Get the raw store data of a vendor.
	 *
	 * The base plugin turns this into a Correios sender; the marketplace only
	 * reports what its own store profile holds.
	 *
	 * @param int $vendor_id Vendor ID.
	 *
	 * @return array{
	 * 		shop_name: string,
	 * 		owner_name: string,
	 * 		email: string,
	 * 		phone: string,
	 * 		address: array{
	 * 			street_1: string,
	 * 			street_2: string,
	 * 			number: string,
	 * 			neighborhood: string,
	 * 			city: string,
	 * 			state: string,
	 * 			zip: string,
	 * 			country: string
	 * 		}
	 * }
	 */
	abstract public function getVendorStoreData( $vendor_id );

	/**
	 * Get the vendor the current request speaks for.
	 *
	 * Not the same as the logged in user on every marketplace: WCFM resolves
	 * staff and group accounts to the store owner.
	 *
	 * @return int Vendor ID, zero when the current user is not a vendor.
	 */
	abstract public function getCurrentVendorId();

	/**
	 * Resolve the vendor an order operation belongs to.
	 *
	 * An explicit vendor always wins, and is only honoured when the order really
	 * holds items of it. Otherwise only a single vendor order can be resolved
	 * without ambiguity.
	 *
	 * @param int $order_id Order ID.
	 * @param int $explicit Vendor named by the caller, zero when it named none.
	 *
	 * @return int Vendor ID, zero when it should not be scoped.
	 */
	public function resolveOrderVendorId( $order_id, $explicit = 0 ) {
		$explicit = (int) $explicit;

		if ( $explicit ) {
			return $this->orderBelongsToVendor( $order_id, $explicit ) ? $explicit : 0;
		}

		$vendor_ids = $this->getOrderVendorIdsById( $order_id );

		return count( $vendor_ids ) === 1 ? (int) reset( $vendor_ids ) : 0;
	}

	/**
	 * Check whether an order holds items of a vendor.
	 *
	 * @param int $order_id  Order ID.
	 * @param int $vendor_id Vendor ID.
	 *
	 * @return bool
	 */
	public function orderBelongsToVendor( $order_id, $vendor_id ) {
		$vendor_id = (int) $vendor_id;

		if ( ! $order_id || ! $vendor_id ) {
			return false;
		}

		return in_array( $vendor_id, $this->getOrderVendorIdsById( $order_id ), true );
	}

	/**
	 * Keep only the orders a vendor has items on.
	 *
	 * Overridden by marketplaces that can answer the whole list in one query.
	 *
	 * @param int[] $order_ids Order IDs.
	 * @param int   $vendor_id Vendor ID.
	 *
	 * @return int[]
	 */
	public function filterOrdersByVendor( $order_ids, $vendor_id ) {
		$order_ids = array_filter( array_map( 'absint', (array) $order_ids ) );
		$vendor_id = (int) $vendor_id;

		if ( ! $order_ids || ! $vendor_id ) {
			return [];
		}

		$owned = [];

		foreach ( $order_ids as $order_id ) {
			if ( $this->orderBelongsToVendor( $order_id, $vendor_id ) ) {
				$owned[] = $order_id;
			}
		}

		return $owned;
	}

	/**
	 * Get every vendor of an order from its ID.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return int[]
	 */
	protected function getOrderVendorIdsById( $order_id ) {
		$order = $order_id ? wc_get_order( $order_id ) : false;

		return $order ? $this->getOrderVendorIds( $order ) : [];
	}
}
