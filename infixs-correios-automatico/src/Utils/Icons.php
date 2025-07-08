<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Truck Icons Utility Class
 * 
 * Maps truck icons for use in PHP templates
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.6.1
 */
class Icons {

	/**
	 * Get all available truck icons
	 * 
	 * @return array Array of truck icons with id, name, content
	 */
	public static function getAll() {
		return apply_filters( 'infixs_correios_automatico_icons', [ 
			[ 
				'id' => 'truck-01',
				'name' => 'Truck 01',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 20 20"><path fill="currentColor" d="M1.5 7.882V4.118a1 1 0 0 1 .553-.894l3-1.5a1 1 0 0 1 .894 0l3 1.5a1 1 0 0 1 .553.894v3.764a1 1 0 0 1-.553.895l-3 1.5a1 1 0 0 1-.894 0l-3-1.5a1 1 0 0 1-.553-.895m1.04-3.576a.5.5 0 0 0 .266.655L5 5.887V8.5a.5.5 0 1 0 1 0V5.887l2.194-.926a.5.5 0 0 0-.389-.921L5.5 5.013L3.194 4.04a.5.5 0 0 0-.655.266m-.498 9.944V9.89l1 .5v3.86c0 .415.336.75.75.75h.259a2.5 2.5 0 0 1 4.9 0h1.1A2.5 2.5 0 0 1 13 13.05v-8.3a.75.75 0 0 0-.75-.75h-1.754a2 2 0 0 0-.338-1h2.092c.966 0 1.75.784 1.75 1.75V6h.881a1.5 1.5 0 0 1 1.342.83l1.618 3.235c.104.209.159.438.159.671V14.5a1.5 1.5 0 0 1-1.5 1.5h-1.55a2.5 2.5 0 0 1-4.9 0h-1.1a2.5 2.5 0 0 1-4.9 0h-.259a1.75 1.75 0 0 1-1.75-1.75M14.95 15h1.55a.5.5 0 0 0 .5-.5V11h-3v2.5c.48.36.827.89.95 1.5m1.742-5L15.33 7.277A.5.5 0 0 0 14.883 7H14v3zM5 15.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 0 0-3 0m7.5 1.5a1.5 1.5 0 1 0 0-3a1.5 1.5 0 0 0 0 3"></path></svg>',
			],
			[ 
				'id' => 'truck-13',
				'name' => 'Truck 13',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" color="currentColor"><path d="M19.5 17.5a2.5 2.5 0 1 1-5 0a2.5 2.5 0 0 1 5 0m-10 0a2.5 2.5 0 1 1-5 0a2.5 2.5 0 0 1 5 0"></path><path d="M14.5 17.5h-5m10 0h.763c.22 0 .33 0 .422-.012a1.5 1.5 0 0 0 1.303-1.302c.012-.093.012-.203.012-.423V13a6.5 6.5 0 0 0-6.5-6.5M2 4h10c1.414 0 2.121 0 2.56.44C15 4.878 15 5.585 15 7v8.5M2 12.75V15c0 .935 0 1.402.201 1.75a1.5 1.5 0 0 0 .549.549c.348.201.815.201 1.75.201M2 7h6m-6 3h4"></path></g></svg>',
			],
			[ 
				'id' => 'truck-14',
				'name' => 'Truck 14',
				'content' => '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 20 20"><g fill="currentColor"><path d="M8 16.5a1.5 1.5 0 1 1-3 0a1.5 1.5 0 0 1 3 0m7 0a1.5 1.5 0 1 1-3 0a1.5 1.5 0 0 1 3 0"></path><path d="M3 4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h1.05a2.5 2.5 0 0 1 4.9 0H10a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1zm11 3a1 1 0 0 0-1 1v6.05q.243-.05.5-.05a2.5 2.5 0 0 1 2.45 2H17a1 1 0 0 0 1-1v-5a1 1 0 0 0-.293-.707l-2-2A1 1 0 0 0 15 7z"></path></g></svg>',
			],
		]
		);
	}

	/**
	 * Get icon by ID
	 * 
	 * @param string $icon_id Icon ID to retrieve
	 * @return array|null Icon data or null if not found
	 */
	public static function getById( $icon_id ) {
		$icons = self::getAll();

		foreach ( $icons as $icon ) {
			if ( $icon['id'] === $icon_id ) {
				return $icon;
			}
		}

		return null;
	}

	/**
	 * Get icon content/SVG by ID
	 * 
	 * @param string $icon_id Icon ID
	 * @return string|null SVG content or null if not found
	 */
	public static function getIconContent( $icon_id ) {
		$icon = self::getById( $icon_id );

		return $icon ? $icon['content'] : null;
	}

	/**
	 * Check if icon exists
	 * 
	 * @param string $icon_id Icon ID to check
	 * @return bool True if icon exists
	 */
	public static function iconExists( $icon_id ) {
		return self::getById( $icon_id ) !== null;
	}

	/**
	 * Get icon options for select fields
	 * 
	 * @return array Array of icon options [id => name]
	 */
	public static function getIconOptions() {
		$icons = self::getAll();
		$options = [];

		foreach ( $icons as $icon ) {
			$options[ $icon['id'] ] = $icon['name'];
		}

		return $options;
	}

	public static function esc_svg( $svg_content ) {
		$allowed_svg = [ 
			'svg' => [ 
				'class' => true,
				'aria-hidden' => true,
				'aria-labelledby' => true,
				'role' => true,
				'xmlns' => true,
				'width' => true,
				'height' => true,
				'viewbox' => true,
				'viewBox' => true,
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
				'stroke-linecap' => true,
				'stroke-linejoin' => true,
				'color' => true,
			],
			'g' => [ 
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
				'stroke-linecap' => true,
				'stroke-linejoin' => true,
				'color' => true,
			],
			'title' => [],
			'path' => [ 
				'd' => true,
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
				'stroke-linecap' => true,
				'stroke-linejoin' => true,
				'color' => true,
			],
			'circle' => [ 
				'cx' => true,
				'cy' => true,
				'r' => true,
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
				'color' => true,
			],
			'rect' => [ 
				'x' => true,
				'y' => true,
				'width' => true,
				'height' => true,
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
				'color' => true,
			],
			'polygon' => [ 
				'points' => true,
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
				'color' => true,
			],
			'polyline' => [ 
				'points' => true,
				'fill' => true,
				'stroke' => true,
				'stroke-width' => true,
				'color' => true,
			],
		];

		return wp_kses( $svg_content, $allowed_svg );
	}
}