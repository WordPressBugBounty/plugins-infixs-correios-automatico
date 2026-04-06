<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\PrepostService;

defined( 'ABSPATH' ) || exit;
class PrepostController {
	/**
	 * Prepost service instance.
	 * 
	 * @since 1.0.0
	 * 
	 * @var PrepostService
	 */
	private $prepostService;

	public function __construct( PrepostService $prepostService ) {
		$this->prepostService = $prepostService;
	}

	/**
	 * List preposts.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_REST_Response
	 */
	public function list( $request ) {
		$search = sanitize_text_field( $request->get_param( 'search' ) );

		$preposts = $this->prepostService->listPreposts( [ 'search' => $search ] );

		return rest_ensure_response( $preposts );
	}

	/**
	 * Create prepost.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function createFromOrder( \WP_REST_Request $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['order_id'] ) ) {
			return new \WP_Error( 'missing_order_id', 'Order ID is required.', [ 'status' => 400 ] );
		}

		if ( isset( $params['invoice_key'] ) && strlen( trim( $params['invoice_key'] ) ) !== 44 ) {
			return new \WP_Error( 'invalid_invoice_key', 'A Chave da nota fiscal deve ter 44 caracteres.', [ 'status' => 400 ] );
		}

		if ( isset( $params['invoice_number'] ) && strlen( trim( $params['invoice_number'] ) ) == 0 ) {
			return new \WP_Error( 'invalid_invoice_number', 'O Número da nota fiscal é obrigatório.', [ 'status' => 400 ] );
		}

		if ( ! Config::boolean( 'auth.active' ) ) {
			return new \WP_Error( 'auth_not_active', 'O Contrato não está ativo, acesse as configurações para ativá-lo. Menu WooCommerce -> Correios Automático -> Configurações -> Contrato.', [ 'status' => 400 ] );
		}

		$data = [];

		if ( isset( $params['invoice_key'] ) ) {
			$data['invoice_key'] = $params['invoice_key'];
		}

		if ( isset( $params['invoice_number'] ) ) {
			$data['invoice_number'] = $params['invoice_number'];
		}

		if ( array_key_exists( 'withDce', $params ) ) {
			$with_dce = filter_var( $params['withDce'], FILTER_VALIDATE_BOOLEAN );
			$data['emiteDCe'] = $with_dce ? 'S' : 'N';
		}

		if ( isset( $params['dangerousProduct'] ) ) {
			$data['dangerousProduct'] = filter_var( $params['dangerousProduct'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( isset( $params['receiptNotice'] ) ) {
			$data['receiptNotice'] = filter_var( $params['receiptNotice'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( isset( $params['ownHands'] ) ) {
			$data['ownHands'] = filter_var( $params['ownHands'], FILTER_VALIDATE_BOOLEAN );
		}

		$prepost = $this->prepostService->createPrepost( $params['order_id'], $data );

		if ( is_wp_error( $prepost ) ) {
			return $prepost;
		}

		do_action( 'infixs_correios_automatico_prepost_controller_created', $params['order_id'], $prepost );

		return rest_ensure_response( $this->prepostService->prepareData( $prepost ) );
	}

	/**
	 * Cancel prepost by ID.
	 * 
	 * @since 1.4.8
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function cancel( $request ) {
		$prepost_id = $request['id'];

		if ( ! $prepost_id ) {
			return new \WP_Error( 'invalid_prepost_id', __( 'Invalid prepost ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$response = $this->prepostService->cancelPrepost( $prepost_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new \WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Delete prepost by ID.
	 * 
	 * @since 1.4.8
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function delete( $request ) {
		$prepost_id = $request['id'];

		if ( ! $prepost_id ) {
			return new \WP_Error( 'invalid_prepost_id', __( 'Invalid prepost ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$response = $this->prepostService->deletePrepost( $prepost_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return new \WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Sync prepost status by ID.
	 * 
	 * @since 1.5.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function sync( $request ) {
		$prepost_id = $request['id'];

		if ( ! $prepost_id ) {
			return new \WP_Error( 'invalid_prepost_id', __( 'Invalid prepost ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		$response = $this->prepostService->sync( $prepost_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $this->prepostService->prepareData( $response ) );
	}

	/**
	 * Print DCe (Documento de Coleta Eletrônico) for a prepost.
	 * 
	 * @since 1.6.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function printDce( $request ) {
		$prepost_id = $request['id'];

		if ( ! $prepost_id ) {
			return new \WP_Error( 'invalid_prepost_id', __( 'Invalid prepost ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		try {
			$this->prepostService->sync( $prepost_id );
		} catch (\Exception $e) {
			// Log the error but continue to attempt to print the DCE, as the sync failure might not prevent it from being printed.
		}

		$response = $this->prepostService->printDce( $prepost_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $response );
	}
}