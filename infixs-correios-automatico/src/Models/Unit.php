<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;
use Infixs\WordpressEloquent\Relations\HasMany;

defined( 'ABSPATH' ) || exit;

/**
 * Unit model.
 * 
 * Unit label group.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.5.0
 * 
 * @property int $id
 * @property string $dispatch_number
 * @property int|null $ceint_id
 * @property string $unit_code
 * @property \Infixs\WordpressEloquent\Collection $codes
 * @property string $unit_code
 * @property string $service_code
 * @property string $status
 * @property string $origin_country
 * @property string $origin_operator_name
 * @property string $destination_operator_name
 * @property string $destination_country
 * @property string $postal_category_code
 * @property string $service_subclass_code
 * @property int $sequence
 * @property string $unit_type
 * @property string $unit_rfid_code
 * @property string $created_at
 * @property string $updated_at
 */
class Unit extends Model {
	protected $prefix = 'infixs_correios_automatico_';

	public function codes(): HasMany {
		return $this->hasMany( TrackingCode::class);
	}
}

