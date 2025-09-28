<?php

namespace Infixs\CorreiosAutomatico\Controllers\Rest;

use Infixs\CorreiosAutomatico\Container;

defined( 'ABSPATH' ) || exit;
class PrintController {
	/**
	 * Settings service instance.
	 * 
	 * @since 1.6.43
	 * 
	 * @var \Infixs\CorreiosAutomatico\Services\SettingsService
	 */
	private $settingsService;

	public function __construct( $settingsService ) {
		$this->settingsService = $settingsService;
	}

	public function getPrintData() {
		return rest_ensure_response( [
			'sender' => $this->settingsService->getSenderData()
		] );
	}
}