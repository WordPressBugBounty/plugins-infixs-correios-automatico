<?php

namespace Infixs\CorreiosAutomatico\Repositories;

use Infixs\CorreiosAutomatico\Core\Support\Repository;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Models\TrackingCodeEvent;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking repository.
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class TrackingRepository extends Repository {

	/**
	 * Create a tracking code.
	 * 
	 * @since 1.0.0
	 * 
	 * @param array $data {
	 *    An array of elements that create a tracking code.
	 *
	 *    @type int     $order_id   Order ID.
	 *    @type string  $code    Tracking code.
	 *    @type int     $user_id    User ID.
	 * }
	 * 
	 * @return TrackingCode|bool TrackingCode Model or false on error.
	 */
	public function create( $data ) {
		$data['created_at'] = current_time( 'mysql' );
		$data['updated_at'] = current_time( 'mysql' );
		return TrackingCode::create( $data );
	}

	/**
	 * Delete a tracking code and its events.
	 * 
	 * The events table has no foreign key: events left behind would never be
	 * reached again, and a tracking code that later reuses the ID would show
	 * them as its own history. They go only once the code itself is gone.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $id Tracking code ID.
	 * 
	 * @return int|bool The number of rows affected or false on error.
	 */
	public function delete( $id ) {
		$deleted = TrackingCode::where( 'id', $id )->delete();

		if ( $deleted ) {
			TrackingCodeEvent::where( 'tracking_code_id', $id )->delete();
		}

		return $deleted;
	}

	/**
	 * Find tracking code by where.
	 * 
	 * @since 1.0.0
	 * 
	 * @param array $where Fields.
	 * @param array $config {
	 * 		@type array $order {
	 * 			@type string $column Column name.
	 * 			@type string $order Order direction "asc" or "desc".
	 * 		}
	 * }
	 * 
	 * @return \Infixs\WordpressEloquent\Collection
	 */
	public function findBy( $where, $config = [] ) {
		$builder = TrackingCode::where( $where );

		if ( isset( $config['with_events'] ) && $config['with_events'] ) {
			$builder->with( 'events' );
		}

		if ( isset( $config['with_unit'] ) && $config['with_unit'] ) {
			$builder->with( 'unit' );
		}

		if ( isset( $config['order'] ) ) {
			$builder->orderBy( $config['order']['column'], $config['order']['order'] );
		}


		return $builder->get();
	}


	/**
	 * Retrieve tracking code by tracking code.
	 * 
	 * @since 1.2.1
	 * 
	 * @param string $tracking_code Tracking code.
	 * 
	 * @return TrackingCode|null
	 */
	public function retrieveByTrackingCode( $tracking_code ) {
		return TrackingCode::with( 'events' )->where( 'code', $tracking_code )->first();
	}

	public function whereIn( $column, $values, $config = [] ) {
		$builder = TrackingCode::query();

		if ( isset( $config['with_events'] ) && $config['with_events'] ) {
			$builder->with( 'events' );
		}

		return $builder->whereIn( $column, $values )->get();
	}

	/**
	 * Retrieve tracking code by ID.
	 * 
	 * @since 1.2.2
	 * 
	 * @param int $id Tracking code ID.
	 * @param bool $with_events Retrieve tracking code with events.
	 * 
	 * @return TrackingCode|null
	 */
	public function retrieve( $id, $with_events = false ) {
		if ( $with_events ) {
			return TrackingCode::with( 'events' )->where( 'id', $id )->first();
		} else {
			return TrackingCode::find( $id );
		}
	}

	public function exists( $where ) {
		return TrackingCode::where( $where )->exists();
	}
}