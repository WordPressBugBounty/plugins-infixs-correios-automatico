<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Models\WoocommerceShippingZoneMethod;
use Infixs\CorreiosAutomatico\Repositories\ConfigRepository;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosService;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\APIServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\CeintCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Utils\Helper;
use Infixs\CorreiosAutomatico\Utils\NumberHelper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;
use Infixs\CorreiosAutomatico\Core\Support\Log;

defined( 'ABSPATH' ) || exit;

class ShippingService {

	/**
	 * Correios Service
	 * 
	 * @var CorreiosService
	 */
	protected $correiosService;

	/**
	 * InfixsApi
	 * 
	 * @var InfixsApi
	 */
	protected $infixsApi;

	/**
	 * Config Repository
	 * 
	 * @var ConfigRepository
	 */
	protected $configRepository;

	/**
	 * Constructor
	 * 
	 * @param CorreiosService $correiosService
	 * @param InfixsApi $infixsApi
	 * 
	 */
	public function __construct( CorreiosService $correiosService, InfixsApi $infixsApi, ConfigRepository $configRepository ) {
		$this->correiosService = $correiosService;
		$this->infixsApi = $infixsApi;
		$this->configRepository = $configRepository;
	}

	/**
	 * Get shipping methods
	 * 
	 * @since 1.0.0
	 * 
	 * @param array{is_enabled: bool|null, method_id: string[]|null} $filters
	 * 
	 * @return array
	 */
	public function list_shipping_methods( $filters ) {
		$enabled = $filters['is_enabled'] ?? null;
		$method_id = $filters['method_id'] ?? null;

		$shipping_zones = \WC_Shipping_Zones::get_zones();

		$shipping_methods = [];

		foreach ( $shipping_zones as $zone ) {
			/** @var \WC_Shipping_Method $shipping_method **/
			foreach ( $zone['shipping_methods'] as $shipping_method ) {
				if ( $enabled !== null && $shipping_method->is_enabled() !== $enabled ) {
					continue;
				}

				if ( $method_id !== null && count( $method_id ) > 0 && ! in_array( $shipping_method->id, $method_id ) ) {
					continue;
				}

				$shipping_methods[] = [ 
					"zone_id" => $zone['id'],
					"instance_id" => $shipping_method->get_instance_id(),
					"method_id" => $shipping_method->id,
					"title" => $shipping_method->get_title(),
					"enabled" => $shipping_method->is_enabled(),
				];
			}
		}

		return $shipping_methods;
	}

	/**
	 * Import active shipping methods by plugin ids
	 * 
	 * @since 1.0.0
	 * 
	 * @param array $plugin_ids
	 * @param bool $disable_imported_methods
	 * 
	 * @return array
	 */
	public function import_shipping_methods_by_plugin_id( $plugin_ids, $disable_imported_methods = true ) {

		$plugin_shipping_methods = $this->get_compatible_methods();

		$allowed_method_ids = [];

		$return_data = [ 
			"total_imported" => 0,
			"auth_imported" => false,
		];

		foreach ( $plugin_ids as $plugin_id ) {
			if ( ! isset( $plugin_shipping_methods[ $plugin_id ] ) ) {
				continue;
			}
			$allowed_method_ids = array_merge( $allowed_method_ids, $plugin_shipping_methods[ $plugin_id ] );
		}


		$shipping_zones = \WC_Shipping_Zones::get_zones();


		foreach ( $shipping_zones as $zone ) {
			/** @var \WC_Shipping_Method $shipping_method **/
			foreach ( $zone['shipping_methods'] as $shipping_method ) {
				if ( $shipping_method->is_enabled() !== true ) {
					continue;
				}

				if ( ! in_array( $shipping_method->id, $allowed_method_ids ) ) {
					continue;
				}

				$created_zone = new \WC_Shipping_Zone( $zone['id'] );
				$instance_id = $created_zone->add_shipping_method( 'infixs-correios-automatico' );

				if ( $instance_id === 0 )
					continue;

				try {
					$created_shipping_method = \WC_Shipping_Zones::get_shipping_method( $instance_id );

					$created_shipping_method->init_instance_settings();

					$data = $this->clone_options( $shipping_method, $created_shipping_method );

					foreach ( $created_shipping_method->get_instance_form_fields() as $key => $field ) {
						if ( 'title' !== $created_shipping_method->get_field_type( $field ) ) {
							try {
								$created_shipping_method->instance_settings[ $key ] = $created_shipping_method->get_field_value( $key, $field, $data );
							} catch (\Exception $e) {
								$created_shipping_method->add_error( $e->getMessage() );
							}
						}
					}

					update_option( $created_shipping_method->get_instance_option_key(), $created_shipping_method->instance_settings, 'yes' );

					$return_data['total_imported']++;

					if ( $disable_imported_methods )
						$this->disable_shipping_method( $shipping_method->instance_id );

				} catch (\Exception $e) {
					$created_zone->delete_shipping_method( $instance_id );
					continue;
				}

			}
		}

		if ( $config = $this->import_contract_config() ) {
			$postcard_response = Container::correiosService()->auth_postcard( $config['user_name'], $config['access_code'], $config['postcard'] );
			if ( ! is_wp_error( $postcard_response ) ) {
				$allowed_services = array_column( $postcard_response['cartaoPostagem']['apis'], 'api' );

				Config::update( 'auth', array_merge( $config, [ 
					'active' => true,
					'environment' => 'production',
					'token' => $postcard_response['token'],
					'contract_number' => sanitize_text_field( isset( $postcard_response['cartaoPostagem']['contrato'] ) ? $postcard_response['cartaoPostagem']['contrato'] : '' ),
					'allowed_services' => $allowed_services ?? [],
					'contract_type' => sanitize_text_field( $postcard_response['perfil'] ),
					'contract_document' => sanitize_text_field( $postcard_response['perfil'] === 'PJ' ? $postcard_response['cnpj'] : $postcard_response['cpf'] ),
				] ) );

				$return_data['auth_imported'] = true;
			}

		}

		return $return_data;
	}


