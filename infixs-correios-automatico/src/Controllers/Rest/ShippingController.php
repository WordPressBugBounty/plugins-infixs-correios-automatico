<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Services\ShippingService;
use Infixs\CorreiosAutomatico\Utils\Helper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping Controller
 * 
 * @since 1.0.0
 * 
 * @package Infixs\CorreiosAutomatico\Controllers\Rest
 */
class ShippingController {

	/**
	 * Shipping Service
	 * 
	 * @since 1.4.0
	 * 
	 * @var ShippingService
	 */
	private $shippingService;

	/**
	 * Constructor
	 * 
	 * @since 1.4.0
	 * 
	 * @param ShippingService $shippingService
	 */
	public function __construct( ShippingService $shippingService ) {
		$this->shippingService = $shippingService;
	}


	/**
	 * Get shipping methods
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return array
	 */
	public function list_shipping_methods( $request ) {
		$params = $request->get_params();

		$enabled = isset( $params['is_enabled'] ) ? Sanitizer::boolean( $params['is_enabled'] ) : null;
		$method_id = isset( $params['method_id'] ) ? Sanitizer::array_strings( $params['method_id'] ) : null;

		try {
			$shipping_methods = $this->shippingService->list_shipping_methods( [ 
				'is_enabled' => $enabled,
				'method_id' => $method_id,
			] );

			$zones = \WC_Shipping_Zones::get_zones();

			$shipping_zones = [];

			foreach ( $zones as $zone ) {
				$shipping_zones[] = [ 
					'id' => $zone['zone_id'],
					'name' => $zone['zone_name'],
				];
			}

			return [ 
				'origin_postcode' => Sanitizer::numeric_text( get_option( 'woocommerce_store_postcode' ) ),
				'shipping_zones' => $shipping_zones,
				'shipping_methods' => $shipping_methods,
			];
		} catch (\Exception $e) {
			return [ 
				'shipping_methods' => [],
			];
		}
	}

	/**
	 * Add shipping methods
	 * 
	 * @since 1.4.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return array|\WP_Error
	 */
	public function add_shipping_methods( $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['shipping_zones'] ) && ! isset( $params['create_shipping_zone'] ) ) {
			return new \WP_Error( 'missing_shipping_zones', 'shipping_zones is required or set create_shipping_zone = true.', [ 
				'status' => 400,
			] );
		}

		if ( ! isset( $params['origin_postcode'] ) ) {
			return new \WP_Error( 'missing_origin_postcode', 'origin_postcode is required.', [ 
				'status' => 400,
			] );
		}

		if ( ! Helper::isValidPostcode( $params['origin_postcode'] ) ) {
			return new \WP_Error( 'invalid_origin_postcode', 'origin_postcode is invalid.', [ 
				'status' => 400,
			] );
		}

		if ( ! isset( $params['basic_service'] ) && ! isset( $params['service_code'] ) ) {
			return new \WP_Error( 'missing_service_code', 'service_code is required or basic_service.', [ 
				'status' => 400,
			] );
		}

		$shipping_zones = [];

		if ( isset( $params['create_shipping_zone'] ) && $params['create_shipping_zone'] === true ) {
			$shipping_zones[] = $this->shippingService->createDefaultShippingZone();
		} else {
			$shipping_zones = Sanitizer::array_numbers( $params['shipping_zones'] );
		}

		$origin_postcode = Sanitizer::numeric_text( $params['origin_postcode'] );
		$basic_service = sanitize_text_field( $params['basic_service'] );

		$props = [ 
			'origin_postcode' => $origin_postcode,
			'basic_service' => $basic_service,
		];

		if ( isset( $params['title'] ) )
			$props['title'] = sanitize_text_field( $params['title'] );

		$success = false;
		$created_shipping_methods = [];

		foreach ( $shipping_zones as $zone_id ) {
			if ( $method = $this->shippingService->createShippingMethod( $zone_id, $props ) ) {
				$success = true;
				$created_shipping_methods[] = [ 
					'zone_id' => $zone_id,
					'method_id' => $method->get_instance_id(),
					'title' => $method->get_title()
				];
			}
		}

		if ( ! $success ) {
			return new \WP_Error( 'failed_create_shipping_methods', 'Failed to create shipping methods.', [ 
				'status' => 500,
			] );
		}

		return [ 
			'success' => true,
			'shipping_zones' => $shipping_zones,
			'shipping_methods' => $created_shipping_methods,
		];
	}

	/**
	 * Update shipping methods
	 * 
	 * @since 1.4.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return array|\WP_Error
	 */
	public function add_batch_shipping_methods( $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['methods'] ) && ! is_array( $params['methods'] ) ) {
			return new \WP_Error( 'missing_methods', 'methods array is required.', [ 
				'status' => 400,
			] );
		}

		if ( ! isset( $params['shipping_zones'] ) && ! isset( $params['create_shipping_zone'] ) ) {
			return new \WP_Error( 'missing_shipping_zones', 'shipping_zones is required or set create_shipping_zone = true.', [ 
				'status' => 400,
			] );
		}

		$shipping_zones = [];

		if ( isset( $params['create_shipping_zone'] ) && $params['create_shipping_zone'] === true ) {
			$shipping_zones[] = $this->shippingService->createDefaultShippingZone();
		} else {
			$shipping_zones = Sanitizer::array_numbers( $params['shipping_zones'] );
		}

		$created_shipping_methods = [];

		foreach ( $params['methods'] as $method ) {
			foreach ( $shipping_zones as $zone_id ) {
				if ( $created_method = $this->shippingService->createShippingMethod( $zone_id, $method ) ) {
					$created_shipping_methods[] = [ 
						'zone_id' => $zone_id,
						'method_id' => $created_method->get_instance_id(),
						'title' => $created_method->get_title()
					];
				}
			}
		}

		return [ 
			'success' => true,
			'shipping_zones' => $shipping_zones,
			'shipping_methods' => $created_shipping_methods,
		];
	}

	/**
	 * Import shipping methods
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return array
	 */
	public function import_shipping_methods( $request ) {
		$data = $request->get_json_params();

		$plugin_ids = Sanitizer::array_strings( $data['plugins'] );
		$disable_imported_methods = Sanitizer::boolean( $data['disable_imported_methods'] );

		$result = $this->shippingService->import_shipping_methods_by_plugin_id( $plugin_ids, $disable_imported_methods );

		return array_merge( $result, [ 
			"success" => true,
		] );
	}

	/**
	 * Get Available Shipping Methods
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function available_shipping_methods( $request ) {
		$data = $request->get_json_params();

		/**
		 * @var \Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod[]
		 */
		$methods = Container::shippingService()->getAvailableZoneMethods( [ 
			'country' => $data['country'] ?? 'BR',
			'state' => $data['state'] ?? '',
			'city' => $data['city'] ?? '',
			'address' => $data['address'] ?? '',
			'postcode' => $data['postcode'] ?? '',
		] );

		$response = [];

		foreach ( $methods as $method ) {
			if ( ! $method->is_enabled() )
				continue;

			$response[] = [ 
				'instance_id' => $method->get_instance_id(),
				'title' => $method->get_title(),
				'description' => $method instanceof CorreiosShippingMethod ? $method->get_description() : '',
			];
		}

		return rest_ensure_response( $response );
	}
}