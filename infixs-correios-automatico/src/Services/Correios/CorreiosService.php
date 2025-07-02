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
		return $this->correiosApi->rastroObjeto( $tracking_code );
	}

	/**
	 * Get multiple tracking history
	 * 
	 * @param array $tracking_codes
	 * 
	 * @return array|\WP_Error
	 */
	public function get_object_trackings( $tracking_codes ) {
		return $this->correiosApi->rastroObjetos( $tracking_codes );
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
}