<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Services\LabelService;

defined( 'ABSPATH' ) || exit;
class LabelController {

	/**
	 * Label controller instance.
	 * 
	 * @since 1.0.0
	 * 
	 * @var LabelService
	 */
	private $labelService;

	public function __construct( LabelService $labelService ) {
		$this->labelService = $labelService;
	}

	/**
	 * List labels.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function list( $request ) {
		$params = $request->get_params();

		$labels = $this->labelService->getLabelsFromOrders( $params['order_id'] );

		return rest_ensure_response( [ 
			"status" => "success",
			"labels" => $labels,
		] );
	}
}