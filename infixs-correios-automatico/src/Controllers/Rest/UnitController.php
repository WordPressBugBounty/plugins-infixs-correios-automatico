<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Services\TrackingService;
use Infixs\CorreiosAutomatico\Services\UnitService;

defined( 'ABSPATH' ) || exit;
class UnitController {
	/**
	 * Unit service instance.
	 * 
	 * @since 1.5.0
	 * 
	 * @var \Infixs\CorreiosAutomatico\Services\UnitService
	 */
	private $unitService;

	/**
	 * Tracking service instance.
	 * 
	 * @since 1.5.1
	 * 
	 * @var \Infixs\CorreiosAutomatico\Services\TrackingService
	 */
	private $trackingService;

	public function __construct( UnitService $unitService, TrackingService $trackingService ) {
		$this->unitService = $unitService;
		$this->trackingService = $trackingService;
	}

	/**
	 * List units.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function list( $request ) {
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$search = $request->get_param( 'search' );
		$unit_id = $request->get_param( 'unit_id' );

		$params = [];

		if ( $page !== null ) {
			$params['page'] = (int) $page;
		}

		if ( $per_page !== null ) {
			$params['per_page'] = (int) $per_page;
		}

		if ( $search !== null ) {
			$params['search'] = $search;
		}

		if ( $unit_id !== null ) {
			$params['unit_id'] = $unit_id;
		}

		$units = $this->unitService->getUnits( $params );

		return rest_ensure_response( $units->toArray( 'units' ) );
	}

	/**
	 * List unit trackings.
	 * 
	 * @since 1.5.1
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function trackings( $request ) {
		$unit_id = $request->get_param( 'id' );

		$trackings = $this->trackingService->getByUnit( $unit_id );

		return rest_ensure_response( $trackings->toArray( 'trackings' ) );
	}

	/**
	 * Remove unit tracking.
	 * 
	 * @since 1.5.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function remove_tracking( $request ) {
		$unit_id = (int) $request->get_param( 'id' );
		$tracking_id = (int) $request->get_param( 'tracking_id' );

		if ( ! $unit_id ) {
			return new \WP_Error( 'invalid_unit_id', __( 'Invalid unit ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		if ( ! $tracking_id ) {
			return new \WP_Error( 'invalid_tracking_id', __( 'Invalid tracking ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$removed = $this->trackingService->removeUnit( $unit_id, $tracking_id );

		if ( ! $removed ) {
			return new \WP_Error( 'tracking_code_not_found', __( 'Tracking code not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( [ 
			"status" => "success",
		] );
	}

	/**
	 * Register tracking code in unit.
	 * 
	 * @since 1.5.1
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function register( $request ) {
		$unit_id = (int) $request->get_param( 'id' );

		if ( ! $unit_id ) {
			return new \WP_Error( 'invalid_unit_id', __( 'Invalid unit ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$registered = $this->unitService->register( $unit_id );

		if ( is_wp_error( $registered ) ) {
			return $registered;
		}

		return rest_ensure_response( [ 
			"status" => "success",
			"data" => $registered
		] );
	}

	/**
	 * Update unit.
	 * 
	 * @since 1.5.1
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function update( $request ) {
		$unit_id = (int) $request->get_param( 'id' );
		$dispatch_number = (int) $request->get_param( 'dispatch_number' );
		$service_code = $request->get_param( 'service_code' );
		$ceint_code = $request->get_param( 'ceint_code' );

		$updated = $this->unitService->update( $unit_id, [ 
			'dispatch_number' => $dispatch_number,
			'service_code' => $service_code,
			'ceint_code' => $ceint_code
		] );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		return rest_ensure_response( [ 
			"status" => "success"
		] );
	}

	/**
	 * Add unit to an invoice.
	 * 
	 * @since 1.6.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function add_to_invoice( $request ) {
		$unit_id = (int) $request->get_param( 'unit_id' );

		if ( ! $unit_id ) {
			return new \WP_Error( 'invalid_unit_id', __( 'Invalid unit ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		// // Create a new invoice and add the unit
		// $new_invoice = $this->unitService->createInvoiceWithUnit( $unit_id );

		// if ( is_wp_error( $new_invoice ) ) {
		// 	return $new_invoice;
		// }

		return rest_ensure_response( [ 
			"status" => "success",
			"message" => __( 'New invoice created and unit added.', 'infixs-correios-automatico' ),
			///"data" => $new_invoice
		] );
	}
}