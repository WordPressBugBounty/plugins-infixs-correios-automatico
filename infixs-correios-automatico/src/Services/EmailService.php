<?php

namespace Infixs\CorreiosAutomatico\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Email service.
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class EmailService {

	/**
	 * Send email.
	 * 
	 * This method is responsible for sending an email.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $to Email recipient.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * 
	 * @return bool True on success, false on failure.
	 */
	public function send_template( $to, $subject, $template_name, $data = [] ) {
		if ( ! file_exists( \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . "/templates/emails/{$template_name}.php" ) ) {
			return false;
		}

		$mailer = WC()->mailer();
		$content = $this->get_template( $mailer, $subject, $template_name, $data );
		$email_html = $mailer->wrap_message( $subject, $content );
		$headers = "Content-Type: text/html\r\n";
		return $mailer->send( $to, $subject, $email_html, $headers );
	}

	private function get_template( $mailer, $email_heading = false, $template_name, $data = [] ) {
		$template = "emails/$template_name.php";

		return wc_get_template_html( $template, array_merge( [ 
			'email_heading' => $email_heading,
			'sent_to_admin' => false,
			'plain_text' => false,
			'email' => $mailer
		], $data ), '', \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . "/templates/" );
	}
}