	/**
	 * Import contract config
	 * 
	 * @since 1.0.0
	 * 
	 * @return array|bool		Return array with auth settings or false if not imported
	 */
	private function import_contract_config() {
		$option_value = get_option( 'virtuaria_correios_settings' );
		if ( ! is_array( $option_value ) ) {
			$option_value = maybe_unserialize( $option_value );
		}

		if ( is_array( $option_value ) && isset( $option_value['username'], $option_value['password'], $option_value['post_card'] ) ) {
			return [ 
				'user_name' => $option_value['username'],
				'access_code' => $option_value['password'],
				'postcard' => $option_value['post_card'],
			];
		}

		$option_value = get_option( 'woocommerce_correios-integration_settings' );

		if ( ! is_array( $option_value ) ) {
			$option_value = maybe_unserialize( $option_value );
		}

		if ( is_array( $option_value ) && isset( $option_value['username'], $option_value['password'], $option_value['post_card'] ) ) {
			return [ 
				'user_name' => $option_value['cws_username'],
				'access_code' => $option_value['cws_access_code'],
				'postcard' => $option_value['cws_posting_card'],
			];
		}


		return false;
	}

	public function disable_shipping_method( $instance_id ) {
		return WoocommerceShippingZoneMethod::update( [ 
			"is_enabled" => 0,
		], [ 
			"instance_id" => $instance_id,
		] );
	}



