<?php

namespace Infixs\CorreiosAutomatico\Services\Correios;

use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\AddicionalServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\ShippingCost;
use Infixs\CorreiosAutomatico\Traits\HttpTrait;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class CorreiosService {

	use HttpTrait;

	/**
	 * CorreiosApi
	 * 
	 * @var CorreiosApi
	 */
	protected $correiosApi;

	/**
	 * Constructor
	 * 
	 * @param CorreiosApi $correiosApi
	 * 
	 */
	public function __construct( $correiosApi ) {
		$this->correiosApi = $correiosApi;
		add_filter( 'correios_automatico_get_shipping_cost', [ $this, 'calculate_shipping_cost' ], 10, 3 );
	}

	/**
	 * Summary of get_shipping_cost
	 * 
	 * @param ShippingCost $shipping_cost
	 * @param array $params
	 * 
	 * @return int|float|false|array
	 */
	public function get_shipping_cost( $shipping_cost ) {
		do_action( 'infixs_correios_automatico_get_shipping_cost', $this );

		$response = apply_filters( 'correios_automatico_get_shipping_cost',
			new \WP_Error( 'correios_automatico_get_shipping_cost', 'Erro ao calcular o frete, método não encontrado.' ),
			$shipping_cost, [] );

		if ( ! is_wp_error( $response ) && isset( $response["pcFinal"] ) ) {
			Log::debug( "Shipping cost api correios response", $response );

			$shipping_cost_response = [
				'shipping_cost' => Sanitizer::numeric( $response["pcFinal"] ) / 100,
			];

			if ( isset( $response['servicoAdicional'] ) ) {
				foreach ( $response['servicoAdicional'] as $service ) {
					if ( isset( $service['coServAdicional'] ) &&
						isset( $service['pcServicoAdicional'] ) &&
						in_array( $service['coServAdicional'], [
							AddicionalServiceCode::INSURANCE_DECLARATION_MINI_ENVIOS,
							AddicionalServiceCode::INSURANCE_DECLARATION_PAC,
							AddicionalServiceCode::INSURANCE_DECLARATION_SEDEX,
						] ) ) {
						$shipping_cost_response['insurance_cost'] = Sanitizer::numeric( $service['pcServicoAdicional'] ) / 100;
						break;
					}
				}
			}

			return $shipping_cost_response;
		}


		if ( is_wp_error( $response ) ) {
			Log::notice( "Não foi possível calcular o frete: " . $response->get_error_message(),
				$shipping_cost->getData()
			);
		}

		do_action( 'infixs_correios_automatico_shipping_cost_failed',
			is_wp_error( $response ) ? $response : new \WP_Error( 'correios_invalid_response', __( 'Resposta inválida dos Correios.', 'infixs-correios-automatico' ) ),
			$shipping_cost
		);

		return false;
	}


	/**
	 * Calculate Shipping Cost
	 * 
	 * @param array $data
	 * @param ShippingCost $shipping_cost
	 * @param array $adicional_services
	 * @param array $extra_fields @since 1.2.9
	 * 
	 * @return array|\WP_Error
	 */
	public function calculate_shipping_cost( $data, $shipping_cost, $adicional_services = [] ) {
		$product_code = $shipping_cost->getProductCode();
		$data = $shipping_cost->getData();

		Log::debug( "Shipping cost correios api with code $product_code", $data );

		/**
		 * @var  \Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi $correiosApi
		 */
		$correiosApi = apply_filters( 'infixs_correios_automatico_calculate_shipping_cost_correios_api', $this->correiosApi, $shipping_cost );

		return $correiosApi->precoNacional(
			$product_code,
			$data
		);
	}

	/**
	 * Create Prepost
	 * 
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 * 
	 * @return array|\WP_Error
	 */
	public function create_prepost( $prepost ) {
		$data = $prepost->getData();
		Log::debug( "Enviando prepostagem para os correios.", $data );

		return $this->prepost_api( $prepost )->prepostagens( $data );
	}

	/**
	 * Get the Correios API instance used to post a prepost.
	 *
	 * @since 1.8.1
	 *
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost Prepost.
	 *
	 * @return CorreiosApi
	 */
	protected function prepost_api( $prepost ) {
		/**
		 * Filters the Correios API instance used to create the prepost.
		 *
		 * Allows marketplace extensions to send the prepost using the vendor's
		 * own contract instead of the store wide one.
		 *
		 * @since 1.8.1
		 *
		 * @param CorreiosApi $correiosApi
		 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
		 */
		return apply_filters( 'infixs_correios_automatico_prepost_correios_api', $this->correiosApi, $prepost );
	}

	/**
	 * Get the Correios API instance used to act on an object that already exists.
	 *
	 * Cancelling, syncing, printing the DCe or tracking an object has to speak to
	 * the contract that issued it, which on a marketplace is the vendor's own.
	 *
	 * @since 1.8.2
	 *
	 * @param string $object_code Correios object code.
	 * @param mixed  $source      Prepost or TrackingCode model the code came from.
	 *
	 * @return CorreiosApi
	 */
	protected function object_api( $object_code, $source = null ) {
		/**
		 * Filters the Correios API instance used to act on an existing object.
		 *
		 * @since 1.8.2
		 *
		 * @param CorreiosApi $correiosApi
		 * @param string      $object_code
		 * @param mixed       $source
		 */
		return apply_filters( 'infixs_correios_automatico_object_correios_api', $this->correiosApi, $object_code, $source );
	}

	/**
	 * Create Packet
	 * 
	 * @since 1.1.7
	 * 
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 * 
	 * @return array|\WP_Error
	 */
	public function create_packet( $prepost ) {
		return $this->prepost_api( $prepost )->packages(
			[
				'packageList' => [
					0 => $prepost->getPacketData()
				]
			]
		);
	}

	/**
	 * Cancel Prepost
	 * 
	 * @param string $prepost_id
	 * 
	 * @return array|\WP_Error
	 */
	public function cancel_prepost( $prepost_id, $source = null ) {
		return $this->object_api( $prepost_id, $source )->cancelarPrepostagem( $prepost_id );
	}

	/**
	 * Get Shipping Time
	 *
	 * @param string $product_code
	 * @param array $params
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\ShippingTime|null $shipping_time @since 1.8.1
	 *
	 * @return int|false
	 */
	public function get_shipping_time( $product_code, $params, $shipping_time = null ) {
		/**
		 * Filters the Correios API instance used to fetch the delivery time.
		 *
		 * Allows marketplace extensions to query the delivery time using the
		 * vendor's own contract instead of the store wide one.
		 *
		 * @since 1.8.1
		 *
		 * @param \Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi $correiosApi
		 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\ShippingTime|null $shipping_time
		 */
		$correiosApi = apply_filters( 'infixs_correios_automatico_shipping_time_correios_api', $this->correiosApi, $shipping_time );

		$response = $correiosApi->authenticated_get(
			$correiosApi->join_url( 'prazo/v1/nacional', $product_code ),
			$params
		);

		if ( ! is_wp_error( $response ) &&
			isset( $response["prazoEntrega"] ) )
			return Sanitizer::numeric( $response["prazoEntrega"] );

		return false;
	}

	/**
	 * Authenticate with postcard
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $user_name
	 * @param string $access_code
	 * @param string $postcard
	 * @param Environment::PRODUCTION|Environment::SANBOX $environment
	 * 
	 * @return array|\WP_Error
	 */
	public function auth_postcard( $user_name, $access_code, $postcard, $environment = null ) {
		return $this->correiosApi->auth_postcard( $user_name, $access_code, $postcard, $environment );
	}

	/**
	 * Fetch address from Correios API
	 * 
	 * @param string $postcode
	 * 
	 * @return array|\WP_Error
	 */
	public function fetch_postcode( $postcode ) {
		$response = $this->correiosApi->consultaCep( $postcode );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$address = [
			'postcode' => $response['cep'],
			'address' => $response['logradouro'],
			'neighborhood' => $response['bairro'],
			'city' => $response['localidade'],
			'state' => $response['uf']
		];

		return $address;
	}

	/**
	 * Get tracking history
	 * 
	 * @param string $tracking_code
	 * 
	 * @return array|\WP_Error
	 */
	public function get_object_tracking( $tracking_code, $source = null ) {
		return $this->object_api( $tracking_code, $source )->rastroObjeto( $tracking_code );
	}

	/**
	 * Get multiple tracking history
	 * 
	 * @param array $tracking_codes
	 * 
	 * @return array|\WP_Error
	 */
	public function get_object_trackings( $tracking_codes ) {
		/**
		 * Filters how tracking codes are grouped before being queried.
		 *
		 * A batch can span several contracts on a marketplace, and one client
		 * cannot serve all of them. Each entry is `[ 'api' => CorreiosApi|null,
		 * 'codes' => string[] ]`; a null client means the store wide one. With
		 * no listener there is exactly one batch, so this is a single request
		 * just like before 1.8.2.
		 *
		 * @since 1.8.2
		 *
		 * @param array    $batches
		 * @param string[] $tracking_codes
		 */
		$batches = apply_filters(
			'infixs_correios_automatico_object_trackings_batches',
			[ [ 'api' => null, 'codes' => $tracking_codes ] ],
			$tracking_codes
		);

		if ( count( $batches ) === 1 ) {
			$batch = reset( $batches );
			$api = ! empty( $batch['api'] ) ? $batch['api'] : $this->correiosApi;

			return $api->rastroObjetos( $batch['codes'] );
		}

		$merged = [];

		foreach ( $batches as $batch ) {
			if ( empty( $batch['codes'] ) ) {
				continue;
			}

			$api = ! empty( $batch['api'] ) ? $batch['api'] : $this->correiosApi;
			$response = $api->rastroObjetos( $batch['codes'] );

			if ( is_wp_error( $response ) ) {
				Log::notice( 'Falha ao rastrear um lote de objetos: ' . $response->get_error_message(), [
					'codes' => $batch['codes'],
				] );

				continue;
			}

			if ( isset( $response['objetos'] ) && is_array( $response['objetos'] ) ) {
				$merged = array_merge( $merged, $response['objetos'] );
			}
		}

		return [ 'objetos' => $merged ];
	}

	/**
	 * Suspend shipping
	 * 
	 * @param string $tracking_code
	 * 
	 * @return array|\WP_Error
	 */
	public function suspend_shipping( $tracking_code, $source = null ) {
		return $this->object_api( $tracking_code, $source )->suspenderEntrega( $tracking_code );
	}

	/**
	 * Register packet unit
	 * 
	 * @param array {
	 * 			dispatchNumber: int,
	 * 			originCountry: string,
	 * 			originOperatorName: string,
	 * 			destinationOperatorName:: string,
	 * 			postalCategoryCode: string,
	 * 			serviceSubclassCode: string,
	 * 			unitList: array {
	 * 				sequence: number,
	 * 				unitType: number,
	 * 				weightKg: number,
	 *				trackingNumbers: string[]
	 * 			}
	 * } $data
	 * 
	 * @return array|\WP_Error
	 */
	public function register_packet_unit( $data ) {
		return $this->correiosApi->registerPacketUnit( $data );
	}

	/**
	 * Cancel packet unit
	 * 
	 * @param string $unit_code
	 * 
	 * @return array|\WP_Error
	 */
	public function cancel_packet_unit( $unit_code ) {
		return $this->correiosApi->cancelPacketUnit( $unit_code );
	}

	/**
	 * Register invoice unit
	 * 
	 * @param array {
	 * 			dispatchNumbers: string[],
	 * } $data
	 * 
	 * @return array|\WP_Error
	 */
	public function register_invoice_unit( $data ) {
		return $this->correiosApi->registerInvoiceUnit( $data );
	}

	public function get_invoice_unit_by_request( $request_id ) {
		return $this->correiosApi->getInvoiceUnitByRequest( $request_id );
	}

	/**
	 * Get Prepost by Object Code
	 * 
	 * @param string $object_code
	 * 
	 * @return array|\WP_Error
	 */
	public function get_prepost( $object_code, $source = null ) {
		return $this->object_api( $object_code, $source )->getPrepostagens( [
			'codigoObjeto' => $object_code
		] );
	}
	/**
	 * Print DCe (Documento de Coleta Eletrônico) for a prepost.
	 * 
	 * @param string $object_code
	 * @param string $dace_type 'R' = Resumida, 'C' = Completa, 'T' = Texto (default: 'C')
	 * 
	 * @return array|\WP_Error
	 */
	public function printDce( $object_code, $dace_type = 'C', $source = null ) {
		return $this->object_api( $object_code, $source )->printDce( [
			'codigosObjetos' => [ $object_code ],
			'tipoDace' => $dace_type
		] );
	}
}