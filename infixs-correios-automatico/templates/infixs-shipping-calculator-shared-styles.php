<?php
/**
 * Shared Helper Functions for Inline Styles
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'infixs_get_inline_style_attribute' ) ) {
	/**
	 * Generate inline style attribute for a specific element
	 *
	 * @param string $element_key The element key to get styles for
	 * @param array  $calculator_styles Array of sanitized calculator styles
	 * @param array  $allowed_dimensions Optional. Array of element keys that support width/height
	 * @return string Inline style attribute or empty string
	 */
	function infixs_get_inline_style_attribute( $element_key, $calculator_styles, $allowed_dimensions = [], $extra = [] ) {
		if ( ! isset( $calculator_styles[ $element_key ] ) || ! is_array( $calculator_styles[ $element_key ] ) ) {
			return '';
		}

		$element_styles = $calculator_styles[ $element_key ];
		$style_properties = [];

		// Default allowed dimensions if not specified
		if ( empty( $allowed_dimensions ) ) {
			$allowed_dimensions = [ 'input', 'button', 'result_column' ];
		}


		foreach ( $element_styles as $property => $value ) {
			switch ( $property ) {

				case 'text_color':
					$style_properties[] = 'color: ' . esc_attr( $value );
					break;

				case 'background_color':
					$style_properties[] = 'background-color: ' . esc_attr( $value );
					break;

				case 'border_color':
					$style_properties[] = 'border-color: ' . esc_attr( $value );
					break;

				case 'font_size':
					$style_properties[] = 'font-size: ' . absint( $value ) . 'px';
					break;

				case 'border_size':
					$style_properties[] = 'border-width: ' . absint( $value ) . 'px';
					$style_properties[] = 'border-style: solid';
					break;

				case 'border_radius':
					$style_properties[] = 'border-radius: ' . absint( $value ) . 'px';
					break;

				case 'width':
					if ( in_array( $element_key, $allowed_dimensions, true ) ) {
						$style_properties[] = 'width: ' . absint( $value ) . 'px';
					}
					break;

				case 'height':
					if ( in_array( $element_key, $allowed_dimensions, true ) ) {
						$style_properties[] = 'height: ' . absint( $value ) . 'px';
					}
					break;

				case 'text_decoration':
					if ( is_array( $value ) && ! empty( $value ) ) {
						foreach ( $value as $decoration ) {
							switch ( $decoration ) {
								case 'bold':
									$style_properties[] = 'font-weight: bold';
									break;
								case 'italic':
									$style_properties[] = 'font-style: italic';
									break;
								case 'underline':
									$style_properties[] = 'text-decoration: underline';
									break;
							}
						}
					}
					break;
			}
		}

		if ( ! empty( $extra ) && is_array( $extra ) ) {
			$style_properties = array_merge( $style_properties, $extra );
		}

		if ( empty( $style_properties ) ) {
			return '';
		}

		return 'style="' . esc_attr( implode( '; ', $style_properties ) ) . '"';
	}
}


if ( ! function_exists( 'infixs_get_icon_color_attribute' ) ) {
	/**
	 * Get icon color attribute for SVG elements
	 *
	 * @param array $calculator_styles Calculator styles array
	 * @return string Fill attribute for SVG or empty string
	 */
	function infixs_get_icon_color_attribute( $calculator_styles ) {
		if ( isset( $calculator_styles['input']['icon_color'] ) ) {
			return 'style="color: ' . esc_attr( $calculator_styles['input']['icon_color'] ) . '"';
		}
		return '';
	}
}


if ( ! function_exists( 'infixs_get_result_element_inline_style' ) ) {
	/**
	 * Generate inline style attribute for result elements
	 * This is an alias of infixs_get_inline_style_attribute for backward compatibility
	 *
	 * @param string $element_key The element key to get styles for
	 * @param array  $calculator_styles Array of sanitized calculator styles
	 * @return string Inline style attribute or empty string
	 */
	function infixs_get_result_element_inline_style( $element_key, $calculator_styles ) {
		$result_dimensions = [ 'result_column', 'result_price', 'result_address', 'result_title_column', 'result_delivery_time' ];
		return infixs_get_inline_style_attribute( $element_key, $calculator_styles, $result_dimensions );
	}
}
