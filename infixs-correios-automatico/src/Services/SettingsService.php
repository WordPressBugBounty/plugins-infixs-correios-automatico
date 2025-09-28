<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Support\Config;

defined( 'ABSPATH' ) || exit;

class SettingsService {
	public function getSenderData() {
		$sanitized_settings = [
			'name' => Config::string( 'sender.name' ),
			'legal_name' => Config::string( 'sender.legal_name' ),
			'email' => Config::string( 'sender.email' ),
			'phone' => Config::string( 'sender.phone' ),
			'celphone' => Config::string( 'sender.celphone' ),
			'document' => Config::string( 'sender.document' ),
			'address_postalcode' => Config::string( 'sender.address_postalcode' ),
			'address_street' => Config::string( 'sender.address_street' ),
			'address_complement' => Config::string( 'sender.address_complement' ),
			'address_number' => Config::string( 'sender.address_number' ),
			'address_neighborhood' => Config::string( 'sender.address_neighborhood' ),
			'address_city' => Config::string( 'sender.address_city' ),
			'address_state' => Config::string( 'sender.address_state' ),
			'address_country' => Config::string( 'sender.address_country' ),
		];

		return $sanitized_settings;
	}
}