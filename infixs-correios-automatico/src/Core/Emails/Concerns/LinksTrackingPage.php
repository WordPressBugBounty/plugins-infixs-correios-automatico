<?php

namespace Infixs\CorreiosAutomatico\Core\Emails\Concerns;

use Infixs\CorreiosAutomatico\Core\Support\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Links a tracking code to the place the customer follows the delivery.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
trait LinksTrackingPage {

	/**
	 * Get tracking code url.
	 *
	 * The code leads to the tracking page of the store when one is set and
	 * published, and to the order in the account of the customer otherwise.
	 *
	 * @since 1.0.0 In TrackingCodeEmail.
	 * @since 1.11.0 Moved here.
	 *
	 * @param  string $tracking_code Tracking code.
	 *
	 * @return string
	 */
	public function get_tracking_code_url( $tracking_code ) {
		$html = sprintf( '<a href="%s#wc-correios-tracking">%s</a>', $this->object->get_view_order_url(), $tracking_code );

		$tracking_page_id = Config::integer( 'general.tracking_page' );

		if ( $tracking_page_id && get_post_status( $tracking_page_id ) === 'publish' ) {
			$tracking_page_url = get_permalink( $tracking_page_id );

			$tracking_page_url = add_query_arg( 'code', $tracking_code, $tracking_page_url );

			$html = sprintf( '<a href="%s">%s</a>', $tracking_page_url, $tracking_code, $tracking_code );
		}

		return apply_filters( 'infixs_correios_automatico_email_tracking_code_url', $html, $tracking_code, $this->object );
	}
}
