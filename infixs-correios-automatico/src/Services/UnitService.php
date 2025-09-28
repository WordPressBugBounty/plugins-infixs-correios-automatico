<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Models\Unit;
use Infixs\CorreiosAutomatico\Repositories\UnitRepository;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\CeintCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;


defined( 'ABSPATH' ) || exit;

class UnitService {

	/**
	 * @var UnitRepository
	 */
	private $unitRepository;

	/**
	 * @var InvoiceUnitService
	 */
	private $invoiceUnitService;


	public function __construct( UnitRepository $unitRepository, InvoiceUnitService $invoiceUnitService ) {
		$this->invoiceUnitService = $invoiceUnitService;
		$this->unitRepository = $unitRepository;
	}

	public function getUnits( $params ) {
		$paginate_params = [
			'order_by' => 'id',
			'order' => 'desc',
			'relations' => [ 'codes', 'invoice_unit' ],
		];

		if ( isset( $params['per_page'] ) ) {
			$paginate_params['per_page'] = $params['per_page'];
		}

		if ( isset( $params['page'] ) ) {
			$paginate_params['current_page'] = $params['page'];
		}

		if ( ! empty( $params['unit_id'] ) ) {
			if ( ! is_array( $params['unit_id'] ) ) {
				$paginate_params['where']['id'] = $params['unit_id'];
			} else {
				$paginate_params['whereIn']['id'] = $params['unit_id'];
			}
		}

		return $this->unitRepository->paginate( $paginate_params, [ $this, 'prepareData' ] );
	}

	public function getAllUnits( $params ) {
		$default_params = [
			'order_by' => 'id',
			'order' => 'desc',
			'relations' => [ 'codes' ],
			'where' => []
		];

		$params = array_merge( $default_params, $params );

		return $this->unitRepository->find( $params );
	}

