<?php

namespace Infixs\CorreiosAutomatico\Config;

defined( 'ABSPATH' ) || exit;

/**
 * General preferences of a single vendor.
 *
 * Holds what belongs to the vendor Correios setup but has no home in the
 * marketplace plugin itself. The store address is not part of it: that lives in
 * the marketplace, which is why only the extensions know how to read it.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.2
 */
class VendorGeneral {

	/**
	 * Store document (CNPJ/CPF) used as the label and prepost sender.
	 *
	 * Falls back to the contract document, then to the store wide sender.
	 *
	 * @var string
	 */
	public $document;

	/**
	 * @param array $data Stored settings.
	 */
	public function __construct( $data = [] ) {
		$this->document = isset( $data['document'] ) ? $data['document'] : '';
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function toArray() {
		return [
			'document' => $this->document,
		];
	}
}
