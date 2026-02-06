<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Notices;

use Infixs\CorreiosAutomatico\Core\Support\Notice;
use Infixs\CorreiosAutomatico\Core\Support\Plugin;

defined( 'ABSPATH' ) || exit;

class PingoNotifyNotice extends Notice {
	public function __construct() {
		$url = admin_url( 'plugin-install.php?tab=search&s=infixs+pingo+notify&type=term' );

		$this->id = 'infixs-correios-automatico-install-pingo-notify';
		$this->title = __( 'Integre as Notificações com WhatsApp', 'infixs-correios-automatico' );
		$this->message = sprintf( __( 'Baixe o plugin Pingo Notify para integrar notificações via WhatsApp e avisar seus clientes com atualizações em tempo real da encomenda.', 'infixs-correios-automatico' ) );
		$this->type = 'info';
		$this->dismissDuration = MONTH_IN_SECONDS;
		$this->buttons = [
			[
				'text' => __( 'Instalar', 'infixs-correios-automatico' ),
				'url' => $url,
				'target' => '_blank',
			],
		];
	}
	public function shouldDisplay() {
		if ( class_exists( 'Infixs\PingoNotify\Services\NotificationService' ) ) {
			return false;
		}
		return true;
	}
}