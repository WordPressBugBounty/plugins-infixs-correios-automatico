<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Notices;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Notice;
use Infixs\CorreiosAutomatico\Core\Support\Plugin;

defined( 'ABSPATH' ) || exit;

class ShippingMethod extends Notice {
	public function __construct() {
		$this->id = 'infixs-correios-automatico-shipping-method-notice';
		$this->title = __( 'Adicione um método de entrega', 'infixs-correios-automatico' );
		$this->message = sprintf( __( 'Para começar a usar o plug-in, adicione pelo menos um método de entrega dos Correios Automático, em WooCommerce -> Configurações -> Entregas, edite a Zona de entrega e adicione o método ou então use o assistente de configuração.', 'infixs-correios-automatico' ) );
		$this->type = 'info';
		$this->dismissDuration = MINUTE_IN_SECONDS;
		$this->buttons = [ 
			[ 
				'text' => __( 'Adicionar manualmente', 'infixs-correios-automatico' ),
				'type' => 'secondary',
				'url' => admin_url( 'admin.php?page=wc-settings&tab=shipping' )
			],
			[ 
				'text' => __( 'Assistente de configuração', 'infixs-correios-automatico' ),
				'url' => admin_url( 'admin.php?page=infixs-correios-automatico&path=/starter' )
			],
		];
	}
	public function shouldDisplay() {
		return ! Container::shippingService()->hasCorreiosAutomaticoActiveMethods();
	}
}