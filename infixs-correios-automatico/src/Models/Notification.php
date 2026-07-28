<?php

namespace Infixs\CorreiosAutomatico\Models;

use Infixs\WordpressEloquent\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Notification model.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.0
 *
 * @property int $id
 * @property string $type
 * @property string $level
 * @property string $title
 * @property string $message
 * @property string $context
 * @property string $dedupe_key
 * @property bool $is_read
 * @property string $read_at
 * @property string $created_at
 * @property string $updated_at
 */
class Notification extends Model {
	protected $prefix = 'infixs_correios_automatico_';
}
