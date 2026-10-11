<?php

namespace Infixs\CorreiosAutomatico\Integrations\Pingo;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Services\ReturnService;
use Infixs\CorreiosAutomatico\Utils\ShipmentCodesFormatter;
use Infixs\PingoNotify\Triggers\Trigger;
use Infixs\PingoNotify\Triggers\Traits\OrderTrigger;

defined( 'ABSPATH' ) || exit;

/**
 * Pingo Notify trigger fired when a customer confirms a return (devolução).
 *
 * Registered via the `infixs_pingo_notify_trigger_classes` filter only when the
 * Pingo Notify plugin is active. Fired through the
 * `infixs_correios_automatico_customer_return_confirmed` action carrying the
 * order id and (when available) the reverse tracking codes.
 *
 * @since 1.8.0
 */
class CustomerReturnTrigger extends Trigger {
	use OrderTrigger;

	public function __construct() {
		$this->id = ReturnService::RETURN_TRIGGER_ID;
		$this->name = __( 'Devolução solicitada', 'infixs-correios-automatico' );
		$this->description = __( 'Dispara quando o cliente confirma a devolução de um pedido.', 'infixs-correios-automatico' );
		$this->group_id = 'woocommerce';
		$this->type = 'wp_action';
		$this->hook = ReturnService::RETURN_TRIGGER_HOOK;
		$this->hook_args = 3;
		$this->hook_priority = 10;
	}

	/**
	 * @since 1.8.0
	 * @since 1.11.0 Receives the code of every volume returned.
	 *
	 * @param int     $order_id
	 * @param string  $return_code  Reverse codes, separated by commas (may be empty).
	 * @param array[] $return_codes Reverse codes, each with `code` and `shipping_item_id`.
	 */
	protected function transform( ...$args ) {
		$order_id = isset( $args[0] ) ? $args[0] : 0;
		$return_code = isset( $args[1] ) ? $args[1] : '';
		$return_codes = isset( $args[2] ) && is_array( $args[2] ) ? $args[2] : [];

		if ( ! function_exists( 'wc_get_order' ) ) {
			return [];
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return [];
		}

		$data = $this->transformOrder( $order );

		if ( empty( $return_code ) && ! $return_codes ) {
			$return_codes = $this->getRecordedReturnCodes( $order );
			$return_code = implode( ', ', wp_list_pluck( $return_codes, 'code' ) );
		}

		$data['order']['return_code'] = $return_code;
		$data['order']['return_codes_text'] = $return_codes ? implode( "\n", ShipmentCodesFormatter::lines( new Order( $order ), $return_codes ) ) : $return_code;

		return $data;
	}

	/**
	 * Get the reverse codes recorded on an order.
	 *
	 * Falls back to the last reverse code, the only one recorded before 1.11.0.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order returned.
	 *
	 * @return array[] Each with `code` and `shipping_item_id`.
	 */
	private function getRecordedReturnCodes( $order ) {
		$reverse_preposts = Container::prepostService()->getReversePreposts( $order );

		if ( $reverse_preposts ) {
			return $reverse_preposts;
		}

		$return_code = $order->get_meta( '_infixs_correios_automatico_reverse_prepost_code' );

		return $return_code ? [ [ 'code' => $return_code, 'shipping_item_id' => 0 ] ] : [];
	}

	protected function placeholders() {
		$placeholders = $this->getOrderPlaceholders();

		$placeholders[] = [
			'path' => 'order.return_code',
			'name' => __( 'Código de Devolução', 'infixs-correios-automatico' ),
			'type' => 'string',
			'description' => __( 'Código de postagem reversa para o cliente levar à agência dos Correios. Numa devolução em volumes, os códigos vêm separados por vírgula.', 'infixs-correios-automatico' ),
		];

		$placeholders[] = [
			'path' => 'order.return_codes_text',
			'name' => __( 'Códigos de Devolução por Volume', 'infixs-correios-automatico' ),
			'type' => 'string',
			'description' => __( 'Um código de postagem reversa por linha, com o volume de cada um quando a devolução é feita em mais de uma caixa.', 'infixs-correios-automatico' ),
		];

		return $placeholders;
	}
}
