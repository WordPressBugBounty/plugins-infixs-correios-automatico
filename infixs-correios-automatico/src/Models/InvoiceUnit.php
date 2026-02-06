<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;
use Infixs\WordpressEloquent\Relations\HasMany;

defined( 'ABSPATH' ) || exit;

/**
 * Invoice Unit model.
 * 
 * Invoice Unit Group.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.5.7
 * 
 * @property int $id
 * @property string $request_id
 * @property string $status
 * @property string $service_code
 * @property string $cn38_code
 * @property string $contract_number
 * @property string $created_at
 * @property string $updated_at
 * @property-read \Infixs\WordpressEloquent\Collection|\Infixs\CorreiosAutomatico\Models\Unit[] $units
 */
class InvoiceUnit extends Model {
	protected $prefix = 'infixs_correios_automatico_';

	public function units(): HasMany {
		return $this->hasMany( Unit::class);
	}
}

