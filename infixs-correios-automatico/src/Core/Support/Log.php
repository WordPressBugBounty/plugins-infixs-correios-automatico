<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

use Infixs\CorreiosAutomatico\Core\Support\Facade;

defined( 'ABSPATH' ) || exit;

/**
 * @method static mixed debug(string $message, array $context = [])
 * @method static mixed info(string $message, array $context = [])
 * @method static mixed notice(string $message, array $context = [])
 * @method static mixed warning(string $message, array $context = [])
 * @method static mixed error(string $message, array $context = [])
 * @method static mixed critical(string $message, array $context = [])
 * @method static mixed alert(string $message, array $context = [])
 * @method static mixed emergency(string $message, array $context = [])
 *
 * @see \Infixs\CorreiosAutomatico\Repositories\LogRepository
 */
class Log extends Facade {

	protected static function getFacadeAccessor() {
		return 'logRepository';
	}
}