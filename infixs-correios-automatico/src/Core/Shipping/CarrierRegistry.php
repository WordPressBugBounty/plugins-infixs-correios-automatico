<?php

namespace Infixs\CorreiosAutomatico\Core\Shipping;

use Infixs\CorreiosAutomatico\Models\TrackingCode;

defined( 'ABSPATH' ) || exit;

/**
 * Carriers the order list can be filtered by.
 *
 * The carriers are declared here instead of being asked from the extensions, so
 * the filter exists on a store with none of them installed and keeps finding the
 * orders of an extension that was removed. Each carrier brings the shipping
 * method ids that ship it — the legacy ones of the plugins it replaced included,
 * so a migrated order is still found — and what hands a line of a third party
 * method over to it: a shipping line meta, or the instance of the method.
 *
 * A carrier slug found on the tracking codes of the store and registered by
 * nobody — an extension this version does not know yet — is a carrier too,
 * matched by those codes alone.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class CarrierRegistry {

	/**
	 * Slug of the orders no known carrier claims.
	 */
	const OTHER = 'other';

	/**
	 * Transient holding the carrier slugs found on the tracking codes.
	 */
	const TRACKED_TRANSIENT = 'infixs_correios_automatico_tracked_carriers';

	/**
	 * Carriers registered for the order list, keyed by slug.
	 *
	 * @since 1.11.0
	 *
	 * @return array<string, array{label: string, method_ids: string[], item_metas: string[], method_instances: array<string, int[]>}>
	 */
	public static function all() {
		$carriers = [ 
			'correios' => [ 
				'label' => 'Correios',
				'method_ids' => [ 'infixs-correios-automatico' ],
				'item_metas' => [ LinkedShippingMethod::ITEM_META_KEY ],
				'method_instances' => LinkedShippingMethod::linked_instances(),
			],
			'pex' => [ 
				'label' => 'PEX',
				'method_ids' => [ 'infixs-pex-automatico', 'pex' ],
				'item_metas' => [],
			],
			'jadlog' => [ 
				'label' => 'JadLog',
				'method_ids' => [ 
					'infixs-jadlog-automatico',
					'wc_jadlog_shipping_expresso',
					'wc_jadlog_shipping_package',
					'wc_jadlog_shipping_rodoviario',
					'wc_jadlog_shipping_economico',
					'wc_jadlog_shipping_doc',
					'wc_jadlog_shipping_corporate',
					'wc_jadlog_shipping_com',
					'wc_jadlog_shipping_cargo',
					'wc_jadlog_shipping_emergencial',
					'wc_jadlog_shipping_pickup',
				],
				'item_metas' => [],
			],
			'loggi' => [ 
				'label' => 'Loggi',
				'method_ids' => [ 'infixs-loggi-automatico', 'loggi_shipping_wc' ],
				'item_metas' => [],
			],
		];

		/**
		 * Filters the carriers the order list can be filtered by.
		 *
		 * A carrier answers with the shipping method ids that ship it and, when a
		 * third party method is handed over to it, the shipping line metas that
		 * say so (`item_metas`) or the instances of that method it ships
		 * (`method_instances`, instance ids keyed by method id). An order matches
		 * the carrier when one of its shipping lines carries one of those, or
		 * when one of its tracking codes was recorded under the slug.
		 *
		 * @since 1.11.0
		 *
		 * @param array<string, array{label: string, method_ids: string[], item_metas: string[], method_instances?: array<string, int[]>}> $carriers Carriers by slug.
		 */
		$carriers = apply_filters( 'infixs_correios_automatico_carrier_shipping_methods', $carriers );

		if ( ! is_array( $carriers ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $carriers as $slug => $carrier ) {
			$slug = sanitize_key( $slug );
			$carrier = self::normalize( $carrier );

			if ( '' === $slug || self::OTHER === $slug || null === $carrier ) {
				continue;
			}

			$normalized[ $slug ] = $carrier;
		}

		return $normalized;
	}

	/**
	 * Get a carrier by slug.
	 *
	 * @since 1.11.0
	 *
	 * @param string $slug Carrier slug.
	 *
	 * @return array{label: string, method_ids: string[], item_metas: string[], method_instances: array<string, int[]>}|null
	 */
	public static function get( $slug ) {
		$carriers = self::all();

		return isset( $carriers[ $slug ] ) ? $carriers[ $slug ] : null;
	}

	/**
	 * Slugs of every carrier an order can belong to.
	 *
	 * The registered carriers come first, then the slugs only the tracking codes
	 * of the store know about. `other` is not one of them.
	 *
	 * @since 1.11.0
	 *
	 * @param array|null $carriers Registered carriers, when the caller already has them.
	 *
	 * @return string[]
	 */
	public static function known( $carriers = null ) {
		$slugs = array_keys( null === $carriers ? self::all() : $carriers );

		foreach ( self::trackedSlugs() as $slug ) {
			if ( ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Slugs the filter accepts, `other` included.
	 *
	 * @since 1.11.0
	 *
	 * @return string[]
	 */
	public static function slugs() {
		$slugs = self::known();
		$slugs[] = self::OTHER;

		return $slugs;
	}

	/**
	 * Options the dashboard shows on the carrier filter.
	 *
	 * Every carrier `slugs()` accepts, in the same order: the registered ones,
	 * then the ones only found on the tracking codes, and `other` last, which is
	 * every order none of them claims.
	 *
	 * @since 1.11.0
	 *
	 * @return array<int, array{slug: string, label: string}>
	 */
	public static function options() {
		$carriers = self::all();
		$options = [];

		foreach ( self::known( $carriers ) as $slug ) {
			$label = isset( $carriers[ $slug ] ) ? $carriers[ $slug ]['label'] : '';

			$options[] = [ 
				'slug' => $slug,
				'label' => '' !== $label ? $label : ucfirst( $slug ),
			];
		}

		$options[] = [ 
			'slug' => self::OTHER,
			'label' => 'Outras',
		];

		return $options;
	}

	/**
	 * Forget the slugs found on the tracking codes, so they are read again.
	 *
	 * @since 1.11.0
	 *
	 * @return void
	 */
	public static function flushTrackedSlugs() {
		delete_transient( self::TRACKED_TRANSIENT );
	}

	/**
	 * Carrier slugs recorded on the tracking codes of the store.
	 *
	 * Read once an hour, or right after a code is added.
	 *
	 * @since 1.11.0
	 *
	 * @return string[]
	 */
	private static function trackedSlugs() {
		$cached = get_transient( self::TRACKED_TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$table = TrackingCode::generateTableName();

		$found = $wpdb->get_col( "SELECT DISTINCT carrier FROM {$table} WHERE carrier IS NOT NULL AND carrier <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$slugs = [];

		foreach ( (array) $found as $slug ) {
			$slug = sanitize_key( $slug );

			if ( '' !== $slug && self::OTHER !== $slug && ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}

		set_transient( self::TRACKED_TRANSIENT, $slugs, HOUR_IN_SECONDS );

		return $slugs;
	}

	/**
	 * Complete a registered carrier, discarding the ones with nothing to match.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $carrier Carrier as registered.
	 *
	 * @return array{label: string, method_ids: string[], item_metas: string[], method_instances: array<string, int[]>}|null
	 */
	private static function normalize( $carrier ) {
		if ( ! is_array( $carrier ) ) {
			return null;
		}

		$method_ids = isset( $carrier['method_ids'] ) ? self::strings( $carrier['method_ids'] ) : [];
		$item_metas = isset( $carrier['item_metas'] ) ? self::strings( $carrier['item_metas'] ) : [];
		$method_instances = isset( $carrier['method_instances'] ) ? self::instances( $carrier['method_instances'] ) : [];

		if ( ! $method_ids && ! $item_metas && ! $method_instances ) {
			return null;
		}

		return [ 
			'label' => isset( $carrier['label'] ) && is_string( $carrier['label'] ) ? $carrier['label'] : '',
			'method_ids' => $method_ids,
			'item_metas' => $item_metas,
			'method_instances' => $method_instances,
		];
	}

	/**
	 * Keep the method instances of a registered carrier that point somewhere.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $values Instance ids keyed by method id, as registered.
	 *
	 * @return array<string, int[]>
	 */
	private static function instances( $values ) {
		$instances = [];

		foreach ( (array) $values as $method_id => $instance_ids ) {
			if ( ! is_string( $method_id ) || '' === trim( $method_id ) ) {
				continue;
			}

			$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $instance_ids ) ) ) );

			if ( $ids ) {
				$instances[ trim( $method_id ) ] = $ids;
			}
		}

		return $instances;
	}

	/**
	 * Keep the non empty strings of a registered list.
	 *
	 * @since 1.11.0
	 *
	 * @param mixed $values Values as registered.
	 *
	 * @return string[]
	 */
	private static function strings( $values ) {
		$strings = [];

		foreach ( (array) $values as $value ) {
			if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
				continue;
			}

			$value = trim( (string) $value );

			if ( '' !== $value && ! in_array( $value, $strings, true ) ) {
				$strings[] = $value;
			}
		}

		return $strings;
	}
}
