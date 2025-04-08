<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Services\TrackingService;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;
class TrackingController {

	/**
	 * Tracking service instance.
	 * 
	 * @since 1.0.0
	 * 
	 * @var TrackingService
	 */
	protected $trackingService;

	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;
	}

	/**
	 * Create a tracking code.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function create( $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['order_id'] ) ) {
			return new \WP_Error( 'order_id_not_found', __( 'Order id not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		if ( ! isset( $params['code'] ) ) {
			return new \WP_Error( 'tracking_code_not_found', __( 'Tracking code not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		$order = wc_get_order( $params['order_id'] );
		$send_email = isset( $params['sendmail'] ) && $params['sendmail'] === true ? true : false;

		$tracking_code = apply_filters( 'infixs_correios_automatico_tracking_create', $params['code'], $params );
		$created_tracking = $this->trackingService->add( $order->get_id(), $tracking_code, $send_email );

		if ( is_wp_error( $created_tracking ) ) {
			return $created_tracking;
		}

		if ( ! $created_tracking ) {
			return new \WP_Error( 'tracking_code_not_created', __( 'Tracking code not created.', 'infixs-correios-automatico' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [ 
			"status" => "success",
			"data" => [ 
				"id" => $created_tracking->id,
				"code" => $created_tracking->code,
			]
		] );
	}

	/**
	 * Delete a tracking code.
	 * 
	 * @since 1.0.0
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function delete( $request ) {
		$tracking_code_id = apply_filters( 'infixs_correios_automatico_tracking_delete', $request['id'], $request );
		$removed = $this->trackingService->delete( $tracking_code_id );

		if ( ! $removed ) {
			return new \WP_Error( 'tracking_code_not_found', __( 'Tracking code not found.', 'infixs-correios-automatico' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( [ 
			"status" => "success",
		] );
	}


	/**
	 * Retrieve a tracking code.
	 * 
	 * @since 1.2.2
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function retrieve( $request ) {
		$params = $request->get_params();

		$force_sync = isset( $params['force_sync'] ) ? Sanitizer::boolean( $params['force_sync'] ) : false;

		$tracking = $this->trackingService->getObjectTrackingById( $params['id'], true, $force_sync );

		if ( is_wp_error( $tracking ) ) {
			return $tracking;
		}

		return rest_ensure_response( [ 
			"status" => "success",
			"tracking" => $tracking,
		] );
	}

	/**
	 * List tracking codes.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function list( $request ) {
		$params = $request->get_params();

		$tracking_codes = $this->trackingService->list();

		return rest_ensure_response( [ 
			"status" => "success",
			"data" => [],
		] );
	}

	/**
	 * Send tracking notification.
	 * 
	 * @since 1.2.3
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function tracking_notification( $request ) {
		$params = $request->get_params();

		$order_id = $params['order_id'];
		$change_status = $params['change_status'] ?? false;

		$tracking = $this->trackingService->getTrackings( $order_id );
		$tracking_codes = $tracking->pluck( 'code' )->toArray();
		$email_send = $this->trackingService->sendTrackingNotification( $order_id, $tracking_codes );

		if ( $email_send ) {
			TrackingCode::update( [ 
				'customer_email_at' => current_time( 'mysql' ),
			], [ 
				'order_id' => $order_id,
			] );

			if ( $change_status ) {
				$order = wc_get_order( $order_id );
				$order->update_status( $change_status );
			}
		}

		return rest_ensure_response( [ 
			"success" => $email_send,
		] );
	}

	/**
	 * Suspend shipping.
	 * 
	 * @since 1.4.6
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function suspend_shipping( $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['id'] ) || empty( $params['id'] ) ) {
			return new \WP_Error( 'missing_id', 'ID is required.', [ 'status' => 400 ] );
		}

		$response = $this->trackingService->suspend_shipping( $params['id'] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( [ 
			"status" => "success",
		] );
	}
}