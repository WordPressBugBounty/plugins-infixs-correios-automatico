<?php
/**
 * Tracking Code HTML
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="infixs-correios-automatico-tracking-box">
	<input type="text" name="infixs_correios_automatico_tracking_code"
		id="infixs-correios-automatico-tracking-code-input" placeholder="Adicione o código de rastreamento" />
	<fieldset>
		<label for="infixs-correios-automatico-tracking-code-email-sendmail">
			<input type="checkbox" name="infixs_correios_automatico_tracking_code_sendmail"
				id="infixs-correios-automatico-tracking-code-email-sendmail" value="1" />
			Enviar email para o cliente
		</label>
		<span class="woocommerce-help-tip"
			data-tip="Se marcado, ao clicar em adicionar código, um email será enviado para o cliente com o código de rastreio."></span>
	</fieldset>

	<input type="hidden" name="infixs_correios_automatico_order_id" id="infixs-correios-automatico-order-id"
		value="<?php echo esc_attr( $order_id ) ?>" />
	<button style="margin-top: 10px;"
		class="button button-primary infixs-correios-automatico-add-tracking-code">Adicionar código</button>
	<div class="infixs-correios-automatico-history-table">
		<div>Código</div>
		<div class="infixs-correios-automatico-header-action-column">Ações</div>
		<?php foreach ( $trackings as $tracking ) : ?>
			<div>
				<a href="#" class="infixs-correios-automatico-tracking-code-modal-view"
					data-order-id="<?php echo esc_attr( $order_id ); ?>" data-tracking-codes="<?php echo esc_attr( wp_json_encode( [ 
						 	[ 
						 		'id' => $tracking->id,
						 		'code' => $tracking->code,
						 	]
						 ] ) ); ?>">
					<?php echo esc_html( $tracking->code ); ?>
				</a>
			</div>
			<div class="infixs-correios-automatico-action-column">
				<a href="#" class="infixs-correios-automatico-tracking-code-modal-view"
					data-order-id="<?php echo esc_attr( $order_id ); ?>" data-tracking-codes="<?php echo esc_attr( wp_json_encode( [ 
						 	[ 
						 		'id' => $tracking->id,
						 		'code' => $tracking->code,
						 	]
						 ] ) ); ?>" title="Verificar Código de Rastreamento">
					<svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 32 32">
						<circle cx="16" cy="16" r="4" />
						<path
							d="M30.94 15.66A16.69 16.69 0 0 0 16 5A16.69 16.69 0 0 0 1.06 15.66a1 1 0 0 0 0 .68A16.69 16.69 0 0 0 16 27a16.69 16.69 0 0 0 14.94-10.66a1 1 0 0 0 0-.68M16 22.5a6.5 6.5 0 1 1 6.5-6.5a6.51 6.51 0 0 1-6.5 6.5" />
					</svg>
				</a>
				<a href="#" class="infixs-correios-automatico-remove-code"
					data-id="<?php echo esc_attr( $tracking->id ); ?>" title="Deletar Código de Rastreamento">
					<svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24">
						<path
							d="M20 6a1 1 0 0 1 .117 1.993L20 8h-.081L19 19a3 3 0 0 1-2.824 2.995L16 22H8c-1.598 0-2.904-1.249-2.992-2.75l-.005-.167L4.08 8H4a1 1 0 0 1-.117-1.993L4 6zm-6-4a2 2 0 0 1 2 2a1 1 0 0 1-1.993.117L14 4h-4l-.007.117A1 1 0 0 1 8 4a2 2 0 0 1 1.85-1.995L10 2z" />
					</svg>
				</a>
			</div>
		<?php endforeach; ?>
	</div>
	<?php ?>
</div>