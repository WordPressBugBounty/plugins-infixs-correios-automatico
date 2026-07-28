<?php

namespace Infixs\CorreiosAutomatico\Repositories;

use Infixs\CorreiosAutomatico\Core\Support\Repository;
use Infixs\CorreiosAutomatico\Models\Notification;

defined( 'ABSPATH' ) || exit;

/**
 * Notification repository.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.0
 */
class NotificationRepository extends Repository {

	/**
	 * Create a new notification.
	 *
	 * @since 1.8.0
	 *
	 * @param array $data
	 *
	 * @return bool|Notification
	 */
	public function create( $data ) {
		$now = current_time( 'mysql' );
		$data['created_at'] = $data['created_at'] ?? $now;
		$data['updated_at'] = $data['updated_at'] ?? $now;
		return Notification::create( $data );
	}

	/**
	 * List notifications, newest first.
	 *
	 * @since 1.8.0
	 *
	 * @param int $per_page
	 * @param int $page
	 *
	 * @return \Infixs\WordpressEloquent\Collection
	 */
	public function listPaginated( $per_page = 15, $page = 1 ) {
		$offset = ( $page - 1 ) * $per_page;
		return Notification::select( '*' )
			->orderBy( 'id', 'desc' )
			->offset( $offset )
			->limit( $per_page )
			->get();
	}

	/**
	 * Total notifications count.
	 *
	 * @since 1.8.0
	 *
	 * @return int
	 */
	public function total() {
		return Notification::count();
	}

	/**
	 * Count unread notifications.
	 *
	 * @since 1.8.0
	 *
	 * @return int
	 */
	public function countUnread() {
		return Notification::where( 'is_read', 0 )->count();
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
		$now = current_time( 'mysql' );
		return Notification::where( 'id', $id )->update( [
			'is_read' => 1,
			'read_at' => $now,
			'updated_at' => $now,
		] );
	}

	/**
	 * Mark every unread notification as read.
	 *
	 * @since 1.8.0
	 *
	 * @return int|false
	 */
	public function markAllRead() {
		$now = current_time( 'mysql' );
		return Notification::where( 'is_read', 0 )->update( [
			'is_read' => 1,
			'read_at' => $now,
			'updated_at' => $now,
		] );
	}

	/**
	 * Find a notification by its dedupe key.
	 *
	 * @since 1.8.0
	 *
	 * @param string $dedupe_key
	 * @param bool   $only_unread
	 *
	 * @return Notification|null
	 */
	public function findByDedupeKey( $dedupe_key, $only_unread = true ) {
		$query = Notification::where( 'dedupe_key', $dedupe_key );
		if ( $only_unread ) {
			$query->where( 'is_read', 0 );
		}
		return $query->orderBy( 'id', 'desc' )->first();
	}

	/**
	 * Find unread notifications whose dedupe key starts with the given prefix.
	 *
	 * @since 1.8.0
	 *
	 * @param string $prefix
	 *
	 * @return \Infixs\WordpressEloquent\Collection
	 */
	public function findUnreadByDedupePrefix( $prefix ) {
		return Notification::where( 'is_read', 0 )
			->where( 'dedupe_key', 'like', $prefix . '%' )
			->get();
	}

	/**
	 * Delete every notification.
	 *
	 * @since 1.8.0
	 *
	 * @return int|false
	 */
	public function clearAll() {
		global $wpdb;
		$table = Notification::getTable();
		// $table comes from the model definition, never user input.
		return $wpdb->query( "DELETE FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Prune the oldest notifications, keeping at most $limit rows.
	 *
	 * @since 1.8.0
	 *
	 * @param int $limit
	 *
	 * @return void
	 */
	public function pruneToLimit( $limit = 100 ) {
		$threshold = Notification::select( 'id' )
			->orderBy( 'id', 'desc' )
			->offset( $limit )
			->limit( 1 )
			->first();

		if ( ! $threshold ) {
			return;
		}

		global $wpdb;
		$table = Notification::getTable();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id <= %d", $threshold->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
