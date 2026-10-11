<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Utils\AccessKey;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the NF-e an order ships with.
 *
 * The invoice of an order may come from another plugin — an ERP that issues
 * it, a marketplace that knows which vendor billed which freight — or from the
 * two order metas every carrier of the family already reads. Another plugin
 * answers through the `infixs_correios_automatico_order_invoice` filter, which
 * wins over the metas; whatever comes back is checked against the layout of the
 * access key, so a truncated key, or the key of a DC-e, never reaches the
 * Correios as if it were the invoice of the store.
 *
 * The plugin that issues the invoice tells the others when it is ready or when
 * it could not be issued, so whoever waits for it — the automatic prepost of the
 * PRO — moves on without polling:
 *
 * ```php
 * // When the invoice of the order becomes available.
 * do_action( 'infixs_correios_automatico_order_invoice_ready', $order_id );
 *
 * // When the invoice could not be issued; the message is shown to the merchant.
 * do_action( 'infixs_correios_automatico_order_invoice_failed', $order_id, $message );
 * ```
 *
 * Both actions are fired by the invoice providers, never by this plugin, and
 * have no listener in the free version.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class OrderInvoiceResolver {

	/**
	 * Order meta with the number of the invoice.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const NUMBER_META_KEY = '_infixs_correios_automatico_invoice_number';

	/**
	 * Order meta with the access key of the invoice.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const KEY_META_KEY = '_infixs_correios_automatico_invoice_key';

	/**
	 * Order meta the PRO keeps while the automatic prepost waits for the invoice.
	 *
	 * Holds the time each shipping line started waiting, keyed by the id of the
	 * line (zero for the order as a whole).
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const WAITING_META_KEY = '_infixs_correios_automatico_auto_prepost_waiting_invoice';

	/**
	 * Service of the PRO that makes the automatic prepost wait for the invoice.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const WAITING_SERVICE_CLASS = '\\Infixs\\CorreiosAutomaticoPro\\Services\\AutoPrepostInvoice';

	/**
	 * Statuses of an order that is no longer a sale.
	 *
	 * @since 1.11.0
	 *
	 * @var string[]
	 */
	const NO_LONGER_SOLD_STATUSES = [ 'cancelled', 'refunded', 'failed', 'trash' ];

	/**
	 * Source of an invoice read from the order metas.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const SOURCE_ORDER_META = 'order_meta';

	/**
	 * Find the invoice an order, or one shipment of it, ships with.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context {
	 *     What the invoice is wanted for.
	 *
	 *     @type string $for              `prepost` when it goes to the Correios, `display` when it is only shown.
	 *     @type int    $shipping_item_id Shipping line being shipped, zero for the order as a whole.
	 *     @type int    $vendor_id        Marketplace vendor posting the shipment, zero for the store.
	 * }
	 *
	 * @return array{invoice_number: string, invoice_key: string, model: string, source: string, source_label: string}|null
	 *         Null when the order has no valid invoice.
	 */
	public function resolve( \WC_Order $order, array $context = [] ) {
		$context = [
			'for' => isset( $context['for'] ) && 'prepost' === $context['for'] ? 'prepost' : 'display',
			'shipping_item_id' => isset( $context['shipping_item_id'] ) ? absint( $context['shipping_item_id'] ) : 0,
			'vendor_id' => isset( $context['vendor_id'] ) ? absint( $context['vendor_id'] ) : 0,
		];

		/**
		 * Filters the NF-e of an order before the order metas are read.
		 *
		 * An invoice provider returns the invoice it issued for the order; a null
		 * leaves the decision to the next provider and, at last, to the metas
		 * `_infixs_correios_automatico_invoice_number` and `_invoice_key`. Only
		 * an NF-e (model 55) with a whole access key is taken; the number is read
		 * from the key when it is not given.
		 *
		 * @since 1.11.0
		 *
		 * @param array|null $invoice {
		 *     Invoice of the order, null when the provider has none.
		 *
		 *     @type string $invoice_number Number of the invoice, optional.
		 *     @type string $invoice_key    Access key, 44 digits.
		 *     @type string $source         Slug of the provider, e.g. `squidcore`.
		 *     @type string $source_label   Name of the provider shown to the merchant, optional.
		 * }
		 * @param \WC_Order  $order   Order.
		 * @param array      $context {
		 *     @type string $for              `prepost` or `display`.
		 *     @type int    $shipping_item_id Shipping line being shipped, zero for the order as a whole.
		 *     @type int    $vendor_id        Marketplace vendor posting the shipment, zero for the store.
		 * }
		 */
		$filtered = apply_filters( 'infixs_correios_automatico_order_invoice', null, $order, $context );
		$invoice = is_array( $filtered ) ? $this->normalize( $filtered ) : null;

		if ( $invoice ) {
			return $invoice;
		}

		return $this->normalize( [
			'invoice_number' => $order->get_meta( self::NUMBER_META_KEY, true ),
			'invoice_key' => $order->get_meta( self::KEY_META_KEY, true ),
			'source' => self::SOURCE_ORDER_META,
			'source_label' => __( 'informada no pedido', 'infixs-correios-automatico' ),
		] );
	}

	/**
	 * Whether the automatic prepost of the order is waiting for the invoice.
	 *
	 * Only while the PRO that waits for it is active and the order is still a
	 * sale: a wait left behind by a PRO since deactivated, or on an order
	 * cancelled meanwhile, is not shown.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return bool
	 */
	public function isWaiting( \WC_Order $order ) {
		if ( ! class_exists( self::WAITING_SERVICE_CLASS ) || $order->has_status( self::NO_LONGER_SOLD_STATUSES ) ) {
			return false;
		}

		$waiting = $order->get_meta( self::WAITING_META_KEY, true );

		return is_array( $waiting ) && ! empty( $waiting );
	}

	/**
	 * Check an invoice and fill what can be read from its key.
	 *
	 * @since 1.11.0
	 *
	 * @param array $invoice Invoice as given by a provider or read from the metas.
	 *
	 * @return array{invoice_number: string, invoice_key: string, model: string, source: string, source_label: string}|null
	 */
	private function normalize( array $invoice ) {
		$key = AccessKey::normalize( isset( $invoice['invoice_key'] ) ? $invoice['invoice_key'] : null );

		if ( null === $key || ! AccessKey::isInvoice( $key ) ) {
			return null;
		}

		$number = isset( $invoice['invoice_number'] ) && is_scalar( $invoice['invoice_number'] )
			? sanitize_text_field( (string) $invoice['invoice_number'] )
			: '';

		$source = isset( $invoice['source'] ) && is_scalar( $invoice['source'] ) && '' !== (string) $invoice['source']
			? sanitize_key( (string) $invoice['source'] )
			: 'unknown';

		$label = isset( $invoice['source_label'] ) && is_scalar( $invoice['source_label'] )
			? sanitize_text_field( (string) $invoice['source_label'] )
			: '';

		return [
			'invoice_number' => '' !== $number ? $number : AccessKey::number( $key ),
			'invoice_key' => $key,
			'model' => AccessKey::model( $key ),
			'source' => $source,
			'source_label' => '' !== $label ? $label : $source,
		];
	}
}
