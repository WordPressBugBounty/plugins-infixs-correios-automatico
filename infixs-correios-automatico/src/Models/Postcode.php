<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Postcode model.
 * 
 * Save the postcode and address cache data.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.5
 */
class Postcode extends Model {
	protected $prefix = 'infixs_correios_automatico_';
}

