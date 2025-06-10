<?php

namespace Infixs\CorreiosAutomatico\Database;

use Infixs\WordpressEloquent\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Migration class.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class Migration {


	public static function run() {
		/**
		 * Create table `infixs_correios_automatico_tracking_codes`.
		 * 
		 * @since 1.0.0
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_tracking_codes', [ 
			'order_id' => 'bigint(20) unsigned NOT NULL',
			'user_id' => 'bigint(20) unsigned DEFAULT NULL',
			'code' => 'varchar(255) DEFAULT NULL',
			'description' => 'varchar(255) DEFAULT NULL', //@since 1.2.1
			'category' => 'varchar(255) DEFAULT NULL', //@since 1.2.1
			'expected_date' => 'datetime DEFAULT NULL', //@since 1.2.1
			'sync_at' => 'datetime DEFAULT NULL', //@since 1.2.3
			'customer_email_at' => 'datetime DEFAULT NULL', //@since 1.2.3
			'unit_id' => 'bigint(20) unsigned DEFAULT NULL', //@since 1.5.0
			'tracking_range_code_id' => 'bigint(20) unsigned DEFAULT NULL', //@since 1.5.94
			'updated_at' => 'datetime NOT NULL',
			'created_at' => 'datetime NOT NULL',
		] );

		/**
		 * Create table `infixs_correios_automatico_tracking_code_events`.
		 * 
		 * @since 1.2.1
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_tracking_code_events', [ 
			'tracking_code_id' => 'bigint(20) unsigned NOT NULL',
			'code' => 'varchar(255) NOT NULL',
			'type' => 'varchar(255) NOT NULL',
			'description' => 'varchar(255) NOT NULL',
			'detail' => 'text DEFAULT NULL',
			'location_type' => 'varchar(255) DEFAULT NULL',
			'location_address' => 'varchar(255) DEFAULT NULL',
			'location_number' => 'varchar(255) DEFAULT NULL',
			'location_neighborhood' => 'varchar(255) DEFAULT NULL',
			'location_city' => 'varchar(255) DEFAULT NULL',
			'location_state' => 'char(2) DEFAULT NULL',
			'location_postcode' => 'char(8) DEFAULT NULL',
			'event_date' => 'datetime NOT NULL',
			'updated_at' => 'datetime NOT NULL',
			'created_at' => 'datetime NOT NULL',
		] );

		/**
		 * Create table `infixs_correios_automatico_preposts`.
		 * 
		 * @since 1.0.0
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_preposts', [ 
			'external_id' => 'varchar(255) NOT NULL',
			'order_id' => 'bigint(20) unsigned DEFAULT NULL', //@since 1.1.3
			'object_code' => 'varchar(255) DEFAULT NULL',
			"service_code" => "varchar(8) NOT NULL",
			"payment_type" => "tinyint(1) unsigned DEFAULT 2",
			"height" => "varchar(8) DEFAULT NULL",
			"width" => "varchar(8) DEFAULT NULL",
			"length" => "varchar(8) DEFAULT NULL",
			"weight" => "varchar(8) DEFAULT NULL",
			"request_pickup" => "tinyint(1) unsigned DEFAULT 0",
			"reverse_logistic" => "tinyint(1) unsigned DEFAULT 0",
			"status" => "tinyint(1) unsigned DEFAULT NULL",
			"status_label" => "varchar(255) NOT NULL",
			"invoice_number" => "varchar(255) DEFAULT NULL",
			"invoice_key" => "varchar(255) DEFAULT NULL",
			"expire_at" => "datetime DEFAULT NULL",
			'updated_at' => 'datetime NOT NULL',
			'created_at' => 'datetime NOT NULL',
			'cancelled_at' => 'datetime DEFAULT NULL', //@since 1.1.3
		] );

		/**
		 * Create table `infixs_correios_automatico_postcodes`.
		 * 
		 * @since 1.0.0
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_postcodes', [ 
			'postcode' => 'char(8) DEFAULT NULL',
			'address' => 'varchar(255) DEFAULT NULL',
			'city' => 'varchar(255) DEFAULT NULL',
			'neighborhood' => 'varchar(255) DEFAULT NULL',
			'state' => 'char(2) DEFAULT NULL',
			'created_at' => 'datetime NOT NULL',
		] );

		/**
		 * Create table `infixs_correios_automatico_tracking_ranges`.
		 * 
		 * @since 1.3.7
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_tracking_ranges', [ 
			'service_code' => 'varchar(8) NOT NULL',
			'range_start' => 'varchar(255) NOT NULL',
			'range_end' => 'varchar(255) NOT NULL',
			'created_at' => 'datetime NOT NULL',
		] );

		/**
		 * Create table `infixs_correios_automatico_tracking_range_codes`.
		 * 
		 * @since 1.3.7
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_tracking_range_codes', [ 
			'tracking_range_id' => 'bigint(20) unsigned NOT NULL',
			'code' => 'varchar(255) NOT NULL',
			'order_id' => 'bigint(20) unsigned DEFAULT NULL',
			'is_used' => 'tinyint(1) unsigned DEFAULT 0',
		] );

		/**
		 * Create table `infixs_correios_automatico_units`.
		 * 
		 * @since 1.5.0
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_units', [ 
			'dispatch_number' => 'int(10) unsigned DEFAULT NULL',
			'ceint_id' => 'int(10) unsigned DEFAULT NULL',
			'unit_code' => 'varchar(255) DEFAULT NULL',
			'service_code' => 'varchar(8) NOT NULL',
			'status' => "varchar(255) DEFAULT 'pending'",
			'origin_country' => 'char(2) DEFAULT NULL',
			'origin_operator_name' => 'char(4) DEFAULT NULL',
			'destination_operator_name' => 'char(4) DEFAULT NULL',
			'postal_category_code' => 'varchar(10) DEFAULT NULL',
			'service_subclass_code' => 'varchar(10) DEFAULT NULL',
			'sequence' => 'int(10) unsigned DEFAULT NULL',
			'unit_type' => 'int(2) unsigned DEFAULT NULL',
			'unit_rfid_code' => 'varchar(30) DEFAULT NULL',
			'invoice_unit_id' => 'bigint(20) unsigned DEFAULT NULL', //@since 1.5.7
			'created_at' => 'datetime NOT NULL',
			'updated_at' => 'datetime NOT NULL',
		] );

		/**
		 * Create table `infixs_correios_automatico_invoice_units`.
		 * 
		 * @since 1.5.7
		 */
		Database::createOrUpdateTable( 'infixs_correios_automatico_invoice_units', [ 
			'request_id' => 'varchar(255) DEFAULT NULL',
			'status' => "varchar(255) DEFAULT 'pending'",
			'service_code' => 'varchar(8) NOT NULL',
			'created_at' => 'datetime NOT NULL',
			'updated_at' => 'datetime NOT NULL',
		] );
	}
}