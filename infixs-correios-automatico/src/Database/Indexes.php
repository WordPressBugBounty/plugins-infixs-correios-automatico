<?php

namespace Infixs\CorreiosAutomatico\Database;

use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;

defined( 'ABSPATH' ) || exit;

/**
 * Indexes of the plugin tables.
 *
 * The table builder only creates the primary key, so the columns the order list
 * looks an order up by are indexed here. Without them every filtered page scans
 * the tracking codes and the preposts of the whole store.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class Indexes {

	/**
	 * Create the indexes that are missing.
	 *
	 * Runs on every update: an index already in place is left alone, so it is
	 * safe to call again.
	 *
	 * @since 1.11.0
	 *
	 * @return void
	 */
	public static function run() {
		global $wpdb;

		$indexes = [ 
			TrackingCode::generateTableName() => [ 
				'order_id' => 'order_id',
				'carrier' => 'carrier',
			],
			Prepost::generateTableName() => [ 
				'order_id' => 'order_id',
				'object_code' => 'object_code(100)',
			],
		];

		$suppressed = $wpdb->suppress_errors();

		foreach ( $indexes as $table => $table_indexes ) {
			foreach ( $table_indexes as $name => $columns ) {
				self::create( $table, $name, $columns );
			}
		}

		$wpdb->suppress_errors( $suppressed );
	}

	/**
	 * Create one index, when the table has it not.
	 *
	 * @since 1.11.0
	 *
	 * @param string $table   Table name, already prefixed.
	 * @param string $name    Index name.
	 * @param string $columns Columns of the index, as SQL.
	 *
	 * @return void
	 */
	private static function create( $table, $name, $columns ) {
		global $wpdb;

		if ( self::exists( $table, $name ) ) {
			return;
		}

		$wpdb->query( "ALTER TABLE {$table} ADD INDEX {$name} ( {$columns} )" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Whether a table already has an index.
	 *
	 * @since 1.11.0
	 *
	 * @param string $table Table name, already prefixed.
	 * @param string $name  Index name.
	 *
	 * @return bool
	 */
	private static function exists( $table, $name ) {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return null !== $found;
	}
}
