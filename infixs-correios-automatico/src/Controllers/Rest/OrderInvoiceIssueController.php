<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Services\OrderInvoiceIssuer;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints that issue the NF-e of an order through the invoice issuer.
 *
 * The dashboard starts the issue and then reads it again while the SEFAZ
 * answers. Both answer `{ data: <result> }`, the result checked by
 * OrderInvoiceIssuer, and a 409 `invoice_issuer_unavailable` when no plugin
 * issues invoices for the order. The issue also answers the 409 of an order
 * OrderInvoiceIssuer refuses: `invoice_already_present`,
 * `invoice_vendor_order` or `invoice_order_not_sold`.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.15.0
 */
class OrderInvoiceIssueController {

	/**
	 * Issuer of the NF-e of the orders.
	 *
	 * @var OrderInvoiceIssuer
	 */
	private $issuer;

	/**
	 * @param OrderInvoiceIssuer $issuer Issuer of the NF-e of the orders.
	 */
	public function __construct( OrderInvoiceIssuer $issuer ) {
		$this->issuer = $issuer;
	}

	/**
	 * POST /orders/{id}/invoice/issue — issue the NF-e of the order.
	 *
	 * @since 1.15.0
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function issue( $request ) {
		$order = $this->order( $request );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		return $this->answer( $this->issuer->issue( $order ) );
	}

	/**
	 * POST /orders/{id}/invoice/issue/refresh — read how the issue of the NF-e is going.
	 *
	 * @since 1.15.0
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function refresh( $request ) {
		$order = $this->order( $request );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		return $this->answer( $this->issuer->refresh( $order ) );
	}

	/**
	 * Order of the request.
	 *
	 * @since 1.15.0
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WC_Order|\WP_Error
	 */
	private function order( $request ) {
		$order = wc_get_order( absint( $request['id'] ) );

		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'invalid_order', __( 'Pedido inválido.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		return $order;
	}

	/**
	 * Answer the result of the issuer, the refusal of the issue, or say that no plugin issues the invoice.
	 *
	 * @since 1.15.0
	 *
	 * @param array|\WP_Error|null $result Result checked by OrderInvoiceIssuer.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	private function answer( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( null === $result ) {
			return new \WP_Error(
				'invoice_issuer_unavailable',
				__( 'Nenhum plugin emissor de NF-e atende este pedido.', 'infixs-correios-automatico' ),
				[ 'status' => 409 ]
			);
		}

		return rest_ensure_response( [ 'data' => $result ] );
	}
}
