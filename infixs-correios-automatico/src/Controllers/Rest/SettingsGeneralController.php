<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Controllers\Sanitizers\CalculatorStylesSanitizer;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;

defined( 'ABSPATH' ) || exit;
class SettingsGeneralController {

	/**
	 * Auth settings save
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function save( $request ) {
		$data = $request->get_json_params();

		do_action( 'infixs_correios_automatico_before_save_general_settings', $data );

		$updated_settings = [];

		$updated_log_settings = [];

		if ( isset( $data['autofill_address'] ) )
			$updated_settings['autofill_address'] = rest_sanitize_boolean( $data['autofill_address'] );

		if ( isset( $data['calculate_shipping_product_page'] ) )
			$updated_settings['calculate_shipping_product_page'] = rest_sanitize_boolean( $data['calculate_shipping_product_page'] );

		if ( isset( $data['calculate_shipping_product_page_position'] ) )
			$updated_settings['calculate_shipping_product_page_position'] = sanitize_text_field( $data['calculate_shipping_product_page_position'] );

		if ( isset( $data['consider_quantity'] ) ) {
			$updated_settings['consider_quantity'] = rest_sanitize_boolean( $data['consider_quantity'] );
		}

		if ( isset( $data['show_order_tracking_form'] ) )
			$updated_settings['show_order_tracking_form'] = rest_sanitize_boolean( $data['show_order_tracking_form'] );

		if ( isset( $data['show_order_label_form'] ) )
			$updated_settings['show_order_label_form'] = rest_sanitize_boolean( $data['show_order_label_form'] );

		if ( isset( $data['show_order_prepost_form'] ) )
			$updated_settings['show_order_prepost_form'] = rest_sanitize_boolean( $data['show_order_prepost_form'] );

		if ( isset( $data['tracking_compatiblity'] ) )
			$updated_settings['tracking_compatiblity'] = rest_sanitize_boolean( $data['tracking_compatiblity'] );

		if ( isset( $data['debug_active'] ) )
			$updated_log_settings['active'] = rest_sanitize_boolean( $data['debug_active'] );

		if ( isset( $data['debug_log'] ) )
			$updated_log_settings['debug_log'] = rest_sanitize_boolean( $data['debug_log'] );

		if ( isset( $data['info_log'] ) )
			$updated_log_settings['info_log'] = rest_sanitize_boolean( $data['info_log'] );

		if ( isset( $data['notice_log'] ) )
			$updated_log_settings['notice_log'] = rest_sanitize_boolean( $data['notice_log'] );

		if ( isset( $data['warning_log'] ) )
			$updated_log_settings['warning_log'] = rest_sanitize_boolean( $data['warning_log'] );

		if ( isset( $data['error_log'] ) )
			$updated_log_settings['error_log'] = rest_sanitize_boolean( $data['error_log'] );

		if ( isset( $data['critical_log'] ) )
			$updated_log_settings['critical_log'] = rest_sanitize_boolean( $data['critical_log'] );

		if ( isset( $data['alert_log'] ) )
			$updated_log_settings['alert_log'] = rest_sanitize_boolean( $data['alert_log'] );

		if ( isset( $data['emergency_log'] ) )
			$updated_log_settings['emergency_log'] = rest_sanitize_boolean( $data['emergency_log'] );

		if ( isset( $data['mask_postcode'] ) )
			$updated_settings['mask_postcode'] = rest_sanitize_boolean( $data['mask_postcode'] );

		if ( isset( $data['send_email_prepost'] ) )
			$updated_settings['send_email_prepost'] = rest_sanitize_boolean( $data['send_email_prepost'] );

		if ( isset( $data['simple_cart_shipping_calculator'] ) )
			$updated_settings['simple_cart_shipping_calculator'] = rest_sanitize_boolean( $data['simple_cart_shipping_calculator'] );

		if ( isset( $data['cart_shipping_calculator_always_visible'] ) )
			$updated_settings['cart_shipping_calculator_always_visible'] = rest_sanitize_boolean( $data['cart_shipping_calculator_always_visible'] );

		if ( isset( $data['auto_calculate_cart_shipping_postcode'] ) )
			$updated_settings['auto_calculate_cart_shipping_postcode'] = rest_sanitize_boolean( $data['auto_calculate_cart_shipping_postcode'] );

		if ( isset( $data['auto_calculate_product_shipping_postcode'] ) )
			$updated_settings['auto_calculate_product_shipping_postcode'] = rest_sanitize_boolean( $data['auto_calculate_product_shipping_postcode'] );

		if ( isset( $data['enable_order_status'] ) ) {
			$updated_settings['enable_order_status'] = rest_sanitize_boolean( $data['enable_order_status'] );
		}

		if ( isset( $data['active_preparing_to_ship'] ) ) {
			$updated_settings['active_preparing_to_ship'] = rest_sanitize_boolean( $data['active_preparing_to_ship'] );
		}

		if ( isset( $data['active_in_transit'] ) ) {
			$updated_settings['active_in_transit'] = rest_sanitize_boolean( $data['active_in_transit'] );
		}

		if ( isset( $data['active_waiting_pickup'] ) ) {
			$updated_settings['active_waiting_pickup'] = rest_sanitize_boolean( $data['active_waiting_pickup'] );
		}

		if ( isset( $data['active_returning'] ) ) {
			$updated_settings['active_returning'] = rest_sanitize_boolean( $data['active_returning'] );
		}

		if ( isset( $data['active_delivered'] ) ) {
			$updated_settings['active_delivered'] = rest_sanitize_boolean( $data['active_delivered'] );
		}

		if ( isset( $data['status_preparing_to_ship'] ) ) {
			$updated_settings['status_preparing_to_ship'] = sanitize_text_field( $data['status_preparing_to_ship'] ) ?: 'Preparando para envio';
		}

		if ( isset( $data['status_in_transit'] ) ) {
			$updated_settings['status_in_transit'] = sanitize_text_field( $data['status_in_transit'] ) ?: 'Em transporte';
		}

		if ( isset( $data['status_waiting_pickup'] ) ) {
			$updated_settings['status_waiting_pickup'] = sanitize_text_field( $data['status_waiting_pickup'] ) ?: 'Aguardando retirada';
		}

		if ( isset( $data['status_returning'] ) ) {
			$updated_settings['status_returning'] = sanitize_text_field( $data['status_returning'] ) ?: 'Em devolução';
		}

		if ( isset( $data['status_delivered'] ) ) {
			$updated_settings['status_delivered'] = sanitize_text_field( $data['status_delivered'] ) ?: 'Entregue';
		}

		if ( isset( $data['change_preparing_to_ship'] ) ) {
			$updated_settings['change_preparing_to_ship'] = sanitize_text_field( $data['change_preparing_to_ship'] ) === 'manual' ? 'manual' : 'auto';
		}

		if ( isset( $data['change_in_transit'] ) ) {
			$updated_settings['change_in_transit'] = sanitize_text_field( $data['change_in_transit'] ) === 'manual' ? 'manual' : 'auto';
		}

		if ( isset( $data['change_waiting_pickup'] ) ) {
			$updated_settings['change_waiting_pickup'] = sanitize_text_field( $data['change_waiting_pickup'] ) === 'manual' ? 'manual' : 'auto';
		}

		if ( isset( $data['change_returning'] ) ) {
			$updated_settings['change_returning'] = sanitize_text_field( $data['change_returning'] ) === 'manual' ? 'manual' : 'auto';
		}

		if ( isset( $data['change_delivered'] ) ) {
			$updated_settings['change_delivered'] = sanitize_text_field( $data['change_delivered'] ) === 'manual' ? 'manual' : 'auto';
		}

		if ( isset( $data['email_preparing_to_ship'] ) ) {
			$updated_settings['email_preparing_to_ship'] = rest_sanitize_boolean( $data['email_preparing_to_ship'] );
		}

		if ( isset( $data['email_in_transit'] ) ) {
			$updated_settings['email_in_transit'] = rest_sanitize_boolean( $data['email_in_transit'] );
		}

		if ( isset( $data['email_waiting_pickup'] ) ) {
			$updated_settings['email_waiting_pickup'] = rest_sanitize_boolean( $data['email_waiting_pickup'] );
		}

		if ( isset( $data['email_returning'] ) ) {
			$updated_settings['email_returning'] = rest_sanitize_boolean( $data['email_returning'] );
		}

		if ( isset( $data['email_delivered'] ) ) {
			$updated_settings['email_delivered'] = rest_sanitize_boolean( $data['email_delivered'] );
		}

		if ( isset( $data['auto_change_order_to_completed'] ) ) {
			$updated_settings['auto_change_order_to_completed'] = rest_sanitize_boolean( $data['auto_change_order_to_completed'] );
		}

		if ( isset( $data['show_full_address_calculate_product'] ) ) {
			$updated_settings['show_full_address_calculate_product'] = rest_sanitize_boolean( $data['show_full_address_calculate_product'] );
		}

		if ( isset( $data['tracking_page'] ) ) {
			$updated_settings['tracking_page'] = (int) $data['tracking_page'];
		}

		if ( isset( $data['shipping_bar_progress_color'] ) ) {
			$updated_settings['shipping_bar_progress_color'] = sanitize_text_field( $data['shipping_bar_progress_color'] );
		}

		if ( isset( $data['shipping_bar_background_color'] ) ) {
			$updated_settings['shipping_bar_background_color'] = sanitize_text_field( $data['shipping_bar_background_color'] );
		}

		if ( isset( $data['shipping_bar_text_color'] ) ) {
			$updated_settings['shipping_bar_text_color'] = sanitize_text_field( $data['shipping_bar_text_color'] );
		}

		if ( isset( $data['shipping_bar_legend_text_color'] ) ) {
			$updated_settings['shipping_bar_legend_text_color'] = sanitize_text_field( $data['shipping_bar_legend_text_color'] );
		}

		if ( isset( $data['shipping_bar_striped_effect'] ) ) {
			$updated_settings['shipping_bar_striped_effect'] = rest_sanitize_boolean( $data['shipping_bar_striped_effect'] );
		}

		if ( isset( $data['shipping_bar_animated_effect'] ) ) {
			$updated_settings['shipping_bar_animated_effect'] = rest_sanitize_boolean( $data['shipping_bar_animated_effect'] );
		}

		if ( isset( $data['free_shipping_bar_cart_page'] ) ) {
			$updated_settings['free_shipping_bar_cart_page'] = rest_sanitize_boolean( $data['free_shipping_bar_cart_page'] );
		}

		if ( isset( $data['hide_bar_until_postcode'] ) ) {
			$updated_settings['hide_bar_until_postcode'] = rest_sanitize_boolean( $data['hide_bar_until_postcode'] );
		}

		if ( isset( $data['when_api_update_tracking_code'] ) ) {
			$updated_settings['when_api_update_tracking_code'] = sanitize_text_field( $data['when_api_update_tracking_code'] );
		}

		if ( isset( $data['calculator_style_id'] ) ) {
			$updated_settings['calculator_style_id'] = sanitize_text_field( $data['calculator_style_id'] );
		}

		if ( isset( $data['calculator_styles'] ) && is_array( $data['calculator_styles'] ) ) {
			$updated_settings['calculator_styles'] = CalculatorStylesSanitizer::sanitize( $data['calculator_styles'] );
		}

		$updated_settings = apply_filters( 'infixs_correios_automatico_save_general_settings', $updated_settings, $data );

		if ( ! empty( $updated_settings ) ) {
			Config::update( 'general', $updated_settings );
			Log::debug( 'Configurações gerais salvas' );
		}

		if ( ! empty( $updated_log_settings ) ) {
			Config::update( 'debug', apply_filters( 'infixs_correios_automatico_save_debug_settings', $updated_log_settings, $data ) );
			Log::debug( 'Configurações de depuração salvas' );
		}

		$response_data = $this->prepare_data();

		$response = [ 
			'status' => 'success',
			'data' => $response_data,
		];
		return rest_ensure_response( $response );
	}

	public function retrieve() {
		$sanitized_settings = $this->prepare_data();
		return rest_ensure_response( $sanitized_settings );
	}

	/**
	 * Terms accept save
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_REST_Request $request
	 * 
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function terms( $request ) {
		$data = $request->get_json_params();

		if ( ! isset( $data['license_key'] ) || empty( $data['license_key'] ) ) {
			return new \WP_Error( 'missing_license_key', 'License Key is required', [ 'status' => 400 ] );
		}

		if ( ! isset( $data['token'] ) || empty( $data['token'] ) ) {
			return new \WP_Error( 'missing_token', 'Token is required', [ 'status' => 400 ] );
		}

		if ( ! isset( $data['accepted'] ) ) {
			return new \WP_Error( 'missing_accepted', 'Accepted is required', [ 'status' => 400 ] );
		}

		Container::infixsApi()->acceptTerms( $data['license_key'], $data['token'] );

		Config::update( 'general', [ 
			"terms_and_conditions_of_use_accepted" => rest_sanitize_boolean( $data['accepted'] )
		] );

		return rest_ensure_response( [ 
			'status' => 'success',
		] );
	}

	/**
	 * Prepare the data
	 *
	 * @since 1.0.0
	 * 
	 * @param array $settings
	 * 
	 * @return array
	 */
	protected function prepare_data() {
		$sanitized_settings = [ 
			'autofill_address' => Config::boolean( 'general.autofill_address' ),
			'calculate_shipping_product_page' => Config::boolean( 'general.calculate_shipping_product_page' ),
			'consider_quantity' => Config::boolean( 'general.consider_quantity' ),
			'calculate_shipping_product_page_position' => Config::string( 'general.calculate_shipping_product_page_position' ),
			'show_order_tracking_form' => Config::boolean( 'general.show_order_tracking_form' ),
			'show_order_label_form' => Config::boolean( 'general.show_order_label_form' ),
			'show_order_prepost_form' => Config::boolean( 'general.show_order_prepost_form' ),
			'tracking_compatiblity' => Config::boolean( 'general.tracking_compatiblity' ),
			'debug_active' => Config::boolean( 'debug.active' ),
			'debug_log' => Config::boolean( 'debug.debug_log' ),
			'info_log' => Config::boolean( 'debug.info_log' ),
			'notice_log' => Config::boolean( 'debug.notice_log' ),
			'warning_log' => Config::boolean( 'debug.warning_log' ),
			'error_log' => Config::boolean( 'debug.error_log' ),
			'critical_log' => Config::boolean( 'debug.critical_log' ),
			'alert_log' => Config::boolean( 'debug.alert_log' ),
			'emergency_log' => Config::boolean( 'debug.emergency_log' ),
			'mask_postcode' => Config::boolean( 'general.mask_postcode' ),
			'send_email_prepost' => Config::boolean( 'general.send_email_prepost' ),
			'simple_cart_shipping_calculator' => Config::boolean( 'general.simple_cart_shipping_calculator' ),
			'cart_shipping_calculator_always_visible' => Config::boolean( 'general.cart_shipping_calculator_always_visible' ),
			'auto_calculate_cart_shipping_postcode' => Config::boolean( 'general.auto_calculate_cart_shipping_postcode' ),
			'auto_calculate_product_shipping_postcode' => Config::boolean( 'general.auto_calculate_product_shipping_postcode' ),
			'enable_order_status' => Config::boolean( 'general.enable_order_status' ),
			'active_preparing_to_ship' => Config::boolean( 'general.active_preparing_to_ship' ),
			'active_in_transit' => Config::boolean( 'general.active_in_transit' ),
			'active_waiting_pickup' => Config::boolean( 'general.active_waiting_pickup' ),
			'active_returning' => Config::boolean( 'general.active_returning' ),
			'active_delivered' => Config::boolean( 'general.active_delivered' ),
			'status_preparing_to_ship' => Config::string( 'general.status_preparing_to_ship' ),
			'status_in_transit' => Config::string( 'general.status_in_transit' ),
			'status_waiting_pickup' => Config::string( 'general.status_waiting_pickup' ),
			'status_returning' => Config::string( 'general.status_returning' ),
			'status_delivered' => Config::string( 'general.status_delivered' ),
			'change_preparing_to_ship' => Config::string( 'general.change_preparing_to_ship' ),
			'change_in_transit' => Config::string( 'general.change_in_transit' ),
			'change_waiting_pickup' => Config::string( 'general.change_waiting_pickup' ),
			'change_returning' => Config::string( 'general.change_returning' ),
			'change_delivered' => Config::string( 'general.change_delivered' ),
			'email_preparing_to_ship' => Config::boolean( 'general.email_preparing_to_ship' ),
			'email_in_transit' => Config::boolean( 'general.email_in_transit' ),
			'email_waiting_pickup' => Config::boolean( 'general.email_waiting_pickup' ),
			'email_returning' => Config::boolean( 'general.email_returning' ),
			'email_delivered' => Config::boolean( 'general.email_delivered' ),
			'auto_change_order_to_completed' => Config::boolean( 'general.auto_change_order_to_completed' ),
			'show_full_address_calculate_product' => Config::boolean( 'general.show_full_address_calculate_product' ),
			'tracking_page' => Config::integer( 'general.tracking_page' ),
			'pages' => array_reduce( get_pages(), function ($result, $page) {
				$result[ $page->ID ] = $page->post_title;
				return $result;
			}, [] ),
			'shipping_bar_progress_color' => Config::string( 'general.shipping_bar_progress_color' ),
			'shipping_bar_background_color' => Config::string( 'general.shipping_bar_background_color' ),
			'shipping_bar_text_color' => Config::string( 'general.shipping_bar_text_color' ),
			'shipping_bar_legend_text_color' => Config::string( 'general.shipping_bar_legend_text_color' ),
			'shipping_bar_striped_effect' => Config::boolean( 'general.shipping_bar_striped_effect' ),
			'shipping_bar_animated_effect' => Config::boolean( 'general.shipping_bar_animated_effect' ),
			'free_shipping_bar_cart_page' => Config::boolean( 'general.free_shipping_bar_cart_page' ),
			'hide_bar_until_postcode' => Config::boolean( 'general.hide_bar_until_postcode' ),
			'when_api_update_tracking_code' => Config::string( 'general.when_api_update_tracking_code' ),
			'calculator_style_id' => Config::string( 'general.calculator_style_id' ),
			'calculator_styles' => Config::get( 'general.calculator_styles', [] ),
		];

		return apply_filters( 'infixs_correios_automatico_prepare_general_settings', $sanitized_settings );
	}
}