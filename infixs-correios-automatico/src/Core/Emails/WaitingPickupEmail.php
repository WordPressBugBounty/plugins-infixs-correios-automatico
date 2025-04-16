<?php

namespace Infixs\CorreiosAutomatico\Core\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Waiting Pickup email.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.4.4
 */
class WaitingPickupEmail extends \WC_Email {

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
		$this->id = 'correios_automatico_waiting_pickup_email';
		$this->title = __( 'Correios Automático - Aguardando retirada', 'infixs-correios-automatico' );
		$this->customer_email = true;
		$this->description = __( 'Esse e-mail é enviado quando a encomenda precisa ser retirada na agência.', 'infixs-correios-automatico' );
		$this->template_html = 'emails/waiting-pickup-email.php';
		$this->template_plain = 'emails/plain/waiting-pickup-email.php';
		$this->placeholders = [ 
			'{order_number}' => '',
			'{order_date}' => '',
			'{pickup_address}' => '',
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
		return __( 'Seu pedido #{order_number} está aguardando retirada nos Correios', 'infixs-correios-automatico' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Aguardando retirada', 'infixs-correios-automatico' );
	}

	/**
	 * Default message content.
	 *
	 * @return string
	 */
	public function get_default_message() {
		return __( 'Olá. Sua encomenda do site {site_title} está aguardando a retirada na agência dos correios.', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'Segue o endereço para retirada do objeto {tracking_code}:', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( '{pickup_address}', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'Qualquer dúvida entre em contato conosco.', 'infixs-correios-automatico' );
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
	 * @return string
	 */
	public function get_message() {
		$message = $this->get_option( 'message', $this->get_default_message() );

		return apply_filters( 'infixs_correios_automatico_email_waiting_pickup_message', $this->format_string( $message ), $this->object );
	}

	/**
	 * Get tracking code url.
	 *
	 * @param string $tracking_code Tracking code.
	 *
	 * @return string
	 */
	public function get_tracking_code_url( $tracking_code ) {
		$html = sprintf( '<a href="%s#wc-correios-tracking">%s</a>', $this->object->get_view_order_url(), $tracking_code );

		return apply_filters( 'infixs_correios_automatico_email_tracking_code_url', $html, $tracking_code, $this->object );
	}

	/**
	 * Trigger email.
	 *
	 * @param  int      $order_id      Order ID.
	 * @param  \WC_Order $order         Order data.
	 * @param  array|string   $tracking_codes Tracking code.
	 * 
	 * @return bool
	 */
	public function trigger( $order_id, $tracking_code, $pickup_address ) {

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
			;

			$this->placeholders['{order_number}'] = $this->object->get_order_number();
			$this->placeholders['{order_date}'] = wc_format_datetime( $this->object->get_date_created() );
			$this->placeholders['{date}'] = date_i18n( wc_date_format(), time() );
			$this->placeholders['{tracking_code}'] = $this->get_tracking_code_url( $tracking_code );
			$this->placeholders['{pickup_address}'] = $pickup_address;
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
