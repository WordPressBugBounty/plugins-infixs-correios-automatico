<?php

namespace Infixs\CorreiosAutomatico\Utils;

use Infixs\CorreiosAutomatico\Core\Shipping\CarrierRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer of the order list filters.
 *
 * Every filter travels as a comma separated list, and a value nobody knows is
 * dropped instead of being taken to the query: an old link, a carrier of an
 * extension that was removed or a status that no longer exists would otherwise
 * turn the list into "no orders found".
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
final class OrderFilterSanitizer {

	/**
	 * Pendency of an order still waiting for a tracking code.
	 */
	const PENDING_TRACKING = 'tracking';

	/**
	 * Pendency of an order still waiting for a prepost.
	 */
	const PENDING_PREPOST = 'prepost';

	/**
	 * Pendency of an order whose automatic prepost waits for the NF-e.
	 *
	 * @since 1.13.0
	 */
	const PENDING_INVOICE = 'invoice';

	/**
	 * Pendencies the plugin answers by itself; the others come from the
	 * extensions, through the `infixs_correios_automatico_order_pending_kinds`
	 * filter.
	 *
	 * @since 1.14.0
	 *
	 * @var string[]
	 */
	const NATIVE_PENDING = [ self::PENDING_TRACKING, self::PENDING_PREPOST, self::PENDING_INVOICE ];

	/**
	 * Sanitize the order statuses of a filter.
	 *
	 * The `wc-` prefix is added back when it is missing, so a status works
	 * spelled either way, and `trash` is kept as it is: it is a post status of
	 * its own, not an order status.
	 *
	 * Only the statuses the store has registered are kept. A status of the
	 * plugin whose setting was turned off is not registered, and the posts
	 * storage drops an unregistered status from the query without a word —
	 * filtering by it alone would answer with every order of the store, the
	 * trashed ones included.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $value Statuses as they arrived.
	 *
	 * @return string[]
	 */
	public static function statuses( $value ) {
		$available = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : [];
		$available = array_map( [ self::class, 'unprefixed' ], $available );
		$available[] = 'trash';

		$statuses = [];

		foreach ( self::toList( $value ) as $status ) {
			$status = self::unprefixed( $status );

			if ( ! in_array( $status, $available, true ) ) {
				continue;
			}

			$status = 'trash' === $status ? $status : 'wc-' . $status;

			if ( ! in_array( $status, $statuses, true ) ) {
				$statuses[] = $status;
			}
		}

		return $statuses;
	}

	/**
	 * Sanitize the carrier slugs of a filter.
	 *
	 * The carriers are only looked up when the filter carries something: the
	 * lookup reads the store, and most listings ask for no carrier at all.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $value Slugs as they arrived.
	 *
	 * @return string[]
	 */
	public static function carriers( $value ) {
		if ( ! self::toList( $value ) ) {
			return [];
		}

		return self::known( $value, CarrierRegistry::slugs() );
	}

	/**
	 * Sanitize the pendencies of a filter.
	 *
	 * Besides the pendencies of the plugin, the ones an extension registered
	 * are kept: the condition that matches their orders is asked from the
	 * extension by `OrderFilterConditions::pending()`.
	 *
	 * @since 1.11.0
	 * @since 1.13.0 Knows the orders waiting for the NF-e.
	 * @since 1.14.0 Knows the pendencies an extension registers.
	 *
	 * @param mixed $value Pendencies as they arrived.
	 *
	 * @return string[]
	 */
	public static function pending( $value ) {
		return self::known( $value, array_merge( self::NATIVE_PENDING, self::registeredPending() ) );
	}

	/**
	 * Pendencies the extensions add to the filter.
	 *
	 * Only a key written as `[a-z_]+` is taken: it travels in the URL of the
	 * dashboard and is saved as a preference, the same way the native ones are.
	 *
	 * @since 1.14.0
	 *
	 * @return string[]
	 */
	private static function registeredPending() {
		/**
		 * Filters the pendencies an extension adds to the filter of the order list.
		 *
		 * Each key is a pendency the dashboard can ask for in the `pending`
		 * filter, next to `tracking`, `prepost` and `invoice`. The SQL condition
		 * that matches the orders carrying it is asked, for each key, through
		 * the `infixs_correios_automatico_order_pending_condition` filter.
		 *
		 * @since 1.14.0
		 *
		 * @param string[] $kinds Keys of the pendencies, each one `[a-z_]+`. Empty by default.
		 */
		$kinds = apply_filters( 'infixs_correios_automatico_order_pending_kinds', [] );
		$registered = [];

		foreach ( is_array( $kinds ) ? $kinds : [] as $kind ) {
			if ( ! is_string( $kind ) || ! preg_match( '/^[a-z_]+$/', $kind ) ) {
				continue;
			}

			if ( ! in_array( $kind, self::NATIVE_PENDING, true ) && ! in_array( $kind, $registered, true ) ) {
				$registered[] = $kind;
			}
		}

		return $registered;
	}

	/**
	 * Keep the values of a list that are known, in the order they arrived.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed    $value     Values as they arrived.
	 * @param string[] $available Values that are known.
	 *
	 * @return string[]
	 */
	private static function known( $value, $available ) {
		$known = [];

		foreach ( self::toList( $value ) as $item ) {
			if ( in_array( $item, $available, true ) && ! in_array( $item, $known, true ) ) {
				$known[] = $item;
			}
		}

		return $known;
	}

	/**
	 * Read a filter as a list of keys.
	 *
	 * Accepts the comma separated list the dashboard sends and the array form
	 * of a saved preference.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $value Value as it arrived.
	 *
	 * @return string[]
	 */
	private static function toList( $value ) {
		$items = is_array( $value ) ? $value : [ $value ];
		$list = [];

		foreach ( $items as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				continue;
			}

			foreach ( wp_parse_list( (string) $item ) as $piece ) {
				$piece = sanitize_key( $piece );

				if ( '' !== $piece ) {
					$list[] = $piece;
				}
			}
		}

		return $list;
	}

	/**
	 * Drop the `wc-` prefix of an order status.
	 *
	 * @since 1.11.0
	 *
	 * @param string $status Order status.
	 *
	 * @return string
	 */
	private static function unprefixed( $status ) {
		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}
}
