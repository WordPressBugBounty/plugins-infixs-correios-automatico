<?php
/**
 * Shipping Calculator Template with Inline Styles
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.1
 * 
 * @global array $calculator_styles Array of sanitized calculator styles
 */

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Utils\Icons;

defined( 'ABSPATH' ) || exit;

// Load shared helper functions
require_once __DIR__ . '/infixs-shipping-calculator-shared-styles.php';

// Get icon color for SVG
$icon_color = InfixsCalculatorStylesHelper::getIconColorAttribute( $calculator_styles );
?>
<div style="clear: both;"></div>
<div class="infixs-correios-automatico-calculator">
	<div class="infixs-correios-automatico-calculator-title" <?php echo InfixsCalculatorStylesHelper::getInlineStyleAttribute( 'title', $calculator_styles ); ?>>
		Calcular o Frete
	</div>
	<div class="infixs-correios-automatico-calculate-box">
		<div class="infixs-correios-automatico-input-text">
			<div class="infixs-correios-automatico-input-text-prepend" <?php echo $icon_color; ?>>
				<?php
				if ( isset( $calculator_styles['input'], $calculator_styles['input']['icon'] ) && Icons::iconExists( $calculator_styles['input']['icon'] ) ) :
					echo Icons::esc_svg( Icons::getIconContent( $calculator_styles['input']['icon'] ) );
				else :
					?>
					<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 20 20">
						<path fill="currentColor"
							d="M1.5 7.882V4.118a1 1 0 0 1 .553-.894l3-1.5a1 1 0 0 1 .894 0l3 1.5a1 1 0 0 1 .553.894v3.764a1 1 0 0 1-.553.895l-3 1.5a1 1 0 0 1-.894 0l-3-1.5a1 1 0 0 1-.553-.895m1.04-3.576a.5.5 0 0 0 .266.655L5 5.887V8.5a.5.5 0 1 0 1 0V5.887l2.194-.926a.5.5 0 0 0-.389-.921L5.5 5.013L3.194 4.04a.5.5 0 0 0-.655.266m-.498 9.944V9.89l1 .5v3.86c0 .415.336.75.75.75h.259a2.5 2.5 0 0 1 4.9 0h1.1A2.5 2.5 0 0 1 13 13.05v-8.3a.75.75 0 0 0-.75-.75h-1.754a2 2 0 0 0-.338-1h2.092c.966 0 1.75.784 1.75 1.75V6h.881a1.5 1.5 0 0 1 1.342.83l1.618 3.235c.104.209.159.438.159.671V14.5a1.5 1.5 0 0 1-1.5 1.5h-1.55a2.5 2.5 0 0 1-4.9 0h-1.1a2.5 2.5 0 0 1-4.9 0h-.259a1.75 1.75 0 0 1-1.75-1.75M14.95 15h1.55a.5.5 0 0 0 .5-.5V11h-3v2.5c.48.36.827.89.95 1.5m1.742-5L15.33 7.277A.5.5 0 0 0 14.883 7H14v3zM5 15.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 0 0-3 0m7.5 1.5a1.5 1.5 0 1 0 0-3a1.5 1.5 0 0 0 0 3" />
					</svg>
				<?php endif; ?>
			</div>
			<input type="text" id="infixs-correios-automatico-input-postcode"
				class="input-text infixs-correios-automatico-input infixs-correios-automatico-postcode-mask"
				maxlength="9" placeholder="Digite seu CEP" <?php echo InfixsCalculatorStylesHelper::getInlineStyleAttribute( 'input', $calculator_styles ); ?>>
			<div class="infixs-correios-automatico-input-text-append infixs-correios-automatico-loading"
				style="display: none;">
				<svg xmlns="http://www.w3.org/2000/svg" class="infixs-correios-automatico-spin-animation" width="20"
					height="20" viewBox="0 0 24 24">
					<g fill="none" fill-rule="evenodd">
						<path
							d="m12.593 23.258l-.011.002l-.071.035l-.02.004l-.014-.004l-.071-.035q-.016-.005-.024.005l-.004.01l-.017.428l.005.02l.01.013l.104.074l.015.004l.012-.004l.104-.074l.012-.016l.004-.017l-.017-.427q-.004-.016-.017-.018m.265-.113l-.013.002l-.185.093l-.01.01l-.003.011l.018.43l.005.012l.008.007l.201.093q.019.005.029-.008l.004-.014l-.034-.614q-.005-.018-.02-.022m-.715.002a.02.02 0 0 0-.027.006l-.006.014l-.034.614q.001.018.017.024l.015-.002l.201-.093l.01-.008l.004-.011l.017-.43l-.003-.012l-.01-.01z" />
						<path fill="currentColor"
							d="M12 4.5a7.5 7.5 0 1 0 0 15a7.5 7.5 0 0 0 0-15M1.5 12C1.5 6.201 6.201 1.5 12 1.5S22.5 6.201 22.5 12S17.799 22.5 12 22.5S1.5 17.799 1.5 12"
							opacity="0.1" />
						<path fill="currentColor"
							d="M12 4.5a7.46 7.46 0 0 0-5.187 2.083a1.5 1.5 0 0 1-2.075-2.166A10.46 10.46 0 0 1 12 1.5a1.5 1.5 0 0 1 0 3" />
					</g>
				</svg>
			</div>
		</div>
		<?php if ( ! Config::boolean( 'general.auto_calculate_product_shipping_postcode' ) ) : ?>
			<div class="infixs-correios-automatico-postcode-button-box">
				<a name="infixs-correios-automatico-postcode" type="submit"
					class="button alt wp-element-button infixs-correios-automatico-calculate-submit" <?php echo InfixsCalculatorStylesHelper::getInlineStyleAttribute( 'button', $calculator_styles, [], [ 'display: flex;' ] ); ?>>
					Calcular
				</a>
			</div>
		<?php endif; ?>
	</div>
	<div>
		<a class="infixs-correios-automatico-calculate-find-link" target="_blank"
			href="https://buscacepinter.correios.com.br/app/endereco/index.php" <?php echo InfixsCalculatorStylesHelper::getInlineStyleAttribute( 'find_postcode', $calculator_styles ); ?>>
			Não sei meu CEP
		</a>
	</div>
	<div id="infixs-correios-automatico-calculate-results"></div>
</div>

<?php
// Add minimal CSS for responsive and hover effects that can't be inline
?>
<style id="infixs-correios-automatico-calculator-responsive-styles">
	/* Icon color inheritance fix */
	.infixs-correios-automatico-input-text-prepend svg {
		fill: currentColor;
	}

	/* Focus and hover effects that can't be inline */
	<?php if ( isset( $calculator_styles['input']['border_color'] ) ) : ?>
		.infixs-correios-automatico-input:focus {
			border-color:
				<?php echo esc_attr( $calculator_styles['input']['border_color'] ); ?>
				!important;
			box-shadow: 0 0 0 1px
				<?php echo esc_attr( $calculator_styles['input']['border_color'] ); ?>
			;
		}

	<?php endif; ?>

	<?php if ( isset( $calculator_styles['button']['background_color'] ) ) : ?>
		.infixs-correios-automatico-calculate-submit:hover {
			background-color:
				<?php echo esc_attr( $calculator_styles['button']['background_color'] ); ?>
				!important;
			opacity: 0.9;
		}

	<?php endif; ?>

	/* Ensure styles don't break layout */
	.infixs-correios-automatico-calculator * {
		box-sizing: border-box;
	}
</style>

<?php
/**
 * Hook for additional custom styles
 */
do_action( 'infixs_correios_automatico_calculator_custom_styles', $calculator_styles );
?>