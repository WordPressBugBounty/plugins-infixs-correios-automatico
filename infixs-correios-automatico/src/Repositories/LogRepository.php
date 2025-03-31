<?php

namespace Infixs\CorreiosAutomatico\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Log repository.
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class LogRepository {

	private $logger;

	/**
	 * LogRepository constructor.
	 *
	 * @var ConfigRepository $configRepository
	 */
	private $configRepository;

	public function __construct( $configRepository ) {
		$this->configRepository = $configRepository;
		$this->logger = wc_get_logger();
	}

	/**
	 * Log a message with the 'debug' level.
	 * 
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function debug( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.debug_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->debug( $message, $context );
		}
	}

	/**
	 * Log a message with the 'info' level.
	 * 
	 * Informacional messages.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function info( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.info_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->info( $message, $context );
		}
	}

	/**
	 * Log a message with the 'notice' level.
	 * 
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function notice( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.notice_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->notice( $message, $context );
		}
	}

	/**
	 * Log a message with the 'warning' level.
	 * 
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function warning( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.warning_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->warning( $message, $context );
		}
	}

	/**
	 * Log a message with the 'error' level.
	 * 
	 * Runtime errors that do not require immediate action but should typically be logged and monitored.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function error( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.error_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->error( $message, $context );
		}
	}

	/**
	 * Log a message with the 'critical' level.
	 * 
	 * Critical conditions that require immediate action.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function critical( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.critical_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->critical( $message, $context );
		}
	}

	/**
	 * Log a message with the 'alert' level.
	 * 
	 * Action must be taken immediately.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function alert( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.alert_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->alert( $message, $context );
		}
	}

	/**
	 * Log a message with the 'emergency' level.
	 * 
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array  $context
	 */
	public function emergency( $message, $context = [] ) {
		if ( $this->configRepository->boolean( 'debug.emergency_log' )
			&& $this->configRepository->boolean( 'debug.active' )
		) {
			$this->logger->emergency( $message, $context );
		}
	}
}