<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

defined( 'ABSPATH' ) || exit;
/**
 * Package class.
 * 
 * @since 1.0.0
 */
class Package {

	/**
	 * Order package.
	 *
	 * @var array{
	 * 		contents: array{
	 * 			array{
	 * 				quantity: int,
	 * 				data: \WC_Product
	 * 			}
	 * 		}
	 * }
	 */
	private $package = [];

	/**
	 * Extra weight in kg
	 * 
	 * @var float|int
	 */
	private $extra_weight = 0;

	/**
	 * Extra weight type
	 * 
	 * @var string "product"|"order"
	 */
	private $extra_weight_type = 'order';

	/**
	 * Minimum package weight
	 * 
	 * @var float
	 */
	private $min_weight = 0;

	/**
	 * Minimum package height (cm)
	 * 
	 * @var int
	 */
	private $min_height = 0;

	/**
	 * Minimum package width (cm)
	 * 
	 * @var int
	 */
	private $min_width = 0;


	/**
	 * Minimum package length (cm)
	 * 
	 * @var int
	 */
	private $min_length = 0;


	/**
	 * Sets the package.
	 *
	 * @param array{
	 * 		contents: array{
	 * 			array{
	 * 				quantity: int,
	 * 				data: \WC_Product
	 * 			}
	 * 		}
	 * } $package
	 */
	public function __construct( $package = [] ) {
		$this->package = $package;
	}

	public function get_items_count() {
		$count = 0;
		foreach ( $this->package['contents'] as $item_id => $values ) {
			$qty = $values['quantity'];
			$count += (int) $qty;
		}
		return $count;
	}

	public function get_total() {
		return $this->package['contents_cost'] ?? 0;
	}

	/**
	 * Extracts the weight and dimensions from the package.
	 *
	 * @return array
	 */
	protected function get_package_data() {
		$count = 0;
		$height = [];
		$width = [];
		$length = [];
		$weight = [];

		$product_extra_weight = 0;
		if ( $this->extra_weight_type === 'product' ) {
			$product_extra_weight = $this->extra_weight;
		}

		foreach ( $this->package['contents'] as $item_id => $values ) {
			$product = $values['data'];
			$qty = $values['quantity'];

			if ( $qty > 0 && $product && $product->needs_shipping() ) {

				$_height = wc_get_dimension( (float) $product->get_height(), 'cm' );
				$_width = wc_get_dimension( (float) $product->get_width(), 'cm' );
				$_length = wc_get_dimension( (float) $product->get_length(), 'cm' );
				$_weight = wc_get_weight( (float) $product->get_weight(), 'kg' );

				$height[ $count ] = $_height;
				$width[ $count ] = $_width;
				$length[ $count ] = $_length;
				$weight[ $count ] = $_weight + $product_extra_weight;

				if ( $qty > 1 ) {
					$n = $count;
					for ( $i = 0; $i < $qty; $i++ ) {
						$height[ $n ] = $_height;
						$width[ $n ] = $_width;
						$length[ $n ] = $_length;
						$weight[ $n ] = $_weight + $product_extra_weight;
						++$n;
					}
					$count = $n;
				}

				++$count;
			}
		}

		$order_extra_weight = 0;

		if ( $this->extra_weight_type === 'order' ) {
			$order_extra_weight = $this->extra_weight;
		}

		$sum_weight = array_sum( $weight ) + $order_extra_weight;

		return [ 
			'height' => array_values( $height ),
			'length' => array_values( $length ),
			'width' => array_values( $width ),
			'weight' => $sum_weight,
		];
	}

	/**
	 * Calculates the cubage of all products.
	 *
	 * @param  array $height Package height.
	 * @param  array $width  Package width.
	 * @param  array $length Package length.
	 *
	 * @return int
	 */
	protected function cubage_total( $height, $width, $length ) {
		// Sets the cubage of all products.
		$total = 0;
		$total_items = count( $height );

		for ( $i = 0; $i < $total_items; $i++ ) {
			$total += $height[ $i ] * $width[ $i ] * $length[ $i ];
		}

		return $total;
	}

	/**
	 * Get the max values.
	 *
	 * @param  array $height Package height.
	 * @param  array $width  Package width.
	 * @param  array $length Package length.
	 *
	 * @return array
	 */
	protected function get_max_values( $height, $width, $length ) {
		$find = [ 
			'height' => max( $height ),
			'width' => max( $width ),
			'length' => max( $length ),
		];

		return $find;
	}

	/**
	 * Calculates the square root of the scaling of all products.
	 *
	 * @param  array $height     Package height.
	 * @param  array $width      Package width.
	 * @param  array $length     Package length.
	 * @param  array $max_values Package bigger values.
	 *
	 * @return float
	 */
	protected function calculate_root( $height, $width, $length, $max_values ) {
		$cubage_total = $this->cubage_total( $height, $width, $length );
		$root = 0;
		$biggest = max( $max_values );

		if ( 0 !== $cubage_total && 0 < $biggest ) {
			// Dividing the value of scaling of all products.
			// With the measured value of greater.
			$division = $cubage_total / $biggest;
			// Total square root.
			$root = round( sqrt( $division ), 1 );
		}

		return $root;
	}

