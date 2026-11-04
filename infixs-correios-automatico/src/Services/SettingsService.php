<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class SettingsService {

	/**
	 * Whether the store sender is a company or a person.
	 *
	 * Stores saved before 1.14.0 have no choice stored: a CPF in the document
	 * reads as a person, anything else as a company, the default.
	 *
	 * @since 1.14.0
	 *
	 * @return string `company` or `person`.
	 */
	public static function senderPersonType() {
		$type = Config::string( 'sender.person_type' );

		if ( in_array( $type, [ 'company', 'person' ], true ) ) {
			return $type;
		}

		return 11 === strlen( Sanitizer::numeric_text( Config::string( 'sender.document' ) ) ) ? 'person' : 'company';
	}

	public function getSenderData() {
		$sanitized_settings = [
			'name' => Config::string( 'sender.name' ),
			'legal_name' => Config::string( 'sender.legal_name' ),
			'email' => Config::string( 'sender.email' ),
			'phone' => Config::string( 'sender.phone' ),
			'celphone' => Config::string( 'sender.celphone' ),
			'document' => Config::string( 'sender.document' ),
			'person_type' => self::senderPersonType(),
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