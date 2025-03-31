<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Services\UnitService;

defined( 'ABSPATH' ) || exit;
class UnitController {
	/**
	 * Unit service instance.
	 * 
	 * @since 1.5.0
	 * 
	 * @var \Infixs\CorreiosAutomatico\Services\UnitService
	 */
	private $unitService;

	public function __construct( UnitService $unitService ) {
		$this->unitService = $unitService;
	}

	/**
	 * List units.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function list( $request ) {
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$search = $request->get_param( 'search' );

		$units = $this->unitService->getUnits( [ 
			[ 
				'page' => $page,
				'per_page' => $per_page,
				'search' => $search
			]
		] );

		return rest_ensure_response( $units->toArray( 'units' ) );
	}
}