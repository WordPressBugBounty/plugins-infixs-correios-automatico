<?php

namespace Infixs\CorreiosAutomatico\Controllers\Sanitizers;

defined( 'ABSPATH' ) || exit;

/**
 * Calculator Styles Sanitizer
 * 
 * @since 1.0.0
 */
class CalculatorStylesSanitizer {

	/**
	 * Valid calculator elements
	 * 
	 * @var array
	 */
	private static $valid_elements = [ 
		'title',
		'result_column',
		'result_price',
		'icon',
		'input',
		'button',
		'find_postcode',
		'result_address',
		'result_title_column',
		'result_table_header',
		'result_delivery_time'
	];

	/**
	 * Valid text decorations
	 * 
	 * @var array
	 */
	private static $valid_text_decorations = [ 
		'bold',
		'italic',
		'underline'
	];

	/**
	 * Style limits
	 * 
	 * @var array
	 */
	private static $limits = [ 
		'font_size' => [ 'min' => 8, 'max' => 72 ],
		'border_size' => [ 'min' => 0, 'max' => 20 ],
		'border_radius' => [ 'min' => 0, 'max' => 100 ],
		'width' => [ 'min' => 10, 'max' => 1000 ],
		'height' => [ 'min' => 10, 'max' => 1000 ]
	];



	/**
	 * Sanitize calculator styles
	 * 
	 * @param array $calculator_styles
	 * @return array
	 */
	public static function sanitize( $calculator_styles ) {
		if ( ! is_array( $calculator_styles ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $calculator_styles as $element_key => $element_style ) {
			if ( ! self::isValidElement( $element_key ) ) {
				continue;
			}

			if ( ! is_array( $element_style ) ) {
				continue;
			}

			$sanitized[ $element_key ] = self::sanitizeElementStyle( $element_style );
		}

		return $sanitized;
	}

	/**
	 * Sanitize individual element style
	 * 
	 * @param array $style
	 * @return array
	 */
	private static function sanitizeElementStyle( $style ) {
		if ( ! is_array( $style ) ) {
			return [];
		}

		$sanitized = [];

		// Sanitize each property
		$properties = [ 
			'icon' => 'sanitizeTextField',
			'icon_color' => 'sanitizeHexColor',
			'background_color' => 'sanitizeHexColor',
			'text_color' => 'sanitizeHexColor',
			'border_color' => 'sanitizeHexColor',
			'text_decoration' => 'sanitizeTextDecoration',
			'font_size' => 'sanitizeFontSize',
			'border_size' => 'sanitizeBorderSize',
			'border_radius' => 'sanitizeBorderRadius',
			'width' => 'sanitizeWidth',
			'height' => 'sanitizeHeight'
		];

		foreach ( $properties as $property => $sanitizer ) {
			if ( isset( $style[ $property ] ) ) {
				$sanitized_value = self::$sanitizer( $style[ $property ] );

				// Only include the property if sanitization returned a valid value
				if ( $sanitized_value !== null && $sanitized_value !== false ) {
					$sanitized[ $property ] = $sanitized_value;
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize text field
	 * 
	 * @param string $text
	 * @return string|null
	 */
	private static function sanitizeTextField( $text ) {
		$sanitized = sanitize_text_field( $text );
		return empty( $sanitized ) ? null : $sanitized;
	}
	/**
	 * Check if element is valid
	 * 
	 * @param string $element
	 * @return bool
	 */
	private static function isValidElement( $element ) {
		return in_array( $element, self::$valid_elements, true );
	}

	/**
	 * Sanitize hex color
	 * 
	 * @param string $color
	 * @return string|null
	 */
	private static function sanitizeHexColor( $color ) {
		$color = sanitize_text_field( $color );

		// Remove # if exists
		$color = ltrim( $color, '#' );

		// Check if valid hex (3 or 6 characters)
		if ( preg_match( '/^([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
			return '#' . $color;
		}

		return null;
	}

	/**
	 * Sanitize text decoration
	 * 
	 * @param array $decorations
	 * @return array|null
	 */
	private static function sanitizeTextDecoration( $decorations ) {
		if ( ! is_array( $decorations ) ) {
			return null;
		}

		$sanitized = [];

		foreach ( $decorations as $decoration ) {
			$decoration = sanitize_text_field( $decoration );
			if ( in_array( $decoration, self::$valid_text_decorations, true ) ) {
				$sanitized[] = $decoration;
			}
		}

		$result = array_unique( $sanitized );
		return empty( $result ) ? [] : $result;
	}

	/**
	 * Sanitize font size
	 * 
	 * @param mixed $size
	 * @return int
	 */
	private static function sanitizeFontSize( $size ) {
		return self::sanitizeNumericValue( $size, 'font_size' );
	}

	/**
	 * Sanitize border size
	 * 
	 * @param mixed $size
	 * @return int
	 */
	private static function sanitizeBorderSize( $size ) {
		return self::sanitizeNumericValue( $size, 'border_size' );
	}

	/**
	 * Sanitize border radius
	 * 
	 * @param mixed $radius
	 * @return int
	 */
	private static function sanitizeBorderRadius( $radius ) {
		return self::sanitizeNumericValue( $radius, 'border_radius' );
	}

	/**
	 * Sanitize width
	 * 
	 * @param mixed $width
	 * @return int
	 */
	private static function sanitizeWidth( $width ) {
		return self::sanitizeNumericValue( $width, 'width' );
	}

	/**
	 * Sanitize height
	 * 
	 * @param mixed $height
	 * @return int
	 */
	private static function sanitizeHeight( $height ) {
		return self::sanitizeNumericValue( $height, 'height' );
	}

	/**
	 * Sanitize numeric value with limits
	 * 
	 * @param mixed $value
	 * @param string $type
	 * @return int|null
	 */
	private static function sanitizeNumericValue( $value, $type ) {
		$value = absint( $value );

		$limits = isset( self::$limits[ $type ] ) ? self::$limits[ $type ] : [ 'min' => 0, 'max' => 1000 ];

		if ( $value < $limits['min'] || $value > $limits['max'] ) {
			return null;
		}

		return $value;
	}

	/**
	 * Validate calculator style ID
	 * 
	 * @param string $style_id
	 * @return string
	 */
	public static function sanitizeCalculatorStyleId( $style_id ) {
		$valid_styles = [ 'default', 'custom' ];
		$style_id = sanitize_text_field( $style_id );

		return in_array( $style_id, $valid_styles, true ) ? $style_id : 'default';
	}

	/**
	 * Get valid elements list
	 * 
	 * @return array
	 */
	public static function getValidElements() {
		return self::$valid_elements;
	}
}