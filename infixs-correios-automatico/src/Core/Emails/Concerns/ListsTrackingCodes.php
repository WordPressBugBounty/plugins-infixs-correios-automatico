<?php

namespace Infixs\CorreiosAutomatico\Core\Emails\Concerns;

defined( 'ABSPATH' ) || exit;

/**
 * Lists the codes an e-mail tells the customer, as HTML and as plain text.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
trait ListsTrackingCodes {

	/**
	 * Get the link of a single code.
	 *
	 * @param string $tracking_code Tracking code.
	 *
	 * @return string
	 */
	abstract public function get_tracking_code_url( $tracking_code );

	/**
	 * Get tracking codes HTML.
	 *
	 * A single code is written as its link, and several codes as a list of
	 * links.
	 *
	 * @since 1.0.0 In TrackingCodeEmail.
	 * @since 1.11.0 Moved here.
	 *
	 * @param string[] $tracking_codes Tracking codes, at least one.
	 *
	 * @return string
	 */
	public function get_tracking_codes( $tracking_codes ) {
		if ( 1 < count( $tracking_codes ) ) {
			$html = '<ul>';
			foreach ( $tracking_codes as $tracking_code ) {
				$html .= '<li>' . $this->get_tracking_code_url( $tracking_code ) . '</li>';
			}
			$html .= '</ul>';

			return $html;
		}

		return $this->get_tracking_code_url( reset( $tracking_codes ) );
	}

	/**
	 * Turn a message carrying the codes into plain text.
	 *
	 * Each code of a list goes on a line of its own, and every link is reduced
	 * to the code it shows, so the plain text e-mail does not print its markup.
	 *
	 * @since 1.11.0
	 *
	 * @param string $message Message with the codes as HTML.
	 *
	 * @return string
	 */
	protected function format_plain_tracking_codes( $message ) {
		$message = str_replace( '<ul>', "\n", $message );
		$message = str_replace( '<li>', "\n - ", $message );
		$message = str_replace( [ '</ul>', '</li>' ], '', $message );

		return wp_strip_all_tags( $message );
	}
}
