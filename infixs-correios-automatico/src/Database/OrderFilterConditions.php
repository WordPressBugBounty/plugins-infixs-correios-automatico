<?php

namespace Infixs\CorreiosAutomatico\Database;

use Infixs\CorreiosAutomatico\Core\Shipping\CarrierRegistry;
use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\PrepostStatusCode;
use Infixs\CorreiosAutomatico\Services\OrderInvoiceResolver;
use Infixs\CorreiosAutomatico\Utils\OrderFilterSanitizer;
use Infixs\CorreiosAutomatico\Utils\OrderQuery;

defined( 'ABSPATH' ) || exit;

/**
 * SQL conditions of the order list filters.
 *
 * Every fragment is prepared here and carries the `{order_id}` token where the
 * order id column belongs, so the same fragment serves the posts storage and
 * the HPOS tables — `OrderQuery` puts the column in place after the fragment is
 * already prepared.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
final class OrderFilterConditions {

	/**
	 * Shipping methods of an order the customer picks up: no shipment leaves
	 * the store, so there is no tracking code to wait for.
	 *
	 * @var string[]
	 */
	const PICKUP_METHOD_IDS = [ 'local_pickup', 'pickup_location' ];

	/**
	 * Prepost statuses the Correios answer for a prepost that will never be
	 * posted, even though nobody cancelled it through the plugin.
	 *
	 * @var int[]
	 */
	const DEAD_PREPOST_STATUSES = [ PrepostStatusCode::EXPRIRADO, PrepostStatusCode::CANCELADO ];

	/**
	 * Condition matching the orders of a set of carriers.
	 *
	 * An order belongs to a carrier when one of its shipping lines was shipped
	 * by a method of that carrier, or was handed over to it, or when one of its
	 * tracking codes was recorded under its slug. `other` is every order no
	 * known carrier claims, an order without any shipping line included.
	 *
	 * @since 1.11.0
	 *
	 * @param string[] $slugs Carrier slugs, already sanitized.
	 *
	 * @return string|null Null when no slug could be matched.
	 */
	public static function carriers( $slugs ) {
		$carriers = CarrierRegistry::all();
		$known = CarrierRegistry::known( $carriers );
		$conditions = [];

		foreach ( (array) $slugs as $slug ) {
			if ( CarrierRegistry::OTHER !== $slug && ! in_array( $slug, $known, true ) ) {
				continue;
			}

			$condition = CarrierRegistry::OTHER === $slug
				? self::unclaimed( $known, $carriers )
				: self::claimed( $slug, $carriers );

			if ( $condition ) {
				$conditions[] = $condition;
			}
		}

		return $conditions ? '( ' . implode( ' OR ', $conditions ) . ' )' : null;
	}

	/**
	 * Condition matching the orders still waiting for one of the actions.
	 *
	 * Two pendencies asked at once match the orders carrying either of them.
	 *
	 * @since 1.11.0
	 * @since 1.13.0 Matches the orders waiting for the NF-e.
	 *
	 * @param string[] $kinds Pendency keys, already sanitized.
	 *
	 * @return string|null Null when no pendency could be matched.
	 */
	public static function pending( $kinds ) {
		$conditions = [];

		foreach ( (array) $kinds as $kind ) {
			$condition = null;

			if ( OrderFilterSanitizer::PENDING_TRACKING === $kind ) {
				$condition = self::withoutTracking();
			}

			if ( OrderFilterSanitizer::PENDING_PREPOST === $kind ) {
				$condition = self::withoutPrepost();
			}

			if ( OrderFilterSanitizer::PENDING_INVOICE === $kind ) {
				$condition = self::waitingInvoice();
			}

			if ( $condition ) {
				$conditions[] = $condition;
			}
		}

		return $conditions ? '( ' . implode( ' OR ', $conditions ) . ' )' : null;
	}

	/**
	 * Condition matching the orders a text search asked for.
	 *
	 * Only the posts storage needs it: the HPOS tables are searched by
	 * `wc_get_orders()` itself. The order id is matched next to the billing
	 * fields, so searching a number finds the order with that id even though it
	 * carries none of the text.
	 *
	 * @since 1.11.0
	 *
	 * @param string $term Term being searched.
	 *
	 * @return string|null Null when there is nothing to search for.
	 */
	public static function search( $term ) {
		global $wpdb;

		$term = trim( (string) $term );

		if ( '' === $term ) {
			return null;
		}

		$fields = [ '_billing_first_name', '_billing_last_name', '_billing_email', '_billing_address_1' ];

		$values = $fields;
		$values[] = '%' . $wpdb->esc_like( $term ) . '%';

		$condition = $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} AS ca_search
				WHERE ca_search.post_id = " . OrderQuery::ORDER_ID_TOKEN . "
					AND ca_search.meta_key IN ( " . self::placeholders( $fields ) . " )
					AND ca_search.meta_value LIKE %s
			)",
			$values
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_numeric( $term ) ) {
			return $condition;
		}

		$by_id = $wpdb->prepare( OrderQuery::ORDER_ID_TOKEN . ' = %d', absint( $term ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return "( {$by_id} OR {$condition} )";
	}

	/**
	 * Condition matching the orders a carrier claims.
	 *
	 * A carrier nobody registered is only known by its tracking codes.
	 *
	 * @since 1.11.0
	 *
	 * @param string $slug     Carrier slug.
	 * @param array  $carriers Registered carriers.
	 *
	 * @return string
	 */
	private static function claimed( $slug, $carriers ) {
		$conditions = [];
		$line = isset( $carriers[ $slug ] ) ? self::shippingLine( $carriers[ $slug ] ) : null;

		if ( $line ) {
			$conditions[] = $line;
		}

		$conditions[] = self::trackedBy( $slug );

		return '( ' . implode( ' OR ', $conditions ) . ' )';
	}

	/**
	 * Condition matching the orders no known carrier claims.
	 *
	 * @since 1.11.0
	 *
	 * @param string[] $known    Slugs of every known carrier.
	 * @param array    $carriers Registered carriers.
	 *
	 * @return string|null
	 */
	private static function unclaimed( $known, $carriers ) {
		$conditions = [];

		foreach ( $known as $slug ) {
			$conditions[] = self::claimed( $slug, $carriers );
		}

		return $conditions ? 'NOT ( ' . implode( ' OR ', $conditions ) . ' )' : null;
	}

	/**
	 * Condition matching the orders with a shipping line of a carrier.
	 *
	 * @since 1.11.0
	 *
	 * @param array $carrier Registered carrier.
	 *
	 * @return string|null Null when the carrier declared nothing to match.
	 */
	private static function shippingLine( $carrier ) {
		global $wpdb;

		$items = $wpdb->prefix . 'woocommerce_order_items';
		$line = self::lineOf( $carrier, 'ca_line' );

		if ( ! $line ) {
			return null;
		}

		return $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$items} AS ca_line
				WHERE ca_line.order_id = " . OrderQuery::ORDER_ID_TOKEN . "
					AND ca_line.order_item_type = 'shipping'
					AND {$line['sql']}
			)",
			$line['values']
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Fragment telling apart a shipping line of a carrier.
	 *
	 * A line belongs to the carrier by its method id, by a line meta that hands
	 * it over, or by the instance of its method.
	 *
	 * @since 1.11.0
	 *
	 * @param array  $carrier Registered carrier.
	 * @param string $alias   Alias of the order items table in the query.
	 *
	 * @return array{sql: string, values: array}|null Null when the carrier declared nothing to match.
	 */
	private static function lineOf( $carrier, $alias ) {
		global $wpdb;

		$itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';

		$matches = [];
		$values = [];

		if ( $carrier['method_ids'] ) {
			$matches[] = "( {$alias}_meta.meta_key = 'method_id' AND {$alias}_meta.meta_value IN ( " . self::placeholders( $carrier['method_ids'] ) . ' ) )';
			$values = array_merge( $values, $carrier['method_ids'] );
		}

		if ( $carrier['item_metas'] ) {
			$matches[] = "( {$alias}_meta.meta_key IN ( " . self::placeholders( $carrier['item_metas'] ) . " ) AND {$alias}_meta.meta_value <> '' AND {$alias}_meta.meta_value <> '0' )";
			$values = array_merge( $values, $carrier['item_metas'] );
		}

		$fragments = [];

		if ( $matches ) {
			$fragments[] = "EXISTS (
				SELECT 1 FROM {$itemmeta} AS {$alias}_meta
				WHERE {$alias}_meta.order_item_id = {$alias}.order_item_id
					AND ( " . implode( ' OR ', $matches ) . ' )
			)';
		}

		$pairs = [];

		foreach ( $carrier['method_instances'] as $method_id => $instance_ids ) {
			$pairs[] = "( {$alias}_method.meta_value = %s AND {$alias}_instance.meta_value IN ( " . self::placeholders( $instance_ids ) . ' ) )';
			$values[] = $method_id;
			$values = array_merge( $values, array_map( 'strval', $instance_ids ) );
		}

		if ( $pairs ) {
			$fragments[] = "EXISTS (
				SELECT 1 FROM {$itemmeta} AS {$alias}_method
				INNER JOIN {$itemmeta} AS {$alias}_instance ON {$alias}_instance.order_item_id = {$alias}_method.order_item_id
				WHERE {$alias}_method.order_item_id = {$alias}.order_item_id
					AND {$alias}_method.meta_key = 'method_id'
					AND {$alias}_instance.meta_key = 'instance_id'
					AND ( " . implode( ' OR ', $pairs ) . ' )
			)';
		}

		if ( ! $fragments ) {
			return null;
		}

		return [ 
			'sql' => '( ' . implode( ' OR ', $fragments ) . ' )',
			'values' => $values,
		];
	}

	/**
	 * Condition matching the orders with a tracking code of a carrier.
	 *
	 * @since 1.11.0
	 *
	 * @param string $slug Carrier slug.
	 *
	 * @return string
	 */
	private static function trackedBy( $slug ) {
		global $wpdb;

		$tracking = TrackingCode::generateTableName();

		return $wpdb->prepare(
			"EXISTS (
				SELECT 1 FROM {$tracking} AS ca_carrier_code
				WHERE ca_carrier_code.order_id = " . OrderQuery::ORDER_ID_TOKEN . "
					AND ca_carrier_code.carrier = %s
					AND ca_carrier_code.code IS NOT NULL
					AND ca_carrier_code.code <> ''
			)",
			$slug
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Condition matching the orders with a shipment still without its code.
	 *
	 * A code recorded for the whole order covers every shipping line, so the
	 * order is not pending. Otherwise every shipping line waits for a code of
	 * its own: a shipment split into volumes is pending while any of its lines
	 * has none, even though the other ones already have theirs. An order with
	 * nothing to ship — no shipping line, or a line picked up at the store —
	 * never gets a code, and is never pending.
	 *
	 * @since 1.11.0
	 *
	 * @return string
	 */
	private static function withoutTracking() {
		global $wpdb;

		$items = $wpdb->prefix . 'woocommerce_order_items';
		$itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$tracking = TrackingCode::generateTableName();
		$token = OrderQuery::ORDER_ID_TOKEN;

		$order_wide = self::shipmentCode( 'ca_order_code' );
		$of_line = self::shipmentCode( 'ca_line_code' );

		$sql = "(
			NOT EXISTS (
				SELECT 1 FROM {$tracking} AS ca_order_code
				WHERE ca_order_code.order_id = {$token}
					AND ( ca_order_code.shipping_item_id IS NULL OR ca_order_code.shipping_item_id = 0 )
					AND {$order_wide['sql']}
			)
			AND EXISTS (
				SELECT 1 FROM {$items} AS ca_shipping_line
				WHERE ca_shipping_line.order_id = {$token}
					AND ca_shipping_line.order_item_type = 'shipping'
					AND NOT EXISTS (
						SELECT 1 FROM {$itemmeta} AS ca_pickup
						WHERE ca_pickup.order_item_id = ca_shipping_line.order_item_id
							AND ca_pickup.meta_key = 'method_id'
							AND ca_pickup.meta_value IN ( " . self::placeholders( self::PICKUP_METHOD_IDS ) . " )
					)
					AND NOT EXISTS (
						SELECT 1 FROM {$tracking} AS ca_line_code
						WHERE ca_line_code.order_id = ca_shipping_line.order_id
							AND ca_line_code.shipping_item_id = ca_shipping_line.order_item_id
							AND {$of_line['sql']}
					)
			)
		)";

		$values = array_merge( $order_wide['values'], self::PICKUP_METHOD_IDS, $of_line['values'] );

		return $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Condition matching the orders with a Correios shipment not pre-posted.
	 *
	 * Only a Correios shipping line waits for a prepost: an order shipped by
	 * another carrier is never pre-posted, and would show up as pending
	 * forever. A prepost made for the whole order covers every line; otherwise
	 * each line needs one of its own, so a shipment split into volumes stays
	 * pending while one of them was not pre-posted — the same rule the prepost
	 * of a volume follows.
	 *
	 * @since 1.11.0
	 *
	 * @return string|null Null when no carrier is registered as the Correios.
	 */
	private static function withoutPrepost() {
		global $wpdb;

		$correios = CarrierRegistry::get( 'correios' );
		$line = $correios ? self::lineOf( $correios, 'ca_prepost_line' ) : null;

		if ( ! $line ) {
			return null;
		}

		$items = $wpdb->prefix . 'woocommerce_order_items';
		$preposts = Prepost::generateTableName();
		$token = OrderQuery::ORDER_ID_TOKEN;

		$order_wide = self::livePrepost( 'ca_order_prepost' );
		$of_line = self::livePrepost( 'ca_line_prepost' );

		$sql = "(
			NOT EXISTS (
				SELECT 1 FROM {$preposts} AS ca_order_prepost
				WHERE ca_order_prepost.order_id = {$token}
					AND ( ca_order_prepost.shipping_item_id IS NULL OR ca_order_prepost.shipping_item_id = 0 )
					AND {$order_wide['sql']}
			)
			AND EXISTS (
				SELECT 1 FROM {$items} AS ca_prepost_line
				WHERE ca_prepost_line.order_id = {$token}
					AND ca_prepost_line.order_item_type = 'shipping'
					AND {$line['sql']}
					AND NOT EXISTS (
						SELECT 1 FROM {$preposts} AS ca_line_prepost
						WHERE ca_line_prepost.order_id = ca_prepost_line.order_id
							AND ca_line_prepost.shipping_item_id = ca_prepost_line.order_item_id
							AND {$of_line['sql']}
					)
			)
		)";

		$values = array_merge( $order_wide['values'], $line['values'], $of_line['values'] );

		return $wpdb->prepare( $sql, $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Condition matching the orders whose automatic prepost waits for the NF-e.
	 *
	 * The same orders OrderInvoiceResolver::isWaiting() tells: only while the
	 * PRO that waits is active, and the order is still a sale. Whatever invoice
	 * provider the order waits for, it is pending here.
	 *
	 * @since 1.13.0
	 *
	 * @return string Matches no order when the PRO that waits is not active.
	 */
	private static function waitingInvoice() {
		global $wpdb;

		if ( ! class_exists( OrderInvoiceResolver::WAITING_SERVICE_CLASS ) ) {
			return '1 = 0';
		}

		$token = OrderQuery::ORDER_ID_TOKEN;
		$gone = array_map( function ( $status ) {
			return 'trash' === $status ? $status : 'wc-' . $status;
		}, OrderInvoiceResolver::NO_LONGER_SOLD_STATUSES );

		if ( OrderQuery::usesOrdersTable() ) {
			$orders = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_orders_table_name();
			$meta = \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableDataStore::get_meta_table_name();
			$meta_order = 'order_id';
			$status = "SELECT 1 FROM {$orders} AS ca_waiting_order WHERE ca_waiting_order.id = {$token} AND ca_waiting_order.status IN ( " . self::placeholders( $gone ) . ' )';
		} else {
			$meta = $wpdb->postmeta;
			$meta_order = 'post_id';
			$status = "SELECT 1 FROM {$wpdb->posts} AS ca_waiting_order WHERE ca_waiting_order.ID = {$token} AND ca_waiting_order.post_status IN ( " . self::placeholders( $gone ) . ' )';
		}

		$sql = "(
			EXISTS (
				SELECT 1 FROM {$meta} AS ca_waiting_invoice
				WHERE ca_waiting_invoice.{$meta_order} = {$token}
					AND ca_waiting_invoice.meta_key = %s
					AND ca_waiting_invoice.meta_value NOT IN ( '', 'a:0:{}' )
			)
			AND NOT EXISTS ( {$status} )
		)";

		return $wpdb->prepare( $sql, array_merge( [ OrderInvoiceResolver::WAITING_META_KEY ], $gone ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Fragment telling apart a prepost that still posts a shipment.
	 *
	 * A prepost cancelled through the plugin, one the Correios answer as
	 * cancelled or expired, and a reverse logistics prepost — which brings a
	 * shipment back instead of posting it — leave the shipment waiting.
	 *
	 * @since 1.11.0
	 *
	 * @param string $alias Alias of the preposts table in the query.
	 *
	 * @return array{sql: string, values: array}
	 */
	private static function livePrepost( $alias ) {
		$sql = "{$alias}.cancelled_at IS NULL
			AND ( {$alias}.status IS NULL OR {$alias}.status NOT IN ( " . implode( ', ', array_fill( 0, count( self::DEAD_PREPOST_STATUSES ), '%d' ) ) . ' ) )';
		$values = self::DEAD_PREPOST_STATUSES;

		$reverse = array_map( 'strval', array_keys( DeliveryServiceCode::getReverseServices() ) );

		if ( $reverse ) {
			$sql .= " AND {$alias}.service_code NOT IN ( " . self::placeholders( $reverse ) . ' )';
			$values = array_merge( $values, $reverse );
		}

		return [ 
			'sql' => $sql,
			'values' => $values,
		];
	}

	/**
	 * Fragment telling apart a code that follows a shipment to the customer.
	 *
	 * A reverse logistics code travels the other way: it is recorded on the line
	 * it brings back, so it is not the code the shipment is still waiting for.
	 *
	 * @since 1.11.0
	 *
	 * @param string $alias Alias of the tracking codes table in the query.
	 *
	 * @return array{sql: string, values: string[]}
	 */
	private static function shipmentCode( $alias ) {
		$preposts = Prepost::generateTableName();
		$reverse = array_map( 'strval', array_keys( DeliveryServiceCode::getReverseServices() ) );

		$sql = "{$alias}.code IS NOT NULL AND {$alias}.code <> ''";

		if ( ! $reverse ) {
			return [ 
				'sql' => $sql,
				'values' => [],
			];
		}

		$sql .= " AND NOT EXISTS (
			SELECT 1 FROM {$preposts} AS {$alias}_reverse
			WHERE {$alias}_reverse.order_id = {$alias}.order_id
				AND {$alias}_reverse.object_code = {$alias}.code
				AND {$alias}_reverse.service_code IN ( " . self::placeholders( $reverse ) . ' )
		)';

		return [ 
			'sql' => $sql,
			'values' => $reverse,
		];
	}

	/**
	 * Placeholders for a list of string values.
	 *
	 * @since 1.11.0
	 *
	 * @param array $values Values of the list.
	 *
	 * @return string
	 */
	private static function placeholders( $values ) {
		return implode( ', ', array_fill( 0, count( $values ), '%s' ) );
	}
}
