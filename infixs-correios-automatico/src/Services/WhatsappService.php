<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;

defined( 'ABSPATH' ) || exit;

/**
 * Whatsapp service.
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.6.95
 */
class WhatsappService {

	/** @var TrackingService */
	protected $trackingService;

	/**
	 * WhatsappService constructor.
	 *
	 * @since 1.6.95
	 * @param TrackingService $trackingService
	 */
	public function __construct( $trackingService ) {
		$this->trackingService = $trackingService;
		add_action( 'init', [ $this, 'init_integration' ] );
	}

	/**
	 * Initialize Whatsapp integration.
	 *
	 * @since 1.6.95
	 * @return void
	 */
	public function init_integration() {
		if ( function_exists( 'infixs_pingo_notify_app' ) ) {
			$this->setupNotification();
			add_filter( 'infixs_pingo_notify_woocommerce_order_placeholders', [ $this, 'add_placeholders' ], 10 );
			add_filter( 'infixs_pingo_notify_woocommerce_order_transform', [ $this, 'transform_data' ], 10, 2 );
		}
	}

	protected function setupNotification() {
		$notificationService = infixs_pingo_notify_app( \Infixs\PingoNotify\Services\NotificationService::class);

		$created = get_option( '_infixs_correios_automatico_whatsapp_created', 0 );
		if ( ! $created ) {
			update_option( '_infixs_correios_automatico_whatsapp_created', 1 );
			$notificationService->create( [
				'title' => 'Preparando para envio',
				'triggerId' => 'woocommerce_order_status_change',
				'connectionId' => '',
				'isActive' => false,
				'recipient' => '{{ order.full_phone }}',
				'messages' => [
					0 => [
						'text' => '<p>Olá <strong>{{ order.shipping.first_name }}</strong>,</p><p></p><p>Seu pedido de número <strong>#{{ order.number }} </strong>já está sendo preparado, aguarde enquanto nós cuidamos para você!</p><p></p><p>📦Produtos:</p><p>{{#each order.items}}</p><p>- {{ name }} x{{ quantity }} - {{ money total }}</p><p>{{ /each }}</p><p></p><p>Obrigado!</p><p>Equipe {{ site_name }}</p>',
					]
				],
				'metas' => [
					'status' => 'wc-preparing-to-ship'
				]
			] );

			$notificationService->create( [
				'title' => 'Em transporte',
				'triggerId' => 'woocommerce_order_status_change',
				'connectionId' => '',
				'isActive' => false,
				'recipient' => '{{ order.full_phone }}',
				'messages' => [
					0 => [
						'text' => '<p>Olá <strong>{{ order.shipping.first_name }}</strong>,</p><p></p><p>Sua encomenda foi <strong>enviada</strong> pelos <strong>Correios</strong>. Para acompanhar a entrega, use o(s) seguinte(s) códigos de rastreio:</p><p></p><p>🔍 Códigos:</p><p>{{#each order.correios_tracking_codes}}</p><p>- {{ code }}</p><p>{{/each}}</p><p></p><p>📦Produtos:</p><p>{{#each order.items}}</p><p>- {{ name }} x{{ quantity }} - {{ money total }}</p><p>{{ /each }}</p><p></p><p>Obrigado!</p><p>Equipe {{ site_name }}</p>',
					]
				],
				'metas' => [
					'status' => 'wc-in-transit'
				]
			] );

			$notificationService->create( [
				'title' => 'Entregue',
				'triggerId' => 'woocommerce_order_status_change',
				'connectionId' => '',
				'isActive' => false,
				'recipient' => '{{ order.full_phone }}',
				'messages' => [
					0 => [
						'text' => '<p>Olá <strong>{{ order.shipping.first_name }}</strong>,</p><p></p><p>Seu pedido foi entregue com sucesso! Obrigado por comprar conosco! 😉</p><p></p><p>Obrigado!</p><p>Equipe {{ site_name }}</p>',
					]
				],
				'metas' => [
					'status' => 'wc-delivered'
				]
			] );
		}
	}

	public function add_placeholders( $placeholders ) {
		$placeholders[] = [
			'path' => 'order.correios_tracking_codes',
			'name' => 'Correios Tracking Codes',
			'type' => 'array',
			'description' => 'An array of tracking codes associated with the order from Infixs Correios Automatico plugin.',
			'children' => [
				[
					'path' => 'code',
					'name' => 'Tracking Code',
					'type' => 'string',
					'description' => 'The tracking code for the shipment.',
				],
			],
		];

		$placeholders[] = [
			'path' => 'order.correios_service_id',
			'name' => 'Correios Service ID',
			'type' => 'string',
			'description' => 'The Correios service ID used for shipping the order from Infixs Correios Automatico plugin.',
		];

		$placeholders[] = [
			'path' => 'order.correios_service_name',
			'name' => 'Correios Service Name',
			'type' => 'string',
			'description' => 'The Correios service name used for shipping the order from Infixs Correios Automatico plugin.',
		];

		return $placeholders;
	}

	public function transform_data( $data, $order ) {

		$tracking_codes = $this->trackingService->getTrackings( $order->get_id() );

		$ca_order = Order::fromId( $order->get_id() );

		$shipping_product_code = $ca_order->getShippingProductCode();

		$data['order']['correios_tracking_codes'] = $tracking_codes->toArray();
		$data['order']['correios_service_code'] = $shipping_product_code;
		$data['order']['correios_service_id'] = DeliveryServiceCode::getCommonId( $shipping_product_code );
		$data['order']['correios_service_name'] = DeliveryServiceCode::getShortDescription( $shipping_product_code );

		return $data;
	}

}