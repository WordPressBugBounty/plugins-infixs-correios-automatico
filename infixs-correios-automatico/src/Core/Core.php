<?php

namespace Infixs\CorreiosAutomatico\Core;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Admin\Admin;
use Infixs\CorreiosAutomatico\Core\Front\Front;
use Infixs\CorreiosAutomatico\Core\Front\WooCommerce\AutofillAddress;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Database\Migration;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;

defined( 'ABSPATH' ) || exit;
/**
 * Correios Automático Core Functions
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Core {
	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'init', [ $this, 'check_update' ] );

		new Install();
		new Admin( Container::infixsApi() );
		new Front();

		$this->load_modules();

		do_action( 'infixs_correios_automatico_plugin_loaded' );
	}

	public function load_modules() {
		if ( Config::boolean( 'general.autofill_address' ) ) {
			new AutofillAddress( Container::shippingService() );
		}
	}

	/**
	 * Check plugin update.
	 *
	 * @since 1.0.0
	 */
	public function check_update() {
		$version = get_option( '_infixs_correios_automatico_version' );

		try {
			$this->migrate_options( $version );
		} catch (\Exception $e) {
			Log::alert( "Error on migrate options: {$e->getMessage()}" );
		}

		if ( $version !== \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_VERSION ) {
			update_option( '_infixs_correios_automatico_version', \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_VERSION );
			Migration::run();
		}
	}

	public function migrate_options( $version ) {
		if ( empty( $version ) )
			return;

		if ( version_compare( $version, '1.4.6', '<' ) && class_exists( 'WC_Shipping_Zones' ) ) {
			$shipping_zones = \WC_Shipping_Zones::get_zones();
			foreach ( $shipping_zones as $zone ) {
				/**
				 * @var \WC_Shipping_Method[] $shipping_methods
				 */
				$shipping_methods = $zone['shipping_methods'];
				foreach ( $shipping_methods as $method ) {
					$method->init_instance_settings();
					if ( $method->id === 'infixs-correios-automatico' && $method->get_product_code() === DeliveryServiceCode::IMPRESSO_MODICO ) {
						$method->instance_settings['modic_use_range'] = 'yes';
						update_option( $method->get_instance_option_key(), $method->instance_settings, true );
					}
				}
			}
		}

		if ( version_compare( $version, '1.4.4', '<' ) ) {
			if ( Config::get( 'general.enable_order_status' ) === 'yes' ) {
				Config::update( 'general.active_preparing_to_ship', 'yes' );
				Config::update( 'general.active_in_transit', 'yes' );

				if ( class_exists( 'Infixs\CorreiosAutomaticoPro\Core\Core' ) ) {
					Config::update( 'general.change_preparing_to_ship', 'auto' );
					Config::update( 'general.change_in_transit', 'auto' );
				}
			}

			if ( Config::boolean( 'auth.active' ) ) {
				$postcard_response = Container::correiosService()->auth_postcard(
					Config::string( 'auth.user_name' ),
					Config::string( 'auth.access_code' ),
					Config::string( 'auth.postcard' )

				);

				if ( ! is_wp_error( $postcard_response ) ) {
					$allowed_services = array_column( $postcard_response['cartaoPostagem']['apis'], 'api' );

					if ( ! empty( $allowed_services ) ) {
						Config::update( 'auth', [ 
							'contract_number' => sanitize_text_field( isset( $postcard_response['cartaoPostagem']['contrato'] ) ? $postcard_response['cartaoPostagem']['contrato'] : '' ),
							'allowed_services' => $allowed_services ?? [],
							'contract_type' => sanitize_text_field( $postcard_response['perfil'] ),
							'contract_document' => sanitize_text_field( $postcard_response['perfil'] === 'PJ' ? $postcard_response['cnpj'] : $postcard_response['cpf'] ),
						] );
					}
				}
			}
		}

		if ( version_compare( $version, '1.5.9', '<' ) ) {
			Install::create_tracking_page();
		}
	}
}