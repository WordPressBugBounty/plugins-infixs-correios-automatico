<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Notices;

use Infixs\CorreiosAutomatico\Core\Support\Notice;
use Infixs\CorreiosAutomatico\Core\Support\Plugin;

defined( 'ABSPATH' ) || exit;

class BuyProNotice extends Notice {
	public function __construct() {
		$this->id = 'infixs-correios-automatico-buy-pro';
		$this->title = __( 'Upgrade para a versão Pro', 'infixs-correios-automatico' );
		$this->message = sprintf( __( 'Desbloqueie recursos avançados e suporte premium com a versão Pro do Correio Automático.', 'infixs-correios-automatico' ) );
		$this->type = 'info';
		$this->dismissDuration = MONTH_IN_SECONDS;
		$this->buttons = [ 
			[ 
				'text' => __( 'Upgrade', 'infixs-correios-automatico' ),
				'url' => Plugin::PRO_URL,
				'target' => '_blank',
			],
		];
	}
	public function shouldDisplay() {
		if ( class_exists( 'Infixs\CorreiosAutomaticoPro\Core\Core' ) ) {
			return false;
		}
		return true;
	}
}