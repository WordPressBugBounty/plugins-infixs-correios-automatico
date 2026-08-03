<?php

namespace Infixs\CorreiosAutomatico\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Per vendor settings, stored as a single user option.
 *
 * The option key is marketplace agnostic on purpose: a store migrating between
 * Dokan and WCFM keeps the contracts its vendors already registered.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.2
 */
class VendorSettings {

	const OPTION_KEY = 'infixs_correios_automatico_vendor_settings';

	/**
	 * Settings already loaded in this request, keyed by vendor.
	 *
	 * The shipping calculation asks for the same vendor several times per
	 * package, and each load is a user option read plus two value objects.
	 *
	 * @var array<int, static>
	 */
	protected static $instances = [];

	/**
	 * Whether the write guard is running its own update.
	 *
	 * @var bool
	 */
	private static $guarding = false;

	/**
	 * Vendor contract credentials.
	 *
	 * @var VendorAuth
	 */
	public $auth;

	/**
	 * Vendor general preferences.
	 *
	 * @var VendorGeneral
	 */
	public $general;

	/**
	 * Vendor user ID.
	 *
	 * @var int
	 */
	protected $vendor_id;

	/**
	 * @param int $vendor_id Vendor user ID.
	 */
	public function __construct( $vendor_id ) {
		$this->vendor_id = (int) $vendor_id;
		$this->loadSettings();
	}

	/**
	 * Load settings from the database.
	 *
	 * @return void
	 */
	protected function loadSettings() {
		$settings = get_user_option( static::OPTION_KEY, $this->vendor_id );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$this->auth = new VendorAuth( isset( $settings['auth'] ) ? $settings['auth'] : [] );
		$this->general = new VendorGeneral( isset( $settings['general'] ) ? $settings['general'] : [] );
	}

	/**
	 * Get the settings of a vendor, loading them at most once per request.
	 *
	 * @param int $vendor_id Vendor user ID.
	 *
	 * @return static
	 */
	public static function get( $vendor_id ) {
		$vendor_id = (int) $vendor_id;

		if ( ! isset( static::$instances[ $vendor_id ] ) ) {
			static::$instances[ $vendor_id ] = new static( $vendor_id );
		}

		return static::$instances[ $vendor_id ];
	}

	/**
	 * Drop the per request cache.
	 *
	 * @param int|null $vendor_id Vendor to forget, null for every vendor.
	 *
	 * @return void
	 */
	public static function flush( $vendor_id = null ) {
		if ( $vendor_id === null ) {
			static::$instances = [];

			return;
		}

		unset( static::$instances[ (int) $vendor_id ] );
	}

	/**
	 * Get the vendor these settings belong to.
	 *
	 * @return int
	 */
	public function getVendorId() {
		return $this->vendor_id;
	}

	/**
	 * Check whether a vendor has a usable contract.
	 *
	 * @param int $vendor_id Vendor user ID.
	 *
	 * @return bool
	 */
	public static function hasActiveContract( $vendor_id ) {
		$settings = static::get( $vendor_id );

		return $settings->auth->isActive() && $settings->auth->hasValidCredentials();
	}

	/**
	 * Reset the settings of the vendor to their defaults.
	 *
	 * @return bool
	 */
	public function reset() {
		$this->auth = new VendorAuth();
		$this->general = new VendorGeneral();

		return $this->save();
	}

	/**
	 * Merge new values into the contract credentials and persist them.
	 *
	 * @param array $data Values to merge.
	 *
	 * @return bool
	 */
	public function updateAuth( array $data ) {
		$this->auth = new VendorAuth( array_merge( $this->auth->toArray(), $data ) );

		return $this->save();
	}

	/**
	 * Merge new values into the general preferences and persist them.
	 *
	 * @param array $data Values to merge.
	 *
	 * @return bool
	 */
	public function updateGeneral( array $data ) {
		$this->general = new VendorGeneral( array_merge( $this->general->toArray(), $data ) );

		return $this->save();
	}

	/**
	 * Save settings to the database.
	 *
	 * @return bool
	 */
	public function save() {
		static::$instances[ $this->vendor_id ] = $this;

		return (bool) update_user_option( $this->vendor_id, static::OPTION_KEY, $this->toArray() );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function toArray() {
		return [
			'auth' => $this->auth->toArray(),
			'general' => $this->general->toArray(),
		];
	}

	/**
	 * Register the guard that protects the stored settings.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'update_user_metadata', [ self::class, 'preserve_general' ], 10, 5 );
	}

	/**
	 * Keep the `general` branch when an extension writes without it.
	 *
	 * Marketplace extensions share this option, and a build that predates
	 * `VendorGeneral` serialises `general` as an empty array. Left alone that
	 * silently erases the document the vendor typed on another marketplace's
	 * panel. Older builds cannot be patched, so the base plugin repairs the
	 * write on its way to the database.
	 *
	 * Deliberately narrow: only a `general` branch that is empty on the way in
	 * and present in storage is restored. Clearing a contract still writes a
	 * populated `auth`, so deactivation keeps working.
	 *
	 * @param null|bool $check      Short circuit value.
	 * @param int       $user_id    User the meta belongs to.
	 * @param string    $meta_key   Meta key being written.
	 * @param mixed     $meta_value Value being written.
	 * @param mixed     $prev_value Previous value the caller required.
	 *
	 * @return null|bool Null to let the write proceed untouched.
	 */
	public static function preserve_general( $check, $user_id, $meta_key, $meta_value, $prev_value ) {
		global $wpdb;

		if ( $check !== null || self::$guarding ) {
			return $check;
		}

		if ( $meta_key !== $wpdb->get_blog_prefix() . self::OPTION_KEY ) {
			return $check;
		}

		if ( ! is_array( $meta_value ) || ! empty( $meta_value['general'] ) ) {
			return $check;
		}

		$stored = get_user_meta( $user_id, $meta_key, true );

		if ( ! is_array( $stored ) || empty( $stored['general'] ) ) {
			return $check;
		}

		$meta_value['general'] = $stored['general'];

		self::$guarding = true;
		$result = update_user_meta( $user_id, $meta_key, $meta_value, $prev_value );
		self::$guarding = false;

		static::flush( $user_id );

		return $result;
	}
}
