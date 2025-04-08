<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Dokan;


defined( 'ABSPATH' ) || exit;

class Calculator {

	public function __construct() {
		//add_filter( 'infixs_correios_automatico_calculate_shipping_origin_postcode', [ $this, 'calculate_vendor_postcode' ], 10, 2 );
	}

	public function calculate_vendor_postcode( $postcode, $package ) {
		foreach ( $package['contents'] as $item_id => $values ) {
			$product = $values['data'];
			$qty = $values['quantity'];

			if ( $qty > 0 && $product && $product->needs_shipping() ) {

			}
		}
	}
}