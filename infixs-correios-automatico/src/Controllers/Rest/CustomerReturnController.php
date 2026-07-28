<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Services\ReturnService;

defined( 'ABSPATH' ) || exit;

/**
 * Customer Return Controller
 *
 * Customer-facing endpoint used from the "view order" page to request a return.
 * Authenticated via the WooCommerce order key (and customer ownership when the
 * customer is logged in).
 *
 * @since 1.8.0
 *
 * @package Infixs\CorreiosAutomatico\Controllers\Rest
 */
class CustomerReturnController {

	/** @var ReturnService */
	protected $returnService;

	/**
	 * @param ReturnService $returnService
	 */
	public function __construct( ReturnService $returnService ) {
		$this->returnService = $returnService;
	}

	/**
	 * Create a customer return request.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function create( $request ) {
		$order_id = absint( $request->get_param( 'order_id' ) );
		$order_key = sanitize_text_field( (string) $request->get_param( 'order_key' ) );

		if ( ! $order_id || empty( $order_key ) ) {
			return new \WP_Error( 'invalid_request', 'Pedido inválido.', [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return new \WP_Error( 'invalid_order_key', 'Não foi possível validar o pedido.', [ 'status' => 403 ] );
		}

		$customer_id = get_current_user_id();
		if ( $customer_id && $order->get_customer_id() && (int) $order->get_customer_id() !== (int) $customer_id ) {
			return new \WP_Error( 'order_not_owned', 'Você não tem acesso a este pedido.', [ 'status' => 403 ] );
		}

		$result = $this->returnService->requestReturn( $order );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}
}