	public function register( $unit_id ) {
		/**
		 * @var Unit $unit
		 */
		$unit = $this->unitRepository->findById( $unit_id, [
			'relations' => [ 'codes' ]
		] );

		if ( ! $unit ) {
			return new \WP_Error( 'unit_not_found', __( 'Unit not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		$origin_country = Config::string( 'sender.address_country' );
		$operator_name = $this->processOperatorName( Config::string( 'sender.name' ) );
		$service = DeliveryServiceCode::PACKET_EXPRESS === $unit->service_code ? 'IX' : 'NX';

		/** TODO: Sequence and Unit Type */
		$result = Container::correiosService()->register_packet_unit( [
			'dispatchNumber' => (int) $unit->dispatch_number,
			'originCountry' => $origin_country,
			'originOperatorName' => $operator_name,
			'destinationOperatorName' => 'CWBA',
			'postalCategoryCode' => 'D',
			'serviceSubclassCode' => $service,
			'unitList' => [
				0 => [
					'sequence' => 1,
					'unitType' => 2,  //pallet = 2, bag = 1
					'trackingNumbers' => $unit->codes->pluck( 'code' )->toArray()
				]
			]
		] );

		if ( is_wp_error( $result ) )
			return $result;

		if ( ! isset( $result['unitResponseList'], $result['unitResponseList'][0], $result['unitResponseList'][0]['unitCode'] ) ) {
			return new \WP_Error( 'unit_not_registered', __( 'Unit not registered.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$unit_code = $result['unitResponseList'][0]['unitCode'];

		$unit->unit_code = $unit_code;
		$unit->status = 'registered';

		$unit->save();

		return $result;
	}


	/**
	 * Create pending unit
	 * 
	 * @since 1.5.1
	 * 
	 * @param int $ceint_id Ceint ID
	 * @param string $service_code Service code
	 * 
	 * @return Unit
	 */
	public function createPendingUnit( $ceint_id, $service_code ) {
		return $this->unitRepository->create( [
			'dispatch_number' => $this->getAndIncrementDispatchNumber(),
			'status' => 'pending',
			'ceint_id' => $ceint_id,
			'service_code' => $service_code,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		] );
	}


	private function processOperatorName( $operator_name ) {
		$operator_name = str_replace( ' ', '', $operator_name );
		$operator_name = substr( $operator_name, 0, 4 );
		$operator_name = strtoupper( $operator_name );
		$operator_name = str_pad( $operator_name, 4, 'X', STR_PAD_RIGHT );

		return $operator_name;
	}

	public function prepareData( Unit $data ) {
		$ceint = $data->ceint_id ? CeintCode::getCeintById( (int) $data->ceint_id ) : null;

		$weight = 0;

		if ( isset( $data->codes ) ) {
			/** @var TrackingCode $code */
			foreach ( $data->codes->all() as $code ) {
				$order = Order::fromId( $code->order_id );
				$items = $order->getShippingItemsData();

				foreach ( $items as $item ) {
					$weight += $item['weight'];
				}
			}
		}

		return [
			'id' => $data->id,
			'status' => $data->status,
			'dispatch_number' => $data->dispatch_number,
			'service_name' => DeliveryServiceCode::getShortDescription( $data->service_code ),
			'service_code' => $data->service_code,
			'unit_code' => $data->unit_code,
			'total_codes' => isset( $data->codes ) ? $data->codes->count() : 0,
			'codes' => isset( $data->codes ) ? array_filter( $data->codes->map( [ $this, 'prepareCodeData' ] ) ) : [],
			'weight' => $weight, //TODO: temp, use unit_items, deprecated
			'ceint' => $ceint,
			'invoice_unit' => isset( $data->invoice_unit ) ? Container::invoiceUnitService()->prepareData( $data->invoice_unit ) : null,
			'created_at' => $data->created_at,
		];
	}

	public function prepareCodeData( TrackingCode $data ) {
		if ( ! $data->order_id )
			return null;

		return [
			'id' => (int) $data->id,
			'code' => $data->code,
			'order_id' => (int) $data->order_id,
		];
	}

	public function update( $id, $data ) {
		$unit = $this->unitRepository->findById( $id );

		if ( ! $unit ) {
			return new \WP_Error( 'unit_not_found', __( 'Unit not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		$success = Unit::update(
			[
				'dispatch_number' => $data['dispatch_number'],
				'service_code' => $data['service_code'],
				'ceint_id' => $data['ceint_code'],
				'updated_at' => current_time( 'mysql' )
			],
			[
				'id' => $id
			]
		);

		if ( ! $success ) {
			return new \WP_Error( 'unit_not_updated', __( 'Unit not updated.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		return true;
	}

	/**
	 * Unit packet
	 * 
	 * @since 1.3.8
	 * 
	 * @param int $order_id Order ID.
	 * 
	 * @return \WP_Error|bool
	 */
	public function unitPacketByOrder( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'invalid_order_id', 'Invalid Order ID.', [ 'status' => 400 ] );
		}

		$caOrder = new Order( $order );

		$address = $caOrder->getAddress();

		$ceint = Container::shippingService()->getCeintByPostCode( $address->getPostCode() );

		if ( ! $ceint ) {
			return new \WP_Error( 'invalid_post_code', 'Invalid Post Code.', [ 'status' => 400 ] );
		}

		$product_code = $caOrder->getShippingProductCode();

		if ( ! $product_code ) {
			return new \WP_Error( 'invalid_product_code', 'Invalid Product Code.', [ 'status' => 400 ] );
		}

		if ( $product_code !== DeliveryServiceCode::PACKET_EXPRESS && $product_code !== DeliveryServiceCode::PACKET_STANDARD ) {
			return new \WP_Error( 'invalid_product_code', 'Invalid Product Code. Need international packet service.', [ 'status' => 400 ] );
		}


		$unit = $this->unitRepository->findOne( [
			'where' => [
				'status' => 'pending',
				'ceint_id' => $ceint['id'],
				'service_code' => $product_code
			]
		] ) ?? $this->createPendingUnit( $ceint['id'], $product_code );

		$codes = Container::trackingService()->getTrackings( $order_id, false, true );

		foreach ( $codes->all() as $code ) {
			if ( $code->unit_id == $unit->id ) {
				continue;
			}

			if ( isset( $code->unit ) && $code->unit->status != 'open' ) {
				continue;
			}

			$code->unit_id = $unit->id;
			$code->save();
		}

		return true;
	}

	public function getAndIncrementDispatchNumber() {
		$currentDispatchNumber = Config::integer( 'unit.current_dispatch_number', 1 );
		$dispatchNumber = $currentDispatchNumber;
		$currentDispatchNumber++;
		Config::update( 'unit.current_dispatch_number', $currentDispatchNumber );
		return $dispatchNumber;
	}

	/**
	 * Add unit to invoice (creates invoice if not provided)
	 * 
	 * @since 1.6.41
	 * 
	 * @param int $unit_id Unit ID.
	 * @param int|null $invoice_id Invoice ID (optional - will create if not provided).
	 * 
	 * @return \WP_Error|array Success response with invoice and unit data
	 */
	public function addUnitToInvoice( $unit_id, $invoice_id = null ) {
		$unit = $this->unitRepository->findById( $unit_id, [ 'relations' => [ 'invoice_unit' ] ] );

		if ( ! $unit ) {
			return new \WP_Error( 'unit_not_found', __( 'Unit not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		if ( $unit->status !== 'registered' ) {
			return new \WP_Error(
				'unit_not_registered',
				__( 'Only registered units can be added to an invoice.', 'infixs-correios-automatico' ),
				[ 'status' => 400 ]
			);
		}

		if ( isset( $unit->invoice_unit ) && $unit->invoice_unit->status !== 'open' ) {
			return new \WP_Error(
				'unit_already_assigned',
				__( 'Unit is already assigned to a non-open invoice.', 'infixs-correios-automatico' ),
				[ 'status' => 400 ]
			);
		}

		$invoice = $this->resolveInvoice( $invoice_id, $unit->service_code );

		if ( is_wp_error( $invoice ) ) {
			return $invoice;
		}

		if ( $invoice->service_code !== $unit->service_code ) {
			return new \WP_Error(
				'unit_service_code_mismatch',
				__( 'Unit service code does not match invoice service code.', 'infixs-correios-automatico' ),
				[ 'status' => 400 ]
			);
		}

		if ( $unit->invoice_unit_id === $invoice->id ) {
			return new \WP_Error(
				'unit_already_added',
				__( 'Unit is already assigned to this invoice.', 'infixs-correios-automatico' ),
				[ 'status' => 400 ]
			);
		}

		$unit->invoice_unit_id = $invoice->id;

		if ( ! $unit->save() ) {
			return new \WP_Error(
				'unit_assignment_failed',
				__( 'Failed to assign unit to invoice.', 'infixs-correios-automatico' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'success' => true,
			'message' => __( 'Unit successfully added to invoice.', 'infixs-correios-automatico' ),
			'data' => [
				'unit_id' => $unit->id,
				'invoice_id' => $invoice->id,
				'invoice_created' => $invoice_id === null
			]

		];
	}

	/**
	 * Resolve invoice - get existing or create new one
	 * 
	 * @param int|null $invoice_id Invoice ID (optional)
	 * @param string $service_code Service code for invoice creation
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\InvoiceUnit|\WP_Error
	 */
	private function resolveInvoice( $invoice_id, $service_code ) {
		if ( $invoice_id ) {
			return $this->invoiceUnitService->getInvoiceById( $invoice_id );
		}

		return $this->invoiceUnitService->findOrCreateInvoice( $service_code );
	}

	public function cancel( $unit_id ) {
		$unit = $this->unitRepository->findById( $unit_id );

		if ( ! $unit ) {
			return new \WP_Error( 'unit_not_found', __( 'Unit not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		$response = Container::correiosService()->cancel_packet_unit( $unit->unit_code );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$unit->status = 'pending';
		$unit->unit_code = null;
		$unit->invoice_unit_id = null;

		$unit->save();

		return true;
	}
}