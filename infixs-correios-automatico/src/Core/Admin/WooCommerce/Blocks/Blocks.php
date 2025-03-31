<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\WooCommerce\Blocks;

use Automattic\WooCommerce\Admin\BlockTemplates\BlockTemplateInterface;
use Automattic\WooCommerce\Admin\Features\ProductBlockEditor\BlockRegistry;
use Automattic\WooCommerce\Admin\Features\ProductBlockEditor\ProductTemplates\ProductFormTemplateInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Register Blocks
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Blocks {
	public function __construct() {
		add_action( 'init', [ $this, 'blocks_init' ] );
		add_filter( 'woocommerce_block_template_register', [ $this, 'add_additional_days_to_product_editor' ], 100 );
	}

	public function blocks_init() {
		// checks if the current page, no nonce is needed for this
		// phpcs:ignore
		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-admin' && method_exists( BlockRegistry::class, 'get_instance' ) ) {
			BlockRegistry::get_instance()->register_block_type_from_metadata( INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . '/build' );
		}
	}

	public function add_additional_days_to_product_editor( BlockTemplateInterface $template ) {
		if ( $template instanceof ProductFormTemplateInterface && 'simple-product' === $template->get_id() ) {
			$shipping_details = $template->get_section_by_id( 'product-fee-and-dimensions-section' );

			if ( $shipping_details ) {
				$shipping_details->add_block(
					[ 
						'id' => 'ncm-code',
						'blockName' => 'woocommerce/product-text-field',
						'attributes' => [ 
							'label' => 'NCM',
							'property' => 'meta_data._infixs_correios_automatico_ncm',
							'required' => false,
							'tooltip' => 'Código NCM do produto, exigido para envios internacionais e usado para geração de etiquetas internacionais com o plugin Correios Automático.'
						]
					],
				);

				$shipping_details->add_block(
					[ 
						'id' => 'additional-days',
						'blockName' => 'woocommerce/product-number-field',
						'attributes' => [ 
							'label' => 'Dias Adicionais',
							'property' => 'meta_data._infixs_correios_automatico_additional_days',
							'suffix' => 'dias',
							'placeholder' => '0',
							'required' => false,
							'tooltip' => 'Adiciona dias ao prazo de entrega no cálculo do frete, somando-os ao prazo real quando esse produto estiver no carrinho. Caso tenha outros produtos com dias adicionais diferentes no mesmo pedido, será considerado apenas os dias adicionais que for maior (Essa opção também está disponível em classes de entrega) - Plugin Correios Automático.'
						]
					],
				);


			}
		}
	}
}