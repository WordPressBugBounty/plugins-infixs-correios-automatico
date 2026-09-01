<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Notices;

use Infixs\CorreiosAutomatico\Core\Support\Notice;

defined( 'ABSPATH' ) || exit;

class RequiredPluginNotice extends Notice {

	/**
	 * Página do produto que adiciona os campos brasileiros ao checkout.
	 *
	 * @var string
	 */
	const CHECKOUT_FIELDS_URL = 'https://infixs.io/product/infixs-checkout-fields-campos-brasileiros/';

	/**
	 * Plugin de campos brasileiros de terceiro que a loja pode já usar.
	 *
	 * @var string
	 */
	const LEGACY_PLUGIN = 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php';

	public function __construct() {
		$this->dismissDuration = DAY_IN_SECONDS;
		$this->buttons = [ 
			[ 
				'text' => __( 'Obter', 'infixs-correios-automatico' ),
				'url' => self::CHECKOUT_FIELDS_URL,
				'target' => '_blank',
			],
		];

		if ( $this->hasLegacyPlugin() ) {
			$this->id = 'infixs-correios-automatico-replace-checkout-fields';
			$this->title = __( 'Troque o plugin de campos brasileiros', 'infixs-correios-automatico' );
			$this->message = __( 'O plugin de campos brasileiros da sua loja está defasado: passou anos sem atualização e não acompanha as versões atuais do WordPress e do WooCommerce. Recomendamos fortemente migrar para o Infixs Checkout Fields, mantido pela mesma equipe do Correios Automático.', 'infixs-correios-automatico' );
			$this->type = 'warning';
			$this->buttons[0]['text'] = __( 'Conhecer o Infixs Checkout Fields', 'infixs-correios-automatico' );

			return;
		}

		$this->id = 'infixs-correios-automatico-required-plugin';
		$this->title = __( 'Plugin recomendado', 'infixs-correios-automatico' );
		$this->message = __( 'Para que os recursos do Correios Automático funcionem corretamente, instale o Infixs Checkout Fields e tenha os campos brasileiros (CPF/CNPJ, número e bairro) no seu checkout.', 'infixs-correios-automatico' );
		$this->type = 'info';
	}

	public function shouldDisplay() {
		return ! defined( 'INFIXS_CHECKOUT_FIELDS_FILE_NAME' );
	}

	/**
	 * Se a loja usa o plugin de campos brasileiros de terceiro que substituímos.
	 *
	 * @since 1.10.4
	 *
	 * @return bool
	 */
	private function hasLegacyPlugin() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( self::LEGACY_PLUGIN );
	}
}
