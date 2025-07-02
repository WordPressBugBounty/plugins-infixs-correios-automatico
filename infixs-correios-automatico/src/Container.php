<?php

namespace Infixs\CorreiosAutomatico;

use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Models\Unit;
use Infixs\CorreiosAutomatico\Repositories\InvoiceUnitRepository;
use Infixs\CorreiosAutomatico\Repositories\UnitRepository;
use Infixs\CorreiosAutomatico\Services\InfixsApi;
use Infixs\CorreiosAutomatico\Services\OrderService;
use Infixs\CorreiosAutomatico\Services\UnitService;
use Pimple\Container as PimpleContainer;
use Infixs\CorreiosAutomatico\Repositories\LogRepository;
use Infixs\CorreiosAutomatico\Repositories\PrepostRepository;
use Infixs\CorreiosAutomatico\Repositories\TrackingRepository;
use Infixs\CorreiosAutomatico\Routes\RestRoutes;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosApi;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosService;
use Infixs\CorreiosAutomatico\Services\LabelService;
use Infixs\CorreiosAutomatico\Services\PrepostService;
use Infixs\CorreiosAutomatico\Services\TrackingService;
use Infixs\CorreiosAutomatico\Repositories\ConfigRepository;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Auth;
use Infixs\CorreiosAutomatico\Services\EmailService;
use Infixs\CorreiosAutomatico\Services\ShippingService;

defined( 'ABSPATH' ) || exit;

/**
 * Class Container
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Container {
	private $container;
	private static $instance = null;

	/**
	 * Get the instance of the class.
	 *
	 * @since 1.0.0
	 * @return Container
	 */
	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Container constructor.
	 */
	public function __construct() {
		$this->container = new PimpleContainer();
		$this->container['routes'] = fn() => new RestRoutes();

		$this->container['trackingRepository'] = fn() => new TrackingRepository( TrackingCode::class);
		$this->container['configRepository'] = fn() => new ConfigRepository();
		$this->container['logRepository'] = fn( $c ) => new LogRepository( $c['configRepository'] );
		$this->container['prepostRepository'] = fn() => new PrepostRepository( Prepost::class);
		$this->container['unitRepository'] = fn() => new UnitRepository( Unit::class);
		$this->container['invoiceUnitRepository'] = fn() => new InvoiceUnitRepository( Unit::class);

		$this->container['correiosApi'] = function ($c) {
			$auth = new Auth( $c['configRepository']->get( 'auth' ) );
			$auth->setUpdateTokenCallback( function ($token) use ($c) {
				$c['configRepository']->update( 'auth.token', $token );
			} );

			return new CorreiosApi( $auth );
		};

		$this->container['infixsApi'] = fn() => new InfixsApi();

		$this->container['correiosService'] = fn( $c ) => new CorreiosService( $c['correiosApi'] );
		$this->container['trackingService'] = fn( $c ) => new TrackingService( $c['trackingRepository'], $c['correiosService'], $c['configRepository'] );
		$this->container['prepostService'] = fn( $c ) => new PrepostService( $c['prepostRepository'], $c['correiosService'] );
		$this->container['orderService'] = fn() => new OrderService();
		$this->container['shippingService'] = fn( $c ) => new ShippingService( $c['correiosService'], $c['infixsApi'], $c['configRepository'] );
		$this->container['emailService'] = fn() => new EmailService();
		$this->container['labelService'] = fn( $c ) => new LabelService( $c['trackingService'], $c['shippingService'] );
		$this->container['unitService'] = fn( $c ) => new UnitService( $c['unitRepository'], $c['invoiceUnitRepository'] );
	}

	/**
	 * Config Repository
	 * 
	 * @since 1.0.0
	 * @return ConfigRepository
	 */
	public static function configRepository() {
		return self::getInstance()->container['configRepository'];
	}

	/**
	 * Unit Repository
	 * 
	 * @since 1.5.0
	 * @return UnitRepository
	 */
	public static function unitRepository() {
		return self::getInstance()->container['unitRepository'];
	}

	/**
	 * Invoice Unit Repository
	 * 
	 * @since 1.5.7
	 * @return InvoiceUnitRepository
	 */
	public static function invoiceUnitRepository() {
		return self::getInstance()->container['invoiceUnitRepository'];
	}

	/**
	 * Correios Service
	 * 
	 * @since 1.0.0
	 * @return CorreiosService
	 */
	public static function correiosService() {
		return self::getInstance()->container['correiosService'];
	}

	public static function correiosApi() {
		return self::getInstance()->container['correiosApi'];
	}

	/**
	 * Tracking Service
	 * 
	 * @since 1.0.0
	 * @return TrackingService
	 */
	public static function trackingService() {
		return self::getInstance()->container['trackingService'];
	}

	/**
	 * Prepost Service
	 * 
	 * @since 1.0.0
	 * @return PrepostService
	 */
	public static function prepostService() {
		return self::getInstance()->container['prepostService'];
	}

	/**
	 * Label Service
	 * 
	 * @since 1.0.0
	 * @return LabelService
	 */
	public static function labelService() {
		return self::getInstance()->container['labelService'];
	}

	/**
	 * Order Service
	 * 
	 * @since 1.0.0
	 * @return OrderService
	 */
	public static function orderService() {
		return self::getInstance()->container['orderService'];
	}

	/**
	 * Shipping Service
	 * 
	 * @since 1.0.0
	 * @return ShippingService
	 */
	public static function shippingService() {
		return self::getInstance()->container['shippingService'];
	}

	public static function logRepository() {
		return self::getInstance()->container['logRepository'];
	}

	/**
	 * Email Service
	 * 
	 * @since 1.0.0
	 * @return EmailService
	 */
	public static function emailService() {
		return self::getInstance()->container['emailService'];
	}

	/**
	 * Unit Service
	 * 
	 * @since 1.5.0
	 * @return UnitService
	 */
	public static function unitService() {
		return self::getInstance()->container['unitService'];
	}

	/**
	 * Infixs Api
	 * 
	 * @since 1.0.0
	 * 
	 * @return InfixsApi
	 */
	public static function infixsApi() {
		return self::getInstance()->container['infixsApi'];
	}

	/**
	 * Routes
	 * 
	 * @since 1.0.0
	 * @return RestRoutes
	 */
	public static function routes() {
		return self::getInstance()->container['routes'];
	}
}