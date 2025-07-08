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
		<div class="infixs-correios-automatico-shipping-results-address" <?php echo infixs_get_result_element_inline_style( 'result_address', $calculator_styles ); ?>>
			<?php echo sprintf( "%s%s%s%s", esc_html( isset( $address['address'] ) && $address['address'] ? $address['address'] . ', ' : '' ), esc_html( isset( $address['neighborhood'] ) && $address['neighborhood'] ? $address['neighborhood'] . ', ' : '' ), esc_html( isset( $address['city'] ) && $address['city'] ? $address['city'] . '/' : '' ), esc_html( $address['state'] ?? '' ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( count( $rates ) > 0 ) : ?>
		<div class="infixs-correios-automatico-shipping-results-grid">
			<div <?php echo infixs_get_result_element_inline_style( 'result_column', $calculator_styles ); ?>>
				Entrega
			</div>
			<div <?php echo infixs_get_result_element_inline_style( 'result_column', $calculator_styles ); ?>>
				Custo
			</div>
			<?php
			foreach ( $rates as $rate ) :
				$meta_data = $rate->get_meta_data();
				?>
				<div>
					<div class="infixs-correios-automatico-shipping-results-method" <?php echo infixs_get_result_element_inline_style( 'result_title_column', $calculator_styles ); ?>>
						<?php echo esc_html( TextHelper::removeShippingTime( $rate->label ) ); ?>
					</div>
					<?php if ( isset( $meta_data['delivery_time'] ) ) : ?>
						<div class="infixs-correios-automatico-shipping-results-time" <?php echo infixs_get_result_element_inline_style( 'result_delivery_time', $calculator_styles ); ?>>
							<?php echo sprintf( "Receba até %s %s", esc_html( $meta_data['delivery_time'] ), esc_html( $meta_data['delivery_time'] > 1 ? 'dias úteis' : 'dia útil' ) ); ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="infixs-correios-automatico-shipping-results-cost" <?php echo infixs_get_result_element_inline_style( 'result_price', $calculator_styles ); ?>>
					<?php echo esc_html( $rate->cost > 0 ? Formatter::format_currency( $rate->cost ) : __( 'Grátis', 'infixs-correios-automatico' ) ); ?>
				</div>

			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<div class="infixs-correios-automatico-shipping-results-empty" <?php echo infixs_get_result_element_inline_style( 'result_column', $calculator_styles ); ?>>
			<?php esc_html_e( 'Nenhum método de entrega disponível para o CEP selecionado.', 'infixs-correios-automatico' ); ?>
		</div>
	<?php endif; ?>
</div>

<?php
// Add minimal CSS for responsive and hover effects that can't be inline
// Build CSS output
$results_css = '';

// Responsive adjustments
$results_css .= "/* Results responsive adjustments */\n";
$results_css .= "@media (max-width: 768px) {\n";
$results_css .= "\t.infixs-correios-automatico-shipping-results-grid {\n";
$results_css .= "\t\tgrid-template-columns: 1fr 1fr !important;\n";
$results_css .= "\t\tgap: 10px;\n";
$results_css .= "\t}\n";

if ( isset( $calculator_styles['result_column'] ) ) {
	$results_css .= "\t.infixs-correios-automatico-shipping-results-grid > div {\n";
	$results_css .= "\t\tpadding: 8px !important;\n";
	$results_css .= "\t}\n";
}

if ( isset( $calculator_styles['result_price'] ) ) {
	$results_css .= "\t.infixs-correios-automatico-shipping-results-cost {\n";
	$results_css .= "\t\ttext-align: center !important;\n";
	$results_css .= "\t}\n";
}

$results_css .= "}\n\n";

// Grid layout fixes
$results_css .= "/* Results grid layout fixes */\n";
$results_css .= ".infixs-correios-automatico-shipping-results-grid {\n";
$results_css .= "\tdisplay: grid;\n";
$results_css .= "\tgrid-template-columns: 1fr auto;\n";
$results_css .= "\tgap: 15px;\n";
$results_css .= "\talign-items: start;\n";
$results_css .= "}\n\n";

// Box sizing
$results_css .= "/* Ensure styles don't break layout */\n";
$results_css .= ".infixs-correios-automatico-shipping-results * {\n";
$results_css .= "\tbox-sizing: border-box;\n";
$results_css .= "}\n\n";

// Hover effects
if ( isset( $calculator_styles['result_column']['background_color'] ) ) {
	$results_css .= "/* Hover effects for result items */\n";
	$results_css .= ".infixs-correios-automatico-shipping-results-grid > div:hover {\n";
	$results_css .= "\tbackground-color: " . esc_attr( $calculator_styles['result_column']['background_color'] ) . " !important;\n";
	$results_css .= "\topacity: 0.9;\n";
	$results_css .= "}\n\n";
}

if ( isset( $calculator_styles['result_price']['text_color'] ) ) {
	$results_css .= ".infixs-correios-automatico-shipping-results-cost {\n";
	$results_css .= "\tfont-weight: bold;\n";
	$results_css .= "}\n\n";
}
?>
<!-- <style id="infixs-correios-automatico-results-responsive-styles">
	<?php //echo $results_css; ?>
</style> -->

<?php
/**
 * Hook for additional custom results styles
 */
do_action( 'infixs_correios_automatico_results_custom_styles', $calculator_styles );
?>