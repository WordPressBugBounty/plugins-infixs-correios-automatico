<?php
/**
 * Notices of the shipping recalculation, rendered inside the order items table.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.0
 *
 * @global array $notices
 */

defined( 'ABSPATH' ) || exit;
?>

<?php
$groups = [
	'warning' => [
		'title' => __( 'Não foi possível calcular o frete dos Correios', 'infixs-correios-automatico' ),
		'icon' => 'dashicons-warning',
		'items' => [],
	],
	'info' => [
		'title' => __( 'Sobre o recálculo do frete dos Correios', 'infixs-correios-automatico' ),
		'icon' => 'dashicons-info-outline',
		'items' => [],
	],
];

foreach ( $notices as $notice ) {
	$type = isset( $notice['type'] ) && isset( $groups[ $notice['type'] ] ) ? $notice['type'] : 'warning';
	$groups[ $type ]['items'][] = $notice;
}
?>

<?php foreach ( $groups as $type => $group ) : ?>
	<?php if ( empty( $group['items'] ) ) {
		continue;
	} ?>
	<tr class="infixs-correios-automatico-notice-row">
		<td colspan="100">
			<div
				class="infixs-correios-automatico-notice infixs-correios-automatico-notice-<?php echo esc_attr( $type ); ?>">
				<span class="dashicons <?php echo esc_attr( $group['icon'] ); ?> infixs-correios-automatico-notice-icon"
					aria-hidden="true"></span>
				<div class="infixs-correios-automatico-notice-content">
					<p class="infixs-correios-automatico-notice-title">
						<?php echo esc_html( $group['title'] ); ?>
					</p>
					<?php foreach ( $group['items'] as $notice ) : ?>
						<p class="infixs-correios-automatico-notice-message">
							<strong><?php echo esc_html( $notice['title'] ); ?>:</strong>
							<?php echo esc_html( $notice['message'] ); ?>
						</p>
					<?php endforeach; ?>
				</div>
			</div>
		</td>
	</tr>
<?php endforeach; ?>
