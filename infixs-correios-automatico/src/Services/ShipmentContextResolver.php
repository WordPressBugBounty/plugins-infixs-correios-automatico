<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Marketplace\MarketplaceRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Splits an order into the shipments the carrier receives separately.
 *
 * Printing, preposting and returning all act on one shipment at a time: a
 * marketplace freight posted by its own vendor, or a volume the plugin broke a
 * shipment into.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class ShipmentContextResolver {

	/**
	 * Split an order into the shipments that have to be handled separately.
	 *
	 * A marketplace order carries one shipping line per vendor, each posted on
	 * its own contract and from its own address, so printing it whole would put
	 * one vendor's package under another vendor's label.
	 *
	 * A caller that named a line gets that shipment and nothing else. The vendor
	 * comes from the resolved scope, which knows it even when the caller only
	 * said who is logged in, and it only ever lets through the freights of that
	 * vendor — what keeps a vendor from receiving the labels of the others. A
	 * vendor shipping in volumes still gets one label per box.
	 *
	 * A store without a marketplace is left alone even when it does have more
	 * than one freight on the order: there the extra lines are one shipment the
	 * store split, not one shipment per seller. The exception is a shipment the
	 * plugin itself broke into volumes — each volume is a box of its own and has
	 * to be printed on a label of its own.
	 *
	 * @since 1.10.0 In LabelService.
	 * @since 1.11.0 Moved here.
	 *
	 * @param int   $order_id Order to split.
	 * @param array $context  Caller context.
	 *
	 * @return array[] One context per shipment.
	 */
	public function resolveShipmentContexts( $order_id, $context ) {
		$order = Order::fromId( $order_id );

		if ( ! $order ) {
			return [ $context ];
		}

		if ( ! MarketplaceRegistry::forOrder( $order->getOrder() ) && ! $this->hasVolumes( $order ) ) {
			return [ $context ];
		}

		$scope = Order::resolveScope( $order, array_merge( [ 'for' => 'label' ], $context ) );

		if ( ! empty( $context['shipping_item_id'] ) ) {
			return [ $context ];
		}

		$vendor_id = ! empty( $scope['vendor_id'] ) ? (int) $scope['vendor_id'] : 0;
		$shipping_items = [];

		foreach ( $order->getShippingItemsPayload() as $shipping_item ) {
			if ( ! $shipping_item['is_correios'] ) {
				continue;
			}

			if ( $vendor_id && (int) $shipping_item['vendor_id'] !== $vendor_id ) {
				continue;
			}

			$shipping_items[] = $shipping_item['item_id'];
		}

		if ( count( $shipping_items ) < 2 ) {
			return [ $context ];
		}

		$contexts = [];
		$vendor_context = $vendor_id ? [ 'vendor_id' => $vendor_id ] : [];

		foreach ( $shipping_items as $shipping_item_id ) {
			$contexts[] = array_merge( $context, $vendor_context, [ 'shipping_item_id' => $shipping_item_id ] );
		}

		return $contexts;
	}

	/**
	 * Whether the shipment of an order was broken into volumes.
	 *
	 * @since 1.11.0
	 *
	 * @param Order $order Order being handled.
	 *
	 * @return bool
	 */
	public function hasVolumes( $order ) {
		foreach ( $order->getShippingItemsPayload() as $shipping_item ) {
			if ( ! empty( $shipping_item['volume_total'] ) && $shipping_item['volume_total'] > 1 ) {
				return true;
			}
		}

		return false;
	}
}
