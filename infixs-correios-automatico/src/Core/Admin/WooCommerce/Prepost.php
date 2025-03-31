<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Router;
use Infixs\CorreiosAutomatico\Services\PrepostService;
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
	 * Prepost constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct( PrepostService $prepostService ) {
		$this->prepostService = $prepostService;
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
		$prepost_id = $order->get_meta( '_infixs_correios_automatico_prepost_id' );
		$prepost = $this->prepostService->getPrepost( $prepost_id );
		$has_prepost = $prepost ? true : false;
		$cancel_prepost_url = Router::resolve( '/prepost', [ 'prepost_id' => $prepost_id ] );
		$is_correios_automatico = $order->has_shipping_method( 'infixs-correios-automatico' );

		include_once INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-prepost-meta-box.php';

	}

}