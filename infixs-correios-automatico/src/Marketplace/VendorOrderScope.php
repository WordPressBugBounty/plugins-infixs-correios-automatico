<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

use Infixs\CorreiosAutomatico\Config\VendorSettings;
use Infixs\CorreiosAutomatico\Core\Support\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Narrows an order down to the shipment of a single vendor.
 *
 * A marketplace that does not create sub orders puts items of several vendors
 * on one WooCommerce order, each with its own shipping line, weight, dimensions
 * and service code. Without this the base plugin would build every label and
 * every prepost from the whole order and its first shipping line, which sends
 * the wrong package to Correios for everyone but the first vendor.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class VendorOrderScope {

	public function __construct() {
		add_filter( 'infixs_correios_automatico_order_scope', [ $this, 'scope' ], 10, 3 );
	}

	/**
	 * Answer which items, which shipping line and which contract are in scope.
	 *
	 * @param array                                     $scope   Store wide scope.
	 * @param \Infixs\CorreiosAutomatico\Entities\Order $order   Order being scoped.
	 * @param array                                     $context Caller context.
	 *
	 * @return array
	 */
	public function scope( $scope, $order, $context = [] ) {
		$wc_order = $order ? $order->getOrder() : null;

		if ( ! $wc_order ) {
			return $scope;
		}

		$adapter = MarketplaceRegistry::forOrder( $wc_order );

		if ( ! $adapter ) {
			return $scope;
		}

		$order_id = $wc_order->get_id();
		$vendor_id = $this->resolveVendor( $adapter, $order_id, $context, $wc_order );

		if ( ! $vendor_id ) {
			return $scope;
		}

		$scope['vendor_id'] = $vendor_id;
		$scope['item_ids'] = array_keys( $adapter->getOrderItemsByVendor( $wc_order, $vendor_id ) );

		if ( empty( $scope['shipping_item_id'] ) ) {
			$shipping_item = $adapter->getOrderShippingItem( $wc_order, $vendor_id );

			if ( $shipping_item ) {
				$scope['shipping_item_id'] = $shipping_item->get_id();
			}
		}

		if ( isset( $context['for'] ) && $context['for'] === 'label' ) {
			$auth = VendorSettings::get( $vendor_id )->auth;

			if ( $auth->isActive() && $auth->hasValidCredentials() ) {
				$scope['contract_number'] = $auth->contract_number;
				$scope['postcard'] = $auth->postcard;
			} elseif ( ! Settings::lendsStoreContract() ) {
				$scope['contract_number'] = '';
				$scope['postcard'] = '';
			}

			/**
			 * With the store contract lent to the vendor both values are left
			 * untouched, so the base plugin prints the store contract — which is
			 * the one the shipment is actually posted under.
			 */
		}

		return $scope;
	}

	/**
	 * Resolve the vendor an operation belongs to.
	 *
	 * @param MarketplaceAdapter $adapter  Marketplace that owns the order.
	 * @param int                $order_id Order ID.
	 * @param array              $context  Caller context.
	 * @param \WC_Order          $wc_order Order.
	 *
	 * @return int Vendor ID, zero when the whole order should be used.
	 */
	private function resolveVendor( $adapter, $order_id, $context, $wc_order ) {
		$explicit = isset( $context['vendor_id'] ) ? absint( $context['vendor_id'] ) : 0;
		$for = isset( $context['for'] ) ? $context['for'] : '';

		if ( ! $explicit && ! empty( $context['shipping_item_id'] ) ) {
			$explicit = $adapter->getShippingItemVendorId( $wc_order, $context['shipping_item_id'] );
		}

		if ( ! $explicit && $for !== 'label' && ! Settings::boolean( 'vendor_prepost' ) ) {
			return 0;
		}

		$vendor_id = $adapter->resolveOrderVendorId( $order_id, $explicit );

		if ( ! $vendor_id && ! $explicit && count( $adapter->getOrderVendorIds( $wc_order ) ) > 1 ) {
			Log::debug( 'Pedido com mais de um vendedor e nenhum informado: seguindo com a loja principal.', [
				'order_id' => $order_id,
				'for' => $for,
			] );
		}

		/**
		 * Filters the vendor an order operation is scoped to.
		 *
		 * @since 1.10.0
		 *
		 * @param int                $vendor_id
		 * @param int                $order_id
		 * @param array              $context
		 * @param MarketplaceAdapter $adapter
		 */
		return (int) apply_filters( 'infixs_correios_automatico_order_vendor_id', $vendor_id, $order_id, $context, $adapter );
	}
}
