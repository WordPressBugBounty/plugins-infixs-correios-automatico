<?php

namespace Infixs\CorreiosAutomatico\Core\Front\WooCommerce;

use Infixs\CorreiosAutomatico\Services\TrackingService;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Tracking Page
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.5.9
 */
class TrackingView {

	/**
	 * Tracking Service
	 *
	 * @var TrackingService
	 */
	private $trackingService;

	public function __construct( TrackingService $trackingService ) {
		$this->trackingService = $trackingService;
		add_shortcode( 'infixs_correios_automatico_tracking_view', [ $this, 'tracking_view_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

	}

	public function enqueue_scripts() {
		global $posts;

		if ( empty( $posts ) ) {
			return;
		}

		$found = false;

		foreach ( $posts as $post ) {
			if ( has_shortcode( $post->post_content, 'infixs_correios_automatico_tracking_view' ) ) {
				$found = true;
				break;
			}
		}

		if ( $found ) {
			wp_enqueue_script(
				'infixs-correios-automatico-tracking-component',
				\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/components/tracking/tracking.js',
				[ 'jquery' ],
				filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/components/tracking/tracking.js' ),
				true
			);

			wp_enqueue_style(
				'infixs-correios-automatico-tracking-component',
				\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_URL . 'assets/components/tracking/tracking.css',
				[],
				filemtime( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'assets/components/tracking/tracking.css' ),
			);
		}
	}

	public function tracking_view_shortcode() {
		ob_start();


		$template = 'infixs-tracking-view.php';

		$objects = [];

		$object_code = isset( $_GET['code'] ) ? sanitize_text_field( trim( $_GET['code'] ) ) : '';

		$object = $this->trackingService->getObjectTrackingByCode( $object_code, true );

		if ( ! is_wp_error( $object ) ) {
			$objects[] = $object;
		}

		wc_get_template(
			$template,
			[ 
				'objects' => $objects,
			],
			'infixs-correios-automatico/',
			\INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'templates/'
		);


		return ob_get_clean();
	}
}