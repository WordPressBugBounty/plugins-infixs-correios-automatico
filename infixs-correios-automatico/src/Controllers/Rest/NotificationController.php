<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Services\NotificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Notification REST controller.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.0
 */
class NotificationController {

	/**
	 * Notification service instance.
	 *
	 * @since 1.8.0
	 *
	 * @var NotificationService
	 */
	private $notificationService;

	public function __construct( NotificationService $notificationService ) {
		$this->notificationService = $notificationService;
	}

	/**
	 * List notifications.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function list( $request ) {
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$data = $this->notificationService->list( [
			'page' => $page,
			'per_page' => $per_page,
		] );

		return rest_ensure_response( array_merge( [ 'status' => 'success' ], $data ) );
	}

	/**
	 * Mark every notification as read.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function markAllRead( $request ) {
		$this->notificationService->markAllRead();

		return rest_ensure_response( [ 'status' => 'success' ] );
	}

	/**
	 * Mark a single notification as read.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function markRead( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! $id ) {
			return new \WP_Error( 'infixs_correios_automatico_invalid_notification_id', __( 'Invalid notification ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$this->notificationService->markRead( $id );

		return rest_ensure_response( [ 'status' => 'success' ] );
	}

	/**
	 * Dismiss (delete) a single notification.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function dismiss( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! $id ) {
			return new \WP_Error( 'infixs_correios_automatico_invalid_notification_id', __( 'Invalid notification ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$this->notificationService->dismiss( $id );

		return rest_ensure_response( [ 'status' => 'success' ] );
	}

	/**
	 * Delete every notification.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function clearAll( $request ) {
		$this->notificationService->clearAll();

		return rest_ensure_response( [ 'status' => 'success' ] );
	}
}
