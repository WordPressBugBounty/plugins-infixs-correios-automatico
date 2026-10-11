<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

use Infixs\CorreiosAutomatico\Services\Correios\Includes\Package;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\PackageSplitter;

defined( 'ABSPATH' ) || exit;

/**
 * Decides how many volumes a shipment travels in and the measures of each.
 *
 * Without packaging of its own, the store keeps the quote it has today whenever
 * that quote is right: the units are stacked for real only to find out whether
 * the shipment fits the limits of the service and how to break it when it does
 * not. A volume is quoted with the contour of its stack only when the estimate
 * of today is over the limits although the units do fit, as a few flat products
 * sent by Mini Envios.
 *
 * A filler handed in by an extension, such as the packaging of the store, packs
 * every shipment, and each volume ships with the measures and the packaging the
 * filler chose.
 *
 * Plans are kept for the rest of the request: the cart totals are calculated
 * several times per page, always with the same contents.
 *
 * @since 1.11.0
 */
class VolumePlanner {

	/**
	 * Plans already made in this request, by what they depend on.
	 *
	 * @var array<string, array{volumes: Package[]|null, within_limits: bool}>
	 */
	private $plans = [];

	/**
	 * Plan the volumes of a shipment.
	 *
	 * @since 1.11.0
	 *
	 * @param array      $package Cart package.
	 * @param array|null $limits  Limits of the service, as DeliveryServiceCode::getDimensionLimits() returns.
	 * @param callable   $factory Receives the contents of a volume and its index, returns the Package
	 *                            the store is quoted with today.
	 * @param array      $options {
	 *     @type array             $measure     Length, width, height and weight the whole shipment is quoted with today.
	 *     @type bool              $overridden  Whether those measures were informed instead of taken from the products.
	 *     @type bool              $split       Whether the shipment may travel in more than one volume.
	 *     @type int               $max_volumes Largest number of volumes.
	 *     @type int               $max_units   Largest number of units stacked one by one; above it the estimate decides.
	 *     @type VolumeFiller|null $filler      Filler that packs every shipment, such as the packaging of the store.
	 *     @type array             $settings    Settings of the shipping method that change the packages.
	 * }
	 *
	 * @return array{volumes: Package[]|null, within_limits: bool} Package of each volume, or null
	 *         when the shipment is quoted whole, as it is today; and whether it is within the limits.
	 */
	public function plan( $package, $limits, $factory, $options ) {
		$options = wp_parse_args( $options, [
			'measure' => [ 'length' => 0, 'width' => 0, 'height' => 0, 'weight' => 0 ],
			'overridden' => false,
			'split' => true,
			'max_volumes' => 10,
			'max_units' => 200,
			'filler' => null,
			'settings' => [],
		] );

		$key = $this->key( $package, $limits, $options );

		if ( ! isset( $this->plans[ $key ] ) ) {
			$this->plans[ $key ] = $this->resolve( $package, $limits, $factory, $options );
		}

		return $this->plans[ $key ];
	}

	/**
	 * Forget the plans made so far.
	 *
	 * @since 1.11.0
	 *
	 * @return void
	 */
	public function flush() {
		$this->plans = [];
	}

	/**
	 * Make the plan of a shipment.
	 *
	 * Measures informed by hand answer for the shipment whenever the service
	 * takes them: they are the box the store actually has. When the service does
	 * not take them and the shipment may not be split, they still answer for it,
	 * over the limits.
	 *
	 * @param array      $package Cart package.
	 * @param array|null $limits  Limits of the service.
	 * @param callable   $factory Builds the Package of a volume.
	 * @param array      $options Options of plan().
	 *
	 * @return array{volumes: Package[]|null, within_limits: bool}
	 */
	private function resolve( $package, $limits, $factory, $options ) {
		$within = ServiceLimits::accepts( $limits, $options['measure'] );

		if ( ! $limits || empty( $package['contents'] ) || ( $options['overridden'] && ( $within || ! $options['split'] ) ) ) {
			return self::whole( $within );
		}

		if ( PackingUnits::count( $package['contents'] ) > (int) $options['max_units'] ) {
			return $this->estimate( $package, $limits, $factory, $options, $within );
		}

		if ( $options['filler'] instanceof VolumeFiller ) {
			$plan = $this->fill( $package, $limits, $factory, $options );

			if ( $plan ) {
				return $plan;
			}
		}

		return $this->stack( $package, $limits, $factory, $options, $within );
	}

