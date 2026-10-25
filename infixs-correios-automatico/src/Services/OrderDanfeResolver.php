<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Utils\AccessKey;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the data of the simplified DANFE ("DANFE Simplificado – Etiqueta") of
 * the NF-e an order ships with.
 *
 * Only the plugin that issued the invoice knows what the DANFE prints — the
 * protocol of authorization, the issuer, the recipient — so it answers through
 * the `infixs_correios_automatico_order_danfe` filter, with the raw values of
 * the authorized XML: digits without masks, the ISO-8601 dates of the XML and
 * the total in cents. This class only checks and cleans those values; how they
 * are printed lives in the two renderers of the labels, the print page and the
 * PDF, which share the same formatter (danfeFormat.ts).
 *
 * A block that does not belong to the invoice of the order — another key, a
 * number or series that disagree with the key, an issuer other than the one in
 * the key — is refused and logged: a DANFE printed with the data of another
 * invoice would travel with the package as if it were the right one.
 *
 * Providers read `OrderDanfeResolver::SCHEMA` to know which version of the
 * block this plugin takes, and detect the support through `method_exists()` on
 * `resolve()`.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.12.0
 */
class OrderDanfeResolver {

	/**
	 * Version of the block the providers answer.
	 *
	 * @since 1.12.0
	 *
	 * @var int
	 */
	const SCHEMA = 1;

	/**
	 * The block was checked and goes to the label.
	 *
	 * @since 1.12.0
	 *
	 * @var string
	 */
	const STATUS_READY = 'ready';

	/**
	 * The provider is still preparing the data.
	 *
	 * @since 1.12.0
	 *
	 * @var string
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * The order has an NF-e, but no provider answered with its data.
	 *
	 * @since 1.12.0
	 *
	 * @var string
	 */
	const STATUS_UNAVAILABLE = 'unavailable';

	/**
	 * A provider answered with a block that was refused.
	 *
	 * @since 1.12.0
	 *
	 * @var string
	 */
	const STATUS_INVALID = 'invalid';

	/**
	 * The order has no NF-e.
	 *
	 * @since 1.12.0
	 *
	 * @var string
	 */
	const STATUS_NONE = 'none';

	/**
	 * Longest name kept, the size of the field `xNome` of the NF-e.
	 *
	 * @since 1.12.0
	 *
	 * @var int
	 */
	const NAME_MAX_LENGTH = 60;

	/**
	 * Longest document kept, the size of the field `idEstrangeiro` of the NF-e.
	 *
	 * @since 1.12.0
	 *
	 * @var int
	 */
	const DOCUMENT_MAX_LENGTH = 20;

	/**
	 * Resolver of the NF-e the block has to belong to.
	 *
	 * @since 1.12.0
	 *
	 * @var OrderInvoiceResolver
	 */
	protected $invoiceResolver;

	/**
	 * Constructor.
	 *
	 * @since 1.12.0
	 *
	 * @param OrderInvoiceResolver $invoiceResolver Resolver of the NF-e of the order.
	 */
	public function __construct( OrderInvoiceResolver $invoiceResolver ) {
		$this->invoiceResolver = $invoiceResolver;
	}

