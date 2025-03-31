<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Product Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.1.5
 */
class Product {
	public function __construct() {
		add_action( 'woocommerce_product_options_shipping', [ $this, 'ncm_shipping_field_for_product' ] );
		add_action( 'woocommerce_process_product_meta', [ $this, 'save_ncm_shipping_field' ] );

	}

	/**
	 * Add NCM field to product shipping tab
	 *
	 * @since 1.1.5
	 */
	public function ncm_shipping_field_for_product() {
		woocommerce_wp_text_input( [ 
			'id' => '_infixs_correios_automatico_ncm',
			'label' => 'NCM',
			'placeholder' => 'Código NCM do produto',
			'desc_tip' => 'true',
			'description' => 'Código NCM do produto, exigido para envios internacionais e usado para geração de etiquetas internacionais com o plugin Correios Automático.',
		] );

		woocommerce_wp_text_input( [ 
			'id' => '_infixs_correios_automatico_additional_days',
			'label' => 'Dias Adicionais',
			'placeholder' => '0',
			'desc_tip' => 'true',
			'type' => 'number',
			'description' => 'Adiciona dias ao prazo de entrega no cálculo do frete, somando-os ao prazo real quando esse produto estiver no carrinho. Caso tenha outros produtos com dias adicionais diferentes no mesmo pedido, será considerado apenas os dias adicionais que for maior (Essa opção também está disponível em classes de entrega) - Plugin Correios Automático.',
			'custom_attributes' => [ 
				'step' => 'any',
				'min' => '0'
			]
		] );
	}

	/**
	 * Save NCM field value
	 *
	 * @since 1.1.5
	 */
	public function save_ncm_shipping_field( $post_id ) {
		// this checked before by woocommerce
		// phpcs:ignore
		$ncm_field_value = isset( $_POST['_infixs_correios_automatico_ncm'] ) ? sanitize_text_field( wp_unslash( $_POST['_infixs_correios_automatico_ncm'] ) ) : '';
		update_post_meta( $post_id, '_infixs_correios_automatico_ncm', $ncm_field_value );

		// this checked before by woocommerce
		// phpcs:ignore
		$additional_days_field_value = isset( $_POST['_infixs_correios_automatico_additional_days'] ) ? intval( $_POST['_infixs_correios_automatico_additional_days'] ) : 0;
		update_post_meta( $post_id, '_infixs_correios_automatico_additional_days', $additional_days_field_value );
	}
}