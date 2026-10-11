<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * Fits boxes inside a container, turning them as needed.
 *
 * The free room of the container is kept as a list of spaces. Each unit is tried
 * in every space and in each of its distinct orientations; the position closest
 * to the floor wins, then the one that leaves the least room unused in its
 * space, then the lowest one. The space taken is cut in three — what is left of
 * the length, of the width and above the unit — so the spaces never overlap.
 *
 * Besides the inner measures of the container, the contour the units occupy may
 * be held to a largest sum of the sides, as the carrier does with a box.
 *
 * Pure PHP on purpose: the plugin build does not scope its vendor folder, and a
 * packing library would clash with the same library shipped by another plugin.
 *
 * @since 1.11.0
 */
class SpacePacker {

	/**
	 * Slack, in cm, accepted when comparing measures.
	 *
	 * @var float
	 */
	const TOLERANCE = 0.01;

	/**
	 * Inner measures of the container, largest first.
	 *
	 * @var float[]
	 */
	private $limits;

	/**
	 * Heaviest load the container takes, in kg; 0 for no limit.
	 *
	 * @var float
	 */
	private $max_weight;

	/**
	 * Largest sum of the sides of the occupied contour, in cm; 0 for no limit.
	 *
	 * @var float
	 */
	private $max_sum;

	/**
	 * Free spaces: x, y, z, length, width, height, then the three sides largest
	 * first and the volume.
	 *
	 * @var array[]
	 */
	private $spaces = [];

	/**
	 * How far the units reach on each axis: x, y and z.
	 *
	 * @var float[]
	 */
	private $extent = [ 0.0, 0.0, 0.0 ];

	/**
	 * Weight of the units placed so far, in kg.
	 *
	 * @var float
	 */
	private $weight = 0.0;

	/**
	 * @param float $length     Inner length of the container, in cm.
	 * @param float $width      Inner width of the container, in cm.
	 * @param float $height     Inner height of the container, in cm.
	 * @param float $max_weight Heaviest load, in kg; 0 for no limit.
	 * @param float $max_sum    Largest sum of the sides of the occupied contour, in cm; 0 for no limit.
	 */
	public function __construct( $length, $width, $height, $max_weight = 0, $max_sum = 0 ) {
		$this->limits = self::sorted( [ (float) $length, (float) $width, (float) $height ] );
		$this->max_weight = (float) $max_weight;
		$this->max_sum = (float) $max_sum;
		$this->addSpace( 0.0, 0.0, 0.0, (float) $length, (float) $width, (float) $height );
	}

	/**
	 * Pack a set of units, the largest first.
	 *
	 * A packer that returned false is left partly filled; use a new one to try
	 * another set.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units with length, width and height in cm and weight in kg.
	 *
	 * @return bool Whether every unit found a place.
	 */
	public function pack( $units ) {
		$units = array_values( $units );

		usort( $units, function ( $a, $b ) {
			return self::volumeOf( $b ) <=> self::volumeOf( $a );
		} );

		$floors = self::floors( $units );

		foreach ( $units as $position => $unit ) {
			if ( ! $this->add( $unit ) ) {
				return false;
			}

			if ( isset( $floors[ $position + 1 ] ) ) {
				$this->discardSpaces( $floors[ $position + 1 ] );
			}
		}

		return true;
	}

	/**
	 * Place one more unit.
	 *
	 * @since 1.11.0
	 *
	 * @param array $unit Unit with length, width and height in cm and weight in kg.
	 *
	 * @return bool Whether the unit found a place; nothing changes when it did not.
	 */
	public function add( $unit ) {
		$weight = isset( $unit['weight'] ) ? (float) $unit['weight'] : 0.0;

		if ( $this->max_weight > 0 && round( $this->weight + $weight, 3 ) > round( $this->max_weight, 3 ) ) {
			return false;
		}

		$sides = self::sidesOf( $unit );

		if ( max( $sides ) <= self::TOLERANCE ) {
			$this->weight += $weight;

			return true;
		}

		$best = $this->bestPosition( $sides );

		if ( $best === null ) {
			return false;
		}

		$this->place( $best );
		$this->weight += $weight;

		return true;
	}

