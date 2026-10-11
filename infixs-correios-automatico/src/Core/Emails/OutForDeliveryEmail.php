<?php

namespace Infixs\CorreiosAutomatico\Core\Emails;

use Infixs\CorreiosAutomatico\Core\Emails\Concerns\LinksTrackingPage;
use Infixs\CorreiosAutomatico\Core\Emails\Concerns\ListsTrackingCodes;

defined( 'ABSPATH' ) || exit;

/**
 * Correios out for delivery email.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class OutForDeliveryEmail extends \WC_Email {
	use ListsTrackingCodes, LinksTrackingPage;

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
		$this->id = 'correios_automatico_out_for_delivery_email';
		$this->title = __( 'Correios Automático - Saiu para entrega', 'infixs-correios-automatico' );
		$this->customer_email = true;
		$this->description = __( 'Esse e-mail é enviado quando a encomenda sai para entrega ao destinatário.', 'infixs-correios-automatico' );
		$this->template_html = 'emails/out-for-delivery-email.php';
		$this->template_plain = 'emails/plain/out-for-delivery-email.php';
		$this->placeholders = [ 
			'{order_number}' => '',
			'{customer_firstname}' => '',
			'{order_date}' => '',
			'{tracking_code}' => '',
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
		return __( 'Seu pedido #{order_number} saiu para entrega', 'infixs-correios-automatico' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Sua encomenda saiu para entrega', 'infixs-correios-automatico' );
	}

	/**
	 * Default message content.
	 *
	 * @return string
	 */
	public function get_default_message() {
		return __( 'Olá. Sua encomenda do site {site_title} saiu para entrega e deve chegar em breve ao endereço informado no pedido.', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'Código de rastreio: {tracking_code}', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'É importante ter alguém no local para receber a encomenda. Qualquer dúvida, entre em contato conosco.', 'infixs-correios-automatico' );
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
				'title' => __( 'Tipo de e-mail', 'infixs-correios-automatico' ),
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
	 * Get email message.
	 *
	 * An order changed by hand may have no tracking code at all. The lines
	 * written to show the code go away with it, so the customer does not read
	 * a label followed by nothing.
	 *
	 * @return string
	 */
	public function get_message() {
		$message = $this->get_option( 'message', $this->get_default_message() );

		if ( '' === $this->placeholders['{tracking_code}'] ) {
			$message = $this->without_tracking_code_lines( $message );
		}

		return apply_filters( 'infixs_correios_automatico_email_out_for_delivery_message', $this->format_string( $message ), $this->object );
	}

	/**
	 * Remove the lines of a message that carry the tracking code placeholder.
	 *
	 * @param string $message Message as saved in the settings.
	 *
	 * @return string
	 */
	protected function without_tracking_code_lines( $message ) {
		$lines = array_filter( preg_split( '/\R/u', $message ), function ( $line ) {
			return false === strpos( $line, '{tracking_code}' );
		} );

		return implode( PHP_EOL, $lines );
	}

	/**
	 * Trigger email.
	 *
	 * @param int             $order_id       Order ID.
	 * @param string|string[] $tracking_codes Codes of the objects out for delivery, none when the order has no code.
	 *
	 * @return bool
	 */
	public function trigger( $order_id, $tracking_codes = [] ) {
		$tracking_codes = array_values( array_filter( (array) $tracking_codes ) );

		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		$this->object = $order;
		$this->recipient = $this->object->get_billing_email();

		$this->placeholders['{order_number}'] = $this->object->get_order_number();
		$this->placeholders['{customer_firstname}'] = $this->object->get_shipping_first_name();
		$this->placeholders['{order_date}'] = wc_format_datetime( $this->object->get_date_created() );
		$this->placeholders['{date}'] = date_i18n( wc_date_format(), time() );
		$this->placeholders['{tracking_code}'] = $tracking_codes ? $this->get_tracking_codes( $tracking_codes ) : '';

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

		$message = $this->format_plain_tracking_codes( $this->get_message() );

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