	/**
	 * Find the NF-e of an order, or of one shipment of it, and the data of its DANFE.
	 *
	 * @since 1.12.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context {
	 *     What the DANFE is wanted for.
	 *
	 *     @type string $for              `label` when it is printed, `display` when it is only shown.
	 *     @type int    $shipping_item_id Shipping line being shipped, zero for the order as a whole.
	 *     @type int    $vendor_id        Marketplace vendor posting the shipment, zero for the store.
	 * }
	 *
	 * @return array{invoice: array|null, danfe: array|null, status: string}
	 *         The NF-e as OrderInvoiceResolver answers it, the checked block of
	 *         the DANFE, and one of the `STATUS_*` constants.
	 */
	public function resolve( \WC_Order $order, array $context = [] ) {
		$context = [
			'for' => isset( $context['for'] ) && 'display' === $context['for'] ? 'display' : 'label',
			'shipping_item_id' => isset( $context['shipping_item_id'] ) ? absint( $context['shipping_item_id'] ) : 0,
			'vendor_id' => isset( $context['vendor_id'] ) ? absint( $context['vendor_id'] ) : 0,
		];

		$invoice = $this->invoiceResolver->resolve( $order, [
			'for' => 'display',
			'shipping_item_id' => $context['shipping_item_id'],
			'vendor_id' => $context['vendor_id'],
		] );

		if ( ! $invoice ) {
			return $this->result( null, null, self::STATUS_NONE );
		}

		$context['invoice'] = $invoice;

		/**
		 * Filters the data of the simplified DANFE of the NF-e of an order.
		 *
		 * The provider that issued the invoice answers with the raw values of
		 * the authorized XML; a null leaves the decision to the next provider.
		 * While the data is being prepared, answer `['schema' => 1, 'pending' => true]`.
		 * A provider answers only for the invoice in `$context['invoice']`, and
		 * a marketplace vendor (`vendor_id`) issues its own invoices.
		 *
		 * @since 1.12.0
		 *
		 * @param array|null $danfe {
		 *     Data of the DANFE, null when the provider has none.
		 *
		 *     @type int      $schema       Version of the block, OrderDanfeResolver::SCHEMA.
		 *     @type string   $source       Slug of the provider, e.g. `squidcore`.
		 *     @type string   $source_label Name of the provider shown to the merchant.
		 *     @type string   $environment  `production` or `homologation`.
		 *     @type string   $key          Access key, 44 digits.
		 *     @type string   $number       Number of the invoice, `ide/nNF`.
		 *     @type string   $series       Series of the invoice, `ide/serie`.
		 *     @type string   $operation    `out` (1 - saída) or `in` (0 - entrada).
		 *     @type string   $issued_at    `ide/dhEmi`, ISO-8601 with the offset of the XML.
		 *     @type array    $protocol     `number` (15 digits) and `authorized_at` (ISO-8601).
		 *     @type array    $emitter      `name`, `document`, `ie` and `uf` of the issuer.
		 *     @type array    $recipient    `name`, `document`, `ie` and `uf` of the recipient.
		 *     @type int|null $total        Total of the invoice in cents, null to leave it out.
		 * }
		 * @param \WC_Order  $order   Order.
		 * @param array      $context {
		 *     @type string $for              `label` or `display`.
		 *     @type int    $shipping_item_id Shipping line being shipped, zero for the order as a whole.
		 *     @type int    $vendor_id        Marketplace vendor posting the shipment, zero for the store.
		 *     @type array  $invoice          NF-e of the order the block has to belong to.
		 * }
		 */
		$filtered = apply_filters( 'infixs_correios_automatico_order_danfe', null, $order, $context );

		if ( ! is_array( $filtered ) ) {
			return $this->result( $invoice, null, self::STATUS_UNAVAILABLE );
		}

		$danfe = $this->read( $filtered );

		if ( ! empty( $filtered['pending'] ) && self::SCHEMA === $danfe['schema'] ) {
			return $this->result( $invoice, null, self::STATUS_PENDING );
		}

		$rejection = $this->rejection( $danfe, $invoice );

		if ( '' !== $rejection ) {
			Log::notice( 'Os dados da DANFE simplificada foram recusados.', [
				'order_id' => $order->get_id(),
				'source' => $danfe['source'],
				'reason' => $rejection,
			] );

			return $this->result( $invoice, null, self::STATUS_INVALID );
		}

		return $this->result( $invoice, $danfe, self::STATUS_READY );
	}

	/**
	 * Build the answer of resolve().
	 *
	 * @since 1.12.0
	 *
	 * @param array|null $invoice NF-e of the order.
	 * @param array|null $danfe   Checked block of the DANFE.
	 * @param string     $status  One of the `STATUS_*` constants.
	 *
	 * @return array{invoice: array|null, danfe: array|null, status: string}
	 */
	private function result( $invoice, $danfe, $status ) {
		return [
			'invoice' => $invoice,
			'danfe' => $danfe,
			'status' => $status,
		];
	}

