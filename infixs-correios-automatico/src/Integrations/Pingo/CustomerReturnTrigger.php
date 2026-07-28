<?php

namespace Infixs\CorreiosAutomatico\Integrations\Pingo;

use Infixs\CorreiosAutomatico\Services\ReturnService;
use Infixs\PingoNotify\Triggers\Trigger;
use Infixs\PingoNotify\Triggers\Traits\OrderTrigger;

defined( 'ABSPATH' ) || exit;

/**
 * Pingo Notify trigger fired when a customer confirms a return (devolução).
 *
 * Registered via the `infixs_pingo_notify_trigger_classes` filter only when the
 * Pingo Notify plugin is active. Fired through the
 * `infixs_correios_automatico_customer_return_confirmed` action carrying the
 * order id and (when available) the reverse tracking code.
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
		$this->hook_args = 2;
		$this->hook_priority = 10;
	}

	/**
	 * @param int    $order_id
	 * @param string $return_code Reverse tracking code (may be empty).
	 */
	protected function transform( ...$args ) {
		$order_id = isset( $args[0] ) ? $args[0] : 0;
		$return_code = isset( $args[1] ) ? $args[1] : '';

		if ( ! function_exists( 'wc_get_order' ) ) {
			return [];
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return [];
		}

		$data = $this->transformOrder( $order );

		if ( empty( $return_code ) ) {
			$return_code = $order->get_meta( '_infixs_correios_automatico_reverse_prepost_code' );
		}

		$data['order']['return_code'] = $return_code;

		return $data;
	}

	protected function placeholders() {
		$placeholders = $this->getOrderPlaceholders();

		$placeholders[] = [
			'path' => 'order.return_code',
			'name' => __( 'Código de Devolução', 'infixs-correios-automatico' ),
			'type' => 'string',
			'description' => __( 'Código de postagem reversa para o cliente levar à agência dos Correios.', 'infixs-correios-automatico' ),
		];

		return $placeholders;
	}
}
