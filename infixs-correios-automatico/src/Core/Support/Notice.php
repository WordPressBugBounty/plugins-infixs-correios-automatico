<?php

namespace Infixs\CorreiosAutomatico\Core\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Notice class.
 * 
 * @since 1.0.5
 */
abstract class Notice {

	/**
	 * ID
	 * 
	 * @since 1.0.5
	 * 
	 * @var string
	 */
	protected $id;

	/**
	 * Title
	 * 
	 * @since 1.0.5
	 * 
	 * @var string
	 */
	protected $title;

	/**
	 * Message
	 * 
	 * @since 1.0.5
	 * 
	 * @var string
	 */
	protected $message;

	/**
	 * Type
	 * 
	 * @since 1.0.5
	 * 
	 * @var string "success", "error", "warning", "info"
	 */
	protected $type = 'info';

	/**
	 * Buttons
	 * 
	 * @since 1.0.5
	 * 
	 * @var array{
	 *      text: int,
	 *      url: string,
	 * }[]
	 */
	protected $buttons = [];

	/**
	 * Dismiss duration
	 * 
	 * @since 1.0.5
	 * 
	 * @var int|null Seconds to dismiss the notice or null to keep it forever
	 */
	protected $dismissDuration = null;

	/**
	 * Constructor
	 * 
	 * @since 1.0.5
	 */
	public function __construct() {
	}


	/**
	 * Get id
	 * 
	 * @since 1.0.5
	 * 
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Get title
	 * 
	 * @since 1.0.5
	 * 
	 * @return string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Get message
	 * 
	 * @since 1.0.5
	 * 
	 * @return string
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * Get type
	 * 
	 * @since 1.0.5
	 * 
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Get buttons
	 * 
	 * @since 1.0.5
	 * 
	 * @return array{
	 *      text: int,
	 *      url: string,
	 * }[]
	 */
	public function getButtons() {
		return $this->buttons;
	}

	public function dismiss() {
		$dismissedNotices = get_user_meta( get_current_user_id(), '_infixs_correios_automatico_dismissed_notices', true );
		if ( ! is_array( $dismissedNotices ) ) {
			$dismissedNotices = [];
		}
		$dismissedNotices[ $this->getId()] = time();
		update_user_meta( get_current_user_id(), '_infixs_correios_automatico_dismissed_notices', $dismissedNotices );
	}

	public function isDismissed() {
		$dismissedNotices = get_user_meta( get_current_user_id(), '_infixs_correios_automatico_dismissed_notices', true );
		if ( ! is_array( $dismissedNotices ) ) {
			return false;
		}
		if ( ! isset( $dismissedNotices[ $this->getId()] ) ) {
			return false;
		}
		if ( $this->dismissDuration === null ) {
			return true;
		}
		$dismissTime = $dismissedNotices[ $this->getId()];
		$now = time();
		$diff = $now - $dismissTime;
		if ( $diff > $this->dismissDuration ) {
			return false;
		}
		return true;
	}

	/**
	 * shouldDisplay
	 * 
	 * @since 1.0.5
	 * 
	 * @return bool
	 */
	abstract public function shouldDisplay();

	public function toArray() {
		return [ 
			'id' => $this->getId(),
			'title' => $this->getTitle(),
			'message' => $this->getMessage(),
			'type' => $this->getType(),
			'buttons' => $this->buttons,
		];
	}
}