<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

defined( 'ABSPATH' ) || exit;

abstract class Validator {

	/**
	 * Data
	 * 
	 * @var array
	 */
	protected $data;

	protected $errors = [];

	/**
	 * Validator constructor
	 * 
	 * @param \WP_REST_Request $request
	 */
	public function __construct( $request ) {
		$this->data = $request->get_json_params();
	}

	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array<string, array<mixed>|string>
	 */
	public function rules() {
		return [];
	}

	public function all() {
		return $this->data;
	}

	/**
	 * Validate the request with the given rules.
	 * 
	 * @return void
	 */
	public function validate() {
		$this->prepareForValidation();

		$rules = $this->rules();

		foreach ( $rules as $field => $rule ) {
			$rules = explode( '|', $rule );

			if ( in_array( 'empty', $rules ) && ( ! isset( $this->data[ $field ] ) || $this->data[ $field ] === null || $this->data[ $field ] === '' ) ) {
				continue;
			}

			foreach ( $rules as $rule ) {
				$rule = explode( ':', $rule );

				$method = $rule[0];
				$parameters = isset( $rule[1] ) ? explode( ',', $rule[1] ) : [];

				if ( ! method_exists( $this, $method ) ) {
					continue;
				}

				call_user_func_array( [ $this, $method ], [ $field, ...$parameters ] );
			}
		}
	}

	public function errors() {
		return $this->errors;
	}

	public function hasErrors() {
		return ! empty( $this->errors );
	}

	protected function required( $field ) {
		if ( ! isset( $this->data[ $field ] ) || $this->data[ $field ] === null || $this->data[ $field ] === '' ) {
			$this->errors[ $field ] = "The field {$field} is required.";
		}
	}

	protected function email( $field ) {
		if ( ! filter_var( $this->data[ $field ], FILTER_VALIDATE_EMAIL ) ) {
			$this->errors[ $field ] = "The field {$field} must be a valid email address.";
		}
	}

	protected function min( $field, $length ) {
		if ( strlen( $this->data[ $field ] ) < $length ) {
			$this->errors[ $field ] = "The field {$field} must be at least {$length} characters.";
		}
	}

	protected function max( $field, $length ) {
		if ( strlen( $this->data[ $field ] ) > $length ) {
			$this->errors[ $field ] = "The field {$field} may not be greater than {$length} characters.";
		}
	}

	protected function size( $field, $size ) {
		if ( strlen( $this->data[ $field ] ) !== (int) $size ) {
			$this->errors[ $field ] = "The field must be {$size} characters.";
		}
	}

	protected function integer( $field ) {
		if ( ! filter_var( $this->data[ $field ], FILTER_VALIDATE_INT ) ) {
			$this->errors[ $field ] = "The field {$field} must be an integer.";
		}
	}


	protected function merge( $fields ) {
		$this->data = array_merge( $this->data, $fields );
	}

	protected function prepareForValidation() {
		// Prepare the data for validation
	}

	/**
	 * Get the data
	 * 
	 * @return array
	 */
	protected function getData() {
		return $this->data;
	}

	/**
	 * Get the value of a given field
	 * 
	 * @param string $field
	 * @return mixed
	 */
	protected function getFieldValue( $field ) {
		return $this->data[ $field ] ?? null;
	}
}