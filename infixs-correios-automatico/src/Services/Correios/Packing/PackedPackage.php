<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

use Infixs\CorreiosAutomatico\Services\Correios\Includes\Package;

defined( 'ABSPATH' ) || exit;

/**
 * A volume that ships with known measures instead of the cubage estimate.
 *
 * The measures come from the stack of its units or from the packaging it travels
 * in. The weight is the weight of the products, extra weight included, plus the
 * empty packaging. The minimums of the shipping method and the package filters
 * apply exactly as they do to any other package.
 *
 * @since 1.11.0
 */
class PackedPackage extends Package {

	/**
	 * Length in cm, the largest side.
	 *
	 * @var float
	 */
	private $length;

	/**
	 * Width in cm, the middle side.
	 *
	 * @var float
	 */
	private $width;

	/**
	 * Height in cm, the smallest side.
	 *
	 * @var float
	 */
	private $height;

	/**
	 * Packaging the volume travels in.
	 *
	 * @var array{id: string, name: string, empty_weight: float}|null
	 */
	private $packaging;

	/**
	 * @param Package    $package    Package of the volume, with the settings of the shipping method.
	 * @param array      $dimensions Length, width and height in cm, in any order.
	 * @param array|null $packaging  Id, name and empty weight in kg of the packaging, or null.
	 */
	public function __construct( $package, $dimensions, $packaging = null ) {
		parent::__construct( [
			'contents' => $package->get_contents(),
			'contents_cost' => $package->get_total(),
		] );

		$this->setExtraWeight( $package->getExtraWeight() );
		$this->setExtraWeightType( $package->getExtraWeightType() );
		$this->setMinWeight( $package->getMinWeight() );
		$this->setMinHeight( $package->getMinHeight() );
		$this->setMinWidth( $package->getMinWidth() );
		$this->setMinLength( $package->getMinLength() );

		$sides = [
			isset( $dimensions['length'] ) ? (float) $dimensions['length'] : 0.0,
			isset( $dimensions['width'] ) ? (float) $dimensions['width'] : 0.0,
			isset( $dimensions['height'] ) ? (float) $dimensions['height'] : 0.0,
		];

		rsort( $sides, SORT_NUMERIC );

		$this->length = $sides[0];
		$this->width = $sides[1];
		$this->height = $sides[2];
		$this->packaging = $packaging ? $packaging : null;
	}

	/**
	 * Get the package data.
	 *
	 * @since 1.11.0
	 *
	 * @return array
	 */
	public function get_data() {
		$data = apply_filters( 'infixs_correios_automatico_default_package', $this->get_package_data() );
		$empty_weight = $this->packaging && isset( $this->packaging['empty_weight'] ) ? (float) $this->packaging['empty_weight'] : 0.0;
		$weight = (float) $data['weight'] + $empty_weight;

		return [
			'height' => apply_filters( 'infixs_correios_automatico_package_height', max( $this->height, $this->getMinHeight() ) ),
			'width' => apply_filters( 'infixs_correios_automatico_package_width', max( $this->width, $this->getMinWidth() ) ),
			'length' => apply_filters( 'infixs_correios_automatico_package_length', max( $this->length, $this->getMinLength() ) ),
			'weight' => apply_filters( 'infixs_correios_automatico_package_weight', max( $weight, $this->getMinWeight() ) ),
		];
	}

	/**
	 * Get the packaging the volume travels in.
	 *
	 * @since 1.11.0
	 *
	 * @return array{id: string, name: string}|null
	 */
	public function get_packaging() {
		if ( ! $this->packaging ) {
			return null;
		}

		return [
			'id' => isset( $this->packaging['id'] ) ? $this->packaging['id'] : '',
			'name' => isset( $this->packaging['name'] ) ? $this->packaging['name'] : '',
		];
	}
}
