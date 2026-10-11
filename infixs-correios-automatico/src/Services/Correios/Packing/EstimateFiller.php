<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * Fills a volume by the cubage estimate the store is quoted with.
 *
 * Every candidate volume is measured through the very Package the shipping
 * method builds, so a volume accepted here is one the quote already accepts.
 * The estimate does not know the real shape of the box, which is why it is kept
 * for the shipments too large to be stacked unit by unit.
 *
 * @since 1.11.0
 */
class EstimateFiller implements VolumeFiller {

	/**
	 * Carrier limits for the service being quoted.
	 *
	 * @var array
	 */
	private $limits;

	/**
	 * Builds the Package of a volume from its contents and position.
	 *
	 * @var callable
	 */
	private $factory;

	/**
	 * @param array    $limits  Limits of the service, as DeliveryServiceCode::getDimensionLimits() returns.
	 * @param callable $factory Receives the contents of a volume and its index, returns a Package.
	 */
	public function __construct( $limits, $factory ) {
		$this->limits = $limits;
		$this->factory = $factory;
	}

	/**
	 * Whether the estimated box of these units is within the carrier limits.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units of the candidate volume.
	 * @param int     $index Position of the volume in the shipment.
	 *
	 * @return bool
	 */
	public function fits( $units, $index ) {
		$package = call_user_func( $this->factory, PackingUnits::toContents( $units ), $index );

		return ServiceLimits::accepts( $this->limits, $package->get_data() );
	}

	/**
	 * The volume ships with the measures estimated from its products.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units of the volume.
	 * @param int     $index Position of the volume in the shipment.
	 *
	 * @return null
	 */
	public function describe( $units, $index ) {
		return null;
	}
}
