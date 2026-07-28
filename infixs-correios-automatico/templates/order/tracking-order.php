<?php

use Infixs\CorreiosAutomatico\Core\Support\Template;
use Infixs\CorreiosAutomatico\Utils\Formatter;
/**
 * Tracking Order Template
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.2.1
 * 
 * @global array $objects
 * @global \WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<?php
$return_service = \Infixs\CorreiosAutomatico\Container::returnService();

if ( $order instanceof \WC_Order ) :
	if ( $return_service->isAlreadyRequested( $order ) ) :
		?>
		<div class="infixs-caref-return-wrap">
			<p class="infixs-caref-return-requested">
				<?php esc_html_e( 'Devolução solicitada. Em breve você receberá o código de postagem.', 'infixs-correios-automatico' ); ?>
			</p>
		</div>
		<?php
	elseif ( $return_service->isEligible( $order ) ) :
		?>
		<div class="infixs-caref-return-wrap">
			<button type="button" class="button wp-element-button infixs-caref-return-button"
				data-order-id="<?php echo esc_attr( $order->get_id() ); ?>"
				data-order-key="<?php echo esc_attr( $order->get_order_key() ); ?>">
				<?php esc_html_e( 'Solicitar Devolução', 'infixs-correios-automatico' ); ?>
			</button>
		</div>

		<div class="infixs-caref-return-modal" style="display:none;">
			<div class="infixs-caref-return-modal-content">
				<h3><?php esc_html_e( 'Confirmar Devolução', 'infixs-correios-automatico' ); ?></h3>
				<p>
					<?php esc_html_e( 'Ao confirmar, você solicitará a devolução deste pedido. O estorno do valor será efetuado somente após a encomenda chegar à loja. Você receberá um código de postagem para levar o produto até a agência dos Correios mais próxima.', 'infixs-correios-automatico' ); ?>
				</p>
				<div class="infixs-caref-return-modal-message"></div>
				<div class="infixs-caref-return-modal-actions">
					<button type="button" class="button wp-element-button infixs-caref-return-cancel">
						<?php esc_html_e( 'Cancelar', 'infixs-correios-automatico' ); ?>
					</button>
					<button type="button" class="button wp-element-button infixs-caref-return-confirm">
						<?php esc_html_e( 'Confirmar Devolução', 'infixs-correios-automatico' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	endif;
endif;
?>

<h2>Acompanhe seu Pedido</h2>

<?php
Template::loadComponent( "tracking/tracking-history.php", [
	'objects' => $objects,
	'order' => $order,
] );
?>