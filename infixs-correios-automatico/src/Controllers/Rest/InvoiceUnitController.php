<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\InvoiceUnitService;
use Infixs\CorreiosAutomatico\Services\TrackingService;
use Infixs\CorreiosAutomatico\Services\UnitService;

defined( 'ABSPATH' ) || exit;
class InvoiceUnitController {

	/**
	 * Invoice Unit service instance.
	 * 
	 * @since 1.6.43
	 * 
	 * @var \Infixs\CorreiosAutomatico\Services\InvoiceUnitService
	 */
	private $invoiceUnitService;
	public function __construct( InvoiceUnitService $invoiceUnitService ) {
		$this->invoiceUnitService = $invoiceUnitService;
	}

	/**
	 * List invoices.
	 * 
	 * @since 1.6.43
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function list( $request ) {
		$filters = array(
			'ids' => $request->get_param( 'ids' ),
		);

		$result = $this->invoiceUnitService->listInvoices( $filters );

		return rest_ensure_response( $result->toArray( 'data' ) );
	}

	/**
	 * List invoices.
	 * 
	 * @since 1.6.43
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function register( $request ) {
		$invoice_unit_id = (int) $request->get_param( 'id' );

		$response = $this->invoiceUnitService->register( $invoice_unit_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( '' );
	}
}