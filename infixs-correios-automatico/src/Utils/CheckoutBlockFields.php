<?php

namespace Infixs\CorreiosAutomatico\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the additional fields the checkout block saved on an order.
 *
 * The checkout block keeps a field registered as "<namespace>/<name>" under the
 * meta "<group key><namespace>/<name>", the group being billing, shipping or
 * other (contact and order fields). The namespace belongs to the plugin that
 * added the field (Infixs Checkout Fields uses "br-checkout-fields"), so the
 * field is found by its name, with or without the billing_ or shipping_
 * prefix: "billing_cpf" and "cpf" both answer for "cpf".
 *
 * Only what the order saved counts. The reader of WooCommerce fills a missing
 * field with the default value of the customer profile, which is not what the
 * order was placed with, so WooCommerce is asked only where each group lives.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.2
 */
class CheckoutBlockFields {

	/**
	 * Service of WooCommerce that knows the meta key of each group.
	 *
	 * @since 1.11.2
	 *
	 * @var string
	 */
	const SERVICE = 'Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields';

	/**
	 * Value of a field of the checkout block saved on an order.
	 *
	 * @since 1.11.2
	 *
	 * @param \WC_Order $order  Order.
	 * @param string[]  $groups Groups to look in, in order: billing, shipping or other.
	 * @param string    $name   Field name without the namespace and the billing_ or shipping_ prefix, e.g. "cpf".
	 *
	 * @return string Empty when the order has no such field.
	 */
	public static function get( $order, $groups, $name ) {
		$meta_data = $order->get_meta_data();

		foreach ( $groups as $group ) {
			$prefixes = self::groupPrefixes( $group );

			foreach ( $meta_data as $meta ) {
				if ( ! is_scalar( $meta->value ) || ! self::isField( (string) $meta->key, $prefixes, $name ) ) {
					continue;
				}

				$value = trim( (string) $meta->value );

				if ( '' !== $value ) {
					return $value;
				}
			}
		}

		return '';
	}

	/**
	 * Meta key prefixes of a group.
	 *
	 * The other group was called additional before WooCommerce 8.9, so orders
	 * placed back then keep it under that name.
	 *
	 * @since 1.11.2
	 *
	 * @param string $group billing, shipping or other.
	 *
	 * @return string[]
	 */
	private static function groupPrefixes( $group ) {
		$prefixes = [ "_wc_{$group}/" ];

		if ( is_callable( [ self::SERVICE, 'get_group_key' ] ) ) {
			array_unshift( $prefixes, call_user_func( [ self::SERVICE, 'get_group_key' ], $group ) );
		}

		if ( 'other' === $group ) {
			$prefixes[] = '_wc_additional/';
		}

		return array_values( array_unique( $prefixes ) );
	}

	/**
	 * Whether a meta key holds the named field in one of the prefixes.
	 *
	 * @since 1.11.2
	 *
	 * @param string   $key      Meta key.
	 * @param string[] $prefixes Prefixes of the group.
	 * @param string   $name     Field name.
	 *
	 * @return bool
	 */
	private static function isField( $key, $prefixes, $name ) {
		foreach ( $prefixes as $prefix ) {
			if ( 0 !== strpos( $key, $prefix ) ) {
				continue;
			}

			$field = substr( strrchr( '/' . substr( $key, strlen( $prefix ) ), '/' ), 1 );

			return preg_replace( '/^(billing|shipping)_/', '', $field ) === $name;
		}

		return false;
	}
}
