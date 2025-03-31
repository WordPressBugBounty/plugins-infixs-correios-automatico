<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Notices;

use Infixs\CorreiosAutomatico\Core\Support\Notice;

defined( 'ABSPATH' ) || exit;

class PluginDeactivation extends Notice {
	public function __construct() {
		$this->id = 'infixs-correios-automatico-plugin-deactivation';
		$this->title = __( 'Desative o plugin "Calculadora de Frete otimizada no carrinho"', 'infixs-correios-automatico' );
		$this->message = sprintf( __( 'O plugin dos Correios Automático já oferece os mesmos recursos que o plugin "Calculadora de Frete otimizada no carrinho", desative-o para evitar conflitos.', 'infixs-correios-automatico' ) );
		$this->type = 'info';
		$this->dismissDuration = MONTH_IN_SECONDS;
		$this->buttons = [ 
			[ 
				'text' => __( 'Ir para Plugins', 'infixs-correios-automatico' ),
				'url' => admin_url( 'plugins.php' ),
			],
		];
	}
	public function shouldDisplay() {
		if ( class_exists( 'WC_Shipping_Calculator_Improvements' ) ) {
			return true;
		}
		return false;
	}
}