<?php
/**
 * Label Metabox HTML
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 *
 * @var bool $prepost_with_invoice The prepost of the order travels with the NF-e of the store (no DC-e to print).
 */

defined( 'ABSPATH' ) || exit;
?>
<div style="display: flex; flex-direction: column; width: 100%; gap: 10px; margin-top: 10px;">
	<button class="button" id="infixs-correios-automatico-print-label" style="width: 100%;">
		Imprimir Etiqueta / Declaração
	</button>

	<?php if ( ! empty( $prepost_with_invoice ) ) : ?>
		<p class="infixs-correios-automatico-label-invoice-note" style="margin: 0; text-align: center;">
			<?php esc_html_e( 'Pré-postagem com NF-e — sem DC-e.', 'infixs-correios-automatico' ); ?>
		</p>
	<?php else : ?>
		<button class="button" id="infixs-correios-automatico-print-dce" style="width: 100%;">
			Imprimir DCe (PDF)
		</button>
	<?php endif; ?>

</div>