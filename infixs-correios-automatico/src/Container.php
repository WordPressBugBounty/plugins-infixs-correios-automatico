<?php

namespace Infixs\CorreiosAutomatico;

use Infixs\CorreiosAutomatico\Models\InvoiceUnit;
use Infixs\CorreiosAutomatico\Models\Notification;
use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Models\TrackingRangeCode;
use Infixs\CorreiosAutomatico\Models\Unit;
use Infixs\CorreiosAutomatico\Prepaid\ContractMode;
use Infixs\CorreiosAutomatico\Prepaid\PrepaidApi;
use Infixs\CorreiosAutomatico\Prepaid\PrepaidChargeNote;
use Infixs\CorreiosAutomatico\Prepaid\PrepaidClientResolver;
use Infixs\CorreiosAutomatico\Prepaid\PrepaidConnection;
use Infixs\CorreiosAutomatico\Prepaid\PrepaidConnector;
use Infixs\CorreiosAutomatico\Prepaid\PrepaidCorreiosApi;
use Infixs\CorreiosAutomatico\Prepaid\PrepaidOAuth;
use Infixs\CorreiosAutomatico\Repositories\InvoiceUnitRepository;
use Infixs\CorreiosAutomatico\Repositories\UnitRepository;
use Infixs\CorreiosAutomatico\Services\InfixsApi;
use Infixs\CorreiosAutomatico\Services\OrderDanfeResolver;
use Infixs\CorreiosAutomatico\Services\OrderInvoiceIssuer;
use Infixs\CorreiosAutomatico\Services\OrderInvoiceResolver;
use Infixs\CorreiosAutomatico\Services\OrderInvoiceStatus;
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
use Infixs\CorreiosAutomatico\Repositories\RangeCodeRepository;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Auth;
use Infixs\CorreiosAutomatico\Services\EmailService;
use Infixs\CorreiosAutomatico\Services\InvoiceUnitService;
use Infixs\CorreiosAutomatico\Services\SettingsService;
use Infixs\CorreiosAutomatico\Services\ShippingService;
use Infixs\CorreiosAutomatico\Services\WhatsappService;
use Infixs\CorreiosAutomatico\Services\ReturnService;
use Infixs\CorreiosAutomatico\Repositories\NotificationRepository;
use Infixs\CorreiosAutomatico\Services\NotificationService;
use Infixs\CorreiosAutomatico\Services\VendorContractService;
use Infixs\CorreiosAutomatico\Services\ShipmentContextResolver;
use Infixs\CorreiosAutomatico\Services\VolumeLineExpander;
use Infixs\CorreiosAutomatico\Services\Correios\Packing\VolumePlanner;

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
		$this->container['invoiceUnitRepository'] = fn() => new InvoiceUnitRepository( InvoiceUnit::class);
		$this->container['rangeCodeRepository'] = fn() => new RangeCodeRepository( TrackingRangeCode::class);

		$this->container['correiosApi'] = function ( $c ) {
			$auth = new Auth( $c['configRepository']->get( 'auth' ) );
			$auth->setUpdateTokenCallback( function ( $token ) use ( $c ) {
				$c['configRepository']->update( 'auth.token', $token );
			} );

			return new CorreiosApi( $auth );
		};

		$this->container['infixsApi'] = fn() => new InfixsApi();

		$this->container['prepaidConnection'] = fn() => new PrepaidConnection();
		$this->container['contractMode'] = fn( $c ) => new ContractMode( $c['configRepository'], $c['prepaidConnection'] );
		$this->container['prepaidApi'] = fn( $c ) => new PrepaidApi( $c['prepaidConnection'] );
		$this->container['prepaidCorreiosApi'] = fn( $c ) => new PrepaidCorreiosApi( $c['prepaidApi'], $c['infixsApi'], $c['prepaidConnection'] );
		$this->container['prepaidConnector'] = fn( $c ) => new PrepaidConnector( new PrepaidOAuth(), $c['prepaidApi'], $c['prepaidConnection'], $c['contractMode'], $c['routes'] );
		$this->container['prepaidClientResolver'] = fn( $c ) => new PrepaidClientResolver( $c['contractMode'], $c['prepaidCorreiosApi'], $c['correiosApi'] );
		$this->container['prepaidChargeNote'] = fn() => new PrepaidChargeNote();

		$this->container['correiosService'] = fn( $c ) => new CorreiosService( $c['correiosApi'] );
		$this->container['trackingService'] = fn( $c ) => new TrackingService( $c['trackingRepository'], $c['correiosService'], $c['configRepository'] );
		$this->container['prepostService'] = fn( $c ) => new PrepostService( $c['prepostRepository'], $c['correiosService'] );
		$this->container['orderService'] = fn() => new OrderService();
		$this->container['orderInvoiceResolver'] = fn() => new OrderInvoiceResolver();
		$this->container['orderDanfeResolver'] = fn( $c ) => new OrderDanfeResolver( $c['orderInvoiceResolver'] );
		$this->container['orderInvoiceStatus'] = fn() => new OrderInvoiceStatus();
		$this->container['orderInvoiceIssuer'] = fn( $c ) => new OrderInvoiceIssuer( $c['orderInvoiceResolver'] );
		$this->container['shippingService'] = fn( $c ) => new ShippingService( $c['correiosService'], $c['infixsApi'], $c['configRepository'], $c['contractMode'], $c['prepaidConnection'] );
		$this->container['emailService'] = fn() => new EmailService();
		$this->container['shipmentContextResolver'] = fn() => new ShipmentContextResolver();
		$this->container['volumePlanner'] = fn() => new VolumePlanner();
		$this->container['volumeLineExpander'] = fn() => new VolumeLineExpander();
		$this->container['labelService'] = fn( $c ) => new LabelService( $c['trackingService'], $c['shippingService'], $c['rangeCodeRepository'], $c['shipmentContextResolver'], $c['prepostService'], $c['orderDanfeResolver'], $c['prepaidConnector'] );
		$this->container['invoiceUnitService'] = fn( $c ) => new InvoiceUnitService( $c['invoiceUnitRepository'] );
		$this->container['unitService'] = fn( $c ) => new UnitService( $c['unitRepository'], $c['invoiceUnitService'] );
		$this->container['settingsService'] = fn() => new SettingsService();
		$this->container['whatsappService'] = fn( $c ) => new WhatsappService( $c['trackingService'] );
		$this->container['returnService'] = fn( $c ) => new ReturnService( $c['prepostService'], $c['trackingService'], $c['shipmentContextResolver'] );
		$this->container['notificationRepository'] = fn() => new NotificationRepository( Notification::class);
		$this->container['notificationService'] = fn( $c ) => new NotificationService( $c['notificationRepository'] );
		$this->container['vendorContractService'] = fn() => new VendorContractService();
	}

	/**
	 * Vendor Contract Service
	 *
	 * @since 1.8.2
	 * @return VendorContractService
	 */
	public static function vendorContractService() {
		return self::getInstance()->container['vendorContractService'];
	}

	/**
	 * Which contract the store posts with.
	 *
	 * @since 1.14.0
	 * @return ContractMode
	 */
	public static function contractMode() {
		return self::getInstance()->container['contractMode'];
	}

	/**
	 * Connection of the store to its Infixs Ship account.
	 *
	 * @since 1.14.0
	 * @return PrepaidConnection
	 */
	public static function prepaidConnection() {
		return self::getInstance()->container['prepaidConnection'];
	}

	/**
	 * Connects the store to its Infixs Ship account.
	 *
	 * @since 1.14.0
	 * @return PrepaidConnector
	 */
	public static function prepaidConnector() {
		return self::getInstance()->container['prepaidConnector'];
	}

	/**
	 * Sends the preposts of the Contrato pré-pago through the Infixs Ship.
	 *
	 * @since 1.14.0
	 * @return PrepaidClientResolver
	 */
	public static function prepaidClientResolver() {
		return self::getInstance()->container['prepaidClientResolver'];
	}

	/**
	 * Notes on the order what a prepaid prepost cost.
	 *
	 * @since 1.14.0
	 * @return PrepaidChargeNote
	 */
	public static function prepaidChargeNote() {
		return self::getInstance()->container['prepaidChargeNote'];
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
	 * Invoice Unit Service
	 * 
	 * @since 1.6.41
	 * @return InvoiceUnitService
	 */
	public static function invoiceUnitService() {
		return self::getInstance()->container['invoiceUnitService'];
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
	 * Shipment Context Resolver
	 *
	 * @since 1.11.0
	 * @return ShipmentContextResolver
	 */
	public static function shipmentContextResolver() {
		return self::getInstance()->container['shipmentContextResolver'];
	}

	/**
	 * Volume Planner
	 *
	 * @since 1.11.0
	 * @return VolumePlanner
	 */
	public static function volumePlanner() {
		return self::getInstance()->container['volumePlanner'];
	}

	/**
	 * Volume Line Expander
	 *
	 * @since 1.11.0
	 * @return VolumeLineExpander
	 */
	public static function volumeLineExpander() {
		return self::getInstance()->container['volumeLineExpander'];
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
	 * Order Invoice Resolver
	 *
	 * @since 1.11.0
	 * @return OrderInvoiceResolver
	 */
	public static function orderInvoiceResolver() {
		return self::getInstance()->container['orderInvoiceResolver'];
	}

	/**
	 * Order DANFE Resolver
	 *
	 * @since 1.12.0
	 * @return OrderDanfeResolver
	 */
	public static function orderDanfeResolver() {
		return self::getInstance()->container['orderDanfeResolver'];
	}

	/**
	 * Order Invoice Status
	 *
	 * @since 1.12.0
	 * @return OrderInvoiceStatus
	 */
	public static function orderInvoiceStatus() {
		return self::getInstance()->container['orderInvoiceStatus'];
	}

	/**
	 * Order Invoice Issuer
	 *
	 * @since 1.15.0
	 * @return OrderInvoiceIssuer
	 */
	public static function orderInvoiceIssuer() {
		return self::getInstance()->container['orderInvoiceIssuer'];
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

	/**
	 * Return Service
	 *
	 * @since 1.8.0
	 * @return ReturnService
	 */
	public static function returnService() {
		return self::getInstance()->container['returnService'];
	}

	public static function logRepository() {
		return self::getInstance()->container['logRepository'];
	}

	/**
	 * Notification Repository
	 *
	 * @since 1.8.0
	 * @return NotificationRepository
	 */
	public static function notificationRepository() {
		return self::getInstance()->container['notificationRepository'];
	}

	/**
	 * Notification Service
	 *
	 * @since 1.8.0
	 * @return NotificationService
	 */
	public static function notificationService() {
		return self::getInstance()->container['notificationService'];
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

	/**
	 * Settings Service
	 * 
	 * @since 1.6.43
	 * 
	 * @return SettingsService
	 */
	public static function settingsService() {
		return self::getInstance()->container['settingsService'];
	}

	/**
	 * Whatsapp Service
	 * 
	 * @since 1.6.95
	 * 
	 * @return WhatsappService
	 */
	public static function whatsappService() {
		return self::getInstance()->container['whatsappService'];
	}
}