<?php

namespace Infixs\CorreiosAutomatico\Repositories;

use Infixs\CorreiosAutomatico\Core\Support\Repository;
use Infixs\CorreiosAutomatico\Models\Prepost;

defined( 'ABSPATH' ) || exit;

/**
 * Prepost repository.
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class PrepostRepository extends Repository {

	/**
	 * Create a new prepost.
	 * 
	 * @since 1.0.0
	 * 
	 * @param array{
	 *      external_id: string,
	 *      object_code: string,
	 *      service_code: string,
	 *      payment_type: int,
	 *      height: string,
	 *      width: string,
	 *      length: string,
	 *      weight: string,
	 *      request_pickup: int,
	 *      reverse_logistic: int,
	 *      status: int,
	 *      status_label: string,
	 *      expire_at: string,
	 *      updated_at: string,
	 *      created_at: string
	 * } $data
	 * 
	 * @return bool|Prepost
	 */
	public function create( $data ) {
		return Prepost::create( $data );
	}

	/**
	 * Paginate preposts.
	 * 
	 * @return \Infixs\WordpressEloquent\Collection
	 */
	public function paginate( $per_page = 10, $page = 1 ) {
		return Prepost::select( "*" )->limit( 10 )->offset( 10 * ( $page - 1 ) )->orderBy( 'created_at', 'desc' )->get();
	}
}