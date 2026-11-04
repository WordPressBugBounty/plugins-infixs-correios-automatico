<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

use Infixs\CorreiosAutomatico\Models\Prepost;

defined( 'ABSPATH' ) || exit;

/**
 * Notes on the order what a prepost of the Contrato pré-pago cost, and what
 * went back to the balance when it was cancelled.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidChargeNote {

	/**
	 * Hook the note.
	 *
	 * @since 1.14.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( PrepaidCorreiosApi::CHARGED_ACTION, [ $this, 'addNote' ], 10, 4 );
		add_action( PrepaidCorreiosApi::REFUNDED_ACTION, [ $this, 'addRefundNote' ], 10, 3 );
	}

	/**
	 * Add a private note with the label, what was charged and the balance left.
	 *
	 * A label the API hands back again (the first answer was lost) gets the note
	 * only if the order does not have it yet.
	 *
	 * @since 1.14.0
	 *
	 * @param int      $order_id      Order of the prepost.
	 * @param int|null $charged_cents What was charged, null when the API did not say.
	 * @param int|null $balance_cents Balance after the charge, null when the API did not say.
	 * @param array    $response      Answer of the Correios.
	 *
	 * @return void
	 */
	public function addNote( $order_id, $charged_cents, $balance_cents, $response = [] ) {
		$order = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order || null === $charged_cents ) {
			return;
		}

		$object_code = isset( $response['codigoObjeto'] ) && is_scalar( $response['codigoObjeto'] ) ? sanitize_text_field( (string) $response['codigoObjeto'] ) : '';

		if ( ! empty( $response[ ContractMode::PREPAID_RESPONSE_KEY ]['replayed'] ) && $this->hasChargeNote( $order, $object_code ) ) {
			return;
		}

		$label = '' === $object_code ? '' : sprintf(
			/* translators: %s: object code of the label. */
			__( 'etiqueta %s, ', 'infixs-correios-automatico' ),
			$object_code
		);

		$note = null === $balance_cents
			/* translators: 1: label and its object code, 2: amount charged. */
			? sprintf( __( 'Correios Automático - Pré-pago: %1$s%2$s debitados do saldo da conta Infixs Ship.', 'infixs-correios-automatico' ), $label, PrepaidMoney::format( $charged_cents ) )
			/* translators: 1: label and its object code, 2: amount charged, 3: balance left. */
			: sprintf( __( 'Correios Automático - Pré-pago: %1$s%2$s debitados do saldo da conta Infixs Ship (saldo: %3$s).', 'infixs-correios-automatico' ), $label, PrepaidMoney::format( $charged_cents ), PrepaidMoney::format( $balance_cents ) );

		$order->add_order_note( $note );
	}

	/**
	 * Whether the order already notes the charge of a label.
	 *
	 * @since 1.14.0
	 *
	 * @param \WC_Order $order       Order.
	 * @param string    $object_code Object code of the label; empty matches no note.
	 *
	 * @return bool
	 */
	private function hasChargeNote( $order, $object_code ) {
		if ( '' === $object_code ) {
			return false;
		}

		foreach ( wc_get_order_notes( [ 'order_id' => $order->get_id() ] ) as $note ) {
			if ( false !== strpos( $note->content, 'debitados' ) && false !== strpos( $note->content, $object_code ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add a private note with what a cancelled prepost gave back to the balance.
	 *
	 * @since 1.14.0
	 *
	 * @param string   $external_id    Id of the prepost at the Correios.
	 * @param int      $refunded_cents What went back to the balance.
	 * @param int|null $balance_cents  Balance after the refund, null when the API did not say.
	 *
	 * @return void
	 */
	public function addRefundNote( $external_id, $refunded_cents, $balance_cents ) {
		$prepost = Prepost::where( 'external_id', $external_id )->orderBy( 'id', 'desc' )->limit( 1 )->first();
		$order = $prepost ? wc_get_order( $prepost->order_id ) : null;

		if ( ! $order ) {
			return;
		}

		$note = null === $balance_cents
			/* translators: %s: amount refunded. */
			? sprintf( __( 'Correios Automático - Pré-pago: %s devolvidos ao saldo da conta Infixs Ship.', 'infixs-correios-automatico' ), PrepaidMoney::format( $refunded_cents ) )
			/* translators: 1: amount refunded, 2: balance after the refund. */
			: sprintf( __( 'Correios Automático - Pré-pago: %1$s devolvidos ao saldo da conta Infixs Ship (saldo: %2$s).', 'infixs-correios-automatico' ), PrepaidMoney::format( $refunded_cents ), PrepaidMoney::format( $balance_cents ) );

		$order->add_order_note( $note );
	}
}
