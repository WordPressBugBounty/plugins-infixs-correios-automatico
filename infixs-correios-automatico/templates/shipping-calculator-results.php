<?php
/**
 * Shipping Cost Results Template
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.1
 * 
 * @global \WC_Shipping_Rate[] $rates
 * @global array $address
 */

use Infixs\CorreiosAutomatico\Utils\Formatter;
use Infixs\CorreiosAutomatico\Utils\TextHelper;

defined( 'ABSPATH' ) || exit;
?>

<div class="infixs-correios-automatico-shipping-results">
	<?php if ( isset( $address ) && $address ) : ?>
		<div class="infixs-correios-automatico-shipping-results-address">
			<?php echo sprintf( "%s%s%s%s", esc_html( isset( $address['address'] ) && $address['address'] ? $address['address'] . ', ' : '' ), esc_html( isset( $address['neighborhood'] ) && $address['neighborhood'] ? $address['neighborhood'] . ', ' : '' ), esc_html( isset( $address['city'] ) && $address['city'] ? $address['city'] . '/' : '' ), esc_html( $address['state'] ?? '' ) ); ?>
		</div>
	<?php endif; ?>
	<?php if ( count( $rates ) > 0 ) : ?>
		<div class="infixs-correios-automatico-shipping-results-grid">
			<div>Entrega</div>
			<div>Custo</div>
			<?php
			foreach ( $rates as $rate ) :
				$meta_data = $rate->get_meta_data();
				?>
				<div>
					<div class="infixs-correios-automatico-shipping-results-method">
						<?php echo esc_html( TextHelper::removeShippingTime( $rate->label ) ); ?>
					</div>
					<?php if ( isset( $meta_data['delivery_time'] ) ) : ?>
						<div class="infixs-correios-automatico-shipping-results-time">
							<?php echo sprintf( "Receba até %s %s", esc_html( $meta_data['delivery_time'] ), esc_html( $meta_data['delivery_time'] > 1 ? 'dias úteis' : 'dia útil' ) ); ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="infixs-correios-automatico-shipping-results-cost">
					<?php echo esc_html( $rate->cost > 0 ? Formatter::format_currency( $rate->cost ) : __( 'Grátis', 'infixs-correios-automatico' ) ); ?>
				</div>

			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="infixs-correios-automatico-shipping-results-empty">
			<?php esc_html_e( 'Nenhum método de entrega disponível para o CEP selecionado.', 'infixs-correios-automatico' ); ?>
		</div>
	<?php endif; ?>
</div>