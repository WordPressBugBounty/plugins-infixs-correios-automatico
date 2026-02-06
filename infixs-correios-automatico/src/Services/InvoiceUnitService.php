<?php
namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Repositories\InvoiceUnitRepository;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;

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
			'status' => 'open',
			'contract_number' => Config::string( 'general.auth.contract_number' ),
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
				'status' => 'open'
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
	 * @param array $options Additional options for retrieval
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\InvoiceUnit|\WP_Error
	 */
	public function getInvoiceById( $invoice_id, $options = [] ) {
		$invoice = $this->invoiceUnitRepository->findById( $invoice_id, $options );

		if ( ! $invoice ) {
			return new \WP_Error( 'invoice_not_found', __( 'Invoice not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		return $invoice;
	}

	public function listInvoices( $filters = [] ) {
		$criteria = [
			'order_by' => 'id',
			'order' => 'desc',
			'relations' => [ 'units' ],
		];

		if ( isset( $filters['ids'] ) && is_array( $filters['ids'] ) && ! empty( $filters['ids'] ) ) {
			$criteria['where']['id'] = $filters['ids'];
		}

		if ( isset( $filters['where'] ) && is_array( $filters['where'] ) ) {
			$criteria['where'] = isset( $criteria['where'] )
				? array_merge( $criteria['where'], $filters['where'] )
				: $filters['where'];
		}

		return $this->invoiceUnitRepository->paginate( $criteria, [ $this, 'prepareData' ] );
	}

	public function prepareData( $data ) {
		if ( ! $data ) {
			return null;
		}

		return [
			'id' => (int) $data->id,
			'status' => $data->status,
			'units' => isset( $data->units ) ? array_filter( $data->units->map( [ Container::unitService(), 'prepareData' ] ) ) : [],
			'service_code' => $data->service_code,
			'service_name' => DeliveryServiceCode::getShortDescription( $data->service_code ),
			'cn38_code' => $data->cn38_code,
			'shipment_date' => date_i18n( 'd/m/Y', strtotime( current_time( 'mysql' ) ) ),
			'contract_number' => $data->contract_number,
			'created_at' => $data->created_at,
		];
	}

	public function register( $invoice_unit_id ) {

		$invoice_unit = $this->getInvoiceById( $invoice_unit_id, [ 'relations' => [ 'units' ] ] );

		if ( is_wp_error( $invoice_unit ) ) {
			return $invoice_unit;
		}

		$dispatch_numbers = $invoice_unit->units->pluck( 'dispatch_number' )->toArray();

		$result = Container::correiosService()->register_invoice_unit( [
			'dispatchNumbers' => $dispatch_numbers
		] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$invoice_unit->request_id = isset( $result['requestId'] ) ? $result['requestId'] : null;
		$invoice_unit->status = isset( $result['requestStatus'] ) ? strtolower( $result['requestStatus'] ) : 'open';
		$invoice_unit->contract_number = Config::string( 'auth.contract_number' );

		sleep( 5 );

		$result = Container::correiosService()->get_invoice_unit_by_request( $result['requestId'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( isset( $result['requestStatus'] ) && $result['requestStatus'] === 'Error' ) {
			$invoice_unit->status = 'error';
			$invoice_unit->save();

			return new \WP_Error( 'invoice_registration_error', $result['errorMessage'], [ 'status' => 500 ] );
		}

		if ( isset( $result['requestStatus'] ) && isset( $result['cn38Code'] ) && $result['requestStatus'] === 'Success' ) {
			$invoice_unit->status = 'registered';
			$invoice_unit->cn38_code = $result['cn38Code'];
		}

		$invoice_unit->save();

		return $result;
	}
}