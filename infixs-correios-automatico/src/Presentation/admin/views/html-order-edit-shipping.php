<?php

use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
/**
 * Edit shipping in order
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.2.7
 * 
 * @global array $shipping_services
 * @global array $shipping_methods
 * @global string $item_id
 * @global \WC_Order_Item_Shipping $item
 * @global bool $is_selected
 * @global array $instances
 */

defined( 'ABSPATH' ) || exit;

$show_shipping_metas = [ '_weight', '_length', '_width', '_height', 'shipping_product_code' ];
$meta_data = $item->get_all_formatted_meta_data( '' );
?>

<div class="view">
	<?php if ( $meta_data ) :
		?>
		<table cellspacing="0" class="display_meta">
			<?php
			foreach ( $meta_data as $meta_id => $meta ) :
				if ( ! in_array( $meta->key, $show_shipping_metas, true ) ) {
					continue;
				}
				?>
				<tr>
					<th><?php echo wp_kses_post( $meta->display_key ); ?>:</th>
					<td><?php echo wp_kses_post( force_balance_tags( $meta->display_value ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	<?php endif; ?>
</div>
<div class="edit" style="display: none;">
	<div class="infixs-edit-shipping-options" style="<?php echo esc_attr( $is_selected ? '' : 'display: none;' ); ?>"
		data-metas="<?php echo esc_attr( wp_json_encode( $meta_data ) ); ?>"
		data-instances="<?php echo esc_attr( wp_json_encode( $instances ) ); ?>">
		<div class="infixs-edit-shipping-options-meta-container">
			<div class="infixs-edit-shipping-options-meta-container-item">
				<label for="infixs-edit-shipping-options-shipping-instance">
					Método de entrega:</label>
				<select class="infixs-edit-shipping-options-service" id="infixs-edit-shipping-options-shipping-instance"
					name="instance_id[<?php echo esc_attr( $item_id ); ?>]"
					value="<?php echo esc_attr( ! is_array( $item->get_instance_id() ) ? $item->get_instance_id() : '' ); ?>"
					style="width: 100%;">
					<?php
					foreach ( $shipping_methods as $method ) {
						$is_active = $item->get_instance_id() == $method->get_instance_id();
						echo sprintf( '<option value="%s" %s>%s - %s</option>', esc_attr( $method->get_instance_id() ), esc_attr( selected( true, $is_active, false ) ), esc_html( $method->get_title() ), esc_html( DeliveryServiceCode::getDescription( $method->get_product_code(), true ) ) );
					}
					?>
				</select>
			</div>

			<?php
			foreach ( $meta_data as $meta_id => $meta ) :
				if ( $meta->key !== 'shipping_product_code' ) {
					continue;
				}
				?>
				<input type="hidden"
					name="meta_key[<?php echo esc_attr( $item_id ); ?>][<?php echo esc_attr( $meta_id ); ?>]"
					value="<?php echo esc_attr( $meta->key ); ?>" />
				<input type="hidden"
					name="meta_value[<?php echo esc_attr( $item_id ); ?>][<?php echo esc_attr( $meta_id ); ?>]"
					value="<?php echo esc_attr( $meta->value ); ?>" />

			<?php endforeach; ?>
			<?php foreach ( $meta_data as $meta_id => $meta ) :
				if ( ! in_array( $meta->key, [ '_weight', '_length', '_width', '_height' ] ) ) {
					continue;
				}
				?>
				<div class="infixs-edit-shipping-options-meta-container-item">
					<label for="infixs-edit-shipping-options-meta-<?php echo esc_attr( $meta->key ); ?>">
						<?php echo esc_html( $meta->display_key ); ?>:</label>
					<input type="text" class="infixs-edit-shipping-options-meta"
						id="infixs-edit-shipping-options-meta-<?php echo esc_attr( $meta->key ); ?>"
						name="meta_value[<?php echo esc_attr( $item_id ); ?>][<?php echo esc_attr( $meta_id ); ?>]"
						value="<?php echo esc_attr( $meta->value ); ?>" />
					<input type="hidden"
						name="meta_key[<?php echo esc_attr( $item_id ); ?>][<?php echo esc_attr( $meta_id ); ?>]"
						value="<?php echo esc_attr( $meta->key ); ?>" />
				</div>

			<?php endforeach; ?>
		</div>
	</div>
</div>