<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Services\LabelService;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;
use Infixs\CorreiosAutomatico\Validators\SettingsLabelValidator;

defined( 'ABSPATH' ) || exit;

/**
 * Settings label controller
 * 
 * @since 1.0.0
 * 
 * @package Infixs\CorreiosAutomatico\Controllers\Rest
 */
class SettingsLabelController {

	/**
	 * Label service
	 * 
	 * @var LabelService
	 */
	protected $labelService;

	public function __construct( LabelService $labelService ) {
		$this->labelService = $labelService;
	}

	/**
	 * Label settings save
	 * 
	 * @since 1.0.0
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function saveProfile( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data['id'] ) ) {
			return new \WP_Error( 'missing_profile_id', 'Profile ID is required.', [ 'status' => 400 ] );
		}

		$profile_id = $data['id'];

		if ( ! empty( $data ) ) {
			Config::update( "label.profiles.$profile_id", $data );
			Log::debug( 'Configurações de impressão de etiqueta foram salvas' );
		}

		$response = [ 
			'status' => 'success',
		];

		return rest_ensure_response( $response );
	}


	/**
	 * Label settings get
	 * 
	 * @since 1.0.0
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function getProfile( $request ) {
		$profile_id = $request->get_param( 'id' ) ?? 'default';

		$profile = Config::get( "label.profiles.$profile_id" );

		$response = [ 
			'status' => 'success',
			'profile' => $this->prepare_data( $profile ),
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Create label range
	 * 
	 * @since 1.3.7
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function createRange( $request ) {
		$data = $request->get_json_params();

		if ( ! isset( $data['service_code'] ) ) {
			return new \WP_Error( 'missing_service_code', 'Service code is required.', [ 'status' => 400 ] );
		}

		if ( ! isset( $data['range_start'] ) ) {
			return new \WP_Error( 'missing_range_start', 'Range start is required.', [ 'status' => 400 ] );
		}

		if ( ! isset( $data['range_end'] ) ) {
			return new \WP_Error( 'missing_range_end', 'Range end is required.', [ 'status' => 400 ] );
		}

		$result = $this->labelService->createRange( $data['service_code'], $data['range_start'], $data['range_end'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = [ 
			'status' => 'success',
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Get label ranges
	 * 
	 * @since 1.3.7
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function getRanges( $request ) {
		$service_code = $request->get_param( 'service_code' );

		$result = $this->labelService->getRanges( [ 
			'service_code' => $service_code
		] );

		return rest_ensure_response( [ 
			'ranges' => $result,
		] );
	}

	/**
	 * Delete label ranges
	 * 
	 * @since 1.3.7
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function deleteRanges( $request ) {
		$range_id = $request->get_param( 'id' );

		$result = $this->labelService->deleteRanges( $range_id );

		if ( ! $result ) {
			return new \WP_Error( 'error', 'Erro ao deletar o range.', [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 
			'success' => true,
		] );
	}

	/**
	 * Get label ranges available
	 * 
	 * @since 1.3.7
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function getRangesAvailable( $request ) {
		$service_code = $request->get_param( 'service_code' );

		$count = $this->labelService->getRangesAvailable( $service_code );

		return rest_ensure_response( [ 
			'available' => $count,
		] );
	}

	/**
	 * Prepare the data
	 *
	 * @since 1.0.0
	 * @param array $profile
	 * 
	 * @return array
	 */
	public function prepare_data( $profile ) {
		if ( isset( $profile['withDeclaration'] ) ) {
			$profile['withDeclaration'] = Sanitizer::boolean( $profile['withDeclaration'] );
		}

		if ( isset( $profile['page'], $profile['page']['items_gap'] ) ) {
			if ( ! isset( $profile['page'], $profile['page']['items_gap_x'] ) ) {
				$profile['page']['items_gap_x'] = $profile['page']['items_gap'];
			}
			if ( ! isset( $profile['page'], $profile['page']['items_gap_y'] ) ) {
				$profile['page']['items_gap_y'] = $profile['page']['items_gap'];
			}
		}

		if ( isset( $profile['page'], $profile['page']['page_margin'] ) ) {
			if ( ! isset( $profile['page'], $profile['page']['page_margin_top'] ) ) {
				$profile['page']['page_margin_top'] = $profile['page']['page_margin'];
			}

			if ( ! isset( $profile['page'], $profile['page']['page_margin_left'] ) ) {
				$profile['page']['page_margin_left'] = $profile['page']['page_margin'];
			}

			if ( ! isset( $profile['page'], $profile['page']['page_margin_right'] ) ) {
				$profile['page']['page_margin_right'] = $profile['page']['page_margin'];
			}

			if ( ! isset( $profile['page'], $profile['page']['page_margin_bottom'] ) ) {
				$profile['page']['page_margin_bottom'] = $profile['page']['page_margin'];
			}
		}

		return $profile;
	}
}