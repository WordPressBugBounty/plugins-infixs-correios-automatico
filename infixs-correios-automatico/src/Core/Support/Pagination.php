<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

defined( 'ABSPATH' ) || exit;


/**
 * Pagination class to handle paginated data.
 *
 * @since 1.5.0
 */
class Pagination {
	/**
	 * Current page number.
	 *
	 * @var int
	 */
	private $currentPage;

	/**
	 * Total number of items.
	 *
	 * @var int
	 */
	private $totalItems;

	/**
	 * Number of items per page.
	 *
	 * @var int
	 */
	private $itemsPerPage;

	/**
	 * Items.
	 *
	 * @var array
	 */
	private $items;

	/**
	 * Constructor to initialize pagination.
	 *
	 * @param int $currentPage
	 * @param int $totalItems
	 * @param int $itemsPerPage
	 */
	public function __construct( $currentPage, $totalItems, $itemsPerPage, $items ) {
		$this->currentPage = $currentPage;
		$this->totalItems = $totalItems;
		$this->itemsPerPage = (int) $itemsPerPage;
		$this->items = $items;
	}

	/**
	 * Get the current page number.
	 *
	 * @return int
	 */
	public function getCurrentPage() {
		return $this->currentPage;
	}

	/**
	 * Get the total number of items.
	 *
	 * @return int
	 */
	public function getTotalItems() {
		return $this->totalItems;
	}

	/**
	 * Get the number of items per page.
	 *
	 * @return int
	 */
	public function getItemsPerPage() {
		return $this->itemsPerPage;
	}

	/**
	 * Calculate the total number of pages.
	 *
	 * @return int
	 */
	public function getTotalPages() {
		return ceil( $this->totalItems / $this->itemsPerPage );
	}

	/**
	 * Calculate the offset for the current page.
	 *
	 * @return int
	 */
	public function getOffset() {
		return ( $this->currentPage - 1 ) * $this->itemsPerPage;
	}

	public function getItems() {
		return $this->items;
	}

	/**
	 * Convert pagination data to an array.
	 *
	 * @return array
	 */
	public function toArray( $items_key = 'items' ) {
		return [ 
			'current_page' => $this->getCurrentPage(),
			'total_items' => $this->getTotalItems(),
			'items_per_page' => $this->getItemsPerPage(),
			'total_pages' => $this->getTotalPages(),
			'offset' => $this->getOffset(),
			$items_key => $this->getItems(),
		];
	}
}
