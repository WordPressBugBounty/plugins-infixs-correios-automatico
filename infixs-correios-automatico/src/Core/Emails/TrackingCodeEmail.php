<?php

namespace Infixs\CorreiosAutomatico\Core\Emails;

use Infixs\CorreiosAutomatico\Core\Support\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Correios tracking code email.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class TrackingCodeEmail extends \WC_Email {

	/**
	 * Message.
	 *
	 * @var string
	 */
	public $message = '';

	/**
	 * Tracking message.
	 *
	 * @var string
	 */
	public $tracking_message = '';

	/**
	 * Initialize tracking template.
	 */
	public function __construct() {
		$this->id = 'correios_automatico_tracking_code_email';
		$this->title = __( 'Correios Automático - Em transporte', 'infixs-correios-automatico' );
		$this->customer_email = true;
		$this->description = __( 'Esse e-mail é enviado quando o pedido for postado.', 'infixs-correios-automatico' );
		$this->template_html = 'emails/tracking-code-email.php';
		$this->template_plain = 'emails/plain/tracking-code-email.php';
		$this->placeholders = [ 
			'{order_number}' => '',
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
		return __( 'Seu pedido #{order_number} foi enviado pelo Correios', 'infixs-correios-automatico' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Sua encomenda foi enviada', 'infixs-correios-automatico' );
	}

	/**
	 * Default tracking message content.
	 *
	 * @return string
	 */
	public function get_default_tracking_message() {
		return __( 'Olá. Sua encomenda do site {site_title} foi enviada pelos Correios.', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'Para acompanhar a entrega, use o(s) seguinte(s) códigos de rastreio: {tracking_code}', 'infixs-correios-automatico' )
			. PHP_EOL . ' ' . PHP_EOL
			. __( 'A entrega agora está em responsabilidade dos correios, mas se tiver alguma dúvida, entre em contato conosco.', 'infixs-correios-automatico' );
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
			'tracking_message' => [ 
				'title' => __( 'Conteúdo do e-mail', 'infixs-correios-automatico' ),
				'type' => 'textarea',
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_tracking_message(),
				'default' => $this->get_default_tracking_message(),
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
	 * Get email tracking message.
	 *
	 * @return string
	 */
	public function get_tracking_message() {
		$message = $this->get_option( 'tracking_message', $this->get_default_tracking_message() );

		return apply_filters( 'infixs_correios_automatico_email_tracking_message', $this->format_string( $message ), $this->object );
	}

	/**
	 * Get tracking code url.
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

	/**
	 * Get tracking codes HTML.
	 *
	 * @param  array $tracking_codes Tracking codes.
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

		return $this->get_tracking_code_url( $tracking_codes[0] );
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
	public function trigger( $order_id, $tracking_codes ) {

		if ( ! is_array( $tracking_codes ) ) {
			$tracking_codes = [ $tracking_codes ];
		}

		if ( empty( $tracking_codes ) ) {
			return false;
		}

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
			$this->placeholders['{tracking_code}'] = $this->get_tracking_codes( $tracking_codes );
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
				'tracking_message' => $this->get_tracking_message(),
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
		$message = $this->get_tracking_message();
		$message = str_replace( '<ul>', "\n", $message );
		$message = str_replace( '<li>', "\n - ", $message );
		$message = str_replace( [ '</ul>', '</li>' ], '', $message );

		wc_get_template(
			$this->template_plain,
			[ 
				'order' => $this->object,
				'email_heading' => $this->get_heading(),
				'tracking_message' => $message,
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
