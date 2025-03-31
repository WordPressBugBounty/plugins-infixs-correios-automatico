<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;
use Infixs\WordpressEloquent\Relations\BelongsTo;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking Range Code model.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.3.7
 * 
 * @property int $id
 * @property int $tracking_range_id
 * @property string $code
 * @property int $order_id
 * @property bool $is_used
 * 
 */
class TrackingRangeCode extends Model {
	protected $prefix = 'infixs_correios_automatico_';

	public function range(): BelongsTo {
		return $this->belongsTo( TrackingRange::class);
	}
}

