<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Notices;

use Infixs\CorreiosAutomatico\Core\Admin\SquidCorePlugin;
use Infixs\CorreiosAutomatico\Core\Support\Notice;
use Infixs\CorreiosAutomatico\Core\Support\Router;

defined( 'ABSPATH' ) || exit;

/**
 * NF-e required of ICMS taxpayers since 2 November 2026, with the SquidCore ERP to issue it.
 *
 * Shown until the SquidCore is active.
 *
 * @since 1.14.0
 */
class InvoiceRequiredNotice extends Notice {
	public function __construct() {
		$this->id = 'infixs-correios-automatico-invoice-required-icms';
		$this->title = __( 'NF-e para contribuintes de ICMS', 'infixs-correios-automatico' );
		$this->message = __( 'Está com erro ou recusa na pré-postagem? Esse pode ser o motivo: desde 2 de novembro de 2026, as empresas contribuintes de ICMS (com inscrição estadual) precisam enviar com NF-e, no lugar da DC-e. Quem não é contribuinte continua com a DC-e. O SquidCore ERP emite a NF-e de cada pedido e já faz a pré-postagem com ela, automaticamente.', 'infixs-correios-automatico' );
		$this->type = 'warning';
		$this->buttons = [
			[
				'text' => __( 'Conhecer o SquidCore', 'infixs-correios-automatico' ),
				'url' => Router::resolve( '/config/integrations/squidcore' ),
			],
			[
				'text' => __( 'Ver vídeo', 'infixs-correios-automatico' ),
				'url' => '',
				'video' => 'squidcore',
			],
		];
	}

	public function shouldDisplay() {
		$squidcore = SquidCorePlugin::status();

		return ! $squidcore['active'];
	}
}
