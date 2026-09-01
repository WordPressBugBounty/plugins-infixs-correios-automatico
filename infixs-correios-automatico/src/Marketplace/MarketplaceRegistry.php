<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the marketplace extensions active on this install.
 *
 * Deliberately a list and not a single slot: WCFM and Dokan are separate
 * plugins and can be installed side by side, so the adapter is chosen per
 * order rather than per site.
 *
 * The base plugin boots while it is being included, long before an extension
 * can register on `plugins_loaded`, so nothing may be decided at wiring time:
 * every consumer asks this registry from inside its own callback.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class MarketplaceRegistry {

	/**
	 * Registered adapters, keyed by slug.
	 *
	 * @var array<string, MarketplaceAdapter>
	 */
	private static $adapters = [];

	/**
	 * Adapter already resolved per order in this request.
	 *
	 * @var array<int, string>
	 */
	private static $resolved = [];

	/**
	 * Register a marketplace extension.
	 *
	 * @param MarketplaceAdapter $adapter Adapter.
	 *
	 * @return void
	 */
	public static function register( MarketplaceAdapter $adapter ) {
		self::$adapters[ $adapter->getSlug() ] = $adapter;
		self::$resolved = [];
	}

	/**
	 * Check whether any marketplace extension is active.
	 *
	 * @return bool
	 */
	public static function hasAny() {
		return ! empty( self::$adapters );
	}

	/**
	 * Get a registered adapter by slug.
	 *
	 * @param string $slug Marketplace slug.
	 *
	 * @return MarketplaceAdapter|null
	 */
	public static function get( $slug ) {
		return isset( self::$adapters[ $slug ] ) ? self::$adapters[ $slug ] : null;
	}

	/**
	 * Get the adapter of the marketplace that owns an order.
	 *
	 * @param \WC_Order|int|null $order Order or order ID.
	 *
	 * @return MarketplaceAdapter|null Null when no marketplace claims it.
	 */
	public static function forOrder( $order ) {
		if ( ! self::$adapters ) {
			return null;
		}

		if ( ! $order instanceof \WC_Order ) {
			$order = $order ? wc_get_order( $order ) : false;
		}

		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$order_id = $order->get_id();

		if ( isset( self::$resolved[ $order_id ] ) ) {
			return self::get( self::$resolved[ $order_id ] );
		}

		foreach ( self::$adapters as $slug => $adapter ) {
			if ( ! $adapter->ownsOrder( $order ) ) {
				continue;
			}

			self::$resolved[ $order_id ] = $slug;

			return $adapter;
		}

		return null;
	}

	/**
	 * Get the adapter of the marketplace the current user sells on.
	 *
	 * @return MarketplaceAdapter|null
	 */
	public static function forCurrentVendor() {
		foreach ( self::$adapters as $adapter ) {
			if ( $adapter->getCurrentVendorId() ) {
				return $adapter;
			}
		}

		return null;
	}

	/**
	 * Drop every registration and the per request cache.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$adapters = [];
		self::$resolved = [];
	}

	/**
	 * Drop the resolved adapter of an order.
	 *
	 * @param int|null $order_id Order to forget, null for every order.
	 *
	 * @return void
	 */
	public static function flushOrder( $order_id = null ) {
		if ( $order_id === null ) {
			self::$resolved = [];

			return;
		}

		unset( self::$resolved[ (int) $order_id ] );
	}
}
