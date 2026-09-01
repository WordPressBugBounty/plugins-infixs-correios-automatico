<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

use Infixs\CorreiosAutomatico\Core\Support\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Store wide settings shared by every marketplace extension.
 *
 * They used to live in a branch per extension, which is how the same toggle
 * ended up meaning opposite things on WCFM and on Dokan. One branch, one
 * meaning: an extension only keeps the settings that are genuinely its own.
 *
 * The base configuration is assembled while the plugin is being included,
 * before any extension can hook `infixs_correios_automatico_settings`, so the
 * defaults are applied on read.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class Settings {

	const BRANCH = 'marketplace';

	/**
	 * Quote a vendor without a contract through the Correios retail table.
	 *
	 * @var string
	 */
	const BEHAVIOR_PUBLIC_TABLE = 'public_table';

	/**
	 * Lend the store contract to a vendor that has none.
	 *
	 * @var string
	 */
	const BEHAVIOR_STORE_CONTRACT = 'store_contract';

	/**
	 * Offer no Correios rate at all for a vendor without a contract.
	 *
	 * @var string
	 */
	const BEHAVIOR_HIDE = 'hide';

	/**
	 * Every accepted value of `no_contract_behavior`.
	 *
	 * @var string[]
	 */
	const NO_CONTRACT_BEHAVIORS = [
		self::BEHAVIOR_PUBLIC_TABLE,
		self::BEHAVIOR_STORE_CONTRACT,
		self::BEHAVIOR_HIDE,
	];

	/**
	 * Branches these settings were read from before 1.10.0.
	 *
	 * @var string[]
	 */
	const LEGACY_BRANCHES = [ 'wcfm', 'dokan' ];

	/**
	 * Default value of every setting.
	 *
	 * @var array<string, string>
	 */
	const DEFAULTS = [
		'allow_vendor_contract' => 'no',
		'no_contract_behavior' => self::BEHAVIOR_PUBLIC_TABLE,
		'vendor_prepost' => 'yes',
		'show_vendor_name_on_label' => 'yes',
		'vendor_sender_on_label' => 'yes',
	];

	/**
	 * Settings whose stored value is never read from a legacy branch.
	 *
	 * `show_vendor_name_on_label` meant the shop name on WCFM and the owner name
	 * on Dokan, so a raw read of the old branch would print the wrong name on
	 * one of them. It is repaired by the one time migration of each extension
	 * instead; until that runs, the default applies — and the default is what
	 * both marketplaces printed anyway.
	 *
	 * @var string[]
	 */
	const NO_LEGACY_READ = [ 'show_vendor_name_on_label' ];

	/**
	 * Read a setting as boolean.
	 *
	 * @param string $key Setting key.
	 *
	 * @return bool
	 */
	public static function boolean( $key ) {
		return self::get( $key ) === 'yes';
	}

	/**
	 * Read a setting.
	 *
	 * Falls back to the branch the setting used to live in, so an install whose
	 * migration has not run yet keeps the behaviour its owner configured.
	 *
	 * @param string $key Setting key.
	 *
	 * @return string
	 */
	public static function get( $key ) {
		if ( $key === 'no_contract_behavior' ) {
			return self::noContractBehavior();
		}

		$stored = Config::get( self::BRANCH . '.' . $key );

		if ( $stored !== null ) {
			return (string) $stored;
		}

		if ( ! in_array( $key, self::NO_LEGACY_READ, true ) ) {
			foreach ( self::LEGACY_BRANCHES as $branch ) {
				$legacy = Config::get( $branch . '.' . $key );

				if ( $legacy !== null ) {
					return is_bool( $legacy ) ? ( $legacy ? 'yes' : 'no' ) : (string) $legacy;
				}
			}
		}

		return self::defaultFor( $key );
	}

	/**
	 * Read what happens to a vendor that has no contract of its own.
	 *
	 * An install that never saw this setting is read from the boolean it
	 * replaced, so the behaviour the store owner configured survives the
	 * upgrade without an migration step.
	 *
	 * @since 1.10.3
	 *
	 * @return string One of NO_CONTRACT_BEHAVIORS.
	 */
	public static function noContractBehavior() {
		$stored = Config::get( self::BRANCH . '.no_contract_behavior' );

		if ( is_string( $stored ) && in_array( $stored, self::NO_CONTRACT_BEHAVIORS, true ) ) {
			return $stored;
		}

		return self::legacyBehavior();
	}

	/**
	 * Check whether the store lends its contract to a vendor without one.
	 *
	 * @since 1.10.3
	 *
	 * @return bool
	 */
	public static function lendsStoreContract() {
		return self::noContractBehavior() === self::BEHAVIOR_STORE_CONTRACT;
	}

	/**
	 * Check whether Correios is withheld from a vendor without a contract.
	 *
	 * @since 1.10.3
	 *
	 * @return bool
	 */
	public static function hidesWithoutContract() {
		return self::noContractBehavior() === self::BEHAVIOR_HIDE;
	}

	/**
	 * Derive the behaviour from the boolean this setting replaced.
	 *
	 * @since 1.10.3
	 *
	 * @return string
	 */
	private static function legacyBehavior() {
		$branches = array_merge( [ self::BRANCH ], self::LEGACY_BRANCHES );

		foreach ( $branches as $branch ) {
			$legacy = Config::get( $branch . '.store_contract_fallback' );

			if ( $legacy === null ) {
				continue;
			}

			$lends = is_bool( $legacy ) ? $legacy : (string) $legacy === 'yes';

			return $lends ? self::BEHAVIOR_STORE_CONTRACT : self::BEHAVIOR_PUBLIC_TABLE;
		}

		return self::BEHAVIOR_PUBLIC_TABLE;
	}

	/**
	 * Read every setting with the defaults applied.
	 *
	 * @return array<string, string>
	 */
	public static function all() {
		$settings = [];

		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			$settings[ $key ] = self::get( $key );
		}

		return $settings;
	}

	/**
	 * Persist a set of settings.
	 *
	 * @param array $values Settings to merge over the stored ones.
	 *
	 * @return void
	 */
	public static function update( array $values ) {
		Config::update( self::BRANCH, array_merge( self::all(), array_intersect_key( $values, self::DEFAULTS ) ) );
	}

	/**
	 * Get the default value of a setting.
	 *
	 * @param string $key Setting key.
	 *
	 * @return string
	 */
	private static function defaultFor( $key ) {
		return isset( self::DEFAULTS[ $key ] ) ? self::DEFAULTS[ $key ] : 'no';
	}
}
