<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;

defined( 'ABSPATH' ) || exit;

/**
 * Return (Devolução / Logística Reversa) service.
 *
 * Orchestrates the customer-initiated return flow: eligibility, status change,
 * reverse prepost creation (when auto_return is enabled) and customer
 * notifications (e-mail + WhatsApp via Pingo Notify).
 *
 * @since 1.8.0
 */
class ReturnService {

	/**
	 * Pingo Notify trigger id for the customer return event.
	 *
	 * @var string
	 */
	const RETURN_TRIGGER_ID = 'infixs_correios_automatico_customer_return';

	/**
	 * WordPress action that fires the customer return trigger.
	 *
	 * @var string
	 */
	const RETURN_TRIGGER_HOOK = 'infixs_correios_automatico_customer_return_confirmed';

	/**
	 * Order meta flagging that the customer already requested a return.
	 *
	 * @var string
	 */
	const RETURN_REQUESTED_META = '_infixs_correios_automatico_return_requested';

	/** @var PrepostService */
	protected $prepostService;

	/** @var TrackingService */
	protected $trackingService;

	/**
	 * @param PrepostService  $prepostService
	 * @param TrackingService $trackingService
	 */
	public function __construct( PrepostService $prepostService, TrackingService $trackingService ) {
		$this->prepostService = $prepostService;
		$this->trackingService = $trackingService;

		// Notify the customer (code) whenever a reverse prepost is created (auto or manual).
		add_action( 'infixs_correios_automatico_reverse_prepost_created', [ $this, 'onReverseCreated' ], 10, 2 );
	}

	/**
	 * Whether the return option is enabled in the settings.
	 *
	 * @since 1.8.0
	 *
	 * @return bool
	 */
	public function isFeatureActive() {
		return Config::boolean( 'return.active' );
	}

	/**
	 * Number of days the return option stays available, counted from completion.
	 *
	 * @since 1.8.0
	 *
	 * @return int
	 */
	public function getReturnDays() {
		return (int) Config::string( 'return.days' );
	}

	/**
	 * Days remaining for the customer to request a return.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order $order
	 *
	 * @return int Remaining days (>= 0). Returns -1 when not eligible/expired.
	 */
	public function getDaysRemaining( $order ) {
		if ( ! $this->isFeatureActive() || $order->get_status() !== 'completed' ) {
			return -1;
		}

		$days = $this->getReturnDays();

		$completed_date = $order->get_date_completed();
		if ( ! $completed_date ) {
			$completed_date = $order->get_date_modified();
		}

		if ( ! $completed_date ) {
			return -1;
		}

		$deadline = $completed_date->getTimestamp() + ( $days * DAY_IN_SECONDS );
		$remaining = (int) ceil( ( $deadline - time() ) / DAY_IN_SECONDS );

		return $remaining > 0 ? $remaining : -1;
	}

	/**
	 * Whether the order is eligible for a customer return request.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order $order
	 *
	 * @return bool
	 */
	public function isEligible( $order ) {
		return $this->getDaysRemaining( $order ) >= 0;
	}

	/**
	 * Whether the customer already requested a return for this order.
	 *
	 * Based on the order status (returning) instead of a meta flag, so the store
	 * can change the order status to hide the "already requested" message.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order $order
	 *
	 * @return bool
	 */
	public function isAlreadyRequested( $order ) {
		return $order->get_status() === 'returning';
	}

	/**
	 * Handle a customer return request.
	 *
	 * Marks the order as returning, and (when auto_return is enabled) creates the
	 * reverse prepost. The customer notification with the reverse code is sent by
	 * {@see onReverseCreated()} once a reverse prepost exists.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order $order
	 *
	 * @return array|\WP_Error
	 */
	public function requestReturn( $order ) {
		if ( ! $this->isFeatureActive() ) {
			return new \WP_Error( 'return_disabled', 'A devolução não está disponível para este pedido.', [ 'status' => 400 ] );
		}

		if ( ! $this->isEligible( $order ) ) {
			return new \WP_Error( 'return_not_eligible', 'O prazo para solicitar a devolução deste pedido expirou.', [ 'status' => 400 ] );
		}

		if ( $this->isAlreadyRequested( $order ) ) {
			return new \WP_Error( 'return_already_requested', 'A devolução deste pedido já foi solicitada.', [ 'status' => 400 ] );
		}

		$order->update_meta_data( self::RETURN_REQUESTED_META, current_time( 'mysql' ) );
		$order->save();

		$order->update_status( 'returning', __( 'Correios Automático - Devolução solicitada pelo cliente.', 'infixs-correios-automatico' ) );

		if ( Config::boolean( 'return.auto_return' ) ) {
			$reverse = $this->prepostService->createReversePrepost( $order->get_id() );

			if ( is_wp_error( $reverse ) ) {
				// Keep the request registered; the store can create the reverse prepost manually.
				Log::notice( 'Não foi possível criar a pré-postagem reversa automaticamente.', [
					'order_id' => $order->get_id(),
					'message' => $reverse->get_error_message(),
				] );
			}
		}

		return [
			'success' => true,
			'message' => 'Devolução solicitada com sucesso.',
		];
	}

	/**
	 * Send the customer notifications (e-mail + WhatsApp) with the reverse code.
	 *
	 * Hooked to `infixs_correios_automatico_reverse_prepost_created`, so it runs
	 * for both automatic and manual (admin) reverse prepost creation.
	 *
	 * @since 1.8.0
	 *
	 * @param int                                              $order_id
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost        $prepost
	 *
	 * @return void
	 */
	public function onReverseCreated( $order_id, $prepost ) {
		$reverse_code = is_object( $prepost ) && isset( $prepost->object_code ) ? $prepost->object_code : '';

		if ( Config::boolean( 'return.send_email' ) ) {
			$this->trackingService->sendReturningNotification( $order_id, $reverse_code, '' );
		}

		if ( Config::boolean( 'return.send_whatsapp' ) ) {
			do_action( self::RETURN_TRIGGER_HOOK, $order_id, $reverse_code );
		}
	}
}
