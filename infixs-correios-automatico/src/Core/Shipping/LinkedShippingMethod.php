<?php

namespace Infixs\CorreiosAutomatico\Core\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Links a native WooCommerce shipping method to a Correios Automático method.
 *
 * Shipping methods like "Frete Grátis" (free_shipping) and "Preço Fixo" (flat_rate)
 * carry no Correios service, so orders placed with them cannot be pre-posted. The
 * merchant links one of the zone's Correios methods to the native method, and that
 * linked method then answers for the service code, object type and package settings.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.8.0
 */
class LinkedShippingMethod {

	/**
	 * Setting key holding the linked Correios instance id, stored in the native
	 * method's instance settings option.
	 */
	const OPTION_KEY = 'infixs_linked_correios_instance';

	/**
	 * Custom WooCommerce settings field type used to render the link selector.
	 */
	const FIELD_TYPE = 'infixs_correios_linked_instance';

	/**
	 * Shipping item meta holding the instance id linked at checkout time.
	 */
	const ITEM_META_KEY = '_infixs_linked_instance_id';

	/**
	 * Native shipping methods that can be linked to a Correios method.
	 *
	 * @since 1.8.0
	 *
	 * @return string[]
	 */
	public static function supported_method_ids() {
		return apply_filters( 'infixs_correios_automatico_linkable_method_ids', [ 'free_shipping', 'flat_rate' ] );
	}

	/**
	 * Check if a shipping method id supports linking.
	 *
	 * @since 1.8.0
	 *
	 * @param string $method_id Shipping method id.
	 *
	 * @return bool
	 */
	public static function is_supported( $method_id ) {
		return in_array( $method_id, self::supported_method_ids(), true );
	}

	/**
	 * Get the Correios instance id linked to a native shipping method instance.
	 *
	 * Reads the raw option instead of going through the method object, so it works
	 * regardless of which classes are loaded on the current request.
	 *
	 * @since 1.8.0
	 *
	 * @param string $method_id   Native shipping method id.
	 * @param int    $instance_id Native shipping method instance id.
	 *
	 * @return int Linked instance id, 0 when not linked.
	 */
	public static function get_linked_instance_id( $method_id, $instance_id ) {
		$instance_id = absint( $instance_id );

		if ( ! $instance_id || ! self::is_supported( $method_id ) ) {
			return 0;
		}

		$settings = get_option( "woocommerce_{$method_id}_{$instance_id}_settings", [] );

		return is_array( $settings ) && isset( $settings[ self::OPTION_KEY ] ) ? absint( $settings[ self::OPTION_KEY ] ) : 0;
	}

	/**
	 * Get the Correios method linked to a native shipping method instance.
	 *
	 * @since 1.8.0
	 *
	 * @param string $method_id   Native shipping method id.
	 * @param int    $instance_id Native shipping method instance id.
	 *
	 * @return CorreiosShippingMethod|false
	 */
	public static function get_linked_method( $method_id, $instance_id ) {
		return self::get_correios_method( self::get_linked_instance_id( $method_id, $instance_id ) );
	}

	/**
	 * Get a Correios method by instance id.
	 *
	 * @since 1.8.0
	 *
	 * @param int $instance_id Correios instance id.
	 *
	 * @return CorreiosShippingMethod|false
	 */
	public static function get_correios_method( $instance_id ) {
		$instance_id = absint( $instance_id );

		if ( ! $instance_id ) {
			return false;
		}

		$method = \WC_Shipping_Zones::get_shipping_method( $instance_id );

		return $method instanceof CorreiosShippingMethod ? $method : false;
	}

	/**
	 * Resolve the Correios method linked to an order shipping item.
	 *
	 * The instance id frozen at checkout wins over the current link, so changing the
	 * link later does not rewrite the service of orders already placed.
	 *
	 * @since 1.8.0
	 *
	 * @param \WC_Order_Item_Shipping $item Order shipping item.
	 *
	 * @return CorreiosShippingMethod|false
	 */
	public static function resolve_from_item( $item ) {
		if ( ! $item instanceof \WC_Order_Item_Shipping ) {
			return false;
		}

		$linked_instance_id = absint( $item->get_meta( self::ITEM_META_KEY ) );

		if ( $linked_instance_id ) {
			$method = self::get_correios_method( $linked_instance_id );

			if ( $method ) {
				return $method;
			}
		}

		return self::get_linked_method( $item->get_method_id(), $item->get_instance_id() );
	}

	/**
	 * Get every Correios method of the zone a shipping instance belongs to.
	 *
	 * Disabled methods are included on purpose: a merchant may keep a Correios method
	 * off the checkout and use it only as the configuration source for a linked method.
	 *
	 * The raw methods come from the data store instead of WC_Shipping_Zone::get_shipping_methods(),
	 * which renders the settings html of every method in the zone. That render is what asks for
	 * this list in the first place, so going through it would recurse endlessly.
	 *
	 * @since 1.8.0
	 *
	 * @param int $instance_id Any shipping method instance id of the zone.
	 *
	 * @return CorreiosShippingMethod[] Keyed by instance id.
	 */
	public static function get_zone_correios_methods( $instance_id ) {
		$instance_id = absint( $instance_id );

		if ( ! $instance_id ) {
			return [];
		}

		$zone = \WC_Shipping_Zones::get_zone_by( 'instance_id', $instance_id );

		if ( ! $zone ) {
			return [];
		}

		$raw_methods = \WC_Data_Store::load( 'shipping-zone' )->get_methods( $zone->get_id(), false );

		$methods = [];

		foreach ( $raw_methods as $raw_method ) {
			if ( 'infixs-correios-automatico' !== $raw_method->method_id ) {
				continue;
			}

			$method = self::get_correios_method( $raw_method->instance_id );

			if ( $method ) {
				$methods[ $method->get_instance_id() ] = $method;
			}
		}

		return $methods;
	}
}
