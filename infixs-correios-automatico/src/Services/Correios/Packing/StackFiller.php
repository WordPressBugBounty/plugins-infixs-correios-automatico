<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * Fills a volume by stacking the units inside the largest box the service takes.
 *
 * The box is virtual: its sides are the largest the service accepts and the
 * contour of the stack is held to the largest sum of the sides, so a volume
 * accepted here is one the carrier receives whatever box the store puts it in.
 *
 * The splitter asks about the same volume over and over, each time with one
 * more unit. The stack of each volume is kept, so the new unit is placed over
 * it; only when that fails is the whole volume packed again, the largest units
 * first. A volume that refused a unit even then is not offered a unit of the
 * same measures again: it only gets fuller from there.
 *
 * @since 1.11.0
 */
class StackFiller implements VolumeFiller {

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
	 * Stack of each volume and the units it holds.
	 *
	 * @var array<int, array{signature: string, packer: SpacePacker}>
	 */
	private $stacks = [];

	/**
	 * Measures of the units each volume refused.
	 *
	 * @var array<int, array<string, bool>>
	 */
	private $refused = [];

	/**
	 * @param array    $limits  Limits of the service, as DeliveryServiceCode::getDimensionLimits() returns.
	 * @param callable $factory Receives the contents of a volume and its index, returns a Package.
	 */
	public function __construct( $limits, $factory ) {
		$this->limits = $limits;
		$this->factory = $factory;
	}

	/**
	 * Whether these units stack within the carrier limits.
	 *
	 * The weight is the one the store is quoted with, extra weight and minimum
	 * weight included.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units of the candidate volume, the one being added last.
	 * @param int     $index Position of the volume in the shipment.
	 *
	 * @return bool
	 */
	public function fits( $units, $index ) {
		$units = array_values( $units );
		$keys = array_column( $units, 'key' );
		$previous = implode( "\n", array_slice( $keys, 0, -1 ) );
		$signature = md5( implode( "\n", $keys ) );

		if ( isset( $this->stacks[ $index ] ) && $this->stacks[ $index ]['signature'] === $signature ) {
			return true;
		}

		$last = $units[ count( $units ) - 1 ];
		$shape = self::shapeOf( $last );

		if ( isset( $this->refused[ $index ][ $shape ] ) ) {
			return false;
		}

		$package = call_user_func( $this->factory, PackingUnits::toContents( $units ), $index );
		$data = $package->get_data();

		if ( round( (float) $data['weight'], 3 ) > round( (float) $this->limits['max']['weight'], 3 ) ) {
			return false;
		}

		$packer = null;

		if ( isset( $this->stacks[ $index ] ) && $this->stacks[ $index ]['signature'] === md5( $previous ) ) {
			$packer = clone $this->stacks[ $index ]['packer'];

			if ( ! $packer->add( $last ) ) {
				$packer = null;
			}
		}

		if ( $packer === null ) {
			$packer = $this->container();

			if ( ! $packer->pack( $units ) ) {
				$this->refused[ $index ][ $shape ] = true;

				return false;
			}
		}

		$this->stacks[ $index ] = [
			'signature' => $signature,
			'packer' => $packer,
		];

		return true;
	}

	/**
	 * The volume ships with the contour of its stack.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units of the volume.
	 * @param int     $index Position of the volume in the shipment.
	 *
	 * @return array{length: float, width: float, height: float, packaging: null}|null
	 */
	public function describe( $units, $index ) {
		$units = array_values( $units );
		$signature = md5( implode( "\n", array_column( $units, 'key' ) ) );

		if ( isset( $this->stacks[ $index ] ) && $this->stacks[ $index ]['signature'] === $signature ) {
			$packer = $this->stacks[ $index ]['packer'];
		} else {
			$packer = $this->container();

			if ( ! $packer->pack( $units ) ) {
				return null;
			}
		}

		return array_merge( $packer->getContour(), [ 'packaging' => null ] );
	}

	/**
	 * Build the virtual box of the service.
	 *
	 * @return SpacePacker
	 */
	private function container() {
		$max = $this->limits['max'];

		return new SpacePacker(
			$max['length'],
			$max['width'],
			$max['height'],
			$max['weight'],
			isset( $max['total_dimensions'] ) ? $max['total_dimensions'] : 0
		);
	}

	/**
	 * Measures of a unit, as a key.
	 *
	 * @param array $unit Unit.
	 *
	 * @return string
	 */
	private static function shapeOf( $unit ) {
		return implode( 'x', [ (float) $unit['length'], (float) $unit['width'], (float) $unit['height'] ] );
	}
}
