<?php
/**
 * Calculator Styles Helper Class
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calculator Styles Helper Class
 * 
 * Modern approach using static class methods to avoid function name conflicts
 * and provide better organization of style-related functionality.
 * 
 * @since 1.0.1
 */
class InfixsCalculatorStylesHelper {

	/**
	 * Default allowed dimensions for elements
	 * 
	 * @var array
	 */
	private static $default_dimensions = [ 'input', 'button', 'result_column' ];

	/**
	 * Result elements that support dimensions
	 * 
	 * @var array
	 */
	private static $result_dimensions = [ 
		'result_column',
		'result_price',
		'result_address',
		'result_title_column',
		'result_delivery_time'
	];

	/**
	 * Generate inline style attribute for a specific element
	 *
	 * @param string $element_key The element key to get styles for
	 * @param array  $calculator_styles Array of sanitized calculator styles
	 * @param array  $allowed_dimensions Optional. Array of element keys that support width/height
	 * @param array  $extra Optional. Additional CSS properties to add
	 * @return string Inline style attribute or empty string
	 */
	public static function getInlineStyleAttribute( $element_key, $calculator_styles, $allowed_dimensions = [], $extra = [] ) {
		if ( ! isset( $calculator_styles[ $element_key ] ) || ! is_array( $calculator_styles[ $element_key ] ) ) {
			return '';
		}

		$element_styles = $calculator_styles[ $element_key ];
		$style_properties = [];

		// Use default dimensions if not specified
		if ( empty( $allowed_dimensions ) ) {
			$allowed_dimensions = self::$default_dimensions;
		}

		// Process each style property
		foreach ( $element_styles as $property => $value ) {
			$processed_styles = self::processStyleProperty( $property, $value, $element_key, $allowed_dimensions );
			if ( ! empty( $processed_styles ) ) {
				$style_properties = array_merge( $style_properties, $processed_styles );
			}
		}

		// Add extra styles if provided
		if ( ! empty( $extra ) && is_array( $extra ) ) {
			$style_properties = array_merge( $style_properties, $extra );
		}

		if ( empty( $style_properties ) ) {
			return '';
		}

		return 'style="' . esc_attr( implode( '; ', $style_properties ) ) . '"';
	}

