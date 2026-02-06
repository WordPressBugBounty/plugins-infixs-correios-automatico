<?php

namespace Infixs\CorreiosAutomatico\Core\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Delivered email.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.6.95
 */
class DeliveredEmail extends \WC_Email {

	/**
	 * Message.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Initialize template.
	 */
	public function __construct() {
		$this->id = 'correios_automatico_delivered_email';
		$this->title = __( 'Correios Automático - Pedido Entregue', 'infixs-correios-automatico' );
		$this->customer_email = true;
		$this->description = __( 'Esse e-mail é enviado quando o pedido for entregue.', 'infixs-correios-automatico' );
		$this->template_html = 'emails/delivered-email.php';
		$this->template_plain = 'emails/plain/delivered-email.php';
		$this->placeholders = [
			'{order_number}' => '',
			'{customer_firstname}' => '',
			'{order_date}' => '',
			'{date}' => '',
		];

		// Call parent constructor.
		parent::__construct();

		$this->template_base = \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'templates/';
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Seu pedido #{order_number} foi entregue', 'infixs-correios-automatico' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Pedido Entregue', 'infixs-correios-automatico' );
	}

	/**
	 * Default message content.
	 *
	 * @return string
	 */
	public function get_default_message() {
		return __( 'Sua encomenda do site {site_title} foi entregue com sucesso.', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'Agradecemos a preferência. Esperamos você novamente em breve.', 'infixs-correios-automatico' );
	}

	/**
	 * Initialise settings form fields.
	 */
	public function init_form_fields() {
		/* translators: %s: list of placeholders */
		$placeholder_text = sprintf( __( 'Códigos disponíveis: %s', 'infixs-correios-automatico' ), '<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>' );

		$this->form_fields = [
			'enabled' => [
				'title' => __( 'Ativar/Desativar', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'label' => __( 'Enable this email notification', 'infixs-correios-automatico' ),
				'default' => 'yes',
			],
			'subject' => [
				'title' => __( 'Assunto', 'infixs-correios-automatico' ),
				'type' => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default' => $this->get_default_subject(),
				'desc_tip' => true,
			],
			'heading' => [
				'title' => __( 'Cabeçalho', 'infixs-correios-automatico' ),
				'type' => 'text',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default' => $this->get_default_heading(),
				'desc_tip' => true,
			],
			'message' => [
				'title' => __( 'Conteúdo do e-mail', 'infixs-correios-automatico' ),
				'type' => 'textarea',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_message(),
				'default' => $this->get_default_message(),
				'desc_tip' => true,
			],
			'email_type' => [
				'title' => __( 'Tipo de email', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Escolha o formato que deseja enviar.', 'infixs-correios-automatico' ),
				'default' => 'html',
				'class' => 'email_type wc-enhanced-select',
				'options' => $this->get_email_type_options(),
				'desc_tip' => true,
			],
		];
	}

	/**
	 * Get email delivered message.
	 *
	 * @return string
	 */
	public function get_message() {
		$message = $this->get_option( 'message', $this->get_default_message() );

		return apply_filters( 'infixs_correios_automatico_email_delivered_message', $this->format_string( $message ), $this->object );
	}

	/**
	 * Trigger email.
	 *
	 * @param  int      $order_id      Order ID.
	 * @param  \WC_Order $order         Order data.
	 * 
	 * @return bool
	 */
	public function trigger( $order_id ) {

		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		if ( is_object( $order ) ) {
			$this->object = $order;
			$this->recipient = $this->object->get_billing_email();

			$this->placeholders['{order_number}'] = $this->object->get_order_number();
			$this->placeholders['{order_date}'] = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{date}'] = date_i18n( wc_date_format(), time() );
			$this->placeholders['{customer_firstname}'] = $order->get_shipping_first_name();
		}

		if ( ! $this->get_recipient() ) {
			return false;
		}

		return $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html() {
		ob_start();

		wc_get_template(
			$this->template_html,
			[
				'order' => $this->object,
				'email_heading' => $this->get_heading(),
				'message' => $this->get_message(),
				'sent_to_admin' => false,
				'plain_text' => false,
				'email' => $this,
			],
			'',
			$this->template_base
		);

		return ob_get_clean();
	}

	/**
	 * Get content plain text.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();

		// Format list.
		$message = $this->get_message();
		$message = str_replace( '<ul>', "\n", $message );
		$message = str_replace( '<li>', "\n - ", $message );
		$message = str_replace( [ '</ul>', '</li>' ], '', $message );

		wc_get_template(
			$this->template_plain,
			[
				'order' => $this->object,
				'email_heading' => $this->get_heading(),
				'message' => $message,
				'sent_to_admin' => false,
				'plain_text' => true,
				'email' => $this,
			],
			'',
			$this->template_base
		);

		return ob_get_clean();
	}
}
