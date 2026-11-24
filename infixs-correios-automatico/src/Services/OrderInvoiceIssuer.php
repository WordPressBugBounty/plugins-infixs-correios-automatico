<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Utils\AccessKey;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;
use Infixs\CorreiosAutomatico\Utils\SiteUrl;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin that issues the NF-e of an order, offered where the invoice is asked for.
 *
 * An order without an NF-e goes to the Correios with a DC-e or with a number
 * and key typed by hand. When the store has an invoice provider — an ERP such
 * as the SquidCore — the dashboard offers to issue the NF-e there instead,
 * from the prepost window and from the menu of the order, and fills the
 * number and the key with what comes back. The provider answers three filters:
 *
 * - `infixs_correios_automatico_invoice_issuer` says who issues and whether it can issue now;
 * - `infixs_correios_automatico_issue_invoice` starts the issue;
 * - `infixs_correios_automatico_refresh_invoice_issue` reads how the issue is going.
 *
 * The first provider that answers wins: one that receives an answer that is
 * not null hands it on untouched. The issuer is only asked about an order of
 * the store that has no NF-e yet, and only for a user who can issue it; it
 * answers with what it has stored, never with a request to its API, as it is
 * asked once per order of the list. An order whose every freight belongs to a
 * marketplace vendor ships with the NF-e of the vendor and is left out, and an
 * order that is not a paid sale gets the issuer only to be told why it cannot
 * issue. The issue itself is refused in the same cases, whatever the issuer
 * would do: the guard of a fiscal document does not depend on the other plugin.
 *
 * Whatever comes back crosses from another plugin and is checked: an unknown
 * state is a failure, a key is taken only when it is the whole key of an
 * NF-e, the number is the one the key carries, a link only when it points to
 * this site.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.15.0
 */
class OrderInvoiceIssuer {

	/**
	 * Who may issue the NF-e of an order, the same who handles its preposts.
	 *
	 * @since 1.15.0
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * States of the invoice of the order as the issuer tells it.
	 *
	 * @since 1.15.0
	 *
	 * @var string[]
	 */
	const ISSUER_STATES = [ 'none', 'scheduled', 'processing', 'blocked', 'failed', 'authorized', 'test' ];

	/**
	 * How the time of a scheduled issue is shown, in the timezone of the site.
	 *
	 * @since 1.15.0
	 *
	 * @var string
	 */
	const SCHEDULED_FORMAT = 'd/m H:i';

	/**
	 * States an issue, started or read again, ends up in.
	 *
	 * @since 1.15.0
	 *
	 * @var string[]
	 */
	const RESULT_STATES = [ 'processing', 'authorized', 'blocked', 'failed', 'unavailable', 'test' ];

	/**
	 * Most pendencies kept, so the window stays readable.
	 *
	 * @since 1.15.0
	 *
	 * @var int
	 */
	const MAX_REASONS = 10;

	/**
	 * Longest wait, in seconds, before the dashboard reads the issue again.
	 *
	 * @since 1.15.0
	 *
	 * @var int
	 */
	const MAX_RETRY_AFTER = 300;

	/**
	 * Statuses of an order not paid yet, besides those that are no longer a sale.
	 *
	 * @since 1.15.0
	 *
	 * @var string[]
	 */
	const UNPAID_STATUSES = [ 'pending', 'checkout-draft' ];

	/**
	 * Finds the NF-e of an order and checks the one an issuer hands back.
	 *
	 * @var OrderInvoiceResolver
	 */
	private $invoiceResolver;

	/**
	 * @param OrderInvoiceResolver $invoiceResolver Finds the NF-e of an order.
	 */
	public function __construct( OrderInvoiceResolver $invoiceResolver ) {
		$this->invoiceResolver = $invoiceResolver;
	}

