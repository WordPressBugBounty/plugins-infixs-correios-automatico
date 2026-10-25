<?php

namespace Infixs\CorreiosAutomatico\Core\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Addresses of the print page of the dashboard.
 *
 * The print page reads the orders and what to print from the query string, so
 * every screen that sends the merchant there — the bulk action of the
 * WooCommerce orders list, the screens of an invoice provider — builds the
 * address here instead of repeating it.
 *
 * Invoice providers detect the simplified DANFE print through `method_exists()`
 * on `orders()`.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.12.0
 */
class PrintUrl {

	/**
	 * The shipping labels, with whatever the print profile adds to them.
	 *
	 * @since 1.12.0
	 *
	 * @var string
	 */
	const VIEW_LABEL = 'label';

	/**
	 * Only the simplified DANFE ("DANFE Simplificado – Etiqueta") of the orders.
	 *
	 * @since 1.12.0
	 *
	 * @var string
	 */
	const VIEW_DANFE = 'danfe';

	/**
	 * Address of the print page for a set of orders.
	 *
	 * @since 1.12.0
	 *
	 * @param int[]  $ids  Orders to print; repeated and invalid ids are dropped.
	 * @param string $view `label` for the labels, `danfe` for only the simplified DANFE.
	 *
	 * @return string Address in the admin; every value is a fixed slug or digits, so nothing needs encoding.
	 */
	public static function orders( array $ids, $view = self::VIEW_LABEL ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ), function ( $id ) {
			return $id > 0;
		} ) ) );

		$args = [
			'page' => 'infixs-correios-automatico',
			'path' => '/print',
			'orders' => implode( ',', $ids ),
		];

		if ( self::VIEW_DANFE === $view ) {
			$args['view'] = self::VIEW_DANFE;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}
}
