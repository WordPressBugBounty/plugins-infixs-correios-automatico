<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Order queries narrowed by conditions of our own, on both order storages.
 *
 * `wc_get_orders()` has no argument for "the order has a shipping line of this
 * carrier" or "the order has no tracking code", and filtering the page after it
 * was fetched would break the total and the paging. The conditions travel in a
 * query var of their own and are injected in the `WHERE` of the query the
 * WooCommerce builds, which is also the one it counts with — so the total keeps
 * matching what the list shows.
 *
 * A condition is a SQL fragment carrying the `{order_id}` token where the order
 * id column belongs: the column is the post id on the posts storage and the
 * order id on the HPOS tables, and it is put in place after the fragment was
 * prepared, so a column name is never taken for a placeholder.
 *
 * The SQL itself never travels in the query: the query var only carries the
 * key the fragments were kept under, in the memory of this request. Any code
 * can build a query from arguments it did not write, and a key nobody kept is
 * ignored, so the WHERE of a query only ever takes the fragments prepared here.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
final class OrderQuery {

	/**
	 * Query var that carries the key of the conditions of a query built here.
	 *
	 * @var string
	 */
	const CONDITIONS = 'infixs_correios_automatico_conditions';

	/**
	 * Token a condition uses where the order id column belongs.
	 *
	 * @var string
	 */
	const ORDER_ID_TOKEN = '{order_id}';

	/**
	 * Conditions prepared on this request, by the key their query carries.
	 *
	 * @var array<string, string[]>
	 */
	private static $kept = [];

	/**
	 * Hook both storages. Called once, by the plugin core.
	 *
	 * @since 1.11.0
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'posts_where', [ self::class, 'postsWhere' ], 10, 2 );
		add_filter( 'woocommerce_orders_table_query_clauses', [ self::class, 'ordersTableClauses' ], 10, 3 );
	}

	/**
	 * Prepare `wc_get_orders()` arguments narrowed by conditions.
	 *
	 * The posts storage passes an argument it does not know on to WP_Query as
	 * it is, so the key reaches the query var `postsWhere()` reads. WP_Query
	 * keeps the ids it found under its SQL, and only forgets them when a post
	 * changes: a tracking code or a prepost changes no post, and the page would
	 * keep answering as before it. The result of a narrowed query is not kept.
	 *
	 * @since 1.11.0
	 *
	 * @param array                $args       Arguments of the query.
	 * @param string|string[]|null $conditions SQL fragments, or null ones to ignore.
	 *
	 * @return array
	 */
	public static function withConditions( array $args, $conditions ) {
		$conditions = self::clean( $conditions );

		if ( ! $conditions ) {
			return $args;
		}

		$conditions = array_merge( self::kept( isset( $args[ self::CONDITIONS ] ) ? $args[ self::CONDITIONS ] : null ), $conditions );
		$key = md5( implode( "\n", $conditions ) );

		self::$kept[ $key ] = $conditions;

		$args[ self::CONDITIONS ] = $key;
		$args['cache_results'] = false;

		return $args;
	}

	/**
	 * Narrow the query of the posts storage.
	 *
	 * The count of a paginated query is the same query with
	 * `SQL_CALC_FOUND_ROWS`, so the conditions are counted too.
	 *
	 * @since 1.11.0
	 *
	 * @param string    $where WHERE clause.
	 * @param \WP_Query $query Query being run.
	 *
	 * @return string
	 */
	public static function postsWhere( $where, $query ) {
		if ( ! $query instanceof \WP_Query ) {
			return $where;
		}

		global $wpdb;

		return $where . self::clauses( self::kept( $query->get( self::CONDITIONS ) ), "{$wpdb->posts}.ID" );
	}

	/**
	 * Narrow the query of the HPOS tables.
	 *
	 * The WHERE built here is the one the count query reuses.
	 *
	 * @since 1.11.0
	 *
	 * @param string[] $clauses Clauses of the query.
	 * @param object   $query   Query being run.
	 * @param array    $args    Arguments of the query.
	 *
	 * @return string[]
	 */
	public static function ordersTableClauses( $clauses, $query, $args ) {
		if ( ! is_array( $clauses ) || ! is_array( $args ) || empty( $args[ self::CONDITIONS ] ) ) {
			return $clauses;
		}

		$conditions = self::kept( $args[ self::CONDITIONS ] );

		if ( ! $conditions ) {
			return $clauses;
		}

		$where = isset( $clauses['where'] ) ? $clauses['where'] : '';
		$clauses['where'] = $where . self::clauses( $conditions, self::ordersTableColumn( $query ) );

		return $clauses;
	}

	/**
	 * Whether orders live in the HPOS tables.
	 *
	 * @since 1.11.0
	 *
	 * @return bool
	 */
	public static function usesOrdersTable() {
		return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Get the conditions kept under the key a query carries.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $key Key carried by the query.
	 *
	 * @return string[] Empty for a key that was not kept on this request.
	 */
	private static function kept( $key ) {
		return is_string( $key ) && isset( self::$kept[ $key ] ) ? self::$kept[ $key ] : [];
	}

	/**
	 * Build the WHERE additions of a set of conditions.
	 *
	 * @since 1.11.0
	 *
	 * @param string[] $conditions Conditions kept for the query.
	 * @param string   $column     Order id column of the storage.
	 *
	 * @return string
	 */
	private static function clauses( $conditions, $column ) {
		$sql = '';

		foreach ( $conditions as $condition ) {
			$sql .= ' AND ( ' . str_replace( self::ORDER_ID_TOKEN, $column, $condition ) . ' )';
		}

		return $sql;
	}

	/**
	 * Keep the conditions that carry SQL.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $conditions One condition, a list of them, or nothing.
	 *
	 * @return string[]
	 */
	private static function clean( $conditions ) {
		if ( is_string( $conditions ) ) {
			$conditions = [ $conditions ];
		}

		if ( ! is_array( $conditions ) ) {
			return [];
		}

		$clean = [];

		foreach ( $conditions as $condition ) {
			if ( is_string( $condition ) && '' !== trim( $condition ) ) {
				$clean[] = $condition;
			}
		}

		return $clean;
	}

	/**
	 * Order id column of the HPOS orders table.
	 *
	 * @since 1.11.0
	 *
	 * @param object $query Query being run.
	 *
	 * @return string
	 */
	private static function ordersTableColumn( $query ) {
		$table = '';

		if ( is_object( $query ) && method_exists( $query, 'get_table_name' ) ) {
			$table = (string) $query->get_table_name( 'orders' );
		}

		if ( '' === $table && class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore' ) ) {
			$table = (string) \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
		}

		if ( '' === $table ) {
			global $wpdb;

			$table = $wpdb->prefix . 'wc_orders';
		}

		return $table . '.id';
	}
}
