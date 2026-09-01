<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

use Infixs\CorreiosAutomatico\Core\Support\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Prints a vendor label with the vendor's own return address.
 *
 * The print screen resolves one sender for the whole session, which is the
 * store's when an administrator is the one printing. A label built for a vendor
 * carries its own sender so the parcel a vendor ships comes back to the vendor.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class VendorLabelSender {

	public function __construct() {
		add_filter( 'infixs_correios_automatico_label_sender', [ $this, 'sender' ], 10, 3 );
	}

	/**
	 * Answer the sender of a label already scoped to a vendor.
	 *
	 * @param array|null                                $sender  Sender resolved so far, null for the store one.
	 * @param \Infixs\CorreiosAutomatico\Entities\Order $order   Order the label was built from, already scoped.
	 * @param array                                     $context Caller context.
	 *
	 * @return array|null Null keeps the store wide sender of the print screen.
	 */
	public function sender( $sender, $order, $context = [] ) {
		if ( $sender !== null ) {
			return $sender;
		}

		$vendor_id = $order ? $order->getScopedVendorId() : 0;

		if ( ! $vendor_id || ! Settings::boolean( 'vendor_sender_on_label' ) ) {
			return $sender;
		}

		$adapter = MarketplaceRegistry::forOrder( $order->getOrder() );

		if ( ! $adapter ) {
			return $sender;
		}

		$vendor_sender = VendorSender::fromVendor( $vendor_id, $adapter );

		if ( empty( $vendor_sender['address_postalcode'] ) || empty( $vendor_sender['address_street'] ) ) {
			Log::notice( 'Endereço da loja do vendedor incompleto, imprimindo a etiqueta com o remetente da loja principal.', [
				'order_id' => $order->get_id(),
				'vendor_id' => $vendor_id,
			] );

			return $sender;
		}

		return $vendor_sender;
	}
}
