<?php
/**
 * Shipping Calculator Results Template with Inline Styles
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.1
 * 
 * @global \WC_Shipping_Rate[] $rates
 * @global array $address
 * @global array $calculator_styles Array of sanitized calculator styles
 */

use Infixs\CorreiosAutomatico\Utils\Formatter;
use Infixs\CorreiosAutomatico\Utils\TextHelper;
use Infixs\CorreiosAutomatico\Controllers\Sanitizers\CalculatorStylesSanitizer;

defined( 'ABSPATH' ) || exit;

// Load shared helper functions
require_once __DIR__ . '/infixs-shipping-calculator-shared-styles.php';
?>

<div class="infixs-correios-automatico-shipping-results">
	<?php if ( isset( $address ) && $address ) : ?>
		<div class="infixs-correios-automatico-shipping-results-address" <?php echo InfixsCalculatorStylesHelper::getResultElementInlineStyle( 'result_address', $calculator_styles ); ?>>
			<?php echo sprintf( "%s%s%s%s%s, Brasil",
				esc_html( isset( $address['postcode'] ) && $address['postcode'] ? $address['postcode'] . ', ' : '' ),
				esc_html( isset( $address['address'] ) && $address['address'] ? $address['address'] . ', ' : '' ),
				esc_html( isset( $address['neighborhood'] ) && $address['neighborhood'] ? $address['neighborhood'] . ', ' : '' ),
				esc_html( isset( $address['city'] ) && $address['city'] ? $address['city'] . '/' : '' ),
				esc_html( $address['state'] ?? '' )
			); ?>
		</div>
	<?php endif; ?>

	<?php if ( count( $rates ) > 0 ) : ?>
		<div class="infixs-correios-automatico-shipping-results-grid">
			<div <?php echo InfixsCalculatorStylesHelper::getResultElementInlineStyle( 'result_table_header', $calculator_styles, [ 'border-top: none;', 'border-left: none;', 'border-right: none;' ] ); ?>>
				Entrega
			</div>
			<div <?php echo InfixsCalculatorStylesHelper::getResultElementInlineStyle( 'result_table_header', $calculator_styles, [ 'border-top: none;', 'border-left: none;', 'border-right: none;' ] ); ?>>
				Custo
			</div>
			<?php
			foreach ( $rates as $rate ) :
				$meta_data = $rate->get_meta_data();
				$delivery_time = isset( $meta_data['delivery_time'] ) ? $meta_data['delivery_time'] : ( isset( $meta_data['_delivery_time'] ) ? $meta_data['_delivery_time'] : false );
				?>
				<div>
					<div class="infixs-correios-automatico-shipping-results-method" <?php echo InfixsCalculatorStylesHelper::getResultElementInlineStyle( 'result_title_column', $calculator_styles ); ?>>
						<?php echo esc_html( TextHelper::removeShippingTime( $rate->label ) ); ?>
					</div>
					<?php if ( $delivery_time !== false ) :
						$time_is_numeric = is_numeric( $delivery_time );
						?>
						<div class="infixs-correios-automatico-shipping-results-time" <?php echo InfixsCalculatorStylesHelper::getResultElementInlineStyle( 'result_delivery_time', $calculator_styles ); ?>>
							<?php
							if ( $time_is_numeric ) {
								echo sprintf( "Receba até %s %s", esc_html( $delivery_time ), esc_html( $delivery_time > 1 ? 'dias úteis' : 'dia útil' ) );
							} else {
								echo esc_html( trim( $delivery_time, "()" ) );
							}
							?>
						</div>
					<?php endif; ?>
				</div>
				<div class="infixs-correios-automatico-shipping-results-cost" <?php echo InfixsCalculatorStylesHelper::getResultElementInlineStyle( 'result_price', $calculator_styles ); ?>>
					<?php echo esc_html( $rate->cost > 0 ? Formatter::format_currency( $rate->cost ) : __( 'Grátis', 'infixs-correios-automatico' ) ); ?>
				</div>

			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="infixs-correios-automatico-shipping-results-empty" <?php echo InfixsCalculatorStylesHelper::getResultElementInlineStyle( 'result_column', $calculator_styles ); ?>>
			<?php esc_html_e( 'Nenhum método de entrega disponível para o CEP selecionado.', 'infixs-correios-automatico' ); ?>
		</div>
	<?php endif; ?>
</div>