	/**
	 * Clean every value of a block, still without checking it.
	 *
	 * Texts are sanitized, numbers lose the zeros on the left, the empty state
	 * registration becomes null and a total that is not whole cents is left out
	 * (it is optional on the DANFE).
	 *
	 * @since 1.12.0
	 *
	 * @param array $raw Block as the provider answered it.
	 *
	 * @return array Block in the shape of the label payload.
	 */
	private function read( array $raw ) {
		$protocol = isset( $raw['protocol'] ) && is_array( $raw['protocol'] ) ? $raw['protocol'] : [];
		$source = sanitize_key( $this->text( $raw, 'source' ) );
		$source_label = $this->text( $raw, 'source_label' );

		return [
			'schema' => isset( $raw['schema'] ) && is_numeric( $raw['schema'] ) ? (int) $raw['schema'] : 0,
			'source' => '' !== $source ? $source : 'unknown',
			'source_label' => '' !== $source_label ? $source_label : ( '' !== $source ? $source : 'unknown' ),
			'environment' => $this->text( $raw, 'environment' ),
			'key' => preg_replace( '/\s+/', '', $this->text( $raw, 'key' ) ),
			'number' => $this->unpadded( $this->text( $raw, 'number' ) ),
			'series' => $this->unpadded( $this->text( $raw, 'series' ) ),
			'operation' => $this->text( $raw, 'operation' ),
			'issued_at' => $this->text( $raw, 'issued_at' ),
			'protocol' => [
				'number' => $this->text( $protocol, 'number' ),
				'authorized_at' => $this->text( $protocol, 'authorized_at' ),
			],
			'emitter' => $this->party( isset( $raw['emitter'] ) ? $raw['emitter'] : null ),
			'recipient' => $this->party( isset( $raw['recipient'] ) ? $raw['recipient'] : null ),
			'total' => $this->cents( isset( $raw['total'] ) ? $raw['total'] : null ),
			'contingency' => null,
		];
	}

	/**
	 * Clean the issuer or the recipient of a block.
	 *
	 * @since 1.12.0
	 *
	 * @param mixed $raw Party as the provider answered it.
	 *
	 * @return array{name: string, document: string, ie: string|null, uf: string}
	 */
	private function party( $raw ) {
		$raw = is_array( $raw ) ? $raw : [];
		$name = $this->text( $raw, 'name' );
		$document = $this->text( $raw, 'document' );
		$ie = $this->text( $raw, 'ie' );

		return [
			'name' => trim( mb_substr( $name, 0, self::NAME_MAX_LENGTH ) ),
			'document' => preg_match( '/^[\d.\-\/\s]*$/', $document )
				? preg_replace( '/\D/', '', $document )
				: mb_substr( $document, 0, self::DOCUMENT_MAX_LENGTH ),
			'ie' => '' !== $ie ? $ie : null,
			'uf' => strtoupper( $this->text( $raw, 'uf' ) ),
		];
	}

