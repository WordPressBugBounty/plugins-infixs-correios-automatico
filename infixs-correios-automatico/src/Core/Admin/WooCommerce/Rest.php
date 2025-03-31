<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Services\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Rest Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.3.7
 */
class Rest {

	/**
	 * Tracking service instance.
	 *
	 * @var TrackingService
	 */
	protected $trackingService;

	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;
		add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'add_tracking_to_order_response' ], 10, 3 );
	}

	public function add_tracking_to_order_response( $response, $object, $request ) {
		if ( ! is_a( $object, 'WC_Order' ) ) {
			return $response;
		}

		$trackings = $this->trackingService->getTrackings( $object->get_id() );

		$response_codes = [];

		if ( ! $trackings->isEmpty() ) {
			foreach ( $trackings as $track ) {
				$response_codes[] = [ 
					'id' => $track->id,
					'code' => $track->code,
					'carrier' => 'correios',
					'description' => $track->description ?? "",
					'category' => $track->category ?? "",
					'date_created_gmt' => wc_rest_prepare_date_response( $track->created_at )
				];
			}

			$response->data['correios_tracking_code'] = implode( ",", $trackings->pluck( 'code' )->toArray() );
		}

		$response->data['tracking_codes'] = apply_filters(
			'infixs_correios_automatico_rest_shop_order_object_tracking_codes',
			$response_codes
		);

		return $response;
	}
}

