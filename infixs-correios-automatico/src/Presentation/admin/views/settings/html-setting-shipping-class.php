<?php
/**
 * HTML Shipping Class Render Input
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.3.1
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="view">{{ data.infixs_additional_days }}</div>

<div class="edit">
	<input type="number" name="infixs_additional_days" data-attribute="infixs_additional_days"
		value="{{ data.infixs_additional_days }}" placeholder="0" />
	<div class="wc-shipping-class-modal-help-text">
		<?php esc_html_e( 'Adiciona dias ao prazo de entrega no cálculo do frete, somando-os ao prazo real (Correios Automático)', 'infixs-correios-automatico' ); ?>
	</div>
</div>