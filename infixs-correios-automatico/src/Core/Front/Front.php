<?php

namespace Infixs\CorreiosAutomatico\Core\Front;

use Infixs\CorreiosAutomatico\Core\Front\WooCommerce\WCIntegration;
use Infixs\CorreiosAutomatico\Core\Support\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Front-End Functions
 * 
 * Settup all functions for public front end area, actions and filters.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Front {

	public function __construct() {
		new WCIntegration( $this );
	}


	public function enqueue_scripts() {
		global $post;

		wp_enqueue_style(
			'infixs-correios-automatico-front',
			\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/front/css/style.css',
			[],
			filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/front/css/style.css' ),
		);

		wp_enqueue_script(
			'infixs-correios-automatico-front',
			\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/front/js/main.js',
			[ 'jquery', 'jquery-blockui', 'wp-util' ],
			filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/front/js/main.js' ),
			true
		);

		$script_data = [ 
			'nonce' => wp_create_nonce( 'infixs_correios_automatico_nonce' ),
		];

		if ( function_exists( 'is_product' ) && is_product() ) {
			$script_data['productId'] = $post->ID;
			$script_data['options'] = [ 
				'autoCalculateProductShippingPostcode' => Config::boolean( 'general.auto_calculate_product_shipping_postcode' ),
			];
		}

		wp_localize_script(
			'infixs-correios-automatico-front',
			'infxsCorreiosAutomatico',
			$script_data
		);

		if ( function_exists( 'is_cart' ) && is_cart() ) {

			wp_enqueue_script(
				'infixs-correios-automatico-front-cart',
				\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/front/js/cart.js',
				[ 'jquery' ],
				filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/front/js/cart.js' ),
				true
			);


			wp_localize_script(
				'infixs-correios-automatico-front-cart',
				'infxsCorreiosAutomaticoCart',
				[ 
					'options' => [ 
						'shippingCalculatorAlwaysVisible' => Config::boolean( 'general.cart_shipping_calculator_always_visible' ),
						'autoCalculateCartShippingPostcode' => Config::boolean( 'general.auto_calculate_cart_shipping_postcode' ),
					]
				]
			);
		}
	}
}