	/**
	 * Get the contour the units occupy, largest side first.
	 *
	 * Rounded to three decimals: twenty sheets of 0.2 cm reach 4 cm, not the
	 * 4.0000000000000009 cm their float sum leaves.
	 *
	 * @since 1.11.0
	 *
	 * @return array{length: float, width: float, height: float}
	 */
	public function getContour() {
		$sides = self::sorted( $this->extent );

		return [
			'length' => round( $sides[0], 3 ),
			'width' => round( $sides[1], 3 ),
			'height' => round( $sides[2], 3 ),
		];
	}

	/**
	 * Get the weight of the units placed, in kg.
	 *
	 * @since 1.11.0
	 *
	 * @return float
	 */
	public function getWeight() {
		return $this->weight;
	}

	/**
	 * Find where a unit goes.
	 *
	 * A space that is higher than the best position found, or as high but
	 * roomier, cannot win, so its orientations are not even tried.
	 *
	 * @param float[] $sides Measures of the unit.
	 *
	 * @return array{space: int, orientation: float[], extent: float[]}|null Null when
	 *         there is no place for the unit.
	 */
	private function bestPosition( $sides ) {
		$orientations = self::orientations( $sides );
		$sorted = self::sorted( $sides );
		$unit_volume = $sides[0] * $sides[1] * $sides[2];
		$tolerance = self::TOLERANCE;
		$best = null;
		$best_z = 0.0;
		$best_waste = 0.0;
		$best_height = 0.0;

		foreach ( $this->spaces as $index => $space ) {
			if ( $sorted[0] > $space[6] + $tolerance || $sorted[1] > $space[7] + $tolerance || $sorted[2] > $space[8] + $tolerance ) {
				continue;
			}

			$waste = $space[9] - $unit_volume;

			if ( $best !== null ) {
				if ( $space[2] > $best_z + $tolerance ) {
					continue;
				}

				if ( abs( $space[2] - $best_z ) <= $tolerance && $waste > $best_waste + $tolerance ) {
					continue;
				}
			}

			foreach ( $orientations as $orientation ) {
				if ( $orientation[0] > $space[3] + $tolerance || $orientation[1] > $space[4] + $tolerance || $orientation[2] > $space[5] + $tolerance ) {
					continue;
				}

				$x = max( $this->extent[0], $space[0] + $orientation[0] );
				$y = max( $this->extent[1], $space[1] + $orientation[1] );
				$z = max( $this->extent[2], $space[2] + $orientation[2] );

				if ( ! $this->allows( $x, $y, $z ) ) {
					continue;
				}

				if ( $best !== null && ! self::isBetter( [ $space[2], $waste, $orientation[2] ], [ $best_z, $best_waste, $best_height ] ) ) {
					continue;
				}

				$best = [
					'space' => $index,
					'orientation' => $orientation,
					'extent' => [ $x, $y, $z ],
				];
				$best_z = $space[2];
				$best_waste = $waste;
				$best_height = $orientation[2];
			}
		}

		return $best;
	}

	/**
	 * Whether the container still takes a contour of this extent.
	 *
	 * @param float $x Reach on the x axis.
	 * @param float $y Reach on the y axis.
	 * @param float $z Reach on the z axis.
	 *
	 * @return bool
	 */
	private function allows( $x, $y, $z ) {
		if ( $this->max_sum > 0 && $x + $y + $z > $this->max_sum + self::TOLERANCE ) {
			return false;
		}

		$sides = self::sorted( [ $x, $y, $z ] );

		return $sides[0] <= $this->limits[0] + self::TOLERANCE
			&& $sides[1] <= $this->limits[1] + self::TOLERANCE
			&& $sides[2] <= $this->limits[2] + self::TOLERANCE;
	}

	/**
	 * Put a unit in the chosen space and cut what is left of it.
	 *
	 * @param array $choice Position found by bestPosition().
	 *
	 * @return void
	 */
	private function place( $choice ) {
		$space = $this->spaces[ $choice['space'] ];
		$orientation = $choice['orientation'];

		unset( $this->spaces[ $choice['space'] ] );
		$this->spaces = array_values( $this->spaces );

		$this->addSpace( $space[0] + $orientation[0], $space[1], $space[2], $space[3] - $orientation[0], $space[4], $space[5] );
		$this->addSpace( $space[0], $space[1] + $orientation[1], $space[2], $orientation[0], $space[4] - $orientation[1], $space[5] );
		$this->addSpace( $space[0], $space[1], $space[2] + $orientation[2], $orientation[0], $orientation[1], $space[5] - $orientation[2] );

		$this->extent = $choice['extent'];
	}

