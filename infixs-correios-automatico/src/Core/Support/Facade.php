<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

use Infixs\CorreiosAutomatico\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Facades class.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
abstract class Facade {

	/**
	 * Resolve the facade root instance from the container.
	 *
	 * @param  string  $name
	 * @return mixed
	 */
	protected static function resolveFacadeInstance( $name ) {
		return Container::$name();
	}

	/**
	 * Get the root object behind the facade.
	 *
	 * @return mixed
	 */
	public static function getFacadeRoot() {
		return static::resolveFacadeInstance( static::getFacadeAccessor() );
	}

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 *
	 * @throws \RuntimeException
	 */
	protected static function getFacadeAccessor() {

		throw new \RuntimeException( 'Facade does not implement getFacadeAccessor method.' );
	}

	/**
	 * Handle dynamic, static calls to the object.
	 *
	 * @param  string  $method
	 * @param  array  $args
	 * @return mixed
	 *
	 * @throws \RuntimeException
	 */
	public static function __callStatic( $method, $args ) {
		$instance = static::getFacadeRoot();

		if ( ! $instance ) {
			throw new \RuntimeException( 'A facade root has not been set.' );
		}

		return $instance->$method( ...$args );
	}
}