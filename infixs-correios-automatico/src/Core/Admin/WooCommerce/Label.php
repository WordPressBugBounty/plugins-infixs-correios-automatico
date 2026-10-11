<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\LabelService;
use Infixs\CorreiosAutomatico\Services\PrepostService;

defined( 'ABSPATH' ) || exit;

/**
 * Label Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Label {

	/**
	 * Label Service instance.
	 *
	 * @since 1.0.0
	 * @var LabelService
	 */
	private $labelService;

	/**
	 * Prepost Service instance.
	 *
	 * @since 1.11.0
	 * @var PrepostService
	 */
	private $prepostService;

	/**
	 * Label constructor.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 Takes the prepost service, to tell a prepost sent with the NF-e of the store.
	 */
	public function __construct( LabelService $labelService, PrepostService $prepostService ) {
		$this->labelService = $labelService;
		$this->prepostService = $prepostService;
	}


	public function register_order_meta_box() {
		if ( ! Config::boolean( "general.show_order_label_form" ) )
			return;

		add_meta_box(
			'infixs-correios-automatico-label',
			'Etiqueta e Declaracão',
			[ $this, 'render_order_meta_box' ],
			WCIntegration::get_shop_order_screen(),
			'side',
			'high'
		);
	}

	/**
	 * Render the label meta box.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 No DC-e to print for a prepost sent with the NF-e of the store.
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
		$prepost = $prepost_id ? $this->prepostService->getPrepost( $prepost_id ) : null;
		$prepost_with_invoice = $prepost && $this->prepostService->hasStoreInvoiceOnly( $prepost );

		include INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-label-meta-box.php';

	}

}