	/**
	 * The issuer that can issue the NF-e of an order, as the dashboard shows it.
	 *
	 * @since 1.15.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context {
	 *     What the plugin already knows about the invoice of the order.
	 *
	 *     @type int        $vendor_id      Marketplace vendor the order is shown to, zero for the store.
	 *     @type array|null $invoice        NF-e of the order, as OrderInvoiceResolver answers it; read when missing.
	 *     @type array      $shipping_items Freights of the order, as Order::getShippingItemsData() answers them; read when missing.
	 * }
	 *
	 * @return array{source: string, label: string, available: bool, reason: string, state: string, message: string, scheduled_at: int|null, scheduled_at_label: string, reasons: array, resolve_url: string}|null
	 *         Null when the order has an NF-e, is shown to a vendor or has only freights of vendors, the user cannot
	 *         issue it or no issuer answered. An order that is not a paid sale is never available, and the reason says why.
	 *         `scheduled_at_label` is the time of a scheduled issue as the dashboard shows it, in the timezone of the site.
	 */
	public function describe( \WC_Order $order, array $context = [] ) {
		$shipping_items = $this->shippingItems( $order, $context );
		$context = $this->context( $order, $context );

		if ( null !== $context['invoice'] || null !== $context['vendor_id'] || ! current_user_can( self::CAPABILITY ) || $this->shippedByVendors( $shipping_items ) ) {
			return null;
		}

		/**
		 * Filters the plugin that can issue the NF-e of an order.
		 *
		 * Asked only for an order of the store without an NF-e, once per order
		 * of the list: answer with what is stored, without requests to an API.
		 * An issuer that receives an answer that is not null hands it on.
		 *
		 * @since 1.15.0
		 *
		 * @param array|null $issuer {
		 *     Issuer of the invoice, null when no plugin issues it.
		 *
		 *     @type string $source      Slug of the issuer, e.g. `squidcore`.
		 *     @type string $label       Name of the issuer shown to the merchant.
		 *     @type bool   $available   Whether it can issue the invoice of this order now.
		 *     @type string   $reason       Why it cannot, e.g. the store is not connected; empty when available.
		 *     @type string   $state        `none`, `scheduled`, `processing`, `blocked`, `failed`, `authorized` or `test`.
		 *     @type string   $message      Message of the state, e.g. why an automatic issue skipped the order; empty for none.
		 *     @type int|null $scheduled_at When a `scheduled` automatic issue runs, as a Unix timestamp; null otherwise.
		 *     @type array    $reasons      Pendencies of a `blocked` invoice, each with a `message`.
		 *     @type string   $resolve_url  Where the pendencies are solved, on this site; empty for none.
		 * }
		 * @param \WC_Order  $order   Order.
		 * @param array      $context {
		 *     @type int|null   $vendor_id Marketplace vendor, null for the store.
		 *     @type array|null $invoice   NF-e of the order, null when it has none.
		 * }
		 */
		$issuer = apply_filters( 'infixs_correios_automatico_invoice_issuer', null, $order, $context );
		$issuer = is_array( $issuer ) ? $this->sanitizeIssuer( $issuer ) : null;
		$unsold = $this->unsoldReason( $order );

		if ( null === $issuer || '' === $unsold ) {
			return $issuer;
		}

		$issuer['available'] = false;
		$issuer['reason'] = $unsold;

		return $issuer;
	}

	/**
	 * Ask the issuer to issue the NF-e of an order.
	 *
	 * Refused before the issuer is asked when the order already has an NF-e,
	 * ships only freights of marketplace vendors or is not a paid sale: a
	 * second NF-e of the same sale, or one of a sale that did not happen, is
	 * not left to the other plugin to prevent.
	 *
	 * @since 1.15.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context Same as describe().
	 *
	 * @return array{state: string, message: string, reasons: array, invoice: array|null, retry_after: int|null, resolve_url: string}|\WP_Error|null
	 *         Null when no issuer answered; a WP_Error with status 409 when the order may not be issued.
	 */
	public function issue( \WC_Order $order, array $context = [] ) {
		$shipping_items = $this->shippingItems( $order, $context );
		$context = $this->context( $order, $context );
		$refusal = $this->refusal( $order, $context, $shipping_items );

		if ( null !== $refusal ) {
			return $refusal;
		}

		/**
		 * Filters the issue of the NF-e of an order, started by the merchant.
		 *
		 * The issuer starts the issue and answers how it went; a null leaves it
		 * to the next issuer. An issuer that receives an answer that is not
		 * null hands it on.
		 *
		 * @since 1.15.0
		 *
		 * @param array|null $result {
		 *     How the issue went, null when no plugin issued it.
		 *
		 *     @type string     $state       `processing`, `authorized`, `blocked`, `failed`, `unavailable` or `test`.
		 *     @type string     $message     Message of the state.
		 *     @type array      $reasons     Pendencies, each with a `message`.
		 *     @type array|null $invoice     `invoice_number` and `invoice_key` (44 digits) of an `authorized` NF-e.
		 *     @type int|null   $retry_after Seconds before reading the issue again, while `processing`.
		 *     @type string     $resolve_url Where the pendencies are solved, on this site; empty for none.
		 * }
		 * @param \WC_Order  $order   Order.
		 * @param array      $context Same as `infixs_correios_automatico_invoice_issuer`.
		 */
		$result = apply_filters( 'infixs_correios_automatico_issue_invoice', null, $order, $context );

		return is_array( $result ) ? $this->sanitizeResult( $result ) : null;
	}

