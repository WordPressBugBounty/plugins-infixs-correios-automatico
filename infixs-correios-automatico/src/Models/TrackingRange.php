<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;
use Infixs\WordpressEloquent\Relations\HasMany;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking Range Model.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.3.7
 * 
 * @property int $id
 * @property string $service_code
 * @property string $range_start
 * @property string $range_end
 * @property string $created_at
 * @property \Infixs\WordpressEloquent\Collection $codes
 * 
 */
class TrackingRange extends Model {
	protected $prefix = 'infixs_correios_automatico_';

	public function codes(): HasMany {
		return $this->hasMany( TrackingRangeCode::class);
	}
}

