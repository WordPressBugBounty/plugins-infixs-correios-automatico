<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;

defined( 'ABSPATH' ) || exit;

/**
 * TrackingCodeEvent model.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.2.1
 * 
 * @property int $tracking_code_id
 * @property string $code
 * @property string $type
 * @property string $description
 * @property string $detail
 * @property string $location_type
 * @property string $location_address
 * @property string $location_number
 * @property string $location_neighborhood
 * @property string $location_city
 * @property string $location_state
 * @property string $location_postcode
 * @property string $event_date
 * @property string $updated_at
 * @property string $created_at
 */
class TrackingCodeEvent extends Model {
	protected $prefix = 'infixs_correios_automatico_';
}

