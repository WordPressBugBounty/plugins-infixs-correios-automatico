<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\ReturnService;

defined( 'ABSPATH' ) || exit;

/**
 * Settings Return Controller
 * 
 * @since 1.2.1
 * 
 * @package Infixs\CorreiosAutomatico\Controllers\Rest
 */
class SettingsReturnController {

	/**
	 * Return settings save
	 * 
	 * @since 1.2.1
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function save( $request ) {
		$data = $request->get_json_params();

		$updated_settings = [
			'active' => rest_sanitize_boolean( $data['active'] ),
			'auto_return' => rest_sanitize_boolean( $data['auto_return'] ),
			'days' => sanitize_text_field( $data['days'] ),
			'same_service' => rest_sanitize_boolean( $data['same_service'] ),
			'send_whatsapp' => rest_sanitize_boolean( $data['send_whatsapp'] ),
			'send_email' => rest_sanitize_boolean( $data['send_email'] ),
			'status' => sanitize_text_field( $data['status'] ) ?: 'Em devolução',
		];

		Config::update( 'return', $updated_settings );

		$response_data = $this->prepare_data();

		$response = [ 
			'status' => 'success',
			'return' => $response_data,
		];
		return rest_ensure_response( $response );
	}

	public function retrieve() {

		$response = [ 
			'status' => 'success',
			'return' => $this->prepare_data(),
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Prepare the data
	 *
	 * @since 1.2.1
	 * 
	 * @param array $settings
	 * 
	 * @return array
	 */
	public function prepare_data() {
		$active_notification_id = $this->get_active_return_notification_id();

		$sanitized_settings = [
			'active' => Config::boolean( 'return.active' ),
			'days' => Config::string( 'return.days' ),
			'auto_return' => Config::boolean( 'return.auto_return' ),
			'same_service' => Config::boolean( 'return.same_service' ),
			'send_whatsapp' => Config::boolean( 'return.send_whatsapp' ),
			'send_email' => Config::boolean( 'return.send_email' ),
			'status' => Config::string( 'return.status' ),
			'pingo_installed' => function_exists( 'infixs_pingo_notify_app' ),
			'return_notification_active' => null !== $active_notification_id,
			'return_notification_id' => $active_notification_id,
			'pingo_notifications_url' => admin_url( 'admin.php?page=infixs-pingo-notify&path=/notifications' ),
			'email_settings_url' => admin_url( 'admin.php?page=wc-settings&tab=email&section=correios_automatico_returning_email' ),
		];

		return $sanitized_settings;
	}

	/**
	 * Get the id of the first active Pingo Notify notification registered for the
	 * customer-return trigger, or null when none exists.
	 *
	 * @since 1.8.0
	 *
	 * @return int|null
	 */
	private function get_active_return_notification_id() {
		if ( ! class_exists( '\\Infixs\\PingoNotify\\Models\\Notification' ) ) {
			return null;
		}

		try {
			$notification = \Infixs\PingoNotify\Models\Notification::where( 'trigger_id', ReturnService::RETURN_TRIGGER_ID )
				->where( 'is_active', 1 )
				->first();

			return $notification ? (int) $notification->id : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}