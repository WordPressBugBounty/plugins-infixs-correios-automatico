<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\LabelService;

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
	 * Label constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct( LabelService $labelService ) {
		$this->labelService = $labelService;
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
	 * Render the tracking code meta box.
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

		include_once INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-label-meta-box.php';

	}

}