	/**
	 * Clone shipping options
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WC_Shipping_Method $source
	 * @param CorreiosShippingMethod  $destination
	 * 
	 * @return array
	 */
	private function clone_options( $source, $destination ) {
		$field_enabled = $destination->get_field_key( 'enabled' );
		$field_title = $destination->get_field_key( 'title' );
		$field_origin_postcode = $destination->get_field_key( 'origin_postcode' );
		$field_advanced_mode = $destination->get_field_key( 'advanced_mode' );
		$field_advanced_service = $destination->get_field_key( 'advanced_service' );
		$field_basic_service = $destination->get_field_key( 'basic_service' );
		$field_estimated_delivery = $destination->get_field_key( 'estimated_delivery' );
		$field_additional_days = $destination->get_field_key( 'additional_days' );
		$field_extra_weight = $destination->get_field_key( 'extra_weight' );
		$field_additional_tax = $destination->get_field_key( 'additional_tax' );
		$field_receipt_notice = $destination->get_field_key( 'receipt_notice' );
		$field_own_hands = $destination->get_field_key( 'own_hands' );
		$field_minimum_height = $destination->get_field_key( 'minimum_height' );
		$field_minimum_width = $destination->get_field_key( 'minimum_width' );
		$field_minimum_length = $destination->get_field_key( 'minimum_length' );
		$field_minimum_weight = $destination->get_field_key( 'minimum_weight' );
		$field_object_type = $destination->get_field_key( 'object_type' );
		$field_insurance = $destination->get_field_key( 'insurance' );
		$field_min_insurance_value = $destination->get_field_key( 'min_insurance_value' );
		$field_extra_weight_type = $destination->get_field_key( 'extra_weight_type' );
		$shipping_classes = $destination->get_field_key( 'shipping_class' );
		$store_postcode = get_option( 'woocommerce_store_postcode' );


		$post_data = [];
		foreach ( $destination->get_instance_form_fields() as $key => $field ) {
			$field_key = $destination->get_field_key( $key );
			if ( $field['type'] === 'checkbox' ) {
				$post_data[ $field_key ] = Sanitizer::post_checkbox( $field['default'] );
				continue;
			}
			$post_data[ $field_key ] = $field['default'] ?? '';
		}

		switch ( $source->id ) {
			case 'correios-cws':
				$postcode = $source->get_option( 'origin_postcode', '' );
				$cws_shipping_class = $source->get_option( 'shipping_class_id' );

				$post_data = array_merge( $post_data, [ 
					$field_enabled => $source->is_enabled(),
					$field_title => $source->get_title(),
					$field_advanced_mode => 'yes',
					$field_origin_postcode => empty( $postcode ) ? $store_postcode : $postcode,
					$field_advanced_service => $source->get_option( 'product_code' ),
					$field_estimated_delivery => Sanitizer::post_checkbox( $source->get_option( 'show_delivery_time' ) ),
					$field_additional_days => $source->get_option( 'additional_time' ),
					$field_extra_weight => $source->get_option( 'extra_weight' ),
					$field_additional_tax => NumberHelper::formatNumber( $source->get_option( 'fee' ), 2, ',' ),
					$field_receipt_notice => Sanitizer::post_checkbox( $source->get_option( 'receipt_notice' ) ),
					$field_own_hands => Sanitizer::post_checkbox( $source->get_option( 'own_hands' ) ),
					$field_minimum_height => $source->get_option( 'minimum_height' ),
					$field_minimum_width => $source->get_option( 'minimum_width' ),
					$field_minimum_length => $source->get_option( 'minimum_length' ),
					$field_minimum_weight => $source->get_option( 'minimum_weight' ),
					$shipping_classes => Sanitizer::array_numbers( is_numeric( $cws_shipping_class ) && $cws_shipping_class > 0 ? [ $cws_shipping_class ] : [] ),
				] );
				return $post_data;
			case 'correios-pac':
			case 'correios-sedex':
			case 'correios-sedex10-pacote':
			case 'correios-sedex12':
			case 'correios-sedex-hoje':
			case 'correios-impresso-normal':
				if ( $source->get_option( 'service_type' ) != "conventional" )
					throw new \Exception( esc_html__( 'Only conventional service type is supported.', 'infixs-correios-automatico' ) );

				$converted = [ 
					"correios-pac" => 'pac',
					"correios-sedex" => 'sedex',
					"correios-sedex10-pacote" => 'sedex10',
					"correios-sedex12" => 'sedex12',
					"correios-sedex-hoje" => 'sedexhoje',
					"correios-impresso-normal" => 'impressonormal',
				];

				$postcode = $source->get_option( 'origin_postcode', '' );

				$post_data = array_merge( $post_data,
					[ 
						$field_enabled => $source->is_enabled(),
						$field_title => $source->get_title(),
						$field_advanced_mode => null,
						$field_origin_postcode => empty( $postcode ) ? $store_postcode : $postcode,
						$field_basic_service => $converted[ $source->id ],
						$field_estimated_delivery => Sanitizer::post_checkbox( $source->get_option( 'show_delivery_time' ) ),
						$field_additional_days => $source->get_option( 'additional_time' ),
						$field_extra_weight => $source->get_option( 'extra_weight' ),
						$field_additional_tax => NumberHelper::formatNumber( $source->get_option( 'fee' ), 2, ',' ),
						$field_receipt_notice => Sanitizer::post_checkbox( $source->get_option( 'receipt_notice' ) ),
						$field_own_hands => Sanitizer::post_checkbox( $source->get_option( 'own_hands' ) ),
						$field_minimum_height => $source->get_option( 'minimum_height' ),
						$field_minimum_width => $source->get_option( 'minimum_width' ),
						$field_minimum_length => $source->get_option( 'minimum_length' ),
						$field_minimum_weight => $source->get_option( 'minimum_weight' ),
					] );
				return $post_data;

			case 'virtuaria-correios-sedex':
				$teste = "virutal";

				$extra_weight = json_decode( $source->get_option( 'extra_weight' ), true );
				$postcode = $source->get_option( 'origin', '' );

				$post_data = array_merge( $post_data,
					[ 
						$field_enabled => $source->is_enabled(),
						$field_title => $source->get_title(),
						$field_advanced_mode => 'yes',
						$field_advanced_service => $source->get_option( 'service_cod' ),
						$field_origin_postcode => empty( $postcode ) ? $store_postcode : $postcode,
						$field_estimated_delivery => $source->get_option( 'hide_delivery_time' ) == 'yes' ? null : 'yes',
						$field_object_type => $source->get_option( 'object_type' ) == "1" ? 'letter' : 'package',
						$field_additional_days => $source->get_option( 'additional_time' ),
						$field_extra_weight => isset( $extra_weight, $extra_weight['weight'] ) ? $extra_weight['weight'] : 0,
						$field_additional_tax => NumberHelper::formatNumber( $source->get_option( 'fee' ), 2, ',' ),
						$field_receipt_notice => Sanitizer::post_checkbox( $source->get_option( 'receipt_notice' ) ),
						$field_own_hands => Sanitizer::post_checkbox( $source->get_option( 'own_hands' ) ),
						$field_minimum_height => $source->get_option( 'minimum_height' ),
						$field_minimum_width => $source->get_option( 'minimum_width' ),
						$field_minimum_length => $source->get_option( 'minimum_length' ),
						$field_minimum_weight => $source->get_option( 'minimum_weight' ),
						$field_insurance => empty( $source->get_option( 'declare_value' ) ) ? null : 'yes',
						$field_min_insurance_value => $source->get_option( 'min_value_declared' ),
						$$field_extra_weight_type => isset( $extra_weight, $extra_weight['type'] ) ? $extra_weight['type'] : 'order',
					] );
				return $post_data;

			case 'melhorenvio_correios_pac':
			case 'melhorenvio_correios_sedex':
			case 'melhorenvio_correios_mini':

				$converted = [ 
					"melhorenvio_correios_pac" => 'pac',
					"melhorenvio_correios_sedex" => 'sedex',
				];

				$post_data = array_merge( $post_data,
					[ 
						$field_enabled => $source->is_enabled(),
						$field_title => $source->get_title(),
						$field_additional_days => $source->get_option( 'additional_time' ),
						$field_additional_tax => NumberHelper::formatNumber( $source->get_option( 'additional_tax' ), 2, ',' ),
					]
				);

				if ( $source->id === 'melhorenvio_correios_mini' ) {
					$post_data[ $field_advanced_mode ] = 'yes';
					$post_data[ $field_advanced_service ] = DeliveryServiceCode::CORREIOS_MINI_ENVIOS_CTR_AG;
				} else {
					$post_data[ $field_basic_service ] = $converted[ $source->id ];
				}

				return $post_data;

		}

		throw new \Exception( esc_html__( 'Shipping method not supported.', 'infixs-correios-automatico' ) );
	}

