<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;
use Infixs\CorreiosAutomatico\Prepaid\ContractMode;
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

	/**
	 * Which contract the store posts with.
	 *
	 * @since 1.14.0
	 *
	 * @var ContractMode
	 */
	private $contractMode;

	/**
	 * @since 1.14.0 Receives the contract mode.
	 *
	 * @param PrepostService $prepostService Prepost service.
	 * @param ContractMode   $contractMode   Which contract the store posts with.
	 */
	public function __construct( PrepostService $prepostService, ContractMode $contractMode ) {
		$this->prepostService = $prepostService;
		$this->contractMode = $contractMode;
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
	 * @since 1.14.0 Allowed by the contract mode: the own contract on, or the Infixs Ship connected.
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

		$data = $this->fiscalDocumentParams( $params );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$contract_error = $this->contractMode->prepostError();

		if ( $contract_error ) {
			return $contract_error;
		}

		if ( isset( $params['dangerousProduct'] ) ) {
			$data['dangerousProduct'] = filter_var( $params['dangerousProduct'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( isset( $params['receiptNotice'] ) ) {
			$data['receiptNotice'] = filter_var( $params['receiptNotice'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( isset( $params['receiptNoticeElectronic'] ) ) {
			$data['receiptNoticeElectronic'] = filter_var( $params['receiptNoticeElectronic'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( isset( $params['ownHands'] ) ) {
			$data['ownHands'] = filter_var( $params['ownHands'], FILTER_VALIDATE_BOOLEAN );
		}

		$data = array_merge( $data, $this->resolveShipmentScope( $params ) );

		$prepost = $this->prepostService->createPrepost( $params['order_id'], $data );

		if ( is_wp_error( $prepost ) ) {
			return $prepost;
		}

		do_action( 'infixs_correios_automatico_prepost_controller_created', $params['order_id'], $prepost );

		return rest_ensure_response( $this->prepostService->prepareData( $prepost ) );
	}

	/**
	 * Tell the fiscal document the reverse prepost of a shipment would travel with.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_REST_Request $request Request with `order_id`, and `shipping_item_id` and `vendor_id` of the shipment.
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function reverseDocument( $request ) {
		$params = $request->get_params();

		if ( empty( $params['order_id'] ) ) {
			return new \WP_Error( 'missing_order_id', 'Order ID is required.', [ 'status' => 400 ] );
		}

		$document = $this->prepostService->previewReverseFiscalDocument( absint( $params['order_id'] ), $this->resolveShipmentScope( $params ) );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return rest_ensure_response( [
			'source' => $document['source'],
			'emite_dce' => 'S' === $document['emiteDCe'],
			'invoice_number' => isset( $document['invoice_number'] ) ? (string) $document['invoice_number'] : '',
			'invoice_key' => isset( $document['invoice_key'] ) ? (string) $document['invoice_key'] : '',
		] );
	}

	/**
	 * Read the fiscal document a prepost request asks for.
	 *
	 * An NF-e informed by the store, or the `withDce` choice of the DC-e
	 * button; an NF-e without `withDce` sends no DC-e.
	 *
	 * @since 1.14.0
	 *
	 * @param array $params Request params.
	 *
	 * @return array|\WP_Error Keys understood by the prepost service, empty when the request leaves it open.
	 */
	private function fiscalDocumentParams( $params ) {
		$data = [];

		if ( isset( $params['invoice_key'] ) ) {
			$invoice_key = preg_replace( '/[^[:alnum:]]+/', '', (string) $params['invoice_key'] );

			if ( strlen( $invoice_key ) !== 44 ) {
				return new \WP_Error( 'invalid_invoice_key', 'A Chave da nota fiscal deve ter 44 caracteres.', [ 'status' => 400 ] );
			}

			$data['invoice_key'] = $invoice_key;
		}

		if ( isset( $params['invoice_number'] ) && strlen( trim( $params['invoice_number'] ) ) == 0 ) {
			return new \WP_Error( 'invalid_invoice_number', 'O Número da nota fiscal é obrigatório.', [ 'status' => 400 ] );
		}

		if ( isset( $params['invoice_number'] ) ) {
			$data['invoice_number'] = $params['invoice_number'];
		}

		if ( array_key_exists( 'withDce', $params ) ) {
			$data['emiteDCe'] = filter_var( $params['withDce'], FILTER_VALIDATE_BOOLEAN ) ? 'S' : 'N';
		} elseif ( isset( $params['invoice_number'] ) && isset( $params['invoice_key'] ) ) {
			$data['emiteDCe'] = 'N';
		}

		return $data;
	}

	/**
	 * Create a reverse logistics prepost (manual) from an order.
	 *
	 * @since 1.8.0
	 * @since 1.14.0 Only on the own contract: the Contrato pré-pago has no reverse prepost yet.
	 * @since 1.14.0 Takes the NF-e or the DC-e the store chose for the return.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function createReverseFromOrder( $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['order_id'] ) ) {
			return new \WP_Error( 'missing_order_id', 'Order ID is required.', [ 'status' => 400 ] );
		}

		$contract_error = $this->contractMode->prepostError( true );

		if ( $contract_error ) {
			return $contract_error;
		}

		$data = $this->fiscalDocumentParams( $params );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( isset( $params['service'] ) && ! empty( $params['service'] ) ) {
			$data['service'] = sanitize_text_field( $params['service'] );
		}

		foreach ( [ 'weight', 'length', 'width', 'height' ] as $field ) {
			if ( isset( $params[ $field ] ) && is_numeric( $params[ $field ] ) ) {
				$data[ $field ] = (float) $params[ $field ];
			}
		}

		$data = array_merge( $data, $this->resolveShipmentScope( $params ) );

		$prepost = $this->prepostService->createReversePrepost( $params['order_id'], $data );

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
		$dace_type = strtoupper( sanitize_text_field( $request->get_param( 'dace_type' ) ?: 'C' ) );

		if ( ! $prepost_id ) {
			return new \WP_Error( 'invalid_prepost_id', __( 'Invalid prepost ID.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		if ( ! in_array( $dace_type, [ 'C', 'R' ], true ) ) {
			return new \WP_Error( 'invalid_dace_type', __( 'Invalid DCe type.', 'infixs-correios-automatico' ), [ 'status' => 400 ] );
		}

		try {
			$this->prepostService->sync( $prepost_id );
		} catch (\Exception $e) {
			// Log the error but continue to attempt to print the DCE, as the sync failure might not prevent it from being printed.
		}

		$response = $this->prepostService->printDce( $prepost_id, $dace_type );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Refresh the DC-e data of a batch of preposts.
	 *
	 * The print screen asks for the preposts it is about to print with a DC-e
	 * and no access key stored: rows created before 1.11.0 have the number and
	 * the protocol of the DC-e but not the key, so the label cannot print the
	 * QR Code. One prepost failing never takes the whole batch down — the
	 * screen prints what it got and reports the rest, each failure with the
	 * reason the service decided and the order it belongs to.
	 *
	 * @since 1.11.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function refreshDce( $request ) {
		$preposts = [];
		$failed = [];

		foreach ( (array) $request->get_param( 'prepost_ids' ) as $prepost_id ) {
			$prepost = $this->prepostService->ensureDceData( $prepost_id );

			if ( is_wp_error( $prepost ) ) {
				$failed[] = $this->prepostService->prepareDceFailure( $prepost_id, $prepost );

				continue;
			}

			$preposts[] = $this->prepostService->prepareDceData( $prepost );
		}

		return rest_ensure_response( [
			'preposts' => $preposts,
			'failed' => $failed,
		] );
	}

	/**
	 * Read the shipment a prepost is being created for.
	 *
	 * An order with more than one shipping line — a marketplace order with one
	 * freight per vendor — needs one prepost per line, each on its own contract.
	 *
	 * @since 1.8.3
	 *
	 * @param array $params Request params.
	 *
	 * @return array Keys understood by the prepost service, empty for whole order preposts.
	 */
	private function resolveShipmentScope( $params ) {
		$scope = [];

		if ( ! empty( $params['shipping_item_id'] ) ) {
			$scope['shipping_item_id'] = absint( $params['shipping_item_id'] );
		}

		if ( ! empty( $params['vendor_id'] ) ) {
			$scope['vendor_id'] = absint( $params['vendor_id'] );
		}

		return $scope;
	}
}