	/**
	 * Ask the issuer how the issue of the NF-e of an order is going.
	 *
	 * Never refused like issue(): it only reads, and an NF-e authorized
	 * meanwhile becomes the NF-e of the order, which the read has to tell.
	 *
	 * @since 1.15.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context Same as describe().
	 *
	 * @return array{state: string, message: string, reasons: array, invoice: array|null, retry_after: int|null, resolve_url: string}|null
	 *         Null when no issuer answered.
	 */
	public function refresh( \WC_Order $order, array $context = [] ) {
		/**
		 * Filters how the issue of the NF-e of an order is going.
		 *
		 * Read by the dashboard while the issue is `processing`, after the
		 * `retry_after` of the last answer. Answers as
		 * `infixs_correios_automatico_issue_invoice`.
		 *
		 * @since 1.15.0
		 *
		 * @param array|null $result  How the issue is going, null when no plugin issues it.
		 * @param \WC_Order  $order   Order.
		 * @param array      $context Same as `infixs_correios_automatico_invoice_issuer`.
		 */
		$result = apply_filters( 'infixs_correios_automatico_refresh_invoice_issue', null, $order, $this->context( $order, $context ) );

		return is_array( $result ) ? $this->sanitizeResult( $result ) : null;
	}

	/**
	 * Context the issuer receives: the vendor, null for the store, and the NF-e of the order.
	 *
	 * @since 1.15.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context Context of the caller.
	 *
	 * @return array{vendor_id: int|null, invoice: array|null}
	 */
	private function context( \WC_Order $order, array $context ) {
		$vendor_id = isset( $context['vendor_id'] ) ? absint( $context['vendor_id'] ) : 0;

		$invoice = array_key_exists( 'invoice', $context )
			? $context['invoice']
			: $this->invoiceResolver->resolve( $order, [ 'for' => 'display', 'vendor_id' => $vendor_id ] );

		return [
			'vendor_id' => $vendor_id ? $vendor_id : null,
			'invoice' => is_array( $invoice ) ? $invoice : null,
		];
	}

	/**
	 * Freights of the order, as the caller already has them or read from the order.
	 *
	 * @since 1.15.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context Context of the caller.
	 *
	 * @return array<int, array> Each with the `vendor_id` that ships it, null for the store.
	 */
	private function shippingItems( \WC_Order $order, array $context ) {
		if ( isset( $context['shipping_items'] ) && is_array( $context['shipping_items'] ) ) {
			return $context['shipping_items'];
		}

		return ( new Order( $order ) )->getShippingItemsData();
	}

