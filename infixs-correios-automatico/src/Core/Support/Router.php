<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

defined( 'ABSPATH' ) || exit;

class Router {
	/**
	 * Resolve a path.
	 *
	 * @since 1.0.0
	 * 
	 * @param string $path
	 * @param array $params
	 * @param string $page
	 * 
	 * @return string
	 */
	public static function resolve( $path, $params = [], $page = "infixs-correios-automatico" ) {
		return admin_url( sprintf( 'admin.php?page=%s&path=%s%s', $page, $path, $params ? "&" . http_build_query( $params ) : "" ) );
	}
}