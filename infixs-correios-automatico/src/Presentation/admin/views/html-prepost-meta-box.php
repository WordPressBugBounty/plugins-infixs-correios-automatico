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
 * @global array $volume_rows Name, status and link of the prepost of each volume, empty when the order was not split. @since 1.11.0
 * @global string $dashboard_url Order on the dashboard. @since 1.11.0
 * @global array|null $invoice NF-e of the order, as the order invoice resolver answers it. @since 1.11.0
 * @global bool $waiting_invoice Whether the automatic prepost waits for the NF-e. @since 1.11.0
 *
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( $is_correios_automatico ) : ?>
	<?php if ( $waiting_invoice ) : ?>
		<div class="notice notice-info inline infixs-correios-automatico-prepost-waiting-invoice" style="margin: 10px 0 0;">
			<p><?php esc_html_e( 'A pré-postagem automática está aguardando a nota fiscal (NF-e) deste pedido.', 'infixs-correios-automatico' ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( $volume_rows ) : ?>
		<ul style="margin: 10px 0;">
			<?php foreach ( $volume_rows as $volume_row ) : ?>
				<li style="display: flex; flex-wrap: wrap; gap: 4px; align-items: center;">
					<strong><?php echo esc_html( $volume_row['name'] ); ?></strong>
					<span>—</span>
					<span><?php echo esc_html( $volume_row['status'] ); ?></span>
					<?php if ( $volume_row['url'] ) : ?>
						<span>—</span>
						<a target="_blank" href="<?php echo esc_url( $volume_row['url'] ); ?>">Ver</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $has_prepost && $prepost->status == PrepostStatusCode::PREPOSTADO ) : ?>
			<input type="hidden" name="infixs_correios_automatico_prepost_id" id="infixs_correios_automatico_prepost_id"
				value="<?php echo esc_attr( $prepost->id ); ?>" />
		<?php endif; ?>
		<p>Este pedido foi dividido em volumes. Crie a pré-postagem de cada volume pelo painel.</p>
		<a target="_blank" class="button button-primary" style="display: block; text-align: center;"
			href="<?php echo esc_url( $dashboard_url ); ?>">Abrir no painel</a>
	<?php elseif ( ! $has_prepost || ( $has_prepost && $prepost->status == PrepostStatusCode::CANCELADO ) ) : ?>
		<?php if ( $invoice ) : ?>
			<p class="infixs-correios-automatico-prepost-invoice-hint" style="margin: 10px 0 0;">
				<?php
				echo esc_html( sprintf(
					/* translators: 1: invoice number, 2: where the invoice came from */
					__( 'NF-e nº %1$s (%2$s)', 'infixs-correios-automatico' ),
					$invoice['invoice_number'],
					$invoice['source_label']
				) );
				?>
			</p>
		<?php endif; ?>
		<div class="infixs-correios-automatico-prepost-box"
			style="display: flex; width: 100%; gap: 10px; margin-top: 10px; position: relative;">
			<button id="infixs-correios-automatico-create-prepost-declaration"
				style="white-space: normal; flex-basis: 50%; line-height: 1; padding: 5px 5px 12px 5px; display: flex; flex-direction: column; gap: 3px; align-items: center; justify-content: center;"
				class="button">
				<svg xmlns="http://www.w3.org/2000/svg" width="4em" height="4em" viewBox="0 0 48 48">
					<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
						d="M41.038 12.488a1.84 1.84 0 0 0-1.842-1.842H8.804a1.84 1.84 0 0 0-1.842 1.842v23.024a1.84 1.84 0 0 0 1.842 1.842h30.392a1.84 1.84 0 0 0 1.842-1.842Z" />
					<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
						d="m38.06 10.646l-.582-2.176a1.84 1.84 0 0 0-2.256-1.302l-12.98 3.478M41.038 33.26l1.096-.294a1.84 1.84 0 0 0 1.303-2.256l-2.4-8.953M9.94 37.354l.582 2.176a1.84 1.84 0 0 0 2.256 1.302l12.98-3.478M6.962 14.74l-1.096.294a1.84 1.84 0 0 0-1.303 2.256l2.414 9.008m5.971-10.126h22.103m-22.103 5.219h22.103m-22.103 5.218h22.103m-22.103 5.219H24" />
				</svg>
				<div>
					Criar Pré-Postagem c/ Declaração Eletrônica (DCe)
				</div>
			</button>
			<button id="infixs-correios-automatico-create-prepost-invoice"
				style="white-space: normal; flex-basis: 50%; line-height: 1; padding: 5px 5px 12px 5px; display: flex; flex-direction: column; gap: 3px; align-items: center; justify-content: center;"
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
			<input type="text" id="infixs-correios-automatico-prepost-invoice-number" placeholder="Número da nota fiscal"
				value="<?php echo esc_attr( $invoice ? $invoice['invoice_number'] : '' ); ?>" />
			<input type="text" id="infixs-correios-automatico-prepost-invoice-key" placeholder="Chave da nota fiscal"
				value="<?php echo esc_attr( $invoice ? $invoice['invoice_key'] : '' ); ?>" />
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