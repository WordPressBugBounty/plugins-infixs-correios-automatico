<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Emails\PreparingToShipEmail;
use Infixs\CorreiosAutomatico\Core\Emails\ReturningEmail;
use Infixs\CorreiosAutomatico\Core\Emails\TrackingCodeEmail;
use Infixs\CorreiosAutomatico\Core\Emails\WaitingPickupEmail;

defined( 'ABSPATH' ) || exit;

/**
 * Email Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.3.4
 */
class Email {

	public function __construct() {
		add_filter( 'woocommerce_email_classes', [ $this, 'include_emails' ] );
	}

	/**
	 * Include emails.
	 *
	 * @param  array $emails Default emails.
	 *
	 * @return array
	 */
	public function include_emails( $emails ) {
		if ( ! isset( $emails['Correios_Automatico_Preparing_To_Ship_Email'] ) ) {
			$emails['Correios_Automatico_Preparing_To_Ship_Email'] = new PreparingToShipEmail();
		}

		if ( ! isset( $emails['Correios_Automatico_Tracking_Code_Email'] ) ) {
			$emails['Correios_Automatico_Tracking_Code_Email'] = new TrackingCodeEmail();
		}

		if ( ! isset( $emails['Correios_Automatico_Waiting_Pickup_Email'] ) ) {
			$emails['Correios_Automatico_Waiting_Pickup_Email'] = new WaitingPickupEmail();
		}

		if ( ! isset( $emails['Correios_Automatico_Returning_Email'] ) ) {
			$emails['Correios_Automatico_Returning_Email'] = new ReturningEmail();
		}

		return $emails;
	}

}