	/**
	 * Process individual style property
	 * 
	 * @param string $property Style property name
	 * @param mixed  $value Property value
	 * @param string $element_key Element key
	 * @param array  $allowed_dimensions Allowed dimensions for this element
	 * @return array Array of CSS properties
	 */
	private static function processStyleProperty( $property, $value, $element_key, $allowed_dimensions ) {
		$style_properties = [];

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
					$style_properties = array_merge( $style_properties, self::processTextDecorations( $value ) );
				}
				break;
		}

		return $style_properties;
	}

	/**
	 * Process text decorations
	 * 
	 * @param array $decorations Array of text decorations
	 * @return array Array of CSS properties
	 */
	private static function processTextDecorations( $decorations ) {
		$style_properties = [];

		foreach ( $decorations as $decoration ) {
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

		return $style_properties;
	}

	/**
	 * Get icon color attribute for SVG elements
	 *
	 * @param array $calculator_styles Calculator styles array
	 * @param string $element_key Optional. Element key to get icon color from (default: 'input')
	 * @return string Color style attribute for SVG or empty string
	 */
	public static function getIconColorAttribute( $calculator_styles, $element_key = 'input' ) {
		if ( isset( $calculator_styles[ $element_key ]['icon_color'] ) ) {
			return 'style="color: ' . esc_attr( $calculator_styles[ $element_key ]['icon_color'] ) . '"';
		}
		return '';
	}

	/**
	 * Generate inline style attribute for result elements
	 * 
	 * @param string $element_key The element key to get styles for
	 * @param array  $calculator_styles Array of sanitized calculator styles
	 * @param array  $extra Optional. Additional CSS properties to add
	 * @return string Inline style attribute or empty string
	 */
	public static function getResultElementInlineStyle( $element_key, $calculator_styles, $extra = [] ) {
		return self::getInlineStyleAttribute( $element_key, $calculator_styles, self::$result_dimensions, $extra );
	}

	/**
	 * Get icon HTML with proper styling
	 * 
	 * @param string $icon_id Icon ID
	 * @param array  $calculator_styles Calculator styles array
	 * @param string $element_key Element key to get styles from
	 * @param array  $attributes Additional HTML attributes
	 * @return string Icon HTML or empty string
	 */
	public static function getIconHtml( $icon_id, $calculator_styles, $element_key = 'input', $attributes = [] ) {
		// Get icon content (you'll need to implement this based on your icons system)
		$icon_content = self::getIconContent( $icon_id );

		if ( empty( $icon_content ) ) {
			return '';
		}

		// Build attributes
		$attr_strings = [];

		// Add color styling
		$color_attr = self::getIconColorAttribute( $calculator_styles, $element_key );
		if ( ! empty( $color_attr ) ) {
			$attr_strings[] = $color_attr;
		}

		// Add additional attributes
		foreach ( $attributes as $attr => $value ) {
			$attr_strings[] = sprintf( '%s="%s"', esc_attr( $attr ), esc_attr( $value ) );
		}

		$attributes_string = ! empty( $attr_strings ) ? ' ' . implode( ' ', $attr_strings ) : '';

		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="%s"%s>%s</svg>',
			esc_attr( $icon_content['viewBox'] ?? '0 0 24 24' ),
			$attributes_string,
			$icon_content['content'] ?? ''
		);
	}

	/**
	 * Get icon content by ID
	 * 
	 * @param string $icon_id Icon ID
	 * @return array Icon data with viewBox and content
	 */
	private static function getIconContent( $icon_id ) {
		// This should be implemented based on your icon system
		// For now, returning empty array as placeholder
		return [];
	}

	/**
	 * Check if element supports dimensions
	 * 
	 * @param string $element_key Element key
	 * @param array  $allowed_dimensions Optional. Custom allowed dimensions
	 * @return bool
	 */
	public static function supportsDimensions( $element_key, $allowed_dimensions = [] ) {
		if ( empty( $allowed_dimensions ) ) {
			$allowed_dimensions = self::$default_dimensions;
		}

		return in_array( $element_key, $allowed_dimensions, true );
	}

	/**
	 * Get default dimensions for different element types
	 * 
	 * @param string $type Element type ('default' or 'result')
	 * @return array
	 */
	public static function getDefaultDimensions( $type = 'default' ) {
		switch ( $type ) {
			case 'result':
				return self::$result_dimensions;
			default:
				return self::$default_dimensions;
		}
	}
}

// Backward compatibility functions
if ( ! function_exists( 'infixs_get_inline_style_attribute' ) ) {
	/**
	 * @deprecated Use InfixsCalculatorStylesHelper::getInlineStyleAttribute() instead
	 */
	function infixs_get_inline_style_attribute( $element_key, $calculator_styles, $allowed_dimensions = [], $extra = [] ) {
		return InfixsCalculatorStylesHelper::getInlineStyleAttribute( $element_key, $calculator_styles, $allowed_dimensions, $extra );
	}
}

if ( ! function_exists( 'infixs_get_icon_color_attribute' ) ) {
	/**
	 * @deprecated Use InfixsCalculatorStylesHelper::getIconColorAttribute() instead
	 */
	function infixs_get_icon_color_attribute( $calculator_styles ) {
		return InfixsCalculatorStylesHelper::getIconColorAttribute( $calculator_styles );
	}
}

if ( ! function_exists( 'infixs_get_result_element_inline_style' ) ) {
	/**
	 * @deprecated Use InfixsCalculatorStylesHelper::getResultElementInlineStyle() instead
	 */
	function infixs_get_result_element_inline_style( $element_key, $calculator_styles, $extra = [] ) {
		return InfixsCalculatorStylesHelper::getResultElementInlineStyle( $element_key, $calculator_styles, $extra );
	}
}
