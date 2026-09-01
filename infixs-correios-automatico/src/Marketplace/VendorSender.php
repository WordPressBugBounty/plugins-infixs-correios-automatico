<?php

namespace Infixs\CorreiosAutomatico\Marketplace;

use Infixs\CorreiosAutomatico\Config\VendorSettings;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Address;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Person;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the Correios sender out of a marketplace store.
 *
 * Used by the label screen and by the prepost alike, so a vendor always ships
 * from its own address whichever surface asked.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.10.0
 */
class VendorSender {

	/**
	 * Build the sender payload of a vendor.
	 *
	 * @param int                     $vendor_id Vendor ID.
	 * @param MarketplaceAdapter|null $adapter   Marketplace the vendor sells on.
	 *
	 * @return array Empty when no marketplace can describe the vendor.
	 */
	public static function fromVendor( $vendor_id, $adapter = null ) {
		$vendor_id = (int) $vendor_id;

		if ( ! $adapter ) {
			$adapter = MarketplaceRegistry::forCurrentVendor();
		}

		if ( ! $vendor_id || ! $adapter ) {
			return [];
		}

		$store = $adapter->getVendorStoreData( $vendor_id );
		$address = isset( $store['address'] ) && is_array( $store['address'] ) ? $store['address'] : [];

		$shop_name = isset( $store['shop_name'] ) ? $store['shop_name'] : '';
		$owner_name = isset( $store['owner_name'] ) ? $store['owner_name'] : '';
		$name = Settings::boolean( 'show_vendor_name_on_label' ) && $shop_name ? $shop_name : $owner_name;

		$phone = isset( $store['phone'] ) ? $store['phone'] : '';
		$postcode = isset( $address['zip'] ) ? $address['zip'] : '';

		/**
		 * O marketplace guarda um único telefone por loja. Ele só é repetido
		 * como celular quando tem 11 dígitos: repetir um fixo criaria um
		 * celular inexistente na prépostagem.
		 */
		$is_celphone = strlen( Sanitizer::numeric_text( $phone ) ) === 11;

		return [
			'name' => $name ? $name : $shop_name,
			'legal_name' => $shop_name,
			'email' => isset( $store['email'] ) ? $store['email'] : '',
			'phone' => $phone,
			'celphone' => $is_celphone ? $phone : '',
			'document' => self::resolveDocument( $vendor_id ),
			'address_postalcode' => $postcode ? Sanitizer::postcode( $postcode ) : '',
			'address_street' => isset( $address['street_1'] ) ? $address['street_1'] : '',
			'address_complement' => isset( $address['street_2'] ) ? $address['street_2'] : '',
			'address_number' => isset( $address['number'] ) ? $address['number'] : '',
			'address_neighborhood' => isset( $address['neighborhood'] ) ? $address['neighborhood'] : '',
			'address_city' => isset( $address['city'] ) ? $address['city'] : '',
			'address_state' => isset( $address['state'] ) ? $address['state'] : '',
			'address_country' => ! empty( $address['country'] ) ? $address['country'] : 'BR',
		];
	}

	/**
	 * Build the Correios sender object of a vendor.
	 *
	 * @param int                     $vendor_id Vendor ID.
	 * @param MarketplaceAdapter|null $adapter   Marketplace the vendor sells on.
	 *
	 * @return Person|null Null when the store has no usable address.
	 */
	public static function personFromVendor( $vendor_id, $adapter = null ) {
		$data = self::fromVendor( $vendor_id, $adapter );

		if ( empty( $data['address_postalcode'] ) || empty( $data['address_street'] ) ) {
			return null;
		}

		return new Person(
			$data['name'],
			new Address(
				$data['address_postalcode'],
				$data['address_street'],
				$data['address_number'],
				$data['address_complement'],
				$data['address_neighborhood'],
				$data['address_city'],
				$data['address_state'],
				$data['address_country']
			),
			$data['document'],
			$data['phone'],
			$data['celphone'],
			$data['email']
		);
	}

	/**
	 * Resolve the sender document of a vendor.
	 *
	 * Prefers what the vendor typed, then the document of its own contract. The
	 * store document is only the last resort while the store contract is being
	 * lent to the vendor, since that is the one case where the store is who
	 * actually posts the parcel — outside it, printing the marketplace document
	 * on a vendor parcel is a fiscal claim nobody made.
	 *
	 * @param int $vendor_id Vendor ID.
	 *
	 * @return string
	 */
	private static function resolveDocument( $vendor_id ) {
		$settings = VendorSettings::get( $vendor_id );

		if ( ! empty( $settings->general->document ) ) {
			return $settings->general->document;
		}

		if ( ! empty( $settings->auth->contract_document ) ) {
			return $settings->auth->contract_document;
		}

		if ( Settings::lendsStoreContract() ) {
			return Config::string( 'sender.document' );
		}

		return '';
	}
}
