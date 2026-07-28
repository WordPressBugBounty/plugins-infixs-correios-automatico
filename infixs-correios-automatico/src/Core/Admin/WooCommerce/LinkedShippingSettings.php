<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

use Infixs\CorreiosAutomatico\Core\Shipping\LinkedShippingMethod;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;

defined( 'ABSPATH' ) || exit;

/**
 * Correios Automático Linked Shipping Settings Class
 *
 * Adds the "Serviço dos Correios" field to the settings screen of the native
 * WooCommerce shipping methods that support linking (Frete Grátis, Preço Fixo).
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.0
 */
class LinkedShippingSettings {

	public function __construct() {
		foreach ( LinkedShippingMethod::supported_method_ids() as $method_id ) {
			add_filter( "woocommerce_shipping_instance_form_fields_{$method_id}", [ $this, 'add_field' ] );
		}

		add_filter( 'woocommerce_generate_' . LinkedShippingMethod::FIELD_TYPE . '_html', [ $this, 'render_field' ], 10, 4 );
	}

	/**
	 * Add the link field to a native shipping method instance form fields.
	 *
	 * The field goes before "requires" instead of at the end because of the shipping zone
	 * modal: possiblyHideFreeShippingRequirements() in WooCommerce's wc-shipping-zone-methods.js
	 * hides every label and fieldset that follows the "requires" field whenever it is empty or
	 * "coupon", assuming its own fields are the last ones. Appended at the end, the field would
	 * disappear for those two options.
	 *
	 * @since 1.8.0
	 *
	 * @param array $fields Instance form fields.
	 *
	 * @return array
	 */
	public function add_field( $fields ) {
		$field = [
			'title' => __( 'Serviço dos Correios', 'infixs-correios-automatico' ),
			'type' => LinkedShippingMethod::FIELD_TYPE,
			'description' => __( 'Vincule um método dos Correios Automático desta zona para permitir a geração de pré-postagem, etiqueta e rastreio neste frete.', 'infixs-correios-automatico' ),
			'desc_tip' => true,
			'default' => '',
			'sanitize_callback' => 'absint',
		];

		$position = array_search( 'requires', array_keys( $fields ), true );

		if ( false === $position ) {
			$fields[ LinkedShippingMethod::OPTION_KEY ] = $field;

			return $fields;
		}

		return array_merge(
			array_slice( $fields, 0, $position, true ),
			[ LinkedShippingMethod::OPTION_KEY => $field ],
			array_slice( $fields, $position, null, true )
		);
	}

	/**
	 * Render the link field.
	 *
	 * WooCommerce passes the shipping method instance to this filter, which is the only
	 * way to know which zone the options must be scoped to.
	 *
	 * @since 1.8.0
	 *
	 * @param string $html       Field html.
	 * @param string $key        Field key.
	 * @param array  $data       Field data.
	 * @param \WC_Shipping_Method $wc_method Shipping method instance.
	 *
	 * @return string
	 */
	public function render_field( $html, $key, $data, $wc_method ) {
		if ( ! $wc_method instanceof \WC_Shipping_Method ) {
			return $html;
		}

		$correios_methods = LinkedShippingMethod::get_zone_correios_methods( $wc_method->instance_id );

		if ( empty( $correios_methods ) ) {
			return $this->render_empty_notice( $data );
		}

		$data['type'] = 'select';
		$data['options'] = $this->build_options( $correios_methods );

		return $wc_method->generate_select_html( $key, $data );
	}

	/**
	 * Build the select options from the zone Correios methods.
	 *
	 * @since 1.8.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod[] $correios_methods Correios methods.
	 *
	 * @return array
	 */
	protected function build_options( $correios_methods ) {
		$options = [ '' => __( 'Nenhum (não gerar pré-postagem)', 'infixs-correios-automatico' ) ];

		foreach ( $correios_methods as $method ) {
			$label = sprintf(
				'%s — %s',
				$method->get_title(),
				DeliveryServiceCode::getDescription( $method->get_product_code(), true )
			);

			if ( ! $method->is_enabled() ) {
				$label .= ' ' . __( '(desabilitado)', 'infixs-correios-automatico' );
			}

			$options[ $method->get_instance_id() ] = $label;
		}

		return $options;
	}

	/**
	 * Render a notice when the zone has no Correios method to link to.
	 *
	 * @since 1.8.0
	 *
	 * @param array $data Field data.
	 *
	 * @return string
	 */
	protected function render_empty_notice( $data ) {
		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $data['title'] ); ?></th>
			<td class="forminp">
				<p class="description">
					<?php esc_html_e( 'Nenhum método dos Correios Automático foi encontrado nesta zona de entrega. Adicione um método Correios Automático à zona para poder vinculá-lo a este frete.', 'infixs-correios-automatico' ); ?>
				</p>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}
}
