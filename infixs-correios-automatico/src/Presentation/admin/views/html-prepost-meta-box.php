<?php
use Infixs\CorreiosAutomatico\Services\Correios\Enums\PrepostStatusCode;
use Infixs\CorreiosAutomatico\Utils\Formatter;

/**
 * Prepost Metabox HTML
 *
 * @package Infixs\CorreiosAutomatico
 * 
 * @global \Infixs\CorreiosAutomatico\Models\Prepost $prepost
 * @global bool $has_prepost
 * @global string $cancel_prepost_url
 * @global bool $is_correios_automatico
 * 
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( $is_correios_automatico ) : ?>
	<?php if ( ! $has_prepost || ( $has_prepost && $prepost->status == PrepostStatusCode::CANCELADO ) ) : ?>
		<div class="infixs-correios-automatico-prepost-box"
			style="display: flex; width: 100%; gap: 10px; margin-top: 10px; position: relative;">
			<button id="infixs-correios-automatico-create-prepost-declaration"
				style="white-space: normal; line-height: 1; padding: 5px 5px 12px 5px; display: flex; flex-direction: column; gap: 3px; align-items: center; justify-content: center;"
				class="button">
				<svg xmlns="http://www.w3.org/2000/svg" width="4em" height="4em" viewBox="0 0 48 48">
					<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
						d="M41.038 12.488a1.84 1.84 0 0 0-1.842-1.842H8.804a1.84 1.84 0 0 0-1.842 1.842v23.024a1.84 1.84 0 0 0 1.842 1.842h30.392a1.84 1.84 0 0 0 1.842-1.842Z" />
					<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
						d="m38.06 10.646l-.582-2.176a1.84 1.84 0 0 0-2.256-1.302l-12.98 3.478M41.038 33.26l1.096-.294a1.84 1.84 0 0 0 1.303-2.256l-2.4-8.953M9.94 37.354l.582 2.176a1.84 1.84 0 0 0 2.256 1.302l12.98-3.478M6.962 14.74l-1.096.294a1.84 1.84 0 0 0-1.303 2.256l2.414 9.008m5.971-10.126h22.103m-22.103 5.219h22.103m-22.103 5.218h22.103m-22.103 5.219H24" />
				</svg>
				<div>
					Criar Pré-Postagem c/ Declaração
				</div>
			</button>
			<button id="infixs-correios-automatico-create-prepost-invoice"
				style="white-space: normal; line-height: 1; padding: 5px 5px 12px 5px; display: flex; flex-direction: column; gap: 3px; align-items: center; justify-content: center;"
				class="button">
				<svg xmlns="http://www.w3.org/2000/svg" width="4em" height="4em" viewBox="0 0 48 48">
					<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
						d="M17.541 9.713a3.746 3.746 0 0 1 3.746 3.745h0a3.746 3.746 0 1 1-3.746-3.745M13.795 24h20.41m-20.41 6.319h20.41m-20.41 6.319h20.41" />
					<rect width="31" height="39" x="8.45" y="4.5" fill="none" stroke="currentColor" stroke-linecap="round"
						stroke-linejoin="round" rx="4" ry="4" />
				</svg>
				<div>
					Criar Pré-Postagem c/ Nota Fiscal
				</div>
			</button>
		</div>
		<div class="infixs-correios-automatico-prepost-invoice-box">
			<input type="text" id="infixs-correios-automatico-prepost-invoice-number" placeholder="Número da nota fiscal" />
			<input type="text" id="infixs-correios-automatico-prepost-invoice-key" placeholder="Chave da nota fiscal" />
			<div style="display: flex; gap: 10px;">
				<button class="button button-primary infixs-correios-automatico-create-prepost-invoice-create"
					style="width: 100%">Criar</button>
				<button class="button infixs-correios-automatico-create-prepost-invoice-cancel"
					style="width: 100%">Cancelar</button>
			</div>
		</div>
	<?php else : ?>
		<?php if ( $prepost->status == PrepostStatusCode::PREPOSTADO ) : ?>
			<p style="text-align: center;">
				<strong
					style="padding: 5px 10px; background-color: #f1f1f1; border-radius: 10px; ;"><?php echo esc_html( $prepost->status_label ); ?></strong>
			</p>
			<p style="text-align: center;">
				Pré-Postagem criada em<br />
				<strong><?php echo esc_html( Formatter::format_datetime( $prepost->created_at ) ); ?></strong>
			</p>
			<?php if ( $prepost->expire_at ) : ?>
				<p style="text-align: center;">
					Prazo para postar<br />
					<strong><?php echo esc_html( Formatter::format_datetime( $prepost->expire_at ) ); ?></strong>
				</p>
			<?php endif; ?>

			<div style="display: flex; gap: 10px;">
				<input type="hidden" name="infixs_correios_automatico_prepost_id" id="infixs_correios_automatico_prepost_id"
					value="<?php echo esc_attr( $prepost->id ); ?>" />
				<a target="_blank" class="button button-primary" style="width: 100%; text-align: center;"
					href="<?php echo esc_url( $cancel_prepost_url ) ?>">Ver</a>
				<a target="_blank" class="<?php echo esc_attr(
					apply_filters( 'infixs_correios_automatico_prepost_metabox_cancel_button_class', 'button' )
				); ?>" style="width: 100%; text-align: center;" href="<?php echo esc_url( $cancel_prepost_url ) ?>">Cancelar</a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
<?php else : ?>
	<div class="infixs-correios-automatico-prepost-box">
		<p style="text-align: center;">
			Pré-Postagem está disponível apenas para pedidos com o método de envio dos Correios Automático.
		</p>
	</div>
<?php endif; ?>