	/**
	 * Whether every freight of the order belongs to a marketplace vendor, who issues its NF-e.
	 *
	 * An order without freights is the store's.
	 *
	 * @since 1.15.0
	 *
	 * @param array $shipping_items Freights of the order.
	 *
	 * @return bool
	 */
	private function shippedByVendors( array $shipping_items ) {
		if ( empty( $shipping_items ) ) {
			return false;
		}

		foreach ( $shipping_items as $shipping_item ) {
			if ( empty( $shipping_item['vendor_id'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Why the order is not a paid sale to issue the NF-e of; empty when it is.
	 *
	 * Paid is told by the date of the payment, as the automatic prepost does:
	 * a paid order moved to a status of the store, such as "Preparando para
	 * envio", is still a sale.
	 *
	 * @since 1.15.0
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return string
	 */
	private function unsoldReason( \WC_Order $order ) {
		if ( $order->has_status( OrderInvoiceResolver::NO_LONGER_SOLD_STATUSES ) ) {
			return sprintf(
				/* translators: %s: status of the order, e.g. Cancelado */
				__( 'O pedido não é mais uma venda (%s): a NF-e não é emitida.', 'infixs-correios-automatico' ),
				wc_get_order_status_name( $order->get_status() )
			);
		}

		if ( null === $order->get_date_paid() || $order->has_status( self::UNPAID_STATUSES ) ) {
			return __( 'O pagamento do pedido ainda não foi confirmado: a NF-e é emitida depois dele.', 'infixs-correios-automatico' );
		}

		return '';
	}

	/**
	 * Why the NF-e of the order may not be issued, null when it may.
	 *
	 * @since 1.15.0
	 *
	 * @param \WC_Order $order          Order.
	 * @param array     $context        Context the issuer receives.
	 * @param array     $shipping_items Freights of the order.
	 *
	 * @return \WP_Error|null
	 */
	private function refusal( \WC_Order $order, array $context, array $shipping_items ) {
		if ( null !== $context['invoice'] ) {
			return new \WP_Error(
				'invoice_already_present',
				sprintf(
					/* translators: %s: number of the NF-e */
					__( 'O pedido já tem a NF-e nº %s: não há outra a emitir.', 'infixs-correios-automatico' ),
					isset( $context['invoice']['invoice_number'] ) ? $context['invoice']['invoice_number'] : ''
				),
				[ 'status' => 409 ]
			);
		}

		if ( null !== $context['vendor_id'] || $this->shippedByVendors( $shipping_items ) ) {
			return new \WP_Error(
				'invoice_vendor_order',
				__( 'O frete deste pedido é de um vendedor do marketplace, que emite a própria NF-e.', 'infixs-correios-automatico' ),
				[ 'status' => 409 ]
			);
		}

		$unsold = $this->unsoldReason( $order );

		if ( '' !== $unsold ) {
			return new \WP_Error( 'invoice_order_not_sold', $unsold, [ 'status' => 409 ] );
		}

		return null;
	}

	/**
	 * Check the issuer, field by field.
	 *
	 * @since 1.15.0
	 *
	 * @param array $issuer Issuer as the provider answered it.
	 *
	 * @return array|null Null when it has neither a slug nor a name.
	 */
	private function sanitizeIssuer( array $issuer ) {
		$source = sanitize_key( $this->text( $issuer, 'source' ) );
		$label = $this->text( $issuer, 'label' );

		if ( '' === $source && '' === $label ) {
			return null;
		}

		$state = $this->text( $issuer, 'state' );
		$state = in_array( $state, self::ISSUER_STATES, true ) ? $state : 'failed';
		$available = isset( $issuer['available'] ) && Sanitizer::boolean( $issuer['available'] );
		$scheduled_at = 'scheduled' === $state ? $this->timestamp( isset( $issuer['scheduled_at'] ) ? $issuer['scheduled_at'] : null ) : null;

		return [
			'source' => '' !== $source ? $source : sanitize_key( $label ),
			'label' => '' !== $label ? $label : $source,
			'available' => $available,
			'reason' => $available ? '' : $this->text( $issuer, 'reason' ),
			'state' => $state,
			'message' => $this->text( $issuer, 'message' ),
			'scheduled_at' => $scheduled_at,
			'scheduled_at_label' => null !== $scheduled_at ? (string) wp_date( self::SCHEDULED_FORMAT, $scheduled_at ) : '',
			'reasons' => $this->reasons( isset( $issuer['reasons'] ) ? $issuer['reasons'] : [] ),
			'resolve_url' => SiteUrl::own( isset( $issuer['resolve_url'] ) ? $issuer['resolve_url'] : '' ),
		];
	}

	/**
	 * A Unix timestamp the issuer answered, null when it is not a positive whole number.
	 *
	 * @since 1.15.0
	 *
	 * @param mixed $value Timestamp as the issuer answered it.
	 *
	 * @return int|null
	 */
	private function timestamp( $value ) {
		if ( is_bool( $value ) || ! is_numeric( $value ) || (int) $value < 1 ) {
			return null;
		}

		return (int) $value;
	}

	/**
	 * Check the result of an issue, field by field.
	 *
	 * An unknown state is a failure, and so is an authorization that does not
	 * hand back the key of an NF-e: there would be nothing to fill the prepost
	 * with.
	 *
	 * @since 1.15.0
	 *
	 * @param array $result Result as the issuer answered it.
	 *
	 * @return array{state: string, message: string, reasons: array, invoice: array|null, retry_after: int|null, resolve_url: string}
	 */
	private function sanitizeResult( array $result ) {
		$state = $this->text( $result, 'state' );
		$state = in_array( $state, self::RESULT_STATES, true ) ? $state : 'failed';
		$message = $this->text( $result, 'message' );
		$invoice = 'authorized' === $state ? $this->invoice( isset( $result['invoice'] ) ? $result['invoice'] : null ) : null;

		if ( 'authorized' === $state && null === $invoice ) {
			$state = 'failed';
			$message = __( 'O emissor autorizou a NF-e, mas não devolveu uma chave de acesso de NF-e válida.', 'infixs-correios-automatico' );
		}

		if ( '' === $message ) {
			$message = $this->defaultMessage( $state );
		}

		return [
			'state' => $state,
			'message' => $message,
			'reasons' => $this->reasons( isset( $result['reasons'] ) ? $result['reasons'] : [] ),
			'invoice' => $invoice,
			'retry_after' => 'processing' === $state ? $this->retryAfter( isset( $result['retry_after'] ) ? $result['retry_after'] : null ) : null,
			'resolve_url' => SiteUrl::own( isset( $result['resolve_url'] ) ? $result['resolve_url'] : '' ),
		];
	}

	/**
	 * Message of a result the issuer gave no message for, so the merchant always reads why.
	 *
	 * @since 1.15.0
	 *
	 * @param string $state State of the result, already checked.
	 *
	 * @return string Empty for the states that speak for themselves.
	 */
	private function defaultMessage( $state ) {
		switch ( $state ) {
			case 'processing':
				return __( 'A NF-e está sendo emitida. Aguardando a SEFAZ.', 'infixs-correios-automatico' );
			case 'blocked':
				return __( 'A NF-e tem pendências a resolver antes de ser emitida.', 'infixs-correios-automatico' );
			case 'unavailable':
				return __( 'O emissor não pode emitir a NF-e deste pedido agora.', 'infixs-correios-automatico' );
			case 'test':
				return __( 'A NF-e foi emitida em homologação, sem valor fiscal.', 'infixs-correios-automatico' );
			case 'failed':
				return __( 'Não foi possível emitir a NF-e.', 'infixs-correios-automatico' );
		}

		return '';
	}

	/**
	 * Number and key of the NF-e an issuer handed back, null when the key is not of an NF-e.
	 *
	 * The number is the one printed in the key, whatever number the issuer
	 * sent beside it: the pair goes to the Correios as it is, and a number
	 * that does not match its key would be refused there.
	 *
	 * @since 1.15.0
	 *
	 * @param mixed $invoice Invoice as the issuer answered it.
	 *
	 * @return array{invoice_number: string, invoice_key: string}|null
	 */
	private function invoice( $invoice ) {
		$invoice = is_array( $invoice ) ? $this->invoiceResolver->normalize( $invoice ) : null;

		if ( null === $invoice ) {
			return null;
		}

		return [
			'invoice_number' => AccessKey::number( $invoice['invoice_key'] ),
			'invoice_key' => $invoice['invoice_key'],
		];
	}

	/**
	 * Keep the pendencies that have a message, at most ten.
	 *
	 * @since 1.15.0
	 *
	 * @param mixed $reasons Pendencies as the issuer answered them.
	 *
	 * @return array<int, array{message: string}>
	 */
	private function reasons( $reasons ) {
		if ( ! is_array( $reasons ) ) {
			return [];
		}

		$kept = [];

		foreach ( $reasons as $reason ) {
			$message = is_array( $reason ) ? $this->text( $reason, 'message' ) : '';

			if ( '' === $message ) {
				continue;
			}

			$kept[] = [ 'message' => $message ];

			if ( count( $kept ) >= self::MAX_REASONS ) {
				break;
			}
		}

		return $kept;
	}

	/**
	 * Seconds before reading the issue again, from one to five minutes; null when not given.
	 *
	 * @since 1.15.0
	 *
	 * @param mixed $seconds Wait as the issuer answered it.
	 *
	 * @return int|null
	 */
	private function retryAfter( $seconds ) {
		if ( ! is_numeric( $seconds ) || (int) $seconds < 1 ) {
			return null;
		}

		return min( (int) $seconds, self::MAX_RETRY_AFTER );
	}

	/**
	 * Sanitized text of a field, empty when it is missing or not a text.
	 *
	 * @since 1.15.0
	 *
	 * @param array  $values Values as the issuer answered them.
	 * @param string $field  Field to read.
	 *
	 * @return string
	 */
	private function text( array $values, $field ) {
		return Sanitizer::scalar_text( isset( $values[ $field ] ) ? $values[ $field ] : null );
	}
}
