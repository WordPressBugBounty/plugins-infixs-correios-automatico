<?php

namespace Infixs\CorreiosAutomatico\Core\Admin\Dokan;


defined( 'ABSPATH' ) || exit;

class Dokan {

	public function __construct() {
		if ( class_exists( 'WeDevs_Dokan' ) ) {
			// Silence 
		}
	}
}