	/**
	 * Sets the final cubage.
	 *
	 * @param  array $height Package height.
	 * @param  array $width  Package width.
	 * @param  array $length Package length.
	 *
	 * @return array
	 */
	protected function get_cubage( $height, $width, $length ) {
		if ( count( $height ) === 1 && count( $width ) === 1 && count( $length ) === 1 ) {
			return [ 
				'height' => $height[0],
				'width' => $width[0],
				'length' => $length[0],
			];
		}

		$cubage = [];
		$max_values = $this->get_max_values( $height, $width, $length );
		$root = $this->calculate_root( $height, $width, $length, $max_values );
		$greatest = array_search( max( $max_values ), $max_values, true );

		switch ( $greatest ) {
			case 'height':
				$cubage = [ 
					'height' => max( $height ),
					'width' => $root,
					'length' => $root,
				];
				break;
			case 'width':
				$cubage = [ 
					'height' => $root,
					'width' => max( $width ),
					'length' => $root,
				];
				break;
			case 'length':
				$cubage = [ 
					'height' => $root,
					'width' => $root,
					'length' => max( $length ),
				];
				break;

			default:
				$cubage = [ 
					'height' => 0,
					'width' => 0,
					'length' => 0,
				];
				break;
		}

		return $cubage;
	}

	public function get_contents() {
		return $this->package['contents'];
	}

	/**
	 * Get the package data.
	 *
	 * @return array
	 */
	// Get the package data.
	public function get_data() {
		$data = apply_filters( 'infixs_correios_automatico_default_package', $this->get_package_data() );

		if ( ! empty( $data['height'] ) && ! empty( $data['width'] ) && ! empty( $data['length'] ) ) {
			$cubage = $this->get_cubage( $data['height'], $data['width'], $data['length'] );
		} else {
			$cubage = [ 
				'height' => 0,
				'width' => 0,
				'length' => 0,
			];
		}

		return [ 
			'height' => apply_filters( 'infixs_correios_automatico_package_height', max( $cubage['height'], $this->min_height ) ),
			'width' => apply_filters( 'infixs_correios_automatico_package_width', max( $cubage['width'], $this->min_width ) ),
			'length' => apply_filters( 'infixs_correios_automatico_package_length', max( $cubage['length'], $this->min_length ) ),
			'weight' => apply_filters( 'infixs_correios_automatico_package_weight', max( $data['weight'], $this->min_weight ) ),
		];
	}

	/**
	 * Get the extra weight (kg).
	 *
	 * @return float|int
	 */
	public function getExtraWeight() {
		return $this->extra_weight;
	}

	/**
	 * Set the extra weight (kg)..
	 *
	 * @param float|int $extra_weight Extra weight.
	 */
	public function setExtraWeight( $extra_weight ) {
		$this->extra_weight = $extra_weight;
	}

	/**
	 * Get the extra weight type.
	 *
	 * @return string "order"|"product"
	 */
	public function getExtraWeightType() {
		return $this->extra_weight_type;
	}

	/**
	 * Set the extra weight type.
	 *
	 * @param string $extra_weight_type "order"|"product"
	 */
	public function setExtraWeightType( $extra_weight_type ) {
		$this->extra_weight_type = $extra_weight_type;
	}

	/**
	 * Get the minimum weight (kg).
	 *
	 * @return float
	 */
	public function getMinWeight() {
		return $this->min_weight;
	}

	/**
	 * Set the minimum weight. (kg).
	 *
	 * @param float $min_weight Minimum weight.
	 */
	public function setMinWeight( $min_weight ) {
		$this->min_weight = $min_weight;
	}

	/**
	 * Get the minimum height (cm).
	 *
	 * @return int
	 */
	public function getMinHeight() {
		return $this->min_height;
	}

	/**
	 * Set the minimum height (cm).
	 *
	 * @param int $min_height Minimum height.
	 */
	public function setMinHeight( $min_height ) {
		$this->min_height = $min_height;
	}

	/**
	 * Get the minimum width (cm).
	 *
	 * @return int
	 */
	public function getMinWidth() {
		return $this->min_width;
	}

	/**
	 * Set the minimum width (cm).
	 *
	 * @param int $min_width Minimum width.
	 */
	public function setMinWidth( $min_width ) {
		$this->min_width = $min_width;
	}

	/**
	 * Get the minimum length (cm).
	 *
	 * @return int
	 */
	public function getMinLength() {
		return $this->min_length;
	}

	/**
	 * Set the minimum length (cm).
	 *
	 * @param int $min_length Minimum length.
	 */
	public function setMinLength( $min_length ) {
		$this->min_length = $min_length;
	}
}
