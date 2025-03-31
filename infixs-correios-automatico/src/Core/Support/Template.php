<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

defined( 'ABSPATH' ) || exit;

class Template {

	public static function loadComponent( $path, $params = [], $base = null ) {
		extract( $params );

		$base ??= \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/components/';

		$full_path = "{$base}{$path}";
		if ( file_exists( $full_path ) ) {
			include $full_path;
		}
	}

	public static function adminView( $path, $params = [], $base = null ) {
		extract( $params );

		$base ??= \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/';

		$full_path = "{$base}{$path}";
		if ( file_exists( $full_path ) ) {
			include $full_path;
		}
	}
}