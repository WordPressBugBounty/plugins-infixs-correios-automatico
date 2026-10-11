<?php

namespace Infixs\CorreiosAutomatico\Core\Shipping;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Admin\Admin;
use Infixs\CorreiosAutomatico\Core\Shipping\Concerns\HasDiscountRules;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Models\WoocommerceShippingZoneMethod;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Package;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\ShippingCost;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\ShippingTime;
use Infixs\CorreiosAutomatico\Services\Correios\Packing\PackedPackage;
use Infixs\CorreiosAutomatico\Services\Correios\Packing\ServiceLimits;
use Infixs\CorreiosAutomatico\Services\Correios\Packing\VolumeFiller;
use Infixs\CorreiosAutomatico\Utils\Currency;
use Infixs\CorreiosAutomatico\Utils\Helper;
use Infixs\CorreiosAutomatico\Utils\NumberHelper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;
/**
 * Correios Automático Core Functions
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class CorreiosShippingMethod extends \WC_Shipping_Method {

	use HasDiscountRules;

	/**
	 * Action fired when the shipping cost calculation ends without a rate.
	 *
	 * @since 1.8.0
	 */
	const CALCULATION_FAILED_HOOK = 'infixs_correios_automatico_shipping_calculation_failed';

	/**
	 * Rate meta describing the volumes a shipment was quoted in.
	 *
	 * @var string
	 */
	const VOLUMES_META_KEY = '_volumes';

	/**
	 * Shipping line meta with the position of the volume in the shipment.
	 *
	 * @var string
	 */
	const VOLUME_INDEX_META_KEY = '_infixs_volume_index';

	/**
	 * Shipping line meta with how many volumes the shipment has.
	 *
	 * @var string
	 */
	const VOLUME_TOTAL_META_KEY = '_infixs_volume_total';

	/**
	 * Shipping line meta with the products travelling in the volume.
	 *
	 * @var string
	 */
	const VOLUME_ITEMS_META_KEY = '_infixs_volume_items';

	/**
	 * Shipping line meta with the id of the packaging the volume travels in.
	 *
	 * @var string
	 */
	const PACKAGING_ID_META_KEY = '_infixs_packaging_id';

	/**
	 * Shipping line meta with the name of the packaging the volume travels in.
	 *
	 * @var string
	 */
	const PACKAGING_NAME_META_KEY = 'infixs_packaging_name';

	/**
	 * Advanced mode
	 *
	 * @var bool
	 */
	protected $advanced_mode = false;

	/**
	 * International
	 *
	 * @var bool
	 */
	protected $international = false;

	/**
	 * Basic services
	 *
	 * @var string
	 */
	protected $basic_service = '';

	/**
	 * Advanced service
	 *
	 * @var string
	 */
	protected $advanced_service = '';

	/**
	 * Modic use range
	 *
	 * @var bool
	 */
	protected $modic_use_range = false;

	/**
	 * Shipping class
	 *
	 * @var array
	 */
	protected $shipping_class = [];

	/**
	 * Object type
	 *
	 * @var string
	 */
	protected $object_type = 'package';

	/**
	 * Origin postcode
	 *
	 * @var string
	 */
	protected $origin_postcode = '';

	/**
	 * Estimated delivery
	 *
	 * @var bool
	 */
	protected $estimated_delivery = false;

	/**
	 * Additional days
	 *
	 * @var int
	 */
	protected $additional_days = 0;

	/**
	 * How the delivery time shown on the checkout is built.
	 *
	 * `add` sums `additional_days` to what the carrier estimates (the legacy
	 * behaviour); `fixed` announces `fixed_delivery_days` and ignores the
	 * carrier estimate entirely.
	 *
	 * @since 1.9.1
	 *
	 * @var string "add"|"fixed"
	 */
	protected $delivery_time_mode = 'add';

	/**
	 * Delivery time announced when the mode is `fixed`.
	 *
	 * @since 1.9.1
	 *
	 * @var int
	 */
	protected $fixed_delivery_days = 0;

	/**
	 * Additional tax percentage
	 *
	 * @var int
	 */
	protected $additional_tax_percentage = 0;

	/**
	 * Additional tax
	 *
	 * @var int
	 */
	protected $additional_tax = 0;

	/**
	 * Own hands
	 *
	 * @var bool
	 */
	protected $own_hands = false;

	/**
	 * Receipt notice
	 *
	 * @var bool
	 */
	protected $receipt_notice = false;

	/**
	 * Electronic receipt notice
	 *
	 * @var bool
	 */
	protected $receipt_notice_electronic = false;

	/**
	 * Insurance
	 *
	 * @var bool
	 */
	protected $insurance = false;


	/**
	 * Min Insurance value
	 *
	 * @var bool
	 */
	protected $min_insurance_value = 0;


	/**
	 * Insurance customer cost
	 * 
	 * @var bool
	 */
	protected $insurance_customer_cost = true;

	/**
	 * Minimum height in cm
	 *
	 * @var float
	 */
	protected $minimum_height = 2;

	/**
	 * Minimum width in cm
	 *
	 * @var float
	 */
	protected $minimum_width = 11;

	/**
	 * Minimum length in cm
	 *
	 * @var float
	 */
	protected $minimum_length = 16;

	/**
	 * Minimum weight in kg
	 *
	 * @var float
	 */
	protected $minimum_weight = 0.1;

	/**
	 * Extra weight in kg
	 *
	 * @var float
	 */
	protected $extra_weight = 0;


	/**
	 * Auto prepost
	 *
	 * @var bool
	 */
	protected $auto_prepost = true;

	/**
	 * Auto prepost delay type: immediate or delayed
	 *
	 * @var string
	 */
	protected $auto_prepost_delay_type = 'immediate';

	/**
	 * Auto prepost delay value
	 *
	 * @var int
	 */
	protected $auto_prepost_delay_value = 1;

	/**
	 * Auto prepost delay unit: minutes, hours or days
	 *
	 * @var string
	 */
	protected $auto_prepost_delay_unit = 'minutes';

	/**
	 * Hide when Exceed
	 *
	 * @var bool
	 */
	protected $hide_exceed = false;

	/**
	 * Split the shipment into volumes when it exceeds the service limits
	 *
	 * @var bool
	 */
	protected $split_exceed = true;

	/**
	 * Extra weight type
	 *
	 * @var string "product"|"order"
	 */
	protected $extra_weight_type = 'order';

	/**
	 * Enable import tax
	 *
	 * @var bool
	 */
	protected $enable_import_tax = false;

	/**
	 * Enable ICMS tax
	 *
	 * @var bool
	 */
	protected $enable_icms_tax = false;

	/**
	 * When less minimum
	 *
	 * @var string "force"|"hide"|"none"
	 */
	protected $when_less_minimum = 'force';

	/**
	 * Advanced rules
	 *
	 * @since 1.5.9
	 * 
	 * @var array
	 */
	protected $advanced_rules = [];

	/**
	 * When exceed maximum insurance
	 *
	 * @var string "ignore_insurance"|"hide_method"
	 */
	protected $when_exceed_maximum_insurance = 'ignore_insurance';

	/**
	 * Initialize the Correios Automático shipping method.
	 *
	 * @param int $instance_id Shipping method instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id = 'infixs-correios-automatico';
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'Correios Automático', 'infixs-correios-automatico' );
		$this->method_description = __( 'Método de envio dos Correios Automático.', 'infixs-correios-automatico' );
		$this->supports = [
			'shipping-zones',
			'instance-settings',
		];

		add_filter( "woocommerce_shipping_{$this->id}_instance_settings_values", [ $this, "update_instance_settings_values" ] );
		add_filter( "woocommerce_shipping_{$this->id}_instance_option", [ $this, "get_instance_option_filter" ], 10, 2 );

		$this->init_form_fields();

		$this->enabled = $this->get_option( 'enabled' );
		$this->title = $this->get_option( 'title' );
		$this->advanced_mode = Sanitizer::boolean( $this->get_option( 'advanced_mode' ) );
		$this->international = Sanitizer::boolean( $this->get_option( 'international' ) );
		$this->basic_service = $this->get_option( 'basic_service' );
		$this->advanced_service = $this->get_option( 'advanced_service' );
		$this->shipping_class = is_array( $this->get_option( 'shipping_class' ) ) ? $this->get_option( 'shipping_class' ) : [];
		$this->modic_use_range = Sanitizer::boolean( $this->get_option( 'modic_use_range' ) );
		$this->origin_postcode = $this->get_option( 'origin_postcode' );
		$this->estimated_delivery = Sanitizer::boolean( $this->get_option( 'estimated_delivery' ) );
		$this->additional_days = $this->get_option( 'additional_days' );
		// Normaliza aqui em vez de confiar só no default do form field: um valor
		// salvo vazio não cai no default.
		$this->delivery_time_mode = 'fixed' === $this->get_option( 'delivery_time_mode' ) ? 'fixed' : 'add';
		$this->fixed_delivery_days = (int) $this->get_option( 'fixed_delivery_days' );
		$this->additional_tax = $this->get_option( 'additional_tax' );
		$this->additional_tax_percentage = (int) $this->get_option( 'additional_tax_percentage' );
		$this->own_hands = Sanitizer::boolean( $this->get_option( 'own_hands' ) );
		$this->receipt_notice = Sanitizer::boolean( $this->get_option( 'receipt_notice' ) );
		$this->receipt_notice_electronic = Sanitizer::boolean( $this->get_option( 'receipt_notice_electronic' ) );
		$this->minimum_height = (float) $this->get_option( 'minimum_height' );
		$this->minimum_width = (float) $this->get_option( 'minimum_width' );
		$this->minimum_length = (float) $this->get_option( 'minimum_length' );
		$this->minimum_weight = (float) $this->get_option( 'minimum_weight' );
		$this->extra_weight = (float) $this->get_option( 'extra_weight' );
		$this->insurance = Sanitizer::boolean( $this->get_option( 'insurance' ) );
		$this->min_insurance_value = (int) $this->get_option( 'min_insurance_value' );
		$this->insurance_customer_cost = Sanitizer::boolean( $this->get_option( 'insurance_customer_cost' ) );
		$this->auto_prepost = Sanitizer::boolean( $this->get_option( 'auto_prepost' ) );
		$this->auto_prepost_delay_type = $this->get_option( 'auto_prepost_delay_type' ) ?? 'immediate';
		$this->auto_prepost_delay_value = (int) $this->get_option( 'auto_prepost_delay_value', 1 );
		$this->auto_prepost_delay_unit = $this->get_option( 'auto_prepost_delay_unit' ) ?? 'minutes';
		$this->extra_weight_type = $this->get_option( 'extra_weight_type' );
		$this->init_discount_options();
		$this->hide_exceed = Sanitizer::boolean( $this->get_option( 'hide_exceed' ) );
		$this->split_exceed = Sanitizer::boolean( $this->get_option( 'split_exceed' ) );
		$this->enable_import_tax = Sanitizer::boolean( $this->get_option( 'enable_import_tax' ) );
		$this->enable_icms_tax = Sanitizer::boolean( $this->get_option( 'enable_icms_tax' ) );
		$this->when_less_minimum = $this->get_option( 'when_less_minimum' );
		$this->advanced_rules = $this->get_option( 'advanced_rules' ) ?? [];
		$this->when_exceed_maximum_insurance = $this->get_option( 'when_exceed_maximum_insurance' );
	}

	public function get_instance_option_filter( $value, $key ) {
		if ( $key === 'discount_rules' && is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		if ( $key === 'advanced_rules' && is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		return $value;
	}

	public function update_instance_settings_values( $value ) {
		// this checked before by woocommerce
		// phpcs:ignore
		if ( ! $value || ! isset( $_REQUEST['instance_id'] ) || absint( $_REQUEST['instance_id'] ) !== $this->instance_id ) { // WPCS: input var ok, CSRF ok.
			return $value;
		}

		WooCommerceShippingZoneMethod::update( [
			"is_enabled" => $value['enabled'] === 'yes' ? 1 : 0,
		], [
			"instance_id" => $this->instance_id,
		] );

		return $value;
	}

	public function get_enabled_option() {
		$instance = WooCommerceShippingZoneMethod::where( 'instance_id', $this->instance_id )->first();
		return ( $instance !== null && $instance->is_enabled == "1" ) ? 'yes' : 'no';
	}

	public function init_form_fields() {
		$this->instance_form_fields = [
			'enabled' => [
				'title' => __( 'Enable/Disable', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'label' => __( 'Enable this shipping method', 'infixs-correios-automatico' ),
				'default' => 'yes',
			],
			'title' => [
				'title' => __( 'Title', 'infixs-correios-automatico' ),
				'type' => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => $this->method_title,
			],
			'advanced_mode' => [
				'title' => __( 'Advanced Mode', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Advanded mode controls', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'object_type' => [
				'title' => __( 'Object Type', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Select the object type.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'package' => __( 'Pacote', 'infixs-correios-automatico' ),
					'letter' => __( 'Carta', 'infixs-correios-automatico' ),
				],
				'default' => 'package',
			],
			'international' => [
				'title' => __( 'International', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable international shipping.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'basic_service' => [
				'title' => __( 'Basic Services', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Select the basic services that will be available for the user.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'pac' => __( 'PAC', 'infixs-correios-automatico' ),
					'sedex' => __( 'SEDEX', 'infixs-correios-automatico' ),
					'sedex10' => __( 'SEDEX 10', 'infixs-correios-automatico' ),
					'sedex12' => __( 'SEDEX 12', 'infixs-correios-automatico' ),
					'sedexhoje' => __( 'SEDEX HOJE', 'infixs-correios-automatico' ),
					'minienvios' => __( 'Mini Envios', 'infixs-correios-automatico' ),
					'impressonormal' => __( 'IMPRESSO NORMAL', 'infixs-correios-automatico' ),
					'impressomodico' => __( 'IMPRESSO MÓDICO', 'infixs-correios-automatico' ),
					'packet_standard' => __( 'PACKET STANDARD', 'infixs-correios-automatico' ),
					'packet_express' => __( 'PACKET EXPRESS', 'infixs-correios-automatico' ),
					'exporta_facil_standard' => __( 'EXPORTA FÁCIL STANDARD', 'infixs-correios-automatico' ),
					'exporta_facil_premium' => __( 'EXPORTA FÁCIL PREMIUM', 'infixs-correios-automatico' ),
					'exporta_facil_economico' => __( 'EXPORTA FÁCIL ECONÔMICO', 'infixs-correios-automatico' ),
					'exporta_facil_expresso' => __( 'EXPORTA FÁCIL EXPRESSO', 'infixs-correios-automatico' ),
				],
				'default' => '',
			],
			'advanced_service' => [
				'title' => __( 'Advanced Services', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Enter the advanced services that will be available for the user.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => DeliveryServiceCode::getAll(),
				'default' => '',
			],
			'modic_use_range' => [
				'title' => __( 'Modic Use Range', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the modic use range.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'shipping_class' => [
				'title' => __( 'Shipping Class', 'infixs-correios-automatico' ),
				'type' => 'multiselect',
				'description' => __( 'Select for which shipping class this method will be applied.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => [],
				'sanitize_callback' => [ $this, 'sanitizer_shipping_classes' ],
				'options' => $this->get_shipping_classes_options(),
			],
			'origin_postcode' => [
				'title' => __( 'Postcode', 'infixs-correios-automatico' ),
				'type' => 'text',
				'description' => __( 'Enter the postcode of the sender.', 'infixs-correios-automatico' ),
				'sanitize_callback' => [ Sanitizer::class, 'numeric_text' ],
				'desc_tip' => true,
				'default' => Sanitizer::numeric_text( get_option( 'woocommerce_store_postcode' ) ),
			],
			'estimated_delivery' => [
				'title' => __( 'Estimated Delivery', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the estimated delivery.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'yes',
			],
			'delivery_time_mode' => [
				'title' => __( 'Delivery Time Mode', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Define se o prazo exibido soma dias ao prazo da transportadora ou usa um valor fixo.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'add' => __( 'Somar dias ao prazo da transportadora', 'infixs-correios-automatico' ),
					'fixed' => __( 'Definir um prazo fixo', 'infixs-correios-automatico' ),
				],
				'default' => 'add',
			],
			'additional_days' => [
				'title' => __( 'Additional Days', 'infixs-correios-automatico' ),
				'type' => 'number',
				'description' => __( 'Enter the additional days for the estimated delivery.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => '0',
			],
			'fixed_delivery_days' => [
				'title' => __( 'Fixed Delivery Days', 'infixs-correios-automatico' ),
				'type' => 'number',
				'description' => __( 'Prazo anunciado no checkout, ignorando o prazo da transportadora. Os dias adicionais definidos no produto ou na classe de entrega continuam sendo somados.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => '0',
			],
			'additional_tax' => [
				'title' => __( 'Additional Tax', 'infixs-correios-automatico' ),
				'type' => 'money',
				'description' => __( 'Enter the additional tax for the shipping.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => '0',
				'sanitize_callback' => [ Sanitizer::class, 'money100' ],
			],
			'additional_tax_percentage' => [
				'title' => __( 'Additional Tax Percentage', 'infixs-correios-automatico' ),
				'type' => 'number',
				'description' => __( 'Enter the additional tax percentage for the shipping.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => '0',
				'sanitize_callback' => [ Sanitizer::class, 'numeric_text' ],
			],
			'own_hands' => [
				'title' => __( 'Own Hands', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the own hands.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'receipt_notice' => [
				'title' => __( 'Receipt Notice', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the receipt notice.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'receipt_notice_electronic' => [
				'title' => __( 'Electronic Receipt Notice', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the electronic receipt notice.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'insurance' => [
				'title' => __( 'Insuranse', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the insurance for package.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'when_exceed_maximum_insurance' => [
				'title' => __( 'When Exceed Maximum Insurance', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Select the action when the package exceeds the maximum insurance.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'ignore_insurance' => __( 'Ignore Insurance', 'infixs-correios-automatico' ),
					'hide_method' => __( 'Hide Method', 'infixs-correios-automatico' ),
				],
				'default' => 'ignore_insurance',
			],
			'min_insurance_value' => [
				'title' => __( 'Min Insurance Value', 'infixs-correios-automatico' ),
				'type' => 'money',
				'description' => __( 'Min insurance value for order.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => '50',
				'sanitize_callback' => [ Sanitizer::class, 'money100' ],
			],
			'insurance_customer_cost' => [
				'title' => __( 'Insurance Customer Cost', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the insurance customer cost.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'yes',
			],
			'minimum_height' => [
				'title' => __( 'Minimum Height', 'infixs-correios-automatico' ),
				'type' => 'float',
				'description' => __( 'Define the minimum height.', 'infixs-correios-automatico' ),
				'sanitize_callback' => [ Sanitizer::class, 'float_text' ],
				'desc_tip' => true,
				'default' => '2',
			],
			'minimum_width' => [
				'title' => __( 'Minimum Width', 'infixs-correios-automatico' ),
				'type' => 'float',
				'description' => __( 'Define the minimum width.', 'infixs-correios-automatico' ),
				'sanitize_callback' => [ Sanitizer::class, 'float_text' ],
				'desc_tip' => true,
				'default' => '11',
			],
			'minimum_length' => [
				'title' => __( 'Minimum Length', 'infixs-correios-automatico' ),
				'type' => 'float',
				'description' => __( 'Define the minimum length.', 'infixs-correios-automatico' ),
				'sanitize_callback' => [ Sanitizer::class, 'float_text' ],
				'desc_tip' => true,
				'default' => '16',
			],
			'minimum_weight' => [
				'title' => __( 'Minimum Weight', 'infixs-correios-automatico' ),
				'type' => 'float',
				'description' => __( 'Define the minimum weight.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'sanitize_callback' => [ Sanitizer::class, 'float_text' ],
				'default' => '0.100',
			],
			'extra_weight' => [
				'title' => __( 'Extra Weight', 'infixs-correios-automatico' ),
				'type' => 'float',
				'description' => __( 'Define the extra weight.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'sanitize_callback' => [ Sanitizer::class, 'float_text' ],
				'default' => '0',
			],
			'auto_prepost' => [
				'title' => __( 'Insuranse', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the insurance for package.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'yes',
			],
			'auto_prepost_delay_type' => [
				'title' => __( 'Auto Prepost Delay Type', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'When to trigger the automatic pre-posting.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'immediate' => __( 'Immediately', 'infixs-correios-automatico' ),
					'delayed' => __( 'After a delay', 'infixs-correios-automatico' ),
				],
				'default' => 'immediate',
			],
			'auto_prepost_delay_value' => [
				'title' => __( 'Auto Prepost Delay Value', 'infixs-correios-automatico' ),
				'type' => 'number',
				'description' => __( 'Amount of time to wait before triggering the automatic pre-posting.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'sanitize_callback' => 'absint',
				'default' => '1',
			],
			'auto_prepost_delay_unit' => [
				'title' => __( 'Auto Prepost Delay Unit', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Unit of time for the automatic pre-posting delay.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'minutes' => __( 'Minutes', 'infixs-correios-automatico' ),
					'hours' => __( 'Hours', 'infixs-correios-automatico' ),
					'days' => __( 'Days', 'infixs-correios-automatico' ),
				],
				'default' => 'minutes',
			],
			'extra_weight_type' => [
				'title' => __( 'Extra Weight Type', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Select the extra weight type.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'order' => __( 'Per Order', 'infixs-correios-automatico' ),
					'product' => __( 'Per Product', 'infixs-correios-automatico' ),
				],
				'default' => 'order',
			],
			'hide_exceed' => [
				'title' => __( 'Hide When Exceed', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Hide the shipping method when the package exceeds the maximum weight.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'split_exceed' => [
				'title' => __( 'Split Into Volumes', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Split the shipment into more volumes when it exceeds the limits of the service, quoting each one.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'yes',
			],
			'enable_import_tax' => [
				'title' => __( 'Enable Import Tax', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the import tax.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'enable_icms_tax' => [
				'title' => __( 'Enable ICMS Tax', 'infixs-correios-automatico' ),
				'type' => 'checkbox',
				'description' => __( 'Enable the ICMS tax.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'default' => 'no',
			],
			'when_less_minimum' => [
				'title' => __( 'Quando menor que o mínimo', 'infixs-correios-automatico' ),
				'type' => 'select',
				'description' => __( 'Select the action when the package is less than the minimum weight.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'options' => [
					'force' => __( 'Force', 'infixs-correios-automatico' ),
					'hide' => __( 'Hide', 'infixs-correios-automatico' ),
					'none' => __( 'None', 'infixs-correios-automatico' ),
				],
				'default' => 'force',
			],
			'advanced_rules' => [
				'title' => __( 'Advanced Rules', 'infixs-correios-automatico' ),
				'type' => 'array',
				'description' => __( 'Add or remove advanced rules.', 'infixs-correios-automatico' ),
				'desc_tip' => true,
				'sanitize_callback' => [ $this, 'sanitize_advanced_rules' ],
				'default' => [],
			],
		] + $this->discount_form_fields();
	}

	protected function sanitizer_shipping_classes( $value ) {
		$cleaned = Sanitizer::multiselect( $value, 'intval' );
		$available_classes = array_keys( $this->get_shipping_classes_options() );
		$cleaned = array_intersect( $cleaned, $available_classes );
		return $cleaned;
	}

	public function sanitize_advanced_rules( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( stripslashes( $value ), true );
		}

		foreach ( $value as $key => $rule ) {
			$value[ $key ]['type'] = sanitize_text_field( $rule['type'] );
			$value[ $key ]['condition'] = sanitize_text_field( $rule['condition'] );
			$value[ $key ]['action'] = sanitize_text_field( $rule['action'] );

			if ( $value[ $key ]['type'] === 'cart_shipping_class' ) {
				$value[ $key ]['value'] = Sanitizer::multiselect( $rule['value'], 'intval' );
			} elseif ( in_array( $value[ $key ]['type'], [ 'cart_weight', 'volume_weight' ], true ) ) {
				$value[ $key ]['value'] = floatval( $rule['value'] );
			} else {
				$value[ $key ]['value'] = sanitize_text_field( $rule['value'] );
			}
		}

		return $value;
	}

	public function admin_options() {
		Admin::load_dashboard_scripts();

		wp_localize_script(
			'infixs-correios-automatico-admin',
			'infixsCorreiosAutomaticoWCSettings',
			[
				'fields' => $this->map_options(),
				'prefix' => "{$this->plugin_id}{$this->id}_",
				'advanced_service_groups' => DeliveryServiceCode::getGroups(),
				'carrier' => 'correios', //@since 1.9.0
			]
		);

		$this->get_admin_options_html();
	}

	/**
	 * Get shipping classes options.
	 *
	 * @return array
	 */
	protected function get_shipping_classes_options() {
		$shipping_classes = WC()->shipping->get_shipping_classes();
		$options = [];

		if ( ! empty( $shipping_classes ) ) {
			$options += wp_list_pluck( $shipping_classes, 'name', 'term_id' );
		}

		return $options;
	}

	public function get_shipping_classes() {
		return $this->sanitizer_shipping_classes( $this->shipping_class );
	}

	protected function has_shipping_class( $package ) {
		$pass = true;

		if ( ! is_array( $this->shipping_class ) ) {
			return $pass;
		}

		$shipping_classes = $this->sanitizer_shipping_classes( $this->shipping_class );

		if ( empty( $shipping_classes ) ) {
			return $pass;
		}

		foreach ( $package['contents'] as $item_id => $values ) {
			$product = $values['data'];
			$qty = $values['quantity'];

			if ( $qty > 0 && $product && $product->needs_shipping() ) {
				if ( ! in_array( $product->get_shipping_class_id(), $shipping_classes ) ) {
					$pass = false;
					Log::info( "Não foi possível calcular o frete pois a classe requirida no método não existe no produto" );
					$this->calculation_failed(
						'shipping_class',
						__( 'Há produtos fora da classe de entrega exigida por este método. Ajuste a classe de entrega dos produtos ou limpe o campo "Classe de entrega" nas configurações do método.', 'infixs-correios-automatico' ),
						$package
					);
					break;
				}
			}
		}

		return $pass;
	}

	/**
	 * Notify that the shipping cost could not be calculated.
	 *
	 * Fires an action with a machine readable code and a message written for the store
	 * operator, so the reason of a quote that produced no rate can be shown in the admin
	 * instead of being lost in the logs.
	 *
	 * @since 1.8.0
	 *
	 * @param string $code    Failure code.
	 * @param string $message Message describing what has to be done.
	 * @param array  $package Shipping package.
	 *
	 * @return void
	 */
	protected function calculation_failed( $code, $message, $package ) {
		do_action( self::CALCULATION_FAILED_HOOK, $code, $message, $package, $this );
	}

	public function map_options() {
		$options = $this->get_instance_form_fields();
		$map = [];

		foreach ( $options as $key => $option ) {
			$value = $this->get_option( $key );

			switch ( $option['type'] ) {
				case 'checkbox':
					$map[ $key ]['value'] = $value === 'yes' ? true : false;
					break;
				case 'number':
					$map[ $key ]['value'] = (int) $value;
					break;
				case 'float':
					$map[ $key ]['value'] = (float) $value;
					break;
				case 'money':
					$map[ $key ]['value'] = ( (int) $value ) / 100;
					break;
				case 'text':
					$map[ $key ]['value'] = $value;
					break;
				case 'multiselect':
					$map[ $key ]['value'] = is_array( $value ) ? $value : [];
					break;
				case 'array':
					if ( empty( $value ) || ! is_array( $value ) ) {
						$value = [];
						$map[ $key ]['value'] = [];
					} else {
						$map[ $key ]['value'] = $value;
					}
					break;
				default:
					$map[ $key ]['value'] = $value;
					break;
			}

			if ( $key == 'enabled' ) {
				$map[ $key ]['value'] = Sanitizer::boolean( $this->get_enabled_option() );
			}

			if ( $key === 'discount_rules' ) {
				$map[ $key ]['value'] = $this->map_discount_rules_value( $value );
			}

			if ( $key === 'advanced_rules' ) {
				foreach ( $value as $rule_key => $rule ) {
					$map[ $key ]['value'][ $rule_key ]['enabled'] = Sanitizer::boolean( $rule['enabled'] );
					$map[ $key ]['value'][ $rule_key ]['type'] = sanitize_text_field( $rule['type'] );
					$map[ $key ]['value'][ $rule_key ]['condition'] = sanitize_text_field( $rule['condition'] );
					if ( $map[ $key ]['value'][ $rule_key ]['type'] === 'cart_shipping_class' ) {
						$map[ $key ]['value'][ $rule_key ]['value'] = Sanitizer::multiselect( $rule['value'] );
					} elseif ( in_array( $map[ $key ]['value'][ $rule_key ]['type'], [ 'cart_weight', 'volume_weight' ], true ) ) {
						$map[ $key ]['value'][ $rule_key ]['value'] = floatval( $rule['value'] ?? 0 );
					} else {
						$map[ $key ]['value'][ $rule_key ]['value'] = sanitize_text_field( $rule['value'] );
					}
					$map[ $key ]['value'][ $rule_key ]['action'] = sanitize_text_field( $rule['action'] );

				}
			}

			if ( $key === 'shipping_class' ) {
				$map[ $key ]['value'] = $this->sanitizer_shipping_classes( $value );
			}


			if ( $option['type'] === 'select' || $option['type'] === 'multiselect' ) {
				$map[ $key ]['options'] = $option['options'];
			}

			if ( isset( $option['title'] ) )
				$map[ $key ]['title'] = $option['title'];

			if ( isset( $option['description'] ) )
				$map[ $key ]['description'] = $option['description'];

		}

		return $map;
	}

	public function get_admin_options_html() {
		include_once \INFIXS_CORREIOS_AUTOMATICO_PLUGIN_PATH . 'src/Presentation/admin/views/html-wc-shipping-settings.php';
	}

	/**
	 * Check if it's possible to calculate the shipping.
	 *
	 * @param  array $package Cart package.
	 * @param  string $product_code Product code.
	 * 
	 * @return bool
	 */
	protected function can_be_calculated( $package, $product_code ) {
		if ( ! in_array( $product_code, DeliveryServiceCode::getInternationals() ) ) {
			if ( empty( $package['destination']['postcode'] ) || 'BR' !== ( $package['destination']['country'] ?? '' ) ) {
				Log::info( "Não é possível calcular o frete para o pacote sem CEP de destino ou quando o país não é BR.",
					[
						'postcode' => $package['destination']['postcode'] ?? '',
						'product_code' => $product_code,
					]
				);

				if ( empty( $package['destination']['postcode'] ) ) {
					$this->calculation_failed(
						'destination_postcode',
						__( 'CEP de entrega não informado. Preencha o CEP no endereço de entrega do pedido antes de calcular.', 'infixs-correios-automatico' ),
						$package
					);
				} else {
					$this->calculation_failed(
						'destination_country',
						__( 'Este serviço é nacional e o endereço de entrega do pedido não é do Brasil. Use um serviço internacional (Packet ou Exporta Fácil) para esse destino.', 'infixs-correios-automatico' ),
						$package
					);
				}

				return false;
			}
		} else {
			if ( 'BR' === ( $package['destination']['country'] ?? '' ) ) {
				Log::info( "Não é possível calcular o frete internacional para o pacote com CEP de destino no Brasil.", [
					'product_code' => $product_code,
					'postcode' => $package['destination']['postcode'] ?? '',
				] );
				$this->calculation_failed(
					'international_service',
					__( 'Este é um serviço internacional e o endereço de entrega é no Brasil. Selecione um serviço nacional (PAC ou SEDEX) na linha de entrega.', 'infixs-correios-automatico' ),
					$package
				);
				return false;
			}
		}

		return true;
	}

	public function resolve_basic_service( $service ) {
		$is_contract_enabled = Config::boolean( 'auth.active' );

		switch ( $service ) {
			case 'pac':
				return $is_contract_enabled ? DeliveryServiceCode::PAC_CONTRATO_AG : DeliveryServiceCode::PAC;
			case 'sedex':
				return $is_contract_enabled ? DeliveryServiceCode::SEDEX_CONTRATO_AG : DeliveryServiceCode::SEDEX;
			case 'sedex10':
				return $is_contract_enabled ? DeliveryServiceCode::SEDEX_10_CONTRATO_AG : DeliveryServiceCode::SEDEX_10;
			case 'sedex12':
				return $is_contract_enabled ? DeliveryServiceCode::SEDEX_12_CONTRATO_AG : DeliveryServiceCode::SEDEX_12;
			case 'sedexhoje':
				return $is_contract_enabled ? DeliveryServiceCode::SEDEX_HOJE_CONTRATO_AG : DeliveryServiceCode::SEDEX_HOJE;
			case 'minienvios':
				return DeliveryServiceCode::CORREIOS_MINI_ENVIOS_CTR_AG;
			case 'impressonormal':
				return DeliveryServiceCode::IMPRESSO_NORMAL;
			case 'impressomodico':
				return DeliveryServiceCode::IMPRESSO_MODICO;
			case 'packet_standard':
				return DeliveryServiceCode::PACKET_STANDARD;
			case 'packet_express':
				return DeliveryServiceCode::PACKET_EXPRESS;
			case 'exporta_facil_standard':
				return DeliveryServiceCode::EXPORTA_FACIL_STANDARD;
			case 'exporta_facil_premium':
				return DeliveryServiceCode::EXPORTA_FACIL_PREMIUM;
			case 'exporta_facil_economico':
				return DeliveryServiceCode::EXPORTA_FACIL_ECNOMICO;
			case 'exporta_facil_expresso':
				return DeliveryServiceCode::EXPORTA_FACIL_EXPRESSO;

			default:
				return $is_contract_enabled ? DeliveryServiceCode::PAC_CONTRATO_AG : DeliveryServiceCode::PAC;
		}
	}

	/**
	 * Get the package data.
	 *
	 * @param  array $package Cart package.
	 * 
	 * @return Package
	 */
	public function get_package( $package ) {
		$shipping_package = new Package( $package );
		$shipping_package->setExtraWeight( $this->extra_weight );
		$shipping_package->setExtraWeightType( $this->extra_weight_type );

		if ( $this->when_less_minimum == 'force' ) {
			$shipping_package->setMinWeight( $this->minimum_weight );
		}

		$shipping_package->setMinHeight( $this->minimum_height );
		$shipping_package->setMinWidth( $this->minimum_width );
		$shipping_package->setMinLength( $this->minimum_length );
		return $shipping_package;
	}


	/**
	 * Check if the dimensions are within the allowed limits for the given product code.
	 *
	 * @param float $length
	 * @param float $width
	 * @param float $height
	 * @param float $weight
	 * @param string $product_code
	 *
	 *
	 * @return bool
	 */
	public function are_dimensions_within_limits( $length, $width, $height, $weight, $product_code ) {
		return ServiceLimits::accepts( DeliveryServiceCode::getDimensionLimits( $product_code ), [
			'length' => $length,
			'width' => $width,
			'height' => $height,
			'weight' => $weight,
		] );
	}

	/**
	 * Describe how a package ships, volume by volume, without quoting it.
	 *
	 * It follows the same plan as the quote, so a native method linked to a
	 * Correios service, or an order written by another system, hands the carrier
	 * the very boxes this method would have priced.
	 *
	 * @since 1.11.0
	 *
	 * @param array       $package      Cart package.
	 * @param string|null $product_code Correios service, the method's own when null.
	 *
	 * @return array{
	 *     data: array{weight: float, length: float, width: float, height: float},
	 *     packaging: array{id: string, name: string}|null,
	 *     volumes: array{package: Package, data: array{weight: float, length: float, width: float, height: float}, packaging: array{id: string, name: string}|null}[],
	 *     within_limits: bool
	 * } `data` and `packaging` belong to the shipping line: those of the only volume,
	 *   or the measures of the whole shipment when it travels in several volumes.
	 */
	public function describe_shipment( $package, $product_code = null ) {
		$product_code = $product_code ? $product_code : $this->get_product_code();
		$whole = $this->get_package( $package );
		$data = $whole->get_data();
		$plan = $this->plan_shipment( $package, $product_code, $data, false );
		$volumes = [];

		foreach ( $plan['volumes'] ? $plan['volumes'] : [ $whole ] as $volume_package ) {
			$volumes[] = [
				'package' => $volume_package,
				'data' => $volume_package === $whole ? $data : $volume_package->get_data(),
				'packaging' => $this->get_volume_packaging( $volume_package ),
			];
		}

		$single = count( $volumes ) === 1;

		return [
			'data' => $single ? $volumes[0]['data'] : $data,
			'packaging' => $single ? $volumes[0]['packaging'] : null,
			'volumes' => $volumes,
			'within_limits' => $plan['within_limits'],
		];
	}

	/**
	 * Describe the volumes of a shipment that is not priced by this method.
	 *
	 * A native method linked to a Correios service charges its own price — free
	 * shipping, a flat rate — but the store still hands the carrier one box per
	 * volume, so the order has to be split all the same.
	 *
	 * @since 1.11.0
	 *
	 * @param array  $package      Cart package.
	 * @param string $product_code Correios service the method is linked to.
	 *
	 * @return string|false
	 */
	public function linked_volumes_meta( $package, $product_code = null ) {
		$shipment = $this->describe_shipment( $package, $product_code );

		if ( count( $shipment['volumes'] ) < 2 ) {
			return false;
		}

		$quotes = [];

		foreach ( $shipment['volumes'] as $volume ) {
			$quotes[] = [
				'shipping_cost' => null,
				'cost' => 0,
				'package' => $volume['package'],
				'data' => $volume['data'],
			];
		}

		return $this->volumes_meta( $quotes );
	}

	/**
	 * Plan the volumes a package ships in.
	 *
	 * @since 1.11.0
	 *
	 * @param array  $package      Cart package.
	 * @param string $product_code Correios service.
	 * @param array  $measure      Length, width, height and weight the whole package is quoted with.
	 * @param bool   $overridden   Whether those measures were informed instead of taken from the products.
	 *
	 * @return array{volumes: Package[]|null, within_limits: bool}
	 */
	private function plan_shipment( $package, $product_code, $measure, $overridden ) {
		$limits = DeliveryServiceCode::getDimensionLimits( $product_code );
		$filler = null;
		$factory = function ($contents, $index) use ($package) {
			return $this->get_volume_package( $package, $contents, $index );
		};

		if ( $limits ) {
			/**
			 * Filters the filler that packs the volumes of a shipment.
			 *
			 * A filler returned here packs every shipment of the service, not only
			 * the ones over its limits, and each volume ships with the measures and
			 * the packaging the filler describes. When it cannot pack the shipment,
			 * the units are stacked in the largest box the service takes, as they
			 * are without a filler. Plans are kept for the rest of the request by
			 * the contents, the service, the settings of the method and the class
			 * of the filler.
			 *
			 * @since 1.11.0
			 *
			 * @param VolumeFiller|null      $filler  Filler, null to stack the units in the largest box the service takes.
			 * @param array                  $limits  Limits of the service.
			 * @param array                  $package Cart package.
			 * @param CorreiosShippingMethod $method  Shipping method.
			 * @param callable               $factory Receives the contents of a volume and its index, returns the Package
			 *                                        the volume is weighed with: products and the extra weight of the method.
			 */
			$filler = apply_filters( 'infixs_correios_automatico_volume_filler', null, $limits, $package, $this, $factory );
		}

		/**
		 * Filters how many units a shipment may have to be stacked unit by unit.
		 *
		 * Above it, the cubage estimate decides whether the shipment is over the
		 * limits and how it is split.
		 *
		 * @since 1.11.0
		 *
		 * @param int                    $max_units Largest number of units.
		 * @param array                  $package   Cart package.
		 * @param CorreiosShippingMethod $method    Shipping method.
		 */
		$max_units = (int) apply_filters( 'infixs_correios_automatico_packing_max_units', 200, $package, $this );

		return Container::volumePlanner()->plan( $package, $limits, $factory, [
			'measure' => $measure,
			'overridden' => $overridden,
			'split' => $this->split_exceed,
			'max_volumes' => $this->get_max_volumes( $package ),
			'max_units' => $max_units,
			'filler' => $filler instanceof VolumeFiller ? $filler : null,
			'settings' => [
				$product_code,
				$this->extra_weight,
				$this->extra_weight_type,
				$this->when_less_minimum,
				$this->minimum_weight,
				$this->minimum_length,
				$this->minimum_width,
				$this->minimum_height,
			],
		] );
	}

	/**
	 * Get how many volumes one shipment may be broken into.
	 *
	 * @since 1.11.0
	 *
	 * @param array $package Cart package.
	 *
	 * @return int
	 */
	private function get_max_volumes( $package ) {
		/**
		 * Filters how many volumes one shipment may be broken into.
		 *
		 * @since 1.11.0
		 *
		 * @param int                    $max_volumes Largest number of volumes.
		 * @param array                  $package     Cart package.
		 * @param CorreiosShippingMethod $method      Shipping method.
		 */
		return (int) apply_filters( 'infixs_correios_automatico_max_volumes', 10, $package, $this );
	}

	/**
	 * Build the quote of each volume of the shipment.
	 *
	 * A shipment that travels in a single volume keeps the value it declares
	 * whole; each volume of a split shipment declares what is inside it.
	 *
	 * @since 1.11.0
	 *
	 * @param Package[]    $packages      Package of each volume.
	 * @param ShippingCost $shipping_cost Quote of the whole shipment.
	 * @param string       $product_code  Correios service being quoted.
	 *
	 * @return ShippingCost[]
	 */
	private function volume_costs( $packages, $shipping_cost, $product_code ) {
		$volumes = [];

		foreach ( $packages as $volume_package ) {
			$volume = new ShippingCost(
				$product_code,
				$shipping_cost->getOriginPostcode(),
				$shipping_cost->getDestinationPostcode(),
				$shipping_cost->getDestinationCountry()
			);

			$volume->setPackage( $volume_package );
			$volume->setOwnHands( $shipping_cost->getOwnHands() );
			$volume->setReceiptNotice( $shipping_cost->getReceiptNotice() );
			$volume->setReceiptNoticeElectronic( $shipping_cost->getReceiptNoticeElectronic() );
			$volume->setModico( $shipping_cost->getModico() );

			if ( $shipping_cost->getInsuranceDeclarationValue() ) {
				$volume->setInsuranceDeclarationValue( count( $packages ) === 1
					? $shipping_cost->getInsuranceDeclarationValue()
					: $this->get_contents_cost( $volume_package->get_contents() ) );
			}

			$volumes[] = $volume;
		}

		if ( count( $volumes ) > 1 ) {
			Log::debug( "Encomenda dividida em " . count( $volumes ) . " volumes.", [
				'product_code' => $product_code,
			] );
		}

		return $volumes;
	}

	/**
	 * Get the measures a quote is made with.
	 *
	 * @since 1.11.0
	 *
	 * @param ShippingCost $shipping_cost Quote.
	 *
	 * @return array{length: float, width: float, height: float, weight: float}
	 */
	private function get_cost_measure( $shipping_cost ) {
		return [
			'length' => $shipping_cost->getLength(),
			'width' => $shipping_cost->getWidth(),
			'height' => $shipping_cost->getHeight(),
			'weight' => $shipping_cost->getWeight(),
		];
	}

	/**
	 * Get the packaging a volume travels in.
	 *
	 * @since 1.11.0
	 *
	 * @param Package|null $volume_package Package of the volume.
	 *
	 * @return array{id: string, name: string}|null
	 */
	private function get_volume_packaging( $volume_package ) {
		return $volume_package instanceof PackedPackage ? $volume_package->get_packaging() : null;
	}

	/**
	 * Build the package of a single volume.
	 *
	 * The weight the store adds to every order rides on the first volume only,
	 * otherwise a shipment broken in three would be charged for it three times.
	 *
	 * @since 1.11.0
	 *
	 * @param array $package  Cart package.
	 * @param array $contents Contents of the volume.
	 * @param int   $index    Position of the volume in the shipment.
	 *
	 * @return Package
	 */
	private function get_volume_package( $package, $contents, $index ) {
		$volume_package = $package;
		$volume_package['contents'] = $contents;
		$volume_package['contents_cost'] = $this->get_contents_cost( $contents );
		$volume_package['cart_subtotal'] = $this->get_contents_cost( $contents, 'line_subtotal' );

		unset( $volume_package['rates'] );

		$shipping_package = $this->get_package( $volume_package );

		if ( $index > 0 && $this->extra_weight_type === 'order' ) {
			$shipping_package->setExtraWeight( 0 );
		}

		return $shipping_package;
	}

	/**
	 * Sum a money field of the contents.
	 *
	 * @since 1.11.0
	 *
	 * @param array  $contents Cart contents.
	 * @param string $field    Field being summed.
	 *
	 * @return float
	 */
	private function get_contents_cost( $contents, $field = 'line_total' ) {
		$total = 0;

		foreach ( $contents as $values ) {
			if ( isset( $values[ $field ] ) && is_numeric( $values[ $field ] ) ) {
				$total += (float) $values[ $field ];
			}
		}

		return $total;
	}

	/**
	 * Ask the carrier for the price of one volume.
	 *
	 * @since 1.11.0
	 *
	 * @param ShippingCost $shipping_cost       Volume being quoted.
	 * @param array        $package             Cart package.
	 * @param string       $destination_country Destination country.
	 *
	 * @return array{cost: float, delivery_time: int|false, insurance_cost: float|false}|false
	 */
	private function quote_volume( $shipping_cost, $package, $destination_country ) {
		$transient_key = $this->transient_key( 'shipping_cost', [
			'data' => $shipping_cost->getData(),
			'country' => $destination_country,
			'product' => $shipping_cost->getProductCode()
		], $package );

		$cached_data = get_transient( $transient_key );

		if ( $cached_data === false ) {
			$cost_response = Container::shippingService()->calculateShippingCost( $shipping_cost );
			if ( $cost_response !== false && isset( $cost_response['shipping_cost'] ) && $cost_response['shipping_cost'] !== false ) {
				set_transient( $transient_key, $cost_response, MINUTE_IN_SECONDS );
			}
		} else {
			Log::debug( "Dados de frete recuperados do cache.", [
				'transient_key' => $transient_key,
				'cached_data' => $cached_data,
			] );
			$cost_response = $cached_data;
		}

		$cost = is_array( $cost_response ) && isset( $cost_response['shipping_cost'] ) ? $cost_response['shipping_cost'] : false;

		if ( $cost === false ) {
			return false;
		}

		return [
			'cost' => $cost,
			'delivery_time' => is_array( $cost_response ) && isset( $cost_response['delivery_time'] ) ? $cost_response['delivery_time'] : false,
			'insurance_cost' => is_array( $cost_response ) && isset( $cost_response['insurance_cost'] ) ? $cost_response['insurance_cost'] : false,
		];
	}

	/**
	 * Describe the volumes for the order that comes out of this quote.
	 *
	 * @since 1.11.0
	 *
	 * @param array $quotes Volumes and what the carrier charged for each.
	 *
	 * @return string
	 */
	private function volumes_meta( $quotes ) {
		$volumes = [];

		foreach ( $quotes as $quote ) {
			/** @var ShippingCost|null $shipping_cost */
			$shipping_cost = $quote['shipping_cost'];
			$volume_package = $shipping_cost ? $shipping_cost->getPackage() : $quote['package'];
			$data = $shipping_cost
				? [ 'weight' => $shipping_cost->getWeight(), 'length' => $shipping_cost->getLength(), 'width' => $shipping_cost->getWidth(), 'height' => $shipping_cost->getHeight() ]
				: $quote['data'];
			$items = [];

			foreach ( $volume_package->get_contents() as $values ) {
				$product = isset( $values['data'] ) ? $values['data'] : null;

				if ( ! $product ) {
					continue;
				}

				$items[] = [
					'id' => $product->get_id(),
					'qty' => isset( $values['quantity'] ) ? (int) $values['quantity'] : 0,
				];
			}

			$volume = [
				'weight' => $data['weight'],
				'length' => $data['length'],
				'width' => $data['width'],
				'height' => $data['height'],
				'cost' => (float) $quote['cost'],
				'items' => $items,
			];

			$packaging = $this->get_volume_packaging( $volume_package );

			if ( $packaging ) {
				$volume['packaging_id'] = $packaging['id'];
				$volume['packaging_name'] = $packaging['name'];
			}

			$volumes[] = $volume;
		}

		return wp_json_encode( $volumes );
	}

	/**
	 * Whether every volume declares a value the service can insure.
	 *
	 * The insurance limit of the carrier applies to each box, so a shipment
	 * split into volumes is only hidden when one of its boxes declares more than
	 * the service insures. A volume that declares nothing, because the cart did
	 * not reach the minimum insurance value, is not over any limit.
	 *
	 * @since 1.11.0
	 *
	 * @param ShippingCost[] $volumes Volumes of the shipment.
	 * @param array          $package Shipping package.
	 *
	 * @return bool
	 */
	private function are_volumes_within_insurance_limit( $volumes, $package ) {
		if ( ! $this->insurance || $this->when_exceed_maximum_insurance !== 'hide_method' ) {
			return true;
		}

		foreach ( $volumes as $volume ) {
			if ( $volume->getInsuranceDeclarationValue() === null || $volume->areDeclarationWithinLimits( 'max' ) ) {
				continue;
			}

			Log::info( "Frete não calculado, valor da declaração de seguro excede o máximo permitido.", [
				'product_code' => $volume->getProductCode(),
				'origin_postcode' => $volume->getOriginPostcode(),
				'destination_postcode' => $volume->getDestinationPostcode(),
				'insurance_value' => $volume->getInsuranceDeclarationValue(),
			] );
			$this->calculation_failed(
				'insurance_limit',
				__( 'O valor declarado do seguro está fora do limite deste serviço e o método está configurado para ser ocultado nesse caso. Nas configurações do método, mude "Ao exceder o seguro máximo" para "Ignorar seguro" ou revise o valor mínimo do seguro.', 'infixs-correios-automatico' ),
				$package
			);

			return false;
		}

		return true;
	}

	/**
	 * Calculate Shipping
	 * 
	 * @param array $package
	 * 
	 * @return void
	 */
	public function calculate_shipping( $package = [] ) {
		$product_code = $this->resolve_product_code( $package );

		Log::debug( "Iniciando o cálculo de frete para o serviço $product_code", [
			'package' => $package,
		] );

		if ( ! $this->can_be_calculated( $package, $product_code ) ) {
			return;
		}

		if ( ! $this->has_shipping_class( $package ) ) {
			return;
		}

		$origin_postcode = Sanitizer::numeric_text( apply_filters( "infixs_correios_automatico_calculate_shipping_origin_postcode", $this->origin_postcode, $package, $this ) );
		$destination_postcode = Sanitizer::numeric_text( $package['destination']['postcode'] );
		$destination_country = sanitize_text_field( $package['destination']['country'] );

		if ( empty( $product_code ) || empty( $origin_postcode ) ) {
			Log::info( "Não foi possível calcular o frete, código do produto ou CEP de origem não definidos.", [
				'product_code' => $product_code,
				'origin_postcode' => $origin_postcode,
			] );

			if ( empty( $origin_postcode ) ) {
				$this->calculation_failed(
					'origin_postcode',
					__( 'CEP de origem não configurado. Preencha o CEP de origem nas configurações do método de entrega ou o CEP da loja em WooCommerce > Configurações > Geral.', 'infixs-correios-automatico' ),
					$package
				);
			} else {
				$this->calculation_failed(
					'product_code',
					__( 'Nenhum serviço dos Correios selecionado nas configurações do método de entrega. Escolha o serviço (PAC, SEDEX, etc.) na configuração da instância.', 'infixs-correios-automatico' ),
					$package
				);
			}

			return;
		}

		$shipping_cost = new ShippingCost(
			$product_code,
			$origin_postcode,
			$destination_postcode,
			$destination_country
		);

		$shipping_package = $this->get_package( $package );

		$shipping_cost->setPackage( $shipping_package );
		$shipping_cost->setOwnHands( $this->own_hands );
		$shipping_cost->setReceiptNotice( $this->receipt_notice );
		$shipping_cost->setReceiptNoticeElectronic( $this->receipt_notice_electronic );

		if ( $product_code === DeliveryServiceCode::IMPRESSO_MODICO ) {
			$shipping_cost->setModico( true );
		}

		$calculated = $this->get_cost_measure( $shipping_cost );

		/**
		 * Allows overriding the package data used in the quote, keeping the dimensions and the
		 * weight informed manually (in an order, for example) instead of the ones calculated
		 * from the products.
		 *
		 * @since 1.8.0
		 *
		 * @param ShippingCost           $shipping_cost Shipping cost data.
		 * @param array                  $package       Shipping package.
		 * @param CorreiosShippingMethod $method        Shipping method.
		 */
		$shipping_cost = apply_filters( 'infixs_correios_automatico_shipping_cost_data', $shipping_cost, $package, $this );

		if ( $this->insurance && ( isset( $package['contents_cost'] ) || isset( $package['cart_subtotal'] ) ) ) {
			$content_cost = NumberHelper::parseNumber( $package['cart_subtotal'] ?? $package['contents_cost'] );

			if ( (int) $this->min_insurance_value / 100 <= $content_cost ) {
				$shipping_cost->setInsuranceDeclarationValue( $content_cost );
			}
		}

		$passed = apply_filters( 'infixs_correios_automatico_calculate_shipping_cost_passed', true, $shipping_cost, $package, $this );

		if ( ! $passed ) {
			$this->calculation_failed(
				'filter_blocked',
				__( 'O cálculo foi bloqueado por uma personalização externa (filtro "infixs_correios_automatico_calculate_shipping_cost_passed"). Verifique snippets ou plugins de terceiros que alterem o frete.', 'infixs-correios-automatico' ),
				$package
			);
			return;
		}


		$measure = $this->get_cost_measure( $shipping_cost );
		$plan = $this->plan_shipment( $package, $product_code, $measure, $measure !== $calculated );
		$within_limits = $plan['within_limits'];
		$volumes = $plan['volumes'] ? $this->volume_costs( $plan['volumes'], $shipping_cost, $product_code ) : [ $shipping_cost ];

		if ( ! $within_limits && $this->split_exceed ) {
			Log::info( "Não foi possível dividir a encomenda em volumes dentro dos limites do serviço.", [
				'product_code' => $product_code,
				'weight' => $shipping_cost->getWeight(),
				'length' => $shipping_cost->getLength(),
				'width' => $shipping_cost->getWidth(),
				'height' => $shipping_cost->getHeight(),
			] );
		}

		if ( count( $volumes ) === 1 ) {
			$shipping_cost = $volumes[0];
		}

		if ( ! $this->are_volumes_within_insurance_limit( $volumes, $package ) ) {
			return;
		}

		/**
		 * Filters whether the volumes of the shipment may be quoted.
		 *
		 * Runs after the shipment was split, so a rule can look at each box the
		 * carrier receives instead of at the cart as a whole. A shipment that was
		 * not split has a single volume.
		 *
		 * @since 1.11.0
		 *
		 * @param bool                   $passed  Whether the volumes may be quoted.
		 * @param ShippingCost[]         $volumes Volumes of the shipment.
		 * @param array                  $package Shipping package.
		 * @param CorreiosShippingMethod $method  Shipping method.
		 */
		$volumes_passed = apply_filters( 'infixs_correios_automatico_calculate_shipping_volumes_passed', true, $volumes, $package, $this );

		if ( ! $volumes_passed ) {
			$this->calculation_failed(
				'volume_rule_blocked',
				__( 'O cálculo foi bloqueado por uma regra aplicada aos volumes da encomenda (filtro "infixs_correios_automatico_calculate_shipping_volumes_passed"). Revise as regras avançadas do método, como "Peso do volume", ou snippets e plugins de terceiros que alterem o frete.', 'infixs-correios-automatico' ),
				$package
			);
			return;
		}

		if ( ! $within_limits && $this->hide_exceed ) {
			Log::info( "Frete não calculado, dimensões excedidas." );
			$this->calculation_failed(
				'dimensions_exceeded',
				sprintf(
					/* translators: 1: length, 2: width, 3: height, 4: weight */
					__( 'O pacote (%1$s x %2$s x %3$s cm, %4$s kg) ultrapassa os limites deste serviço dos Correios. Revise o peso e as dimensões dos produtos, ou desmarque "Ocultar quando exceder" nas configurações do método.', 'infixs-correios-automatico' ),
					$shipping_cost->getLength(),
					$shipping_cost->getWidth(),
					$shipping_cost->getHeight(),
					$shipping_cost->getWeight()
				),
				$package
			);
			return;
		}

		if ( $this->when_less_minimum == 'hide' && $shipping_cost->getWeight() < $this->minimum_weight ) {
			Log::info( "Frete não calculado, peso menor que o mínimo.", [
				'weight' => $shipping_cost->getWeight(),
				'min_weight' => $this->minimum_weight,
			]
			);

			$this->calculation_failed(
				'weight_below_minimum',
				sprintf(
					/* translators: 1: package weight, 2: minimum weight configured */
					__( 'O peso do pacote (%1$s kg) é menor que o peso mínimo configurado (%2$s kg) e o método está configurado para ser ocultado nesse caso. Cadastre o peso dos produtos ou mude "Quando menor que o mínimo" para "Forçar" nas configurações do método.', 'infixs-correios-automatico' ),
					$shipping_cost->getWeight(),
					$this->minimum_weight
				),
				$package
			);

			return;
		}

		$cost = 0;
		$times = [];
		$insurance_cost = false;
		$quotes = [];

		foreach ( $volumes as $volume ) {
			$quote = $this->quote_volume( $volume, $package, $destination_country );

			if ( $quote === false ) {
				Log::info( "Não foi possível calcular o custo do frete." );
				$this->calculation_failed(
					'api_error',
					__( 'Os Correios não retornaram o preço deste serviço. Verifique se os CEPs de origem e destino são válidos e se o contrato dos Correios está conectado em Correios Automático > Configurações.', 'infixs-correios-automatico' ),
					$package
				);
				return;
			}

			$cost += (float) $quote['cost'];
			$quotes[] = [ 'shipping_cost' => $volume, 'cost' => $quote['cost'] ];

			if ( $quote['delivery_time'] !== false ) {
				$times[] = (int) $quote['delivery_time'];
			}

			if ( $quote['insurance_cost'] !== false ) {
				$insurance_cost = (float) $insurance_cost + (float) $quote['insurance_cost'];
			}
		}

		$time = $times ? max( $times ) : false;
		$original_cost = $cost;

		if ( $this->insurance && $insurance_cost !== false && ! $this->insurance_customer_cost ) {
			$cost -= $insurance_cost;
		}

		if ( $this->additional_tax_percentage > 0 )
			$cost += $cost * ( $this->additional_tax_percentage / 100 );

		if ( $this->additional_tax > 0 )
			$cost += $this->additional_tax / 100;

		$meta_data = [
			"_original_cost" => $original_cost,
			"_show_original_shipping_discount_price" => $this->show_original_shipping_discount_price,
			"_weight" => $shipping_cost->getWeight(),
			"_length" => $shipping_cost->getLength(),
			"_width" => $shipping_cost->getWidth(),
			"_height" => $shipping_cost->getHeight(),
			"shipping_product_code" => $product_code
		];

		if ( count( $quotes ) > 1 ) {
			$meta_data[ self::VOLUMES_META_KEY ] = $this->volumes_meta( $quotes );
		}

		$packaging = count( $quotes ) === 1 ? $this->get_volume_packaging( $shipping_cost->getPackage() ) : null;

		if ( $packaging ) {
			$meta_data[ self::PACKAGING_ID_META_KEY ] = $packaging['id'];
			$meta_data[ self::PACKAGING_NAME_META_KEY ] = $packaging['name'];
		}

		$matched_rule_method_title = null;

		if ( is_array( $this->discount_rules ) ) {
			$discount_result = $this->apply_discount_rules( $cost, $original_cost, $shipping_package->get_total() );

			$cost = $discount_result['cost'];
			$matched_rule_method_title = $discount_result['method_title'];
			$matched = $discount_result['matched'];
			$enabled_rules = $discount_result['enabled_count'];

			if ( $this->hidden_when_no_match && ! $matched && $enabled_rules > 0 ) {
				Log::info( "Frete não calculado, nenhuma regra de desconto foi atendida (Você optou por ocultar quando não há regras de desconto)." );
				$this->calculation_failed(
					'discount_rules_no_match',
					__( 'Nenhuma regra de desconto do método corresponde a este pedido e o método está configurado para ser ocultado nesse caso. Revise as faixas de valor das regras ou desmarque "Ocultar quando nenhuma regra corresponder".', 'infixs-correios-automatico' ),
					$package
				);
				return;
			}

			if ( $this->hidden_others_when_match && $matched ) {
				$meta_data['_hide_others_rates'] = true;
			}
		}

		if ( $insurance_cost ) {
			$meta_data['_insurance_cost'] = $insurance_cost;
		}

		$delivery_time_text = '';

		if ( $this->estimated_delivery ) {

			// No modo fixo o prazo da transportadora é ignorado, então não vale
			// gastar uma requisição para descobri-lo.
			if ( 'fixed' !== $this->delivery_time_mode && $time === false ) {
				$shipping_time = new ShippingTime(
					$product_code,
					$origin_postcode,
					$destination_postcode,
					$package
				);

				$transient_key = $this->transient_key( 'shipping_time', [
					$product_code,
					$origin_postcode,
					$destination_postcode
				], $package );

				$cached_data = get_transient( $transient_key );

				if ( $cached_data === false ) {
					$time = $shipping_time->calculate();
					if ( $time !== false ) {
						set_transient( $transient_key, $time, MINUTE_IN_SECONDS );
					}
				} else {
					$time = $cached_data;
				}
			}

			$delivery_time = $this->resolve_delivery_time( $time, $package );

			if ( false !== $delivery_time ) {
				$meta_data['delivery_time'] = $delivery_time;
				$delivery_time_text = $delivery_time . ( $delivery_time > 1 ? ' dias úteis' : ' dia útil' );
			}
		}

		$cost = Currency::toCurrentCurrency( $cost, 'BRL' ) ?: $cost;

		$meta_data['_final_cost'] = $cost;

		$rate_title = $this->discount_rate_title( $cost, $matched_rule_method_title );

		$rate = [
			'id' => "{$this->id}_{$this->instance_id}",
			'label' => $rate_title . ( empty( $delivery_time_text ) ? '' : " ({$delivery_time_text})" ),
			'cost' => $cost,
			'package' => $package,
			'meta_data' => $meta_data,
		];

		$this->add_rate( apply_filters( 'infixs_correios_automatico_rate', $rate, $package ) );
	}

	/**
	 * Get the enabled discount rules.
	 *
	 * @return array
	 */
	public function get_enabled_advanced_rules() {
		$enabled_rules = array_filter( $this->advanced_rules, function ( $rule ) {
			return $rule['enabled'] === true;
		} );

		return $enabled_rules;
	}


	public function get_additional_days( $package ) {
		return intval( $this->additional_days ) + $this->get_package_additional_days( $package );
	}

	/**
	 * Get the extra days the package itself asks for.
	 *
	 * Production time the merchant configured per product or per shipping
	 * class, independent of how long the carrier takes. The largest one wins.
	 *
	 * @since 1.9.1
	 *
	 * @param array $package Cart package.
	 *
	 * @return int
	 */
	public function get_package_additional_days( $package ) {
		$product_additional_days = 0;
		foreach ( $package['contents'] as $items ) {
			/** @var \WC_Product $product */
			$product = $items['data'];
			$additional_days = intval( $product->get_meta( '_infixs_correios_automatico_additional_days', true ) );
			if ( $additional_days > $product_additional_days ) {
				$product_additional_days = $additional_days;
			}

			$shipping_class_id = $product->get_shipping_class_id();
			if ( $shipping_class_id ) {
				$class_additional_days = intval( get_term_meta( $shipping_class_id, 'infixs_additional_days', true ) );
				if ( $class_additional_days && $class_additional_days > $product_additional_days ) {
					$product_additional_days = $class_additional_days;
				}
			}
		}

		return intval( $product_additional_days );
	}

	/**
	 * Resolve the delivery time to announce on the checkout.
	 *
	 * @since 1.9.1
	 *
	 * @param int|false $api_time Delivery time the carrier estimated, false when unknown.
	 * @param array     $package  Cart package.
	 *
	 * @return int|false False when there is no delivery time to show.
	 */
	protected function resolve_delivery_time( $api_time, $package ) {
		if ( 'fixed' === $this->delivery_time_mode ) {
			$days = max( 0, (int) $this->fixed_delivery_days ) + $this->get_package_additional_days( $package );

			return $days > 0 ? $days : false;
		}

		if ( false === $api_time ) {
			return false;
		}

		return (int) $api_time + $this->get_additional_days( $package );
	}

	public function get_description( $with_code = true ) {
		return DeliveryServiceCode::getDescription( $this->get_product_code(), $with_code );
	}

	public function get_auto_prepost() {
		return $this->auto_prepost;
	}

	public function get_auto_prepost_delay_type() {
		return $this->auto_prepost_delay_type;
	}

	public function get_auto_prepost_delay_value() {
		return $this->auto_prepost_delay_value;
	}

	public function get_auto_prepost_delay_unit() {
		return $this->auto_prepost_delay_unit;
	}

	public function get_auto_prepost_delay_seconds() {
		$value = max( 1, $this->auto_prepost_delay_value );
		switch ( $this->auto_prepost_delay_unit ) {
			case 'hours':
				return $value * HOUR_IN_SECONDS;
			case 'days':
				return $value * DAY_IN_SECONDS;
			default:
				return $value * MINUTE_IN_SECONDS;
		}
	}

	/**
	 * Get the Correios product (service) code this method instance is set to.
	 *
	 * @return string
	 */
	public function get_product_code() {
		return $this->advanced_mode ? $this->advanced_service : $this->resolve_basic_service( $this->basic_service );
	}

	/**
	 * Resolve the Correios product code to quote a cart package with.
	 *
	 * Unlike get_product_code(), which answers what the method instance is
	 * configured to sell, this one is package aware so a marketplace extension
	 * can swap between the contract and the public table variants of a service
	 * according to who ships the package.
	 *
	 * @since 1.8.1
	 *
	 * @param array $package Cart package.
	 *
	 * @return string
	 */
	public function resolve_product_code( $package ) {
		/**
		 * Filters the Correios product code used to quote a package.
		 *
		 * @since 1.8.1
		 *
		 * @param string $product_code
		 * @param array $package
		 * @param \Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod $this
		 */
		return apply_filters( 'infixs_correios_automatico_product_code', $this->get_product_code(), $package, $this );
	}

	/**
	 * Build a transient key for a shipping lookup.
	 *
	 * @since 1.8.1
	 *
	 * @param string $type    Lookup type, such as `shipping_cost` or `shipping_time`.
	 * @param array  $data    Values that identify the lookup.
	 * @param array  $package Cart package.
	 *
	 * @return string
	 */
	protected function transient_key( $type, $data, $package ) {
		$transient_key = $type . '_' . Helper::generateHashFromArray( $data );

		/**
		 * Filters the transient key used to cache a shipping lookup.
		 *
		 * Marketplace extensions must add the vendor to the key, otherwise two
		 * vendors sharing the same origin postcode and package would also share
		 * the cached result even when only one of them has a contract.
		 *
		 * @since 1.8.1
		 *
		 * @param string $transient_key
		 * @param string $type
		 * @param array $package
		 * @param \Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod $this
		 */
		return apply_filters( 'infixs_correios_automatico_transient_key', $transient_key, $type, $package, $this );
	}

	public function use_range() {
		return $this->modic_use_range;
	}

	/**
	 * Get the product common id.
	 * 
	 * @since 1.1.5
	 * 
	 * @return string|null
	 */
	public function get_product_common_id() {
		$product_code = $this->get_product_code();
		return DeliveryServiceCode::getCommonId( $product_code );
	}

	public function get_object_type_code() {
		if ( $this->object_type === 'letter' ) {
			return 1;
		}
		return 2;
	}

	public function is_receipt_notice() {
		return $this->receipt_notice;
	}

	public function is_receipt_notice_electronic() {
		return $this->receipt_notice_electronic;
	}

	public function is_own_hands() {
		return $this->own_hands;
	}
}