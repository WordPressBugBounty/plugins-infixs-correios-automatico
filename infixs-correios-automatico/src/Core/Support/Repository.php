<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

use Infixs\WordpressEloquent\Model;

defined( 'ABSPATH' ) || exit;

class Repository {

	/**
	 * Model class.
	 *
	 * @var Model
	 */
	private $modelClass;

	public function __construct( $modelClass ) {
		$this->modelClass = $modelClass;
	}

	public function all() {
		return $this->modelClass::all();
	}

	public function create( $data ) {
		return $this->modelClass::create( $data );
	}

	public function delete( $id ) {
		return $this->modelClass::where( 'id', $id )->limit( 1 )->delete();
	}

	public function findById( $id, $options = [] ) {
		$query = $this->modelClass::query();

		if ( isset( $options['relations'] ) ) {
			foreach ( $options['relations'] as $relation ) {
				$query = $query->with( $relation );
			}
		}

		return $query->where( 'id', $id )->first();
	}

	public function find( $options = [] ) {
		$query = $this->modelClass::query();

		if ( isset( $options['where'] ) ) {
			foreach ( $options['where'] as $key => $value ) {
				$query = $query->where( $key, $value );
			}
		}

		if ( isset( $options['relations'] ) ) {
			foreach ( $options['relations'] as $relation ) {
				$query = $query->with( $relation );
			}
		}

		return $query->get();
	}

	public function findOne( $options = [] ) {
		$query = $this->modelClass::query();

		if ( isset( $options['where'] ) ) {
			foreach ( $options['where'] as $key => $value ) {
				$query = $query->where( $key, $value );
			}
		}

		if ( isset( $options['relations'] ) ) {
			foreach ( $options['relations'] as $relation ) {
				$query = $query->with( $relation );
			}
		}

		return $query->first();
	}

	public function count() {
		return $this->modelClass::count();
	}

	protected function getModel() {
		return new $this->modelClass;
	}

	/**
	 * Paginate the given query.
	 *
	 * @param  array {
	 *          per_page: int,
	 *          current_page: int,
	 *          order_by: string,
	 *          order: string,
	 *          relations: array
	 * 			where: array
	 * } $params
	 * @param  callable|null  $map_data
	 * 
	 * @return Pagination
	 */
	public function paginate( $params = [], $map_data = null ) {
		$per_page = $params['per_page'] ?? 15;
		$current_page = $params['current_page'] ?? 1;
		$order_by = $params['order_by'] ?? 'id';
		$order = $params['order'] ?? 'asc';

		$offset = ( $current_page - 1 ) * $per_page;

		$query = $this->modelClass::query();
		if ( isset( $params['where'] ) ) {
			foreach ( $params['where'] as $key => $value ) {
				$query = $query->where( $key, $value );
			}
		}

		if ( isset( $params['whereIn'] ) ) {
			foreach ( $params['whereIn'] as $key => $value ) {
				$query = $query->whereIn( $key, $value );
			}
		}

		$total_items = $query->count();

		$query = $query->offset( $offset )->limit( $per_page );
		$relations = $params['relations'] ?? [];
		foreach ( $relations as $relation ) {
			$query = $query->with( $relation );
		}

		$query = $query->orderBy( $order_by, $order );
		$items = $query->get();

		if ( ! $map_data ) {
			return new Pagination( $current_page, $total_items, $per_page, $items->toArray() );
		}

		$mapped_items = $items->map( $map_data );

		return new Pagination( $current_page, $total_items, $per_page, $mapped_items );
	}
}