	/**
	 * Keep a free space, unless it is flat.
	 *
	 * @param float $x      Position on the x axis.
	 * @param float $y      Position on the y axis.
	 * @param float $z      Position on the z axis.
	 * @param float $length Length of the space.
	 * @param float $width  Width of the space.
	 * @param float $height Height of the space.
	 *
	 * @return void
	 */
	private function addSpace( $x, $y, $z, $length, $width, $height ) {
		if ( min( $length, $width, $height ) <= self::TOLERANCE ) {
			return;
		}

		$sides = self::sorted( [ $length, $width, $height ] );

		$this->spaces[] = [ $x, $y, $z, $length, $width, $height, $sides[0], $sides[1], $sides[2], $length * $width * $height ];
	}

	/**
	 * Drop the spaces that cannot hold any of the units still to be placed.
	 *
	 * @param float[] $floor Smallest measures, largest first, among the units left.
	 *
	 * @return void
	 */
	private function discardSpaces( $floor ) {
		$kept = [];

		foreach ( $this->spaces as $space ) {
			if ( $space[6] + self::TOLERANCE >= $floor[0] && $space[7] + self::TOLERANCE >= $floor[1] && $space[8] + self::TOLERANCE >= $floor[2] ) {
				$kept[] = $space;
			}
		}

		$this->spaces = $kept;
	}

	/**
	 * Whether a position beats the best one found so far.
	 *
	 * Criteria, in order: height of the space, room left unused in it and height
	 * of the unit. Values within the tolerance count as a tie.
	 *
	 * @param float[] $candidate Criteria of the position being tried.
	 * @param float[] $best      Criteria of the best position so far.
	 *
	 * @return bool
	 */
	private static function isBetter( $candidate, $best ) {
		foreach ( $candidate as $criterion => $value ) {
			if ( abs( $value - $best[ $criterion ] ) > self::TOLERANCE ) {
				return $value < $best[ $criterion ];
			}
		}

		return false;
	}

	/**
	 * Smallest measures among the units from each position to the end.
	 *
	 * Sides are compared largest first: a space whose sorted sides are smaller
	 * than these on any position cannot hold any of those units, in any turn.
	 *
	 * @param array[] $units Units in packing order.
	 *
	 * @return array[]
	 */
	private static function floors( $units ) {
		$floors = [];
		$floor = null;

		for ( $i = count( $units ) - 1; $i >= 0; $i-- ) {
			$sides = self::sorted( self::sidesOf( $units[ $i ] ) );
			$floor = $floor === null ? $sides : [ min( $floor[0], $sides[0] ), min( $floor[1], $sides[1] ), min( $floor[2], $sides[2] ) ];
			$floors[ $i ] = $floor;
		}

		return $floors;
	}

	/**
	 * Distinct ways of turning a unit.
	 *
	 * @param float[] $sides Measures of the unit.
	 *
	 * @return array[]
	 */
	private static function orientations( $sides ) {
		list( $a, $b, $c ) = $sides;
		$orientations = [];

		foreach ( [ [ $a, $b, $c ], [ $b, $a, $c ], [ $a, $c, $b ], [ $c, $a, $b ], [ $b, $c, $a ], [ $c, $b, $a ] ] as $orientation ) {
			$orientations[ implode( 'x', $orientation ) ] = $orientation;
		}

		return array_values( $orientations );
	}

	/**
	 * Measures of a unit; a missing one counts as zero.
	 *
	 * @param array $unit Unit.
	 *
	 * @return float[]
	 */
	private static function sidesOf( $unit ) {
		return [
			max( 0.0, isset( $unit['length'] ) ? (float) $unit['length'] : 0.0 ),
			max( 0.0, isset( $unit['width'] ) ? (float) $unit['width'] : 0.0 ),
			max( 0.0, isset( $unit['height'] ) ? (float) $unit['height'] : 0.0 ),
		];
	}

	/**
	 * Volume of a unit.
	 *
	 * @param array $unit Unit.
	 *
	 * @return float
	 */
	private static function volumeOf( $unit ) {
		$sides = self::sidesOf( $unit );

		return $sides[0] * $sides[1] * $sides[2];
	}

	/**
	 * Sort measures, largest first.
	 *
	 * @param float[] $values Measures.
	 *
	 * @return float[]
	 */
	private static function sorted( $values ) {
		rsort( $values, SORT_NUMERIC );

		return $values;
	}
}