	public function createShippingMethod( $zone_id, $params = [] ) {
		$zone = new \WC_Shipping_Zone( $zone_id );

		$instance_id = $zone->add_shipping_method( 'infixs-correios-automatico' );

		if ( $instance_id === 0 )
			return false;

		$shipping_method = \WC_Shipping_Zones::get_shipping_method( $instance_id );
		$shipping_method->init_instance_settings();

		$update_data = [];

		if ( isset( $params['basic_service'] ) ) {
			$field_basic_service = $shipping_method->get_field_key( 'basic_service' );
			$update_data[ $field_basic_service ] = $params['basic_service'];
		}

		if ( isset( $params['origin_postcode'] ) ) {
			$field_origin_postcode = $shipping_method->get_field_key( 'origin_postcode' );
			$update_data[ $field_origin_postcode ] = $params['origin_postcode'];
		}

		if ( isset( $params['title'] ) ) {
			$field_title = $shipping_method->get_field_key( 'title' );
			$update_data[ $field_title ] = $params['title'];
		}


		foreach ( $shipping_method->get_instance_form_fields() as $key => $field ) {
			if ( 'title' !== $shipping_method->get_field_type( $field ) &&
				in_array( $shipping_method->get_field_key( $key ), array_keys( $update_data ) )
			) {
				try {
					$shipping_method->instance_settings[ $key ] = $shipping_method->get_field_value( $key, $field, $update_data );
				} catch (\Exception $e) {
					$shipping_method->add_error( $e->getMessage() );
				}
			}
		}

		if ( ! update_option( $shipping_method->get_instance_option_key(), $shipping_method->instance_settings, 'yes' ) ) {
			$zone->delete_shipping_method( $instance_id );
			return false;
		}

		$shipping_method = \WC_Shipping_Zones::get_shipping_method( $shipping_method->get_instance_id() );

		return $shipping_method;
	}

