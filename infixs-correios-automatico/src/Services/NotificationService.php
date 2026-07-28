<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Repositories\NotificationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Notification service.
 *
 * Central inbox for plugin alerts (prepost errors, contract authentication
 * errors, ...). Backed by the `..._notifications` table.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.0
 */
class NotificationService {

	/**
	 * Maximum notifications to keep.
	 *
	 * @var int
	 */
	const MAX_NOTIFICATIONS = 100;

	/**
	 * Dedupe key for the contract authentication error.
	 *
	 * @var string
	 */
	const AUTH_ERROR_DEDUPE_KEY = 'auth_error';

	/**
	 * Notification repository.
	 *
	 * @var NotificationRepository
	 */
	protected $notificationRepository;

	/**
	 * Create a new instance of the service.
	 *
	 * @since 1.8.0
	 *
	 * @param NotificationRepository $notificationRepository
	 */
	public function __construct( NotificationRepository $notificationRepository ) {
		$this->notificationRepository = $notificationRepository;

		add_action( 'infixs_correios_automatico_correios_auth_failed', [ $this, 'onAuthFailed' ], 10, 1 );
		add_action( 'infixs_correios_automatico_correios_auth_success', [ $this, 'onAuthSuccess' ], 10, 0 );
	}

	/**
	 * Register a notification.
	 *
	 * When a dedupe key is provided and an unread notification with the same key
	 * already exists, it is refreshed (and re-surfaced as unread) instead of
	 * creating a duplicate.
	 *
	 * @since 1.8.0
	 *
	 * @param string $type       Notification type (e.g. prepost_error, auth_error, general).
	 * @param string $level      Severity (error, warning, info).
	 * @param string $title      Short title.
	 * @param string $message    Full message.
	 * @param array  $context    Extra data (order_id, order_number, code, url, ...).
	 * @param string|null $dedupe_key Optional dedupe key.
	 *
	 * @return int Notification ID (0 on failure).
	 */
	public function notify( $type, $level, $title, $message, $context = [], $dedupe_key = null ) {
		$now = current_time( 'mysql' );

		if ( $dedupe_key ) {
			$existing = $this->notificationRepository->findByDedupeKey( $dedupe_key, true );
			if ( $existing ) {
				$this->notificationRepository->update( $existing->id, [
					'type' => $type,
					'level' => $level,
					'title' => $title,
					'message' => $message,
					'context' => wp_json_encode( $context ),
					'is_read' => 0,
					'created_at' => $now,
					'updated_at' => $now,
				] );

				do_action( 'infixs_correios_automatico_notification_created', (int) $existing->id, $type, $level );
				return (int) $existing->id;
			}
		}

		$created = $this->notificationRepository->create( [
			'type' => $type,
			'level' => $level,
			'title' => $title,
			'message' => $message,
			'context' => wp_json_encode( $context ),
			'dedupe_key' => $dedupe_key,
			'is_read' => 0,
			'created_at' => $now,
			'updated_at' => $now,
		] );

		$this->notificationRepository->pruneToLimit( self::MAX_NOTIFICATIONS );

		$id = $created ? (int) $created->id : 0;

		do_action( 'infixs_correios_automatico_notification_created', $id, $type, $level );

		return $id;
	}

	/**
	 * Mark as read the unread notifications matching a dedupe key.
	 *
	 * @since 1.8.0
	 *
	 * @param string $dedupe_key
	 *
	 * @return void
	 */
	public function resolveByDedupeKey( $dedupe_key ) {
		$existing = $this->notificationRepository->findByDedupeKey( $dedupe_key, true );
		if ( $existing ) {
			$this->notificationRepository->markRead( $existing->id );
		}
	}

	/**
	 * Mark as read the unread notifications whose dedupe key starts with a prefix.
	 *
	 * @since 1.8.0
	 *
	 * @param string $prefix
	 *
	 * @return void
	 */
	public function resolveByPrefix( $prefix ) {
		$notifications = $this->notificationRepository->findUnreadByDedupePrefix( $prefix );
		foreach ( $notifications as $notification ) {
			$this->notificationRepository->markRead( $notification->id );
		}
	}

	/**
	 * List notifications.
	 *
	 * @since 1.8.0
	 *
	 * @param array{page?: int, per_page?: int} $query
	 *
	 * @return array
	 */
	public function list( $query = [] ) {
		$page = isset( $query['page'] ) ? max( 1, (int) $query['page'] ) : 1;
		$per_page = isset( $query['per_page'] ) ? (int) $query['per_page'] : 15;

		$notifications = $this->notificationRepository->listPaginated( $per_page, $page );

		$items = [];
		foreach ( $notifications as $notification ) {
			$items[] = $this->prepareData( $notification );
		}

		return [
			'page' => $page,
			'per_page' => $per_page,
			'total' => $this->notificationRepository->total(),
			'unread_count' => $this->getUnreadCount(),
			'notifications' => $items,
		];
	}

	/**
	 * Get the number of unread notifications.
	 *
	 * @since 1.8.0
	 *
	 * @return int
	 */
	public function getUnreadCount() {
		return (int) $this->notificationRepository->countUnread();
	}

	/**
	 * Mark a single notification as read.
	 *
	 * @since 1.8.0
	 *
	 * @param int $id
	 *
	 * @return int|false
	 */
	public function markRead( $id ) {
		return $this->notificationRepository->markRead( $id );
	}

	/**
	 * Mark every notification as read.
	 *
	 * @since 1.8.0
	 *
	 * @return int|false
	 */
	public function markAllRead() {
		return $this->notificationRepository->markAllRead();
	}

	/**
	 * Dismiss (delete) a single notification.
	 *
	 * @since 1.8.0
	 *
	 * @param int $id
	 *
	 * @return int|false
	 */
	public function dismiss( $id ) {
		return $this->notificationRepository->delete( $id );
	}

	/**
	 * Delete every notification.
	 *
	 * @since 1.8.0
	 *
	 * @return int|false
	 */
	public function clearAll() {
		return $this->notificationRepository->clearAll();
	}

	/**
	 * Handle a contract authentication failure.
	 *
	 * @since 1.8.0
	 *
	 * @param \WP_Error|string $error
	 *
	 * @return void
	 */
	public function onAuthFailed( $error ) {
		$message = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;

		if ( '' === trim( (string) $message ) ) {
			$message = 'Não foi possível autenticar o contrato com os Correios. Verifique as credenciais do contrato nas configurações.';
		}

		$this->notify(
			'auth_error',
			'error',
			'Erro de autenticação do contrato',
			$message,
			[
				// Internal SPA target: the dashboard navigates with the Vue router
				// (no page reload). `url` is only a fallback for non-SPA contexts.
				'route' => 'config-contract',
				'path' => '/config/contract',
				'url' => admin_url( 'admin.php?page=infixs-correios-automatico&path=/config/contract' ),
			],
			self::AUTH_ERROR_DEDUPE_KEY
		);
	}

	/**
	 * Handle a successful contract authentication (resolve the pending error).
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function onAuthSuccess() {
		$this->resolveByDedupeKey( self::AUTH_ERROR_DEDUPE_KEY );
	}

	/**
	 * Prepare a notification model for the REST response.
	 *
	 * @since 1.8.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Notification $notification
	 *
	 * @return array
	 */
	public function prepareData( $notification ) {
		$context = json_decode( (string) $notification->context, true );

		return [
			'id' => (int) $notification->id,
			'type' => $notification->type,
			'level' => $notification->level,
			'title' => $notification->title,
			'message' => $notification->message,
			'context' => is_array( $context ) ? $context : [],
			'is_read' => (bool) $notification->is_read,
			'read_at' => $notification->read_at,
			'created_at' => $notification->created_at,
		];
	}
}
