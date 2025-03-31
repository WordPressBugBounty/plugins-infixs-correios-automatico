<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Prepost model.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 * 
 * @property int $id
 * @property int $order_id
 * @property string $external_id
 * @property string $object_code
 * @property string $service_code
 * @property string $payment_type
 * @property string $status
 * @property string $status_label
 * @property string $expire_at
 * @property string $updated_at
 * @property string $created_at
 * @property string $cancelled_at
 * 
 */
class Prepost extends Model {
	protected $prefix = 'infixs_correios_automatico_';
}