	/**
	 * Create default shipping zone
	 * 
	 * @since 1.0.0
	 * 
	 * @return int|bool
	 */
	public function createDefaultShippingZone() {
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( "Brasil" );
		$zone->set_zone_order( 1 );
		$zone->add_location( 'BR', 'country' );
		$zone_id = $zone->save();
		if ( $zone_id ) {
			return $zone_id;
		} else {
			return false;
		}
	}

	public function get_compatible_methods( $flatten = false ) {
		$methods = [ 
			"virtuaria-correios" => [ 
				"virtuaria-correios-sedex"
			],
			"woocommerce-correios" => [ 
				"correios-cws",
				"correios-pac",
				"correios-sedex",
				"correios-sedex10-pacote",
				"correios-sedex12",
				"correios-sedex-hoje",
				"correios-impresso-normal",
				//"correios-sedex10-envelope",
				//"correios-impresso-urgente",
			],
			"melhor-envio-cotacao" => [ 
				"melhorenvio_correios_pac",
				"melhorenvio_correios_sedex",
				"melhorenvio_correios_mini"
			],
		];

		return $flatten ? array_merge( ...array_values( $methods ) ) : $methods;
	}

	/**
	 * Check if has methods to import
	 * 
	 * @since 1.0.0
	 * 
	 * @return bool
	 */
	public function hasMethodsToImport() {
		$compatible_methods = $this->get_compatible_methods( true );

		$shipping_methods = $this->list_shipping_methods( [ 
			'is_enabled' => true,
			'method_id' => $compatible_methods,
		] );

		return count( $shipping_methods ) > 0;
	}

	public function hasCorreiosAutomaticoActiveMethods() {
		$shipping_zones = \WC_Shipping_Zones::get_zones();

		if ( empty( $shipping_zones ) ) {
			return false;
		}

		foreach ( $shipping_zones as $zone ) {
			$shipping_zone = new \WC_Shipping_Zone( $zone['id'] );
			/**
			 * @var \WC_Shipping_Method[] $shipping_methods
			 */
			$shipping_methods = $shipping_zone->get_shipping_methods();
			foreach ( $shipping_methods as $shipping_method ) {
				if ( $shipping_method->id === 'infixs-correios-automatico' && $shipping_method->is_enabled() ) {
					return true;
				}
			}
		}

		return false;
	}

	protected function fetchAddress( $postcode ) {
		$address = $this->fetchViacepAddress( $postcode );

		if ( $address ) {
			return $address;
		}

		$address = $this->correiosService->fetch_postcode( $postcode );

		if ( $address && ! is_wp_error( $address ) ) {
			return $address;
		}

		$address = $this->infixsApi->fetchAddress( $postcode );

		if ( $address && ! is_wp_error( $address ) ) {
			return $address;
		}

		return false;
	}

	/**
	 * Get address by postcode
	 * 
	 * When success return array with address data, otherwise return false
	 * 
	 * @param string $postcode
	 * 
	 * @return array|bool
	 */
	public function getAddressByPostcode( string $postcode ) {

		$postcode = Sanitizer::numeric_text( $postcode );

		$transient_key = "infixs_correios_address_{$postcode}";

		$address = get_transient( $transient_key );

		if ( $address ) {
			return $address;
		}

		$address = $this->fetchAddress( $postcode );

		if ( $address ) {
			set_transient( $transient_key, $address, DAY_IN_SECONDS );
		}

		return $address;
	}

	public function fetchViacepAddress( $postcode ) {
		$address = [];

		$api_url = "https://viacep.com.br/ws/{$postcode}/json/";

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! $body ) {
			return false;
		}

		$data = json_decode( $body );

		if ( ! $data ) {
			return false;
		}

		if ( isset( $data->erro ) && $data->erro ) {
			return false;
		}

