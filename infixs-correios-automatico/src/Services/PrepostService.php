<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Core\Support\Plugin;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Repositories\PrepostRepository;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosService;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\AddicionalServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\ObjectFormatCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\PaymentTypeCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\PrepostStatusCode;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Address;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Person;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class PrepostService {

	/**
	 * Prepost repository.
	 * 
	 * @since 1.0.0
	 * 
	 * @var PrepostRepository
	 */
	protected $prepostRepository;

	/**
	 * Correios service.
	 * 
	 * @since 1.1.3
	 * 
	 * @var CorreiosService
	 */
	protected $correiosService;

	/**
	 * Create a new instance of the service.
	 * 
	 * @since 1.0.0
	 * 
	 * @param PrepostRepository $prepostRepository Prepost repository.
	 * @param CorreiosService $correiosService Correios service.
	 */
	public function __construct( PrepostRepository $prepostRepository, CorreiosService $correiosService ) {
		$this->prepostRepository = $prepostRepository;
		$this->correiosService = $correiosService;
	}

	/**
	 * Create prepost.
	 * 
	 * This method is responsible for generating a prepost.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $order_id Order ID.
	 * @param array{
	 * 		invoice_number: string,
	 * 		invoice_key: string,
	 * } $data Data.
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function createPrepost( $order_id, $data = [] ) {
		if ( empty( Config::string( 'sender.name' ) ) ) {
			Log::notice( "Dados do remetente inválidos, é necessário preencher os dados do remetente nas configurações para utilizar a pré-postagem." );
			return new \WP_Error( 'invalid_sender_data', 'Dados do remetente inválidos, é necessário preencher os dados do remetente nas configurações para utilizar a pré-postagem.', [ 'status' => 400 ] );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Log::notice( "Pedido inválido ao criar a pré-postagem." );
			return new \WP_Error( 'invalid_order', 'Pedido inválido ao criar a pré-postagem.', [ 'status' => 400 ] );
		}
		$ca_order = new Order( $order );

		$shipping_method = $ca_order->getShippingMethod();

		if ( ! $shipping_method ) {
			Log::notice( "Esse pedido não tem o método de envio dos correios automático, a prepostagem não pode ser criada." );
			return new \WP_Error( 'invalid_shipping_method', 'Esse pedido não tem o método de envio dos correios automático, a prepostagem não pode ser criada.', [ 'status' => 400 ] );
		}

		$ca_address = $ca_order->getAddress();

		$recipient = new Person(
			$ca_order->getCustomerFullName(),
			new Address(
				$ca_address->getPostCode(),
				$ca_address->getStreet(),
				$ca_address->getNumber(),
				$ca_address->getComplement(),
				$ca_address->getNeighborhood(),
				$ca_address->getCity(),
				$ca_address->getState()
			),
			$ca_order->getCustomerDocument(),
			$ca_order->getPhone(),
			$ca_order->getCellphone(),
			$order->get_billing_email(),
		);

		$sender = new Person(
			Config::string( 'sender.name' ),
			new Address(
				Sanitizer::numeric_text( Config::string( 'sender.address_postalcode' ) ),
				Config::string( 'sender.address_street' ),
				Config::string( 'sender.address_number' ),
				Config::string( 'sender.address_complement' ),
				Config::string( 'sender.address_neighborhood' ),
				Config::string( 'sender.address_city' ),
				Config::string( 'sender.address_state' ),
				Config::string( 'sender.address_country' )
			),
			Config::string( 'sender.document' ),
			Config::string( 'sender.phone' ),
			Config::string( 'sender.celphone' ),
			Config::string( 'sender.email' )
		);

		$shippingProductCode = $ca_order->getShippingProductCode();

		$prepost = new Prepost(
			$order_id,
			$sender,
			$recipient,
			$shippingProductCode,
			$shipping_method->get_object_type_code()
		);

		if ( DeliveryServiceCode::isLetter( $shippingProductCode ) ) {
			$prepost->setObjectFormatCode( ObjectFormatCode::ENVELOPE );
		}

		if ( $shippingProductCode === DeliveryServiceCode::IMPRESSO_MODICO ) {
			$prepost->addAdditionalService( [
				'code' => '004',
				'declaredValue' => '0'
			] );
		}

		if ( $shippingProductCode === DeliveryServiceCode::CARTA_COML_REG_B1_CHANC_ETIQ ) {
			$prepost->addAdditionalService( [
				'code' => '025',
				'declaredValue' => '0'
			] );
		}

		$use_receipt_notice = isset( $data['receiptNotice'] )
			? filter_var( $data['receiptNotice'], FILTER_VALIDATE_BOOLEAN )
			: $shipping_method->is_receipt_notice();

		if ( $use_receipt_notice ) {
			$prepost->addAdditionalService( [
				'code' => '001',
				'declaredValue' => '0'
			] );
		}

		$use_own_hands = isset( $data['ownHands'] )
			? filter_var( $data['ownHands'], FILTER_VALIDATE_BOOLEAN )
			: false;

		if ( $use_own_hands ) {
			$prepost->addAdditionalService( [
				'code' => '002',
				'declaredValue' => '0'
			] );
		}

		// Check for dangerous products
		$has_dangerous_product = false;
		foreach ( $order->get_items() as $item ) {
			if ( 'yes' === get_post_meta( $item->get_product_id(), '_infixs_correios_automatico_dangerous_product', true ) ) {
				$has_dangerous_product = true;
				break;
			}
		}

		$use_dangerous_product = isset( $data['dangerousProduct'] )
			? filter_var( $data['dangerousProduct'], FILTER_VALIDATE_BOOLEAN )
			: $has_dangerous_product;

		if ( $use_dangerous_product ) {
			$prepost->addAdditionalService( [
				'code' => '095',
				'declaredValue' => '0'
			] );
		}

		$prepost->setItemsFromPackage( $ca_order->getPackage() );

		$shippingItem = $ca_order->getFirstShippingItemData();

		if ( isset( $shippingItem['insurance_cost'] ) && $shippingItem['insurance_cost'] > 0 ) {
			$insurance_code = AddicionalServiceCode::getInsuranceCode( $shippingProductCode );
			if ( $insurance_code ) {
				$prepost->addAdditionalService( [
					'code' => $insurance_code,
					'declaredValue' => $prepost->getItemsTotal()
				] );
			}
		}

		$prepost->setLength( $shippingItem['lenght'] );
		$prepost->setWidth( $shippingItem['width'] );
		$prepost->setHeight( $shippingItem['height'] );
		$prepost->setWeight( $shippingItem['weight'] );

		if ( isset( $data['invoice_number'] ) ) {
			$prepost->setInvoiceNumber( $data['invoice_number'] );
		}

		if ( isset( $data['invoice_key'] ) ) {
			$prepost->setInvoiceKey( $data['invoice_key'] );
		}

		if ( isset( $data['emiteDCe'] ) && in_array( $data['emiteDCe'], [ 'S', 'N' ], true ) ) {
			$prepost->setEmitDce( $data['emiteDCe'] );
		} elseif ( Config::boolean( 'general.emit_dce_auto_prepost' ) ) {
			$prepost->setEmitDce( 'S' );
		}

		if ( $prepost->isPacket() ) {
			$prepost->setFreightPaidValue( $order->get_shipping_total() );
			$prepost->setCurrency( get_woocommerce_currency() );
		}

		$created_prepost = $this->processPrespost( $prepost );

		if ( is_wp_error( $created_prepost ) ) {
			Log::notice( "Erro ao criar a pré-postagem.", [
				'message' => $created_prepost->get_error_message(),
			] );
			return $created_prepost;
		}

		$order->update_meta_data( '_infixs_correios_automatico_prepost_created', 'yes' );
		$order->update_meta_data( '_infixs_correios_automatico_prepost_id', $created_prepost->id );

		if ( isset( $data['invoice_number'] ) ) {
			$order->update_meta_data( '_infixs_correios_automatico_invoice_number', $data['invoice_number'] );
		}

		if ( isset( $data['invoice_key'] ) ) {
			$order->update_meta_data( '_infixs_correios_automatico_invoice_key', $data['invoice_key'] );
		}

		$order->save();

		do_action( 'infixs_correios_automatico_prepost_created', $order_id, $created_prepost );

		Log::debug( 'Pré-postagem criada com sucesso.', [
			'prepost_id' => $created_prepost->id,
			'order_id' => $order_id,
		] );

		return $created_prepost;
	}

	/**
	 * Process prepost.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function processPrespost( $prepost ) {
		if ( $prepost->isPacket() ) {
			return $this->processPacket( $prepost );
		} else {
			return $this->processPrepost( $prepost );
		}
	}

	/**
	 * Process packet.
	 * 
	 * @since 1.1.7
	 * 
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function processPacket( $prepost ) {
		$response = $this->correiosService->create_packet( $prepost );
		if ( is_wp_error( $response ) )
			return $response;

		$object_code = $response['packageResponseList'][0]['trackingNumber'];

		$created_prepost = $this->prepostRepository->create( [
			'external_id' => 0,
			'order_id' => $prepost->getOrderId(),
			'object_code' => $object_code,
			'service_code' => $prepost->getServiceCode(),
			'payment_type' => 1,
			'height' => $prepost->getHeight(),
			'width' => $prepost->getWidth(),
			'length' => $prepost->getLength(),
			'weight' => $prepost->getWeight(),
			'request_pickup' => 0,
			'reverse_logistic' => 0,
			'status' => 2,
			'status_label' => 'Pré-postado',
			'updated_at' => current_time( 'mysql' ),
			'created_at' => current_time( 'mysql' ),
		] );

		if ( ! $created_prepost ) {
			Log::notice( "Erro ao salvar a pré-postagem no banco de dados." );
			return new \WP_Error( 'prepost_save_error', 'Erro ao salvar a pré-postagem no banco de dados.', [ 'status' => 400 ] );
		}

		return $created_prepost;
	}


	/**
	 * Process prepost.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function processPrepost( $prepost ) {
		$response = $this->correiosService->create_prepost( $prepost );

		if ( is_wp_error( $response ) )
			return $response;

		$prazoPostagem = ( new \DateTime( $response['prazoPostagem'] ) )->format( 'Y-m-d H:i:s' );

		$data = [
			'external_id' => $response['id'],
			'order_id' => $prepost->getOrderId(),
			'object_code' => $response['codigoObjeto'],
			'service_code' => $response['codigoServico'],
			'payment_type' => $response['modalidadePagamento'],
			'height' => $response['alturaInformada'],
			'width' => $response['larguraInformada'],
			'length' => $response['comprimentoInformado'],
			'weight' => $response['pesoInformado'],
			'request_pickup' => $response['solicitarColeta'],
			'reverse_logistic' => $response['logisticaReversa'],
			'status' => $response['statusAtual'],
			'status_label' => $response['descStatusAtual'],
			'invoice_number' => $response['numeroNotaFiscal'] ?? null,
			'invoice_key' => $response['chaveNFe'] ?? null,
			'expire_at' => $prazoPostagem,
			'updated_at' => current_time( 'mysql' ),
			'created_at' => current_time( 'mysql' ),
			'dce' => isset( $response['emiteDCe'] ) && $response['emiteDCe'] === 'S' ? 1 : 0,
		];

		$created_prepost = $this->prepostRepository->create( $data );

		if ( ! $created_prepost ) {
			Log::notice( "Erro ao salvar a pré-postagem no banco de dados." );
			return new \WP_Error( 'prepost_save_error', 'Erro ao salvar a pré-postagem no banco de dados.', [ 'status' => 400 ] );
		}

		return $created_prepost;
	}

	/**
	 * Get prepost by ID.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $prepostId
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|null
	 */
	public function getPrepost( $prepostId ) {
		return $this->prepostRepository->findById( $prepostId );
	}

	/**
	 * Get orders
	 * 
	 * @since 1.0.0
	 * 
	 * @param array{
	 * 			page: int,
	 * 			per_page: int
	 * 			search: string
	 * } $query Query parameters.
	 * 
	 * @return array
	 */
	public function listPreposts( $query = [] ) {
		$page = $query['page'] ?? 1;
		$per_page = $query['per_page'] ?? 10;
		$search = $query['search'] ?? null;

		$total_count = $this->prepostRepository->count();
		$data = $this->prepostRepository->paginate( $per_page, $page, $search );

		$items = [];

		foreach ( $data as $prepost ) {
			$items[] = $this->prepareData( $prepost );
		}

		return [
			'page' => $page,
			'per_page' => $per_page,
			'total_results' => count( $items ),
			'total' => $total_count,
			'preposts' => $items,
		];
	}

	/**
	 * Prepare data
	 * 
	 * @since 1.0.0
	 * 
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost
	 */
	public function prepareData( $prepost ) {
		return [
			"id" => (int) $prepost->id,
			"order_id" => (int) $prepost->order_id,
			"expire_at" => $prepost->expire_at,
			"created_at" => $prepost->created_at,
			"object_code" => $prepost->object_code,
			"service" => DeliveryServiceCode::getShortDescription( $prepost->service_code ),
			"status" => PrepostStatusCode::getStatus( $prepost->status ),
			"status_code" => (int) $prepost->status,
			"payment_type" => PaymentTypeCode::getDescription( $prepost->payment_type ),
			"dce" => (bool) $prepost->dce,
		];
	}

	/**
	 * Sync Prepost
	 * 
	 * Synchronizes the prepost status from Correios API and updates invoice key if present.
	 * 
	 * @since 1.5.0
	 * 
	 * @param int $prepost_id
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function sync( $prepost_id ) {
		/** @var \Infixs\CorreiosAutomatico\Models\Prepost $prepost */
		$prepost = $this->prepostRepository->findById( $prepost_id );

		if ( ! $prepost ) {
			Log::notice( "Pré-postagem inválida ao sincronizar." );
			return new \WP_Error( 'invalid_prepost_id', 'Pré-postagem inválida.', [ 'status' => 400 ] );
		}

		$response = $this->correiosService->get_prepost( $prepost->object_code );

		if ( is_wp_error( $response ) ) {
			Log::notice( "Erro ao sincronizar pré-postagem.", [ 'error' => $response->get_error_message() ] );
			return $response;
		}

		if ( ! isset( $response['itens'] ) || empty( $response['itens'] ) ) {
			Log::notice( "Nenhuma pré-postagem encontrada nos Correios para o código: " . $prepost->object_code );
			return new \WP_Error( 'prepost_not_found', 'Pré-postagem não encontrada nos Correios.', [ 'status' => 404 ] );
		}

		$prepost_data = $response['itens'][0];

		// Prepare update data
		$update_data = [
			'status' => isset( $prepost_data['statusAtual'] ) ? $prepost_data['statusAtual'] : $prepost->status,
			'status_label' => isset( $prepost_data['descStatusAtual'] ) ? $prepost_data['descStatusAtual'] : $prepost->status_label,
		];

		if ( isset( $prepost_data['statusAtual'] ) && (int) $prepost_data['statusAtual'] === 2 ) {
			$dce_response = $this->correiosService->printDce( $prepost->object_code, 'T' );

			if ( ! is_wp_error( $dce_response ) && ! empty( $dce_response['dados'] ) ) {
				$dce_text_data = $this->extractDceTextData( $dce_response['dados'] );

				if ( ! empty( $dce_text_data['dce_number'] ) ) {
					$update_data['dce_number'] = $dce_text_data['dce_number'];
				}

				if ( ! empty( $dce_text_data['dce_series'] ) ) {
					$update_data['dce_series'] = $dce_text_data['dce_series'];
				}

				if ( ! empty( $dce_text_data['dce_authorization_protocol'] ) ) {
					$update_data['dce_authorization_protocol'] = $dce_text_data['dce_authorization_protocol'];
				}
			}
		}

		// Update invoice key (chaveNFe) if present in response
		if ( isset( $prepost_data['chaveNFe'] ) && ! empty( $prepost_data['chaveNFe'] ) ) {
			$update_data['invoice_key'] = $prepost_data['chaveNFe'];
		}

		// Update the prepost
		$updated = $this->prepostRepository->update( $prepost_id, $update_data );

		if ( ! $updated ) {
			Log::notice( "Erro ao atualizar pré-postagem." );
			return new \WP_Error( 'update_prepost_error', 'Erro ao atualizar pré-postagem.', [ 'status' => 400 ] );
		}

		// Refresh the prepost data
		$updated_prepost = $this->prepostRepository->findById( $prepost_id );

		Log::debug( 'Pré-postagem sincronizada com sucesso.', [
			'prepost_id' => $prepost_id,
			'object_code' => $prepost->object_code,
		] );

		return $updated_prepost;
	}

	/**
	 * Extract DCe number and series from the text response.
	 *
	 * @param string $dce_text
	 *
	 * @return array{dce_number: string|null, dce_series: string|null, dce_authorization_protocol: string|null}
	 */
	protected function extractDceTextData( $dce_text ) {
		$result = [
			'dce_number' => null,
			'dce_series' => null,
			'dce_authorization_protocol' => null,
		];

		if ( ! is_string( $dce_text ) || '' === trim( $dce_text ) ) {
			return $result;
		}

		if ( preg_match( '/Num:\s*([^\s]+)\s+S[ée]rie:\s*([^\s]+)/u', $dce_text, $matches ) ) {
			$result['dce_number'] = $matches[1];
			$result['dce_series'] = $matches[2];
		}

		if ( preg_match( '/Protocolo de Autoriza[cç][aã]o:\s*([^\s]+)/u', $dce_text, $matches ) ) {
			$result['dce_authorization_protocol'] = $matches[1];
		}

		return $result;
	}

	/**
	 * Cancel Prepost
	 * 
	 * PRO Feature: https://infixs.com/product/correios-automatico-rastreio-etiqueta-e-frete-versao-pro/
	 * 
	 * @param int $prepost_id
	 * 
	 * @return array|\WP_Error
	 */
	public function cancelPrepost( $prepost_id ) {
		$reponse = apply_filters(
			'infixs_correios_automatico_service_cancel_prepost',
			new \WP_Error(
				'cancel_prepost_pro_feature',
				'Essa funcionalidade é uma feature da versão PRO, considerer adquirir a versão PRO para utilizar essa funcionalidade.',
				[
					'status' => 400,
					'buy_pro_url' => Plugin::PRO_URL
				]
			),
			$prepost_id,
			$this->prepostRepository,
			$this->correiosService,
		);

		return $reponse;
	}

	/**
	 * Delete Prepost
	 * 
	 * @param int $prepost_id
	 * 
	 * @return bool|\WP_Error
	 */
	public function deletePrepost( $prepost_id ) {
		/** @var \Infixs\CorreiosAutomatico\Models\Prepost $prepost */
		$prepost = $this->prepostRepository->findById( $prepost_id );

		if ( ! $prepost ) {
			return new \WP_Error( 'invalid_prepost_id', 'Pré-postagem inválida.', [ 'status' => 400 ] );
		}

		if ( ! $this->prepostRepository->delete( $prepost_id ) ) {
			return new \WP_Error( 'delete_prepost_error', 'Erro ao deletar a pré-postagem.', [ 'status' => 400 ] );
		} else {
			Container::trackingService()->deleteTrackingByCode( $prepost->object_code );
			return true;
		}
	}

	/**
	 * Print DCe (Documento de Coleta Eletrônico) for a prepost.
	 * 
	 * @since 1.6.0
	 * 
	 * @param int $prepost_id
	 * 
	 * @return array|\WP_Error
	 */
	public function printDce( $prepost_id ) {
		/** @var \Infixs\CorreiosAutomatico\Models\Prepost $prepost */
		$prepost = $this->prepostRepository->findById( $prepost_id );

		if ( ! $prepost ) {
			return new \WP_Error( 'invalid_prepost_id', 'Pré-postagem inválida.', [ 'status' => 400 ] );
		}

		if ( ! $prepost->dce ) {
			return new \WP_Error( 'prepost_without_dce', 'Esta pré-postagem não possui DCe.', [ 'status' => 400 ] );
		}

		// Call Correios API to get DCe PDF
		$response = $this->correiosService->printDce( $prepost->object_code );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}
}
