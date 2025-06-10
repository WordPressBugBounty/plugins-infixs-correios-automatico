<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;
use Infixs\WordpressEloquent\Relations\HasMany;

defined( 'ABSPATH' ) || exit;

/**
 * TrackingCode model.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 * 
 * @property int $id
 * @property int $order_id
 * @property int $user_id
 * @property int $unit_id
 * @property int $tracking_range_code_id
 * @property string $code
 * @property string $description
 * @property string $category
 * @property string $expected_date
 * @property string $sync_at
 * @property string $customer_email_at
 * @property string $updated_at
 * @property string $created_at
 * @property \Infixs\WordpressEloquent\Collection $events
 */
class TrackingCode extends Model {
	protected $prefix = 'infixs_correios_automatico_';

	public function events(): HasMany {
		return $this->hasMany( TrackingCodeEvent::class);
	}
}