	/**
	 * Why a cleaned block is refused, or an empty string when it is taken.
	 *
	 * @since 1.12.0
	 *
	 * @param array $danfe   Cleaned block.
	 * @param array $invoice NF-e of the order the block has to belong to.
	 *
	 * @return string `schema`, `environment`, `key_invalid`, `key_mismatch`,
	 *                `number`, `operation`, `issued_at`, `protocol`, `emitter`,
	 *                `recipient`, or empty.
	 */
	private function rejection( array $danfe, array $invoice ) {
		$key = $danfe['key'];

		if ( self::SCHEMA !== $danfe['schema'] ) {
			return 'schema';
		}

		if ( ! in_array( $danfe['environment'], [ 'production', 'homologation' ], true ) ) {
			return 'environment';
		}

		if ( ! preg_match( '/^\d{44}$/', $key ) || ! AccessKey::isInvoice( $key ) || ! AccessKey::hasValidCheckDigit( $key ) ) {
			return 'key_invalid';
		}

		if ( $key !== $invoice['invoice_key'] ) {
			return 'key_mismatch';
		}

		if (
			! preg_match( '/^\d{1,9}$/', $danfe['number'] )
			|| ! preg_match( '/^\d{1,3}$/', $danfe['series'] )
			|| AccessKey::number( $key ) !== $danfe['number']
			|| AccessKey::series( $key ) !== str_pad( $danfe['series'], 3, '0', STR_PAD_LEFT )
		) {
			return 'number';
		}

		if ( ! in_array( $danfe['operation'], [ 'out', 'in' ], true ) ) {
			return 'operation';
		}

		if ( ! $this->isDateTime( $danfe['issued_at'] ) ) {
			return 'issued_at';
		}

		if ( ! preg_match( '/^\d{15}$/', $danfe['protocol']['number'] ) || ! $this->isDateTime( $danfe['protocol']['authorized_at'] ) ) {
			return 'protocol';
		}

		$emitter = $danfe['emitter'];

		if ( ! $this->isParty( $emitter ) || str_pad( $emitter['document'], 14, '0', STR_PAD_LEFT ) !== AccessKey::issuerDocument( $key ) ) {
			return 'emitter';
		}

		if ( ! $this->isParty( $danfe['recipient'] ) ) {
			return 'recipient';
		}

		return '';
	}

	/**
	 * Whether a cleaned party has a name and a state (or `EX`, abroad).
	 *
	 * @since 1.12.0
	 *
	 * @param array $party Cleaned party.
	 *
	 * @return bool
	 */
	private function isParty( array $party ) {
		return '' !== $party['name'] && (bool) preg_match( '/^[A-Z]{2}$/', $party['uf'] );
	}

	/**
	 * Whether a value starts with an ISO-8601 date and time that exist.
	 *
	 * Only the wall clock matters: the DANFE prints the date and time of the
	 * XML as they are, without converting the offset. The renderers read the
	 * date with the same pattern.
	 *
	 * @since 1.12.0
	 *
	 * @param string $value Date as the provider answered it.
	 *
	 * @return bool
	 */
	private function isDateTime( $value ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?/', $value, $parts ) ) {
			return false;
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] )
			&& (int) $parts[4] <= 23
			&& (int) $parts[5] <= 59
			&& ( ! isset( $parts[6] ) || (int) $parts[6] <= 59 );
	}

	/**
	 * Sanitized text of a scalar field, empty when it is missing.
	 *
	 * @since 1.12.0
	 *
	 * @param array  $values Values of the block.
	 * @param string $field  Field to read.
	 *
	 * @return string
	 */
	private function text( array $values, $field ) {
		return isset( $values[ $field ] ) && is_scalar( $values[ $field ] ) && ! is_bool( $values[ $field ] )
			? sanitize_text_field( (string) $values[ $field ] )
			: '';
	}

	/**
	 * Drop the zeros on the left of a number given in digits.
	 *
	 * @since 1.12.0
	 *
	 * @param string $value Number as text.
	 *
	 * @return string The number without padding, or the text as it came when it is not digits.
	 */
	private function unpadded( $value ) {
		if ( ! ctype_digit( $value ) ) {
			return $value;
		}

		$number = ltrim( $value, '0' );

		return '' === $number ? '0' : $number;
	}

	/**
	 * Total of the invoice in whole cents.
	 *
	 * @since 1.12.0
	 *
	 * @param mixed $value Total as the provider answered it.
	 *
	 * @return int|null Null when it is not a whole, positive number of cents.
	 */
	private function cents( $value ) {
		if ( is_int( $value ) && $value >= 0 ) {
			return $value;
		}

		return is_string( $value ) && ctype_digit( $value ) ? (int) $value : null;
	}
}
