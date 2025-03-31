<?php
/**
 * Order Tracking Column
 *
 * @since   1.0.0
 * 
 * @var array $tracking_codes
 * @var \WC_Order $order
 * 
 * @package Infixs\CorreiosAutomatico
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$count_tracking_codes = count( $tracking_codes );
?>

<div class="infixs-correios-automatico-tracking-column-wrapper"
	data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
	<div class="infixs-correios-automatico-tracking-code-link">
		<?php if ( count( $tracking_codes ) > 0 ) : ?>
			<a href="#" class="infixs-correios-automatico-tracking-code-modal-view"
				data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
				data-tracking-codes="<?php echo esc_attr( wp_json_encode( $tracking_codes ) ); ?>"
				aria-label="Código de rastreamento"><?php echo sprintf( "%s%s", esc_html( $tracking_codes[0]['code'] ), esc_html( $count_tracking_codes > 1 ? " (+{$count_tracking_codes})" : "" ) ); ?></a>
		<?php endif; ?>
	</div>
</div>