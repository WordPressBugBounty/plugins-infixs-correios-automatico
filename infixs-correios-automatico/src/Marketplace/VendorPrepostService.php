<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Services\Correios\VendorApiFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Creates preposts on behalf of the vendor that ships the order.
 *
 * Which vendor a prepost belongs to is decided by the scope, and carried on the
 * prepost, so here it is only a matter of swapping the sender, the contract and
 * the declared contents.
 *
 * @see VendorOrderScope
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class VendorPrepostService {

	public function __construct() {
		add_filter( 'infixs_correios_automatico_prepost_sender', [ $this, 'sender' ], 10, 3 );
		add_filter( 'infixs_correios_automatico_prepost_correios_api', [ $this, 'correios_api' ], 10, 2 );
		add_filter( 'infixs_correios_automatico_prepost_order_items', [ $this, 'order_items' ], 10, 3 );
	}

	/**
	 * Ship from the vendor store address.
	 *
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Person $sender    Store wide sender.
	 * @param int                                                         $order_id  Order ID.
	 * @param int                                                         $vendor_id Vendor the prepost belongs to.
	 *
	 * @return \Infixs\CorreiosAutomatico\Services\Correios\Includes\Person
	 */
	public function sender( $sender, $order_id, $vendor_id = 0 ) {
		if ( ! $vendor_id ) {
			return $sender;
		}

		$adapter = MarketplaceRegistry::forOrder( $order_id );

		if ( ! $adapter ) {
			return $sender;
		}

		$vendor_sender = VendorSender::personFromVendor( $vendor_id, $adapter );

		if ( ! $vendor_sender ) {
			Log::notice( 'Endereço da loja do vendedor incompleto, usando o remetente da loja principal na prépostagem.', [
				'order_id' => $order_id,
				'vendor_id' => $vendor_id,
			] );

			return $sender;
		}

		return $vendor_sender;
	}

	/**
	 * Sign the prepost with the vendor contract when there is one.
	 *
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi      $correios_api Store wide client.
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost      Prepost.
	 *
	 * @return \Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi
	 */
	public function correios_api( $correios_api, $prepost ) {
		if ( ! $prepost || ! MarketplaceRegistry::forOrder( $prepost->getOrderId() ) ) {
			return $correios_api;
		}

		return VendorApiFactory::forVendor( $prepost->getVendorId(), $correios_api );
	}

	/**
	 * Declare only the items the vendor ships.
	 *
	 * @param \WC_Order_Item_Product[]                                      $items    Order items.
	 * @param int                                                           $order_id Order ID.
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost  Prepost.
	 *
	 * @return \WC_Order_Item_Product[]
	 */
	public function order_items( $items, $order_id, $prepost = null ) {
		$vendor_id = $prepost ? $prepost->getVendorId() : 0;

		if ( ! $vendor_id ) {
			return $items;
		}

		$order = wc_get_order( $order_id );
		$adapter = $order ? MarketplaceRegistry::forOrder( $order ) : null;

		if ( ! $adapter ) {
			return $items;
		}

		$vendor_items = $adapter->getOrderItemsByVendor( $order, $vendor_id );

		return $vendor_items ? $vendor_items : $items;
	}
}
