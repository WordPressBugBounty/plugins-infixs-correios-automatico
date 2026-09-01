<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

defined( 'ABSPATH' ) || exit;

/**
 * Withholds the Correios rates from a vendor that has no contract.
 *
 * Some marketplaces would rather show nothing than quote a vendor on the
 * retail table or on the store contract. Removing the rate here, instead of
 * blocking the calculation, keeps the reason out of the failure diagnostics:
 * nothing failed, the store chose not to offer it.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.3
 */
class VendorRateGuard {

	const METHOD_ID = 'infixs-correios-automatico';

	public function __construct() {
		add_filter( 'woocommerce_package_rates', [ $this, 'filter_rates' ], 210, 2 );
	}

	/**
	 * Drop the Correios rates of a vendor without a contract of its own.
	 *
	 * Runs late on purpose: the WCFM guard restores what its marketplace had
	 * dropped at 200, and a rate put back there has to be removed too.
	 *
	 * @param \WC_Shipping_Rate[] $rates   Package rates.
	 * @param array               $package Cart package.
	 *
	 * @return \WC_Shipping_Rate[]
	 */
	public function filter_rates( $rates, $package ) {
		if ( ! Settings::hidesWithoutContract() ) {
			return $rates;
		}

		$vendor_id = $this->resolveVendor( $package );

		if ( ! $vendor_id || \Infixs\CorreiosAutomatico\Config\VendorSettings::hasActiveContract( $vendor_id ) ) {
			return $rates;
		}

		foreach ( $rates as $rate_id => $rate ) {
			if ( $rate->get_method_id() === self::METHOD_ID ) {
				unset( $rates[ $rate_id ] );
			}
		}

		return $rates;
	}

	/**
	 * Resolve the vendor a cart package belongs to.
	 *
	 * @param array $package Cart package.
	 *
	 * @return int Vendor ID, zero when the package is not a vendor's.
	 */
	private function resolveVendor( $package ) {
		if ( ! empty( $package['vendor_id'] ) ) {
			return (int) $package['vendor_id'];
		}

		/**
		 * Filters the vendor a cart package belongs to.
		 *
		 * Marketplace extensions answer this when the package carries no
		 * vendor of its own.
		 *
		 * @since 1.10.3
		 *
		 * @param int   $vendor_id
		 * @param array $package
		 */
		return (int) apply_filters( 'infixs_correios_automatico_package_vendor_id', 0, $package );
	}
}
