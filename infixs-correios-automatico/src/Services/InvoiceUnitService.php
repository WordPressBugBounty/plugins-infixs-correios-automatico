<?php
namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Repositories\InvoiceUnitRepository;

defined( 'ABSPATH' ) || exit;

class InvoiceUnitService {

	/**
	 * @var InvoiceUnitRepository
	 */
	private $invoiceUnitRepository;

	public function __construct( InvoiceUnitRepository $invoiceUnitRepository ) {
		$this->invoiceUnitRepository = $invoiceUnitRepository;
	}

	/**
	 * Create a new invoice for a specific service code
	 * 
	 * @param string $service_code Service code for the invoice
	 * @param array $additional_data Additional data for invoice creation
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\InvoiceUnit|\WP_Error
	 */
	public function createInvoice( $service_code, $additional_data = [] ) {
		$invoice_data = array_merge( [ 
			'service_code' => $service_code,
			'status' => 'pending',
			'created_at' => current_time( 'mysql' )
		], $additional_data );

		try {
			$invoice = $this->invoiceUnitRepository->create( $invoice_data );

			if ( ! $invoice ) {
				return new \WP_Error( 'invoice_creation_failed', __( 'Failed to create invoice.', 'infixs-correios-automatico' ), [ 'status' => 500 ] );
			}

			return $invoice;
		} catch (\Exception $e) {
			return new \WP_Error( 'invoice_creation_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Find or create an invoice for a specific service code
	 * 
	 * @param string $service_code Service code
	 * @param array $search_criteria Additional search criteria
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\InvoiceUnit|\WP_Error
	 */
	public function findOrCreateInvoice( $service_code, $search_criteria = [] ) {
		// Try to find existing invoice
		$default_criteria = [ 
			'where' => [ 
				'service_code' => $service_code,
				'status' => 'pending'
			]
		];

		$criteria = array_merge_recursive( $default_criteria, $search_criteria );

		$existing_invoice = $this->invoiceUnitRepository->findOne( $criteria );

		if ( $existing_invoice ) {
			return $existing_invoice;
		}

		// Create new invoice if none found
		return $this->createInvoice( $service_code );
	}

	/**
	 * Get invoice by ID
	 * 
	 * @param int $invoice_id Invoice ID
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\InvoiceUnit|\WP_Error|null
	 */
	public function getInvoiceById( $invoice_id ) {
		$invoice = $this->invoiceUnitRepository->findById( $invoice_id );

		if ( ! $invoice ) {
			return new \WP_Error( 'invoice_not_found', __( 'Invoice not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		return $invoice;
	}

	public function listInvoices() {
		return $this->invoiceUnitRepository->find( [ 
			'order_by' => 'id',
			'order' => 'desc'
		] );
	}
}