<?php

namespace Infixs\CorreiosAutomatico\Services\Correios;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Repositories\ConfigRepository;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\AddicionalServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\APIServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\ShippingCost;
use Infixs\CorreiosAutomatico\Traits\HttpTrait;
use Infixs\CorreiosAutomatico\Utils\Helper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class CorreiosService {

	use HttpTrait;

	protected $configRepository;

	protected $contract_enabled;

	/**
	 * CorreiosApi
	 * 
	 * @var CorreiosApi
	 */
	protected $correiosApi;

	/**
	 * Constructor
	 * 
	 * @param ConfigRepository $configRepository
	 * @param CorreiosApi $correiosApi
	 * 
	 */
	public function __construct( $correiosApi, $configRepository ) {
		$this->configRepository = $configRepository;
		$this->correiosApi = $correiosApi;
		$this->contract_enabled = $this->configRepository->boolean( 'auth.active' );

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

		if ( $this->contract_enabled && Helper::contractHasService( APIServiceCode::PRECO ) ) {

			$response = apply_filters( 'correios_automatico_get_shipping_cost',
				new \WP_Error( 'correios_automatico_get_shipping_cost', 'Erro ao calcular o frete, método não encontrado.' ),
				$shipping_cost, [] );

			if ( ! is_wp_error( $response ) && isset( $response["pcFinal"] ) )
				return Sanitizer::numeric( $response["pcFinal"] ) / 100;


			if ( is_wp_error( $response ) ) {
				Log::notice( "Não foi possível calcular o frete: " . $response->get_error_message(),
					$shipping_cost->getData()
				);
			}
		} else {
			$request = [ 
				"origin_postal_code" => $shipping_cost->getOriginPostcode(),
				"destination_postal_code" => $shipping_cost->getDestinationPostcode(),
				"product_code" => $shipping_cost->getProductCode(),
				"type" => $shipping_cost->getObjectType(),
				'insurance' => $shipping_cost->getInsuranceDeclarationValue(),
				"package" => [ 
					"weight" => $shipping_cost->getWeight( 'g' ),
					"length" => $shipping_cost->getLength(),
					"width" => $shipping_cost->getWidth(),
					"height" => $shipping_cost->getHeight(),
				],
				"services" => [ 
					"own_hands" => $shipping_cost->getOwnHands(),
					"receipt_notice" => $shipping_cost->getReceiptNotice(),
				],
			];

			$response = $this->post(
				'https://api.infixs.io/v1/shipping/calculate/correios',
				$request,
				[],
			);

			if ( ! is_wp_error( $response ) && isset( $response["shipping_cost"] ) ) {
				return $response;
			}

			if ( is_wp_error( $response ) ) {
				Log::notice( "Não foi possível calcular o frete via api: " . $response->get_error_message(),
					$request
				);
			}

		}

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
		return $this->correiosApi->precoNacional(
			$shipping_cost->getProductCode(),
			$shipping_cost->getData()
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
		return $this->correiosApi->prepostagens( $data );
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
		return $this->correiosApi->packages(
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
	public function cancel_prepost( $prepost_id ) {
		return $this->correiosApi->cancelarPrepostagem( $prepost_id );
	}

	/**
	 * Get Shipping Time
	 * 
	 * @param string $product_code
	 * @param array $params
	 * 
	 * @return int|false
	 */
	public function get_shipping_time( $product_code, $params ) {
		$response = $this->correiosApi->authenticated_get(
			$this->correiosApi->join_url( 'prazo/v1/nacional', $product_code ),
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
	public function get_object_tracking( $tracking_code ) {
		if ( $this->contract_enabled && Helper::contractHasService( APIServiceCode::SRO_RASTRO ) ) {
			return $this->correiosApi->rastroObjeto( $tracking_code );
		} else {
			return Container::infixsApi()->getTrackingHistory( $tracking_code );
		}
	}

	/**
	 * Get multiple tracking history
	 * 
	 * @param array $tracking_codes
	 * 
	 * @return array|\WP_Error
	 */
	public function get_object_trackings( $tracking_codes ) {
		if ( $this->contract_enabled && Helper::contractHasService( APIServiceCode::SRO_RASTRO ) ) {
			return $this->correiosApi->rastroObjetos( $tracking_codes );
		} else {
			//TODO: Implment API
			return new \WP_Error( 'correios_automatico_get_object_trackings', 'Serviço de rastreamento indisponível.' );
		}
	}

	/**
	 * Suspend shipping
	 * 
	 * @param string $tracking_code
	 * 
	 * @return array|\WP_Error
	 */
	public function suspend_shipping( $tracking_code ) {
		return $this->correiosApi->suspenderEntrega( $tracking_code );
	}
}