	/**
	 * Plan by the cubage estimate, splitting only a shipment over the limits.
	 *
	 * @param array    $package Cart package.
	 * @param array    $limits  Limits of the service.
	 * @param callable $factory Builds the Package of a volume.
	 * @param array    $options Options of plan().
	 * @param bool     $within  Whether the whole shipment is within the limits today.
	 *
	 * @return array{volumes: Package[]|null, within_limits: bool}
	 */
	private function estimate( $package, $limits, $factory, $options, $within ) {
		if ( $within || ! $options['split'] ) {
			return self::whole( $within );
		}

		$splitter = new PackageSplitter( $limits, $factory, $options['max_volumes'] );
		$volumes = $splitter->pack( $package['contents'] );

		if ( ! $volumes ) {
			return self::whole( false );
		}

		$packages = [];

		foreach ( $volumes as $index => $volume ) {
			$packages[] = call_user_func( $factory, $volume['contents'], $index );
		}

		return [
			'volumes' => $packages,
			'within_limits' => true,
		];
	}

	/**
	 * Plan with the filler handed in by an extension.
	 *
	 * @param array    $package Cart package.
	 * @param array    $limits  Limits of the service.
	 * @param callable $factory Builds the Package of a volume.
	 * @param array    $options Options of plan().
	 *
	 * @return array{volumes: Package[], within_limits: bool}|null Null when the filler
	 *         cannot pack the shipment within the limits.
	 */
	private function fill( $package, $limits, $factory, $options ) {
		$splitter = new PackageSplitter( $limits, $factory, $options['max_volumes'], $options['filler'] );
		$volumes = $splitter->pack( $package['contents'] );

		if ( ! $volumes || ( count( $volumes ) > 1 && ! $options['split'] ) ) {
			return null;
		}

		$packages = [];

		foreach ( $volumes as $index => $volume ) {
			$volume_package = call_user_func( $factory, $volume['contents'], $index );

			if ( $volume['description'] ) {
				$volume_package = self::packed( $volume_package, $volume['description'] );
			}

			if ( ! ServiceLimits::accepts( $limits, $volume_package->get_data() ) ) {
				return null;
			}

			$packages[] = $volume_package;
		}

		return [
			'volumes' => $packages,
			'within_limits' => true,
		];
	}

	/**
	 * Plan by stacking the units in the largest box the service takes.
	 *
	 * A shipment within the limits today whose units each fit the box it is
	 * quoted with keeps that quote without being stacked: the packer fills the
	 * floor first, and would break in two a cart a real box holds. A shipment that
	 * fits one box keeps the quote of today when that quote is within the limits.
	 * Otherwise each volume keeps its own estimate when the estimate is within the
	 * limits, and ships with the contour of its stack when it is not.
	 *
	 * @param array    $package Cart package.
	 * @param array    $limits  Limits of the service.
	 * @param callable $factory Builds the Package of a volume.
	 * @param array    $options Options of plan().
	 * @param bool     $within  Whether the whole shipment is within the limits today.
	 *
	 * @return array{volumes: Package[]|null, within_limits: bool}
	 */
	private function stack( $package, $limits, $factory, $options, $within ) {
		if ( $within && self::unitsFit( $package['contents'], $options['measure'] ) ) {
			return self::whole( true );
		}

		$splitter = new PackageSplitter( $limits, $factory, $options['max_volumes'], new StackFiller( $limits, $factory ) );
		$volumes = $splitter->pack( $package['contents'] );

		if ( ! $volumes ) {
			return self::whole( false );
		}

		if ( count( $volumes ) === 1 && $within ) {
			return self::whole( true );
		}

		if ( count( $volumes ) > 1 && ! $options['split'] ) {
			return self::whole( false );
		}

		$packages = [];

		foreach ( $volumes as $index => $volume ) {
			$volume_package = call_user_func( $factory, $volume['contents'], $index );

			if ( ServiceLimits::accepts( $limits, $volume_package->get_data() ) ) {
				$packages[] = $volume_package;
				continue;
			}

			if ( ! $volume['description'] ) {
				return self::whole( false );
			}

			$volume_package = self::packed( $volume_package, $volume['description'] );

			if ( ! ServiceLimits::accepts( $limits, $volume_package->get_data() ) ) {
				return self::whole( false );
			}

			$packages[] = $volume_package;
		}

		return [
			'volumes' => $packages,
			'within_limits' => true,
		];
	}

	/**
	 * Whether each unit, on its own, fits the box a shipment is quoted with today.
	 *
	 * The estimate keeps the volume of the products but not their shape: sheets
	 * wider than the box it made are the shipments it gets wrong.
	 *
	 * @param array $contents Cart package contents.
	 * @param array $measure  Length, width and height the shipment is quoted with today.
	 *
	 * @return bool
	 */
	private static function unitsFit( $contents, $measure ) {
		$tried = [];

		foreach ( PackingUnits::explode( $contents )['units'] as $unit ) {
			if ( isset( $tried[ $unit['key'] ] ) ) {
				continue;
			}

			$tried[ $unit['key'] ] = true;
			$box = new SpacePacker( $measure['length'], $measure['width'], $measure['height'] );

			if ( ! $box->add( $unit ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build the package of a volume that ships with the measures a filler gave.
	 *
	 * @param Package $package     Package of the volume as quoted today.
	 * @param array   $description Description returned by VolumeFiller::describe().
	 *
	 * @return PackedPackage
	 */
	private static function packed( $package, $description ) {
		return new PackedPackage(
			$package,
			$description,
			isset( $description['packaging'] ) ? $description['packaging'] : null
		);
	}

	/**
	 * Plan that quotes the shipment whole.
	 *
	 * @param bool $within Whether the shipment is within the limits.
	 *
	 * @return array{volumes: null, within_limits: bool}
	 */
	private static function whole( $within ) {
		return [
			'volumes' => null,
			'within_limits' => (bool) $within,
		];
	}

	/**
	 * Key of a plan: everything the volumes depend on.
	 *
	 * The filler is told apart by its class, so an extension whose filler changes
	 * within a request has to flush the plans.
	 *
	 * @param array      $package Cart package.
	 * @param array|null $limits  Limits of the service.
	 * @param array      $options Options of plan().
	 *
	 * @return string
	 */
	private function key( $package, $limits, $options ) {
		$contents = [];

		foreach ( isset( $package['contents'] ) ? $package['contents'] : [] as $key => $values ) {
			$product = isset( $values['data'] ) && $values['data'] instanceof \WC_Product ? $values['data'] : null;

			$contents[] = [
				$key,
				isset( $values['quantity'] ) ? (int) $values['quantity'] : 0,
				isset( $values['line_total'] ) ? $values['line_total'] : null,
				isset( $values['line_subtotal'] ) ? $values['line_subtotal'] : null,
				$product ? $product->get_id() : 0,
				$product ? $product->needs_shipping() : false,
				$product ? wc_get_weight( (float) $product->get_weight(), 'kg' ) : 0,
				$product ? wc_get_dimension( (float) $product->get_length(), 'cm' ) : 0,
				$product ? wc_get_dimension( (float) $product->get_width(), 'cm' ) : 0,
				$product ? wc_get_dimension( (float) $product->get_height(), 'cm' ) : 0,
			];
		}

		return md5( wp_json_encode( [
			'contents' => $contents,
			'contents_cost' => isset( $package['contents_cost'] ) ? $package['contents_cost'] : null,
			'limits' => $limits,
			'measure' => $options['measure'],
			'overridden' => (bool) $options['overridden'],
			'split' => (bool) $options['split'],
			'max_volumes' => (int) $options['max_volumes'],
			'max_units' => (int) $options['max_units'],
			'filler' => is_object( $options['filler'] ) ? get_class( $options['filler'] ) : null,
			'settings' => $options['settings'],
		] ) );
	}
}
