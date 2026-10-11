<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Utils\ShipmentCodesFormatter;

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

	/** @var ShipmentContextResolver */
	protected $shipmentContextResolver;

	/**
	 * Whether the notification of each reverse prepost is on hold.
	 *
	 * An automatic return in volumes tells the customer every code at once,
	 * after the last box.
	 *
	 * @since 1.11.0
	 *
	 * @var bool
	 */
	private $holdReverseNotifications = false;

	/**
	 * Notifies the customer (code) whenever a reverse prepost is created, automatically or by hand.
	 *
	 * @since 1.8.0
	 * @since 1.11.0 Receives the shipment context resolver.
	 *
	 * @param PrepostService          $prepostService
	 * @param TrackingService         $trackingService
	 * @param ShipmentContextResolver $shipmentContextResolver
	 */
	public function __construct( PrepostService $prepostService, TrackingService $trackingService, ShipmentContextResolver $shipmentContextResolver ) {
		$this->prepostService = $prepostService;
		$this->trackingService = $trackingService;
		$this->shipmentContextResolver = $shipmentContextResolver;

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
		return $this->isOwnShipment( $order ) && $this->getDaysRemaining( $order ) >= 0;
	}

	/**
	 * Whether the order was shipped by one of our methods.
	 *
	 * The return is a Correios reverse shipment, so it is only offered for an
	 * order this plugin shipped: Correios Automático itself, a method linked to
	 * it, or a carrier extension such as PEX and JadLog. An order carried by an
	 * unrelated plugin is returned through whatever that plugin offers.
	 *
	 * @since 1.10.4
	 *
	 * @param \WC_Order $order
	 *
	 * @return bool
	 */
	public function isOwnShipment( $order ) {
		return ( new Order( $order ) )->hasInfixsShippingMethod();
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
	 * reverse preposts. The customer is notified with the reverse codes once they
	 * exist.
	 *
	 * @since 1.8.0
	 * @since 1.11.0 Creates one reverse prepost per shipment.
	 *
	 * @param \WC_Order $order
	 *
	 * @return array|\WP_Error
	 */
	public function requestReturn( $order ) {
		if ( ! $this->isFeatureActive() ) {
			return new \WP_Error( 'return_disabled', 'A devolução não está disponível para este pedido.', [ 'status' => 400 ] );
		}

		if ( ! $this->isOwnShipment( $order ) ) {
			return new \WP_Error( 'return_not_supported', 'A devolução não está disponível para o método de envio deste pedido.', [ 'status' => 400 ] );
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
			$this->createReversePreposts( $order );
		}

		return [
			'success' => true,
			'message' => 'Devolução solicitada com sucesso.',
		];
	}

	/**
	 * Create the reverse preposts of a return, one per shipment.
	 *
	 * An order shipped as a single package gets one reverse prepost for the
	 * whole order, notified by {@see onReverseCreated()}. An order shipped in
	 * volumes, or by more than one vendor, gets one per shipping line, since the
	 * boxes together may exceed what the Correios take in one object; the
	 * customer is then told once, with the codes that were created. A shipment
	 * that fails keeps the request registered and its error on its own line, for
	 * the store to create that reverse prepost by hand.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order being returned.
	 *
	 * @return void
	 */
	private function createReversePreposts( $order ) {
		$order_id = $order->get_id();
		$contexts = $this->shipmentContextResolver->resolveShipmentContexts( $order_id, [] );
		$grouped = count( $contexts ) > 1;
		$created = [];

		$this->holdReverseNotifications = $grouped;

		try {
			foreach ( $contexts as $context ) {
				$reverse = $this->prepostService->createReversePrepost( $order_id, $context );

				if ( is_wp_error( $reverse ) ) {
					Log::notice( 'Não foi possível criar a pré-postagem reversa automaticamente.', [
						'order_id' => $order_id,
						'shipping_item_id' => isset( $context['shipping_item_id'] ) ? $context['shipping_item_id'] : 0,
						'message' => $reverse->get_error_message(),
					] );

					continue;
				}

				$created[] = $reverse;
			}
		} finally {
			$this->holdReverseNotifications = false;
		}

		if ( $grouped && $created ) {
			$this->notifyReverseCodes( $order_id, $created );
		}
	}

	/**
	 * Send the customer notifications (e-mail + WhatsApp) with the reverse code.
	 *
	 * Hooked to `infixs_correios_automatico_reverse_prepost_created`, so it runs
	 * for both automatic and manual (admin) reverse prepost creation. An
	 * automatic return in volumes holds it and notifies every code at the end.
	 *
	 * @since 1.8.0
	 *
	 * @param int                                              $order_id
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost        $prepost
	 *
	 * @return void
	 */
	public function onReverseCreated( $order_id, $prepost ) {
		if ( $this->holdReverseNotifications ) {
			return;
		}

		$this->notifyReverseCodes( $order_id, [ $prepost ] );
	}

	/**
	 * Get the reverse codes of an order, one line per returned shipment.
	 *
	 * A code of a volume reads "Volume i/N: CODE". Reverse preposts created
	 * before 1.11.0 are not listed.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order returned.
	 *
	 * @return string[]
	 */
	public function getReturnCodeLines( $order ) {
		$reverse_preposts = $this->prepostService->getReversePreposts( $order );

		if ( ! $reverse_preposts ) {
			return [];
		}

		return ShipmentCodesFormatter::lines( new Order( $order ), $reverse_preposts );
	}

	/**
	 * Send a single e-mail and a single WhatsApp with the codes of reverse preposts.
	 *
	 * @since 1.11.0
	 *
	 * @param int                                         $order_id Order returned.
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost[] $preposts Reverse preposts created.
	 *
	 * @return void
	 */
	private function notifyReverseCodes( $order_id, $preposts ) {
		$codes = [];

		foreach ( $preposts as $prepost ) {
			if ( ! is_object( $prepost ) || empty( $prepost->object_code ) ) {
				continue;
			}

			$codes[] = [
				'code' => $prepost->object_code,
				'shipping_item_id' => (int) $prepost->shipping_item_id,
			];
		}

		$code_list = wp_list_pluck( $codes, 'code' );

		if ( Config::boolean( 'return.send_email' ) ) {
			$this->trackingService->sendReturningNotification( $order_id, $code_list, '' );
		}

		if ( Config::boolean( 'return.send_whatsapp' ) ) {
			/**
			 * Fires when the customer has to receive the codes of a return.
			 *
			 * @since 1.8.0
			 * @since 1.11.0 Passes the code of every volume returned.
			 *
			 * @param int     $order_id     Order returned.
			 * @param string  $return_code  Reverse codes, separated by commas; may be empty.
			 * @param array[] $return_codes Reverse codes, each with `code` and `shipping_item_id`.
			 */
			do_action( self::RETURN_TRIGGER_HOOK, $order_id, implode( ', ', $code_list ), $codes );
		}
	}
}
