<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Router;
use Infixs\CorreiosAutomatico\Entities\Order as OrderEntity;
use Infixs\CorreiosAutomatico\Services\OrderInvoiceResolver;
use Infixs\CorreiosAutomatico\Services\PrepostService;
use Infixs\CorreiosAutomatico\Services\ShipmentContextResolver;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Prepost Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Prepost {

	/**
	 * Prepost service instance.
	 *
	 * @since 1.0.0
	 * @var PrepostService
	 */
	private $prepostService;

	/**
	 * Shipment context resolver.
	 *
	 * @since 1.11.0
	 * @var ShipmentContextResolver
	 */
	private $shipmentContextResolver;

	/**
	 * Finds the NF-e of the order, to fill the invoice form.
	 *
	 * @since 1.11.0
	 * @var OrderInvoiceResolver
	 */
	private $orderInvoiceResolver;

	/**
	 * Prepost constructor.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 Receives the shipment context resolver.
	 * @since 1.11.0 Receives the order invoice resolver.
	 *
	 * @param PrepostService          $prepostService          Prepost service.
	 * @param ShipmentContextResolver $shipmentContextResolver Tells whether the order was shipped in volumes.
	 * @param OrderInvoiceResolver    $orderInvoiceResolver    Finds the NF-e of the order.
	 */
	public function __construct( PrepostService $prepostService, ShipmentContextResolver $shipmentContextResolver, OrderInvoiceResolver $orderInvoiceResolver ) {
		$this->prepostService = $prepostService;
		$this->shipmentContextResolver = $shipmentContextResolver;
		$this->orderInvoiceResolver = $orderInvoiceResolver;
	}


	public function register_order_meta_box() {
		if ( ! Config::boolean( "general.show_order_prepost_form" ) )
			return;

		add_meta_box(
			'infixs-correios-automatico-prepost',
			'Pré-Postagem',
			[ $this, 'render_order_meta_box' ],
			WCIntegration::get_shop_order_screen(),
			'side',
			'high'
		);
	}

	/**
	 * Render the prepost meta box.
	 *
	 * An order shipped in volumes lists the prepost of each volume and leaves
	 * the creation to the dashboard: the buttons of the box prepost the order
	 * as a whole, which would declare every volume in a single object.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 Lists the prepost of each volume.
	 * @since 1.11.0 Fills the invoice form with the NF-e of the order and tells
	 *               when the automatic prepost waits for it.
	 *
	 * @param \WP_Post|\WC_Order $post
	 * @return void
	 */
	public function render_order_meta_box( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order->ID );
		}

		if ( ! $order )
			return;

		$order_id = $order->get_id();
		$prepost_id = $order->get_meta( '_infixs_correios_automatico_prepost_id' );
		$prepost = $this->prepostService->getPrepost( $prepost_id );
		$has_prepost = $prepost ? true : false;
		$cancel_prepost_url = Router::resolve( '/prepost', [ 'prepost_id' => $prepost_id ] );
		$is_correios_automatico = $order->has_shipping_method( 'infixs-correios-automatico' );
		$volume_rows = $this->getVolumeRows( new OrderEntity( $order ) );
		$dashboard_url = Router::resolve( '/order', [ 'search' => $order_id ] );
		$invoice = $this->orderInvoiceResolver->resolve( $order, [ 'for' => 'display' ] );
		$waiting_invoice = $this->orderInvoiceResolver->isWaiting( $order );

		include_once INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-prepost-meta-box.php';

	}

	/**
	 * Build the rows the meta box lists for an order shipped in volumes.
	 *
	 * @since 1.11.0
	 *
	 * @param OrderEntity $order Order being rendered.
	 *
	 * @return array<int, array{name: string, status: string, url: string}> Empty when the order was not split.
	 */
	private function getVolumeRows( $order ) {
		if ( ! $this->shipmentContextResolver->hasVolumes( $order ) ) {
			return [];
		}

		$rows = [];

		foreach ( $this->prepostService->getShipmentPreposts( $order ) as $shipment ) {
			$shipping_item = $shipment['shipping_item'];
			$prepost = $shipment['prepost'];

			$rows[] = [
				'name' => $shipping_item['volume_total'] > 1
					? sprintf( 'Volume %d de %d', $shipping_item['volume_index'], $shipping_item['volume_total'] )
					: $shipping_item['method_title'],
				'status' => $prepost ? (string) $prepost->status_label : 'Sem pré-postagem',
				'url' => $prepost ? Router::resolve( '/prepost', [ 'prepost_id' => $prepost->id ] ) : '',
			];
		}

		return $rows;
	}

}
