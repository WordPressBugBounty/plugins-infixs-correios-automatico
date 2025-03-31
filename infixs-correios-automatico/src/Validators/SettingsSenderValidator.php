<?php

namespace Infixs\CorreiosAutomatico\Validators;

use Infixs\CorreiosAutomatico\Core\Support\Validator;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class SettingsSenderValidator extends Validator {
	/**
	 * Get the validation rules.
	 * 
	 * @since 1.0.0
	 * 
	 * @return array
	 */
	public function rules() {
		$rules = [ 
			'name' => 'required|min:3|max:50',
			'email' => 'required|email',
			'address_street' => 'required|max:50',
			'address_complement' => 'max:30',
			'address_number' => 'required|max:6',
			'address_neighborhood' => 'required|max:30',
			'address_city' => 'required|max:30',
			'address_country' => 'required|size:2',
		];

		if ( $this->getFieldValue( 'address_country' ) === 'BR' ) {
			$rules['document'] = 'required|cpfcnpj';
			$rules['celphone'] = 'required|celphone';
			$rules['phone'] = 'phone|empty';
			$rules['address_state'] = 'required|size:2';
			$rules['address_postalcode'] = 'required|cep';
		} elseif ( $this->getFieldValue( 'address_country' ) === 'PY' ) {
			$rules['address_postalcode'] = 'required|size:6';
		} else {
			$rules['address_postalcode'] = 'max:12';
		}

		return $rules;
	}

	public function prepareForValidation() {
		$this->merge( [ 
			'address_postalcode' => Sanitizer::numeric_text( $this->getFieldValue( 'address_postalcode' ) ),
		] );
	}

	public function phone( $field ) {
		if ( ! preg_match( '/^\(\d{2}\) \d{4}-\d{4}$/', $this->data[ $field ] ) ) {
			$this->errors[ $field ] = "O campo é inválido.";
		}
	}

	public function celphone( $field ) {
		if ( ! preg_match( '/^\(\d{2}\) \d{5}-\d{4}$/', $this->data[ $field ] ) ) {
			$this->errors[ $field ] = "O campo é inválido.";
		}
	}

	public function cep( $field ) {
		if ( ! preg_match( '/^\d{5}-?\d{3}$/', $this->data[ $field ] ) ) {
			$this->errors[ $field ] = "CEP inválido.";
		}
	}

	public function cpfcnpj( $field ) {
		if ( ! preg_match( '/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', $this->data[ $field ] ) && ! preg_match( '/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}$/', $this->data[ $field ] ) ) {
			$this->errors[ $field ] = "CPF ou CNPJ inválido.";
			return;
		}

		if ( preg_match( '/^\d{3}\.?\d{3}\.?\d{3}-?\d{2}$/', $this->data[ $field ] ) && ! $this->validateCpf( $this->data[ $field ] ) ) {
			$this->errors[ $field ] = "CPF inválido.";
			return;
		}

		if ( preg_match( '/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}$/', $this->data[ $field ] ) && ! $this->validateCnpj( $this->data[ $field ] ) ) {
			$this->errors[ $field ] = "CNPJ inválido.";
			return;
		}
	}

	public function validateCpf( $value ) {
		$cpf = preg_replace( '/[^0-9]/is', '', $value );

		if ( strlen( $cpf ) != 11 ) {
			return false;
		}

		if ( preg_match( '/(\d)\1{10}/', $cpf ) ) {
			return false;
		}

		for ( $t = 9; $t < 11; $t++ ) {
			for ( $d = 0, $c = 0; $c < $t; $c++ ) {
				$d += $cpf[ $c ] * ( ( $t + 1 ) - $c );
			}
			$d = ( ( 10 * $d ) % 11 ) % 10;
			if ( $cpf[ $c ] != $d ) {
				return false;
			}
		}
		return true;
	}

	public function validateCnpj( $value ) {
		$cnpj = preg_replace( '/[^0-9]/', '', (string) $value );

		if ( strlen( $cnpj ) != 14 )
			return false;

		if ( preg_match( '/(\d)\1{13}/', $cnpj ) )
			return false;

		for ( $i = 0, $j = 5, $soma = 0; $i < 12; $i++ ) {
			$soma += $cnpj[ $i ] * $j;
			$j = ( $j == 2 ) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		if ( $cnpj[12] != ( $resto < 2 ? 0 : 11 - $resto ) )
			return false;

		for ( $i = 0, $j = 6, $soma = 0; $i < 13; $i++ ) {
			$soma += $cnpj[ $i ] * $j;
			$j = ( $j == 2 ) ? 9 : $j - 1;
		}

		$resto = $soma % 11;

		return $cnpj[13] == ( $resto < 2 ? 0 : 11 - $resto );
	}
}