		$address['postcode'] = $data->cep;
		$address['address'] = $data->logradouro;
		$address['neighborhood'] = $data->bairro;
		$address['city'] = $data->localidade;
		$address['state'] = $data->uf;

		return $address;
	}

	public function postcodeMatchRange( $postcode, $start, $end ) {
		$postcode = Sanitizer::numeric( $postcode );
		$start = Sanitizer::numeric( $start );
		$end = Sanitizer::numeric( $end );

		return $postcode >= $start && $postcode <= $end;
	}

	public function getStateByPostcode( $postcode ) {

		$postcode_range_map = [ 
			[ 'start' => '01000000', 'end' => '19999999', 'state' => 'SP' ],
			[ 'start' => '20000000', 'end' => '28999999', 'state' => 'RJ' ],
			[ 'start' => '29000000', 'end' => '29999999', 'state' => 'ES' ],
			[ 'start' => '30000000', 'end' => '39999999', 'state' => 'MG' ],
			[ 'start' => '40000000', 'end' => '48999999', 'state' => 'BA' ],
			[ 'start' => '49000000', 'end' => '49999999', 'state' => 'SE' ],
			[ 'start' => '50000000', 'end' => '56999999', 'state' => 'PE' ],
			[ 'start' => '57000000', 'end' => '57999999', 'state' => 'AL' ],
			[ 'start' => '58000000', 'end' => '58999999', 'state' => 'PB' ],
			[ 'start' => '59000000', 'end' => '59999999', 'state' => 'RN' ],
			[ 'start' => '60000000', 'end' => '63999999', 'state' => 'CE' ],
			[ 'start' => '64000000', 'end' => '64999999', 'state' => 'PI' ],
			[ 'start' => '65000000', 'end' => '65999999', 'state' => 'MA' ],
			[ 'start' => '66000000', 'end' => '68899999', 'state' => 'PA' ],
			[ 'start' => '68900000', 'end' => '68999999', 'state' => 'AP' ],
			[ 'start' => '69000000', 'end' => '69299999', 'state' => 'AM' ],
			[ 'start' => '69300000', 'end' => '69399999', 'state' => 'RR' ],
			[ 'start' => '69400000', 'end' => '69899999', 'state' => 'AM' ],
			[ 'start' => '69900000', 'end' => '69999999', 'state' => 'AC' ],
			[ 'start' => '70000000', 'end' => '72799999', 'state' => 'DF' ],
			[ 'start' => '72800000', 'end' => '72999999', 'state' => 'GO' ],
			[ 'start' => '73000000', 'end' => '73699999', 'state' => 'DF' ],
			[ 'start' => '73700000', 'end' => '76799999', 'state' => 'GO' ],
			[ 'start' => '76800000', 'end' => '76999999', 'state' => 'RO' ],
			[ 'start' => '77000000', 'end' => '77999999', 'state' => 'TO' ],
			[ 'start' => '78000000', 'end' => '78899999', 'state' => 'MT' ],
			[ 'start' => '78900000', 'end' => '78999999', 'state' => 'RO' ],
			[ 'start' => '79000000', 'end' => '79999999', 'state' => 'MS' ],
			[ 'start' => '80000000', 'end' => '87999999', 'state' => 'PR' ],
			[ 'start' => '88000000', 'end' => '89999999', 'state' => 'SC' ],
			[ 'start' => '90000000', 'end' => '99999999', 'state' => 'RS' ],
		];


		foreach ( $postcode_range_map as $range ) {
			if ( $this->postcodeMatchRange( $postcode, $range['start'], $range['end'] ) ) {
				return $range['state'];
			}
		}

		$address = $this->getAddressByPostcode( $postcode );
		return $address ? $address['state'] : '';
	}

	/**
	 * Get Ceint code by postcode
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $postcode
	 * 
	 * @return string|null
	 */
	public function getCeintByPostCode( $postcode ) {
		$ceint_range_map = [ 
			[ 'start' => '01000000', 'end' => '11599999', 'destination' => CeintCode::CEINT_SAO_PAULO ],
			[ 'start' => '60000000', 'end' => '63999999', 'destination' => CeintCode::CEINT_SAO_PAULO ],
			[ 'start' => '11600000', 'end' => '19999999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '30000000', 'end' => '39999999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '69900000', 'end' => '69999999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '74000000', 'end' => '76799999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '76800000', 'end' => '76999999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '77000000', 'end' => '77999999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '78000000', 'end' => '78899999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '79000000', 'end' => '79999999', 'destination' => CeintCode::CEINT_VALINHOS ],
			[ 'start' => '20000000', 'end' => '28999999', 'destination' => CeintCode::CEINT_RIO_DE_JANEIRO ],
			[ 'start' => '29000000', 'end' => '29999999', 'destination' => CeintCode::CEINT_RIO_DE_JANEIRO ],
			[ 'start' => '40000000', 'end' => '48999999', 'destination' => CeintCode::CEINT_RIO_DE_JANEIRO ],
			[ 'start' => '49000000', 'end' => '49999999', 'destination' => CeintCode::CEINT_RIO_DE_JANEIRO ],
			[ 'start' => '70000000', 'end' => '73999999', 'destination' => CeintCode::CEINT_RIO_DE_JANEIRO ],
			[ 'start' => '80000000', 'end' => '87999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '88000000', 'end' => '89999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '90000000', 'end' => '99999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '50000000', 'end' => '56999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '57000000', 'end' => '57999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '58000000', 'end' => '58999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '59000000', 'end' => '59999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '64000000', 'end' => '64999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '65000000', 'end' => '65999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '66000000', 'end' => '68899999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '68900000', 'end' => '68999999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '69000000', 'end' => '69299999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '69300000', 'end' => '69399999', 'destination' => CeintCode::CEINT_CURITIBA ],
			[ 'start' => '69400000', 'end' => '69899999', 'destination' => CeintCode::CEINT_CURITIBA ],
		];

		foreach ( $ceint_range_map as $range ) {
			if ( $this->postcodeMatchRange( $postcode, $range['start'], $range['end'] ) ) {
				return CeintCode::getCeintById( $range['destination'] );
			}
		}

		return null;
	}

	public function getCityByPostcode( $postcode ) {
		$address = $this->getAddressByPostcode( $postcode );
		return $address ? $address['city'] : '';
	}

	/**
	 * Get available zone methods
	 * 
	 * @since 1.0.0
	 * 
	 * @param array{
	 * 			country: string,
	 * 			state: string,
	 * 			postcode: string,
	 * 			city: string,
	 * 			address: string
	 * } $address
	 * 
	 * @return \WC_Shipping_Method[]
	 */
	public function getAvailableZoneMethods( $address = [] ) {
		$shipping_zone = \WC_Shipping_Zones::get_zone_matching_package(
			[ 
				'destination' => [ 
					'address' => $address['address'] ?? '',
					'country' => $address['country'] ?? '',
					'state' => $address['state'] ?? '',
					'postcode' => $address['postcode'] ?? '',
					'city' => $address['city'] ?? '',
				],
			]
		);

		return $shipping_zone->get_shipping_methods();
	}

	/**
	 * Get available zone correios methods
	 * 
	 * @since 1.0.0
	 * 
	 * @param array{
	 * 			country: string,
	 * 			state: string,
	 * 			postcode: string,
	 * 			city: string,
	 * 			address: string
	 * } $address
	 * 
	 * @return CorreiosShippingMethod[]
	 */
	public function getAvailableZoneCorreiosMethods( $address = [] ) {
		$methods = $this->getAvailableZoneMethods( $address );

		return array_filter( $methods, function ($method) {
			return $method instanceof CorreiosShippingMethod;
		} );
	}

	/**
	 * Calculate shipping by method
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $instance_id
	 * @param array $package
	 * 
	 * @return \WC_Shipping_Rate[]
	 */
	public function calculateShippingByMethod( $instance_id, $package = [] ) {
		$method = \WC_Shipping_Zones::get_shipping_method( $instance_id );
		return $method->get_rates_for_package( $package );
	}

	public function calculateShippingCost( $shipping_cost ) {
		$has_active_contract = $this->configRepository->boolean( 'auth.active' ) && Helper::contractHasService( APIServiceCode::PRECO );

		$has_active_contract = apply_filters( 'infixs_correios_automatico_calculate_shipping_has_active_contract', $has_active_contract, $shipping_cost );

		if ( $has_active_contract ) {
			return $this->correiosService->get_shipping_cost( $shipping_cost );
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

			$response = $this->infixsApi->calculateShipping( $request );

			if ( ! is_wp_error( $response ) && isset( $response["shipping_cost"] ) ) {
				Log::debug( "Shipping cost api infixs response", $response );
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
}