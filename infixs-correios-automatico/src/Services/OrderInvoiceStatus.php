<?php

namespace Infixs\CorreiosAutomatico\Services;

defined( 'ABSPATH' ) || exit;

/**
 * State of the NF-e of an order, as the orders panel shows it.
 *
 * The plugin alone knows only what the order metas and the invoice providers
 * already told it: there is an NF-e, the automatic prepost waits for one, or
 * there is none. The plugin that issues the invoice knows much more — that it
 * is being issued, that SEFAZ refused it, that it was cancelled — and answers
 * through the `infixs_correios_automatico_order_invoice_status` filter, with
 * the links of its own documents, such as the full DANFE.
 *
 * Whatever comes back is checked before it reaches the panel: an unknown state
 * or tone falls back to the default, a link only points to this site, and no
 * more than four links are kept.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.12.0
 */
class OrderInvoiceStatus {

	/**
	 * States of an invoice, each with the tone it is shown in when the
	 * provider gives none.
	 *
	 * @since 1.12.0
	 *
	 * @var array<string, string>
	 */
	const STATE_TONES = [
		'none' => 'muted',
		'pending' => 'warning',
		'authorized' => 'success',
		'error' => 'danger',
		'cancelled' => 'muted',
	];

	/**
	 * Tones the panel paints the state with.
	 *
	 * @since 1.12.0
	 *
	 * @var string[]
	 */
	const TONES = [ 'muted', 'warning', 'success', 'danger' ];

	/**
	 * Readiness of the simplified DANFE of the invoice.
	 *
	 * @since 1.12.0
	 *
	 * @var string[]
	 */
	const DANFE_STATES = [ 'ready', 'pending', 'unavailable', 'none' ];

	/**
	 * Most links of the provider kept, so the menu of the order stays short.
	 *
	 * @since 1.12.0
	 *
	 * @var int
	 */
	const MAX_ACTIONS = 4;

	/**
	 * Build the state of the NF-e of an order and let the provider replace it.
	 *
	 * @since 1.12.0
	 *
	 * @param \WC_Order $order   Order.
	 * @param array     $context {
	 *     What the plugin already knows about the invoice of the order.
	 *
	 *     @type int        $vendor_id Marketplace vendor the order is shown to, zero for the store.
	 *     @type array|null $invoice   NF-e of the order, as OrderInvoiceResolver answers it.
	 *     @type bool       $waiting   Whether the automatic prepost waits for the NF-e.
	 *     @type string     $danfe     Status of the simplified DANFE, as OrderDanfeResolver answers it.
	 * }
	 *
	 * @return array{state: string, tone: string, label: string, detail: string, source: string, source_label: string, danfe: string, actions: array}
	 */
	public function resolve( \WC_Order $order, array $context = [] ) {
		$context = [
			'vendor_id' => isset( $context['vendor_id'] ) ? absint( $context['vendor_id'] ) : 0,
			'invoice' => isset( $context['invoice'] ) && is_array( $context['invoice'] ) ? $context['invoice'] : null,
			'waiting' => ! empty( $context['waiting'] ),
			'danfe' => isset( $context['danfe'] ) ? (string) $context['danfe'] : OrderDanfeResolver::STATUS_NONE,
		];

		$default = $this->defaultStatus( $context );

		/**
		 * Filters the state of the NF-e of an order shown in the orders panel.
		 *
		 * The provider that issues the invoice answers with its own state and
		 * the links of its documents; a value that is not a list keeps the
		 * default. Unknown states, tones and readiness fall back to the default,
		 * links must point to this site and only the first four are kept.
		 *
		 * @since 1.12.0
		 *
		 * @param array     $status {
		 *     State of the invoice.
		 *
		 *     @type string $state        `none`, `pending`, `authorized`, `error` or `cancelled`.
		 *     @type string $tone         `muted`, `warning`, `success` or `danger`.
		 *     @type string $label        Short text of the state, e.g. `NF-e nº 123`.
		 *     @type string $detail       Longer text, e.g. the message of an error.
		 *     @type string $source       Slug of the provider.
		 *     @type string $source_label Name of the provider shown to the merchant.
		 *     @type string $danfe        `ready`, `pending`, `unavailable` or `none`: whether the simplified DANFE can be printed.
		 *     @type array  $actions      Links of the provider, each with `id`, `label`, `url` and an optional `target` `_blank`.
		 * }
		 * @param \WC_Order $order   Order.
		 * @param array     $context {
		 *     @type int        $vendor_id Marketplace vendor the order is shown to, zero for the store.
		 *     @type array|null $invoice   NF-e of the order.
		 *     @type bool       $waiting   Whether the automatic prepost waits for the NF-e.
		 * }
		 */
		$filtered = apply_filters( 'infixs_correios_automatico_order_invoice_status', $default, $order, [
			'vendor_id' => $context['vendor_id'],
			'invoice' => $context['invoice'],
			'waiting' => $context['waiting'],
		] );

		return is_array( $filtered ) ? $this->sanitize( $filtered, $default ) : $default;
	}

	/**
	 * State the plugin tells by itself, from the invoice and the wait.
	 *
	 * @since 1.12.0
	 *
	 * @param array $context Normalized context of resolve().
	 *
	 * @return array
	 */
	private function defaultStatus( array $context ) {
		$invoice = $context['invoice'];
		$danfe = OrderDanfeResolver::STATUS_INVALID === $context['danfe'] ? 'unavailable' : $context['danfe'];

		if ( $invoice ) {
			return $this->status(
				'authorized',
				/* translators: %s: number of the invoice. */
				sprintf( __( 'NF-e nº %s', 'infixs-correios-automatico' ), $invoice['invoice_number'] ),
				$invoice['source'],
				$invoice['source_label'],
				in_array( $danfe, self::DANFE_STATES, true ) ? $danfe : 'unavailable'
			);
		}

		if ( $context['waiting'] ) {
			return $this->status( 'pending', __( 'Pré-postagem automática aguardando a NF-e', 'infixs-correios-automatico' ), '', '', 'none' );
		}

		return $this->status( 'none', __( 'Sem NF-e', 'infixs-correios-automatico' ), '', '', 'none' );
	}

	/**
	 * Build a state with the tone of its kind, no detail and no links.
	 *
	 * @since 1.12.0
	 *
	 * @param string $state        One of the keys of STATE_TONES.
	 * @param string $label        Short text of the state.
	 * @param string $source       Slug of the provider.
	 * @param string $source_label Name of the provider.
	 * @param string $danfe        Readiness of the simplified DANFE.
	 *
	 * @return array
	 */
	private function status( $state, $label, $source, $source_label, $danfe ) {
		return [
			'state' => $state,
			'tone' => self::STATE_TONES[ $state ],
			'label' => $label,
			'detail' => '',
			'source' => $source,
			'source_label' => $source_label,
			'danfe' => $danfe,
			'actions' => [],
		];
	}

	/**
	 * Check the state a provider answered, field by field.
	 *
	 * @since 1.12.0
	 *
	 * @param array $status  State as the provider answered it.
	 * @param array $default State the plugin tells by itself.
	 *
	 * @return array
	 */
	private function sanitize( array $status, array $default ) {
		$state = $this->text( $status, 'state' );
		$state = isset( self::STATE_TONES[ $state ] ) ? $state : $default['state'];

		$tone = $this->text( $status, 'tone' );
		$label = $this->text( $status, 'label' );
		$source = sanitize_key( $this->text( $status, 'source' ) );
		$source_label = $this->text( $status, 'source_label' );
		$danfe = $this->text( $status, 'danfe' );

		return [
			'state' => $state,
			'tone' => in_array( $tone, self::TONES, true ) ? $tone : self::STATE_TONES[ $state ],
			'label' => '' !== $label ? $label : $default['label'],
			'detail' => $this->text( $status, 'detail' ),
			'source' => '' !== $source ? $source : $default['source'],
			'source_label' => '' !== $source_label ? $source_label : $default['source_label'],
			'danfe' => in_array( $danfe, self::DANFE_STATES, true ) ? $danfe : $default['danfe'],
			'actions' => $this->actions( isset( $status['actions'] ) ? $status['actions'] : [] ),
		];
	}

	/**
	 * Keep the links of the provider that point to this site, at most four.
	 *
	 * @since 1.12.0
	 *
	 * @param mixed $actions Links as the provider answered them.
	 *
	 * @return array<int, array{id: string, label: string, url: string, target: string}>
	 */
	private function actions( $actions ) {
		if ( ! is_array( $actions ) ) {
			return [];
		}

		$kept = [];

		foreach ( $actions as $action ) {
			if ( count( $kept ) >= self::MAX_ACTIONS ) {
				break;
			}

			if ( ! is_array( $action ) ) {
				continue;
			}

			$id = sanitize_key( $this->text( $action, 'id' ) );
			$label = $this->text( $action, 'label' );
			$url = isset( $action['url'] ) && is_string( $action['url'] ) ? esc_url_raw( $action['url'], [ 'http', 'https' ] ) : '';

			if ( '' === $id || '' === $label || ! $this->isOwnUrl( $url ) ) {
				continue;
			}

			$kept[] = [
				'id' => $id,
				'label' => $label,
				'url' => $url,
				'target' => '_blank' === $this->text( $action, 'target' ) ? '_blank' : '',
			];
		}

		return $kept;
	}

	/**
	 * Whether an address points to this site, on the host of the store or of its admin.
	 *
	 * @since 1.12.0
	 *
	 * @param string $url Address, already escaped.
	 *
	 * @return bool
	 */
	private function isOwnUrl( $url ) {
		$host = '' !== $url ? wp_parse_url( $url, PHP_URL_HOST ) : null;

		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}

		$own = array_filter( [
			wp_parse_url( home_url(), PHP_URL_HOST ),
			wp_parse_url( admin_url(), PHP_URL_HOST ),
		] );

		return in_array( strtolower( $host ), array_map( 'strtolower', $own ), true );
	}

	/**
	 * Sanitized text of a scalar field, empty when it is missing.
	 *
	 * @since 1.12.0
	 *
	 * @param array  $values Values of the state.
	 * @param string $field  Field to read.
	 *
	 * @return string
	 */
	private function text( array $values, $field ) {
		return isset( $values[ $field ] ) && is_scalar( $values[ $field ] ) && ! is_bool( $values[ $field ] )
			? sanitize_text_field( (string) $values[ $field ] )
			: '';
	}
}
