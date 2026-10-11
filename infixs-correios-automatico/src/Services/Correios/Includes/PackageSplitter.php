<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

use Infixs\CorreiosAutomatico\Services\Correios\Packing\EstimateFiller;
use Infixs\CorreiosAutomatico\Services\Correios\Packing\PackingUnits;
use Infixs\CorreiosAutomatico\Services\Correios\Packing\VolumeFiller;

defined( 'ABSPATH' ) || exit;

/**
 * Breaks a shipment into the volumes a carrier is willing to carry.
 *
 * The carrier prices one box at a time, so a cart heavier or larger than the
 * service allows has no price at all — the store either hides the method or
 * quotes a box the post office will refuse to receive. Splitting the cart into
 * volumes that each fit the limits turns that into a real quote: the customer
 * pays the sum, and the order carries one shipping line per volume.
 *
 * Packing is first fit decreasing: the heaviest units go in first and each one
 * lands in the earliest volume that still accepts it. Whether a volume accepts a
 * unit, and the measures the volume ships with, is up to the VolumeFiller; by
 * default every candidate volume is measured by the same cubage the store would
 * be charged for, through the very Package the shipping method builds.
 *
 * @since 1.11.0
 */
class PackageSplitter {

	/**
	 * Carrier limits for the service being quoted.
	 *
	 * @var array{max: array{length: float, width: float, height: float, weight: float, total_dimensions?: float}}
	 */
	private $limits;

	/**
	 * Largest number of volumes a shipment may be broken into.
	 *
	 * @var int
	 */
	private $max_volumes;

	/**
	 * Decides what fits in a volume and how the volume ships.
	 *
	 * @var VolumeFiller
	 */
	private $filler;

	/**
	 * Items that ship on their own account, kept aside from the packing.
	 *
	 * @var array
	 */
	private $not_shipped = [];

	/**
	 * @param array             $limits      Limits of the service, as DeliveryServiceCode::getDimensionLimits() returns.
	 * @param callable          $factory     Receives the contents of a volume and its index, returns a Package.
	 *                                       Owned by the caller because the minimum dimensions, the extra weight
	 *                                       and the way the extra weight is spread belong to the shipping method.
	 * @param int               $max_volumes Largest number of volumes accepted.
	 * @param VolumeFiller|null $filler      Decides what fits in a volume; the cubage estimate when null.
	 */
	public function __construct( $limits, $factory, $max_volumes = 10, $filler = null ) {
		$this->limits = $limits;
		$this->max_volumes = max( 1, (int) $max_volumes );
		$this->filler = $filler instanceof VolumeFiller ? $filler : new EstimateFiller( $limits, $factory );
	}

	/**
	 * Split the contents of a package into volumes.
	 *
	 * @param array $contents Cart package contents.
	 *
	 * @return array[]|false One contents array per volume, or false when the
	 *                       shipment cannot be broken down — a single unit over
	 *                       the limits, or more volumes than allowed.
	 */
	public function split( $contents ) {
		$volumes = $this->pack( $contents );

		return $volumes === false ? false : wp_list_pluck( $volumes, 'contents' );
	}

	/**
	 * Pack the contents of a package into volumes, telling how each one ships.
	 *
	 * @since 1.11.0
	 *
	 * @param array $contents Cart package contents.
	 *
	 * @return array{contents: array, description: array|null}[]|false Contents of each
	 *         volume and the description its filler gave, or false when the shipment
	 *         cannot be broken down.
	 */
	public function pack( $contents ) {
		$exploded = PackingUnits::explode( $contents );
		$units = $exploded['units'];
		$this->not_shipped = $exploded['not_shipped'];

		if ( empty( $units ) ) {
			return false;
		}

		$max_weight = (float) $this->limits['max']['weight'];

		if ( array_sum( wp_list_pluck( $units, 'weight' ) ) > $max_weight * $this->max_volumes ) {
			return false;
		}

		usort( $units, function ($a, $b) {
			if ( $a['weight'] === $b['weight'] ) {
				return $b['volume'] <=> $a['volume'];
			}

			return $b['weight'] <=> $a['weight'];
		} );

		$volumes = [];
		$weights = [];

		foreach ( $units as $unit ) {
			$placed = false;

			foreach ( $volumes as $index => $volume ) {
				/**
				 * Weighing the candidate first costs nothing and answers most of
				 * the attempts: the filler below has to build the box to measure
				 * it, which on a cart of hundreds of units is the whole cost of
				 * packing. A box already at the limit never gets built.
				 */
				if ( round( $weights[ $index ] + $unit['weight'], 3 ) > round( $max_weight, 3 ) ) {
					continue;
				}

				$candidate = array_merge( $volume, [ $unit ] );

				if ( $this->filler->fits( $candidate, $index ) ) {
					$volumes[ $index ] = $candidate;
					$weights[ $index ] += $unit['weight'];
					$placed = true;
					break;
				}
			}

			if ( $placed ) {
				continue;
			}

			$index = count( $volumes );

			if ( $index >= $this->max_volumes || ! $this->filler->fits( [ $unit ], $index ) ) {
				return false;
			}

			$volumes[] = [ $unit ];
			$weights[] = $unit['weight'];
		}

		return $this->toVolumes( $volumes );
	}

	/**
	 * Turn the packed units back into the contents of each volume.
	 *
	 * @param array[] $volumes Units of each volume.
	 *
	 * @return array{contents: array, description: array|null}[]
	 */
	private function toVolumes( $volumes ) {
		$packed = [];

		foreach ( $volumes as $index => $units ) {
			$packed[ $index ] = [
				'contents' => PackingUnits::toContents( $units ),
				'description' => $this->filler->describe( $units, $index ),
			];
		}

		foreach ( $this->not_shipped as $key => $values ) {
			$packed[0]['contents'][ $key ] = $values;
		}

		return $packed;
	}
}
