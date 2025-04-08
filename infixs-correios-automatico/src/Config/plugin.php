<?php

defined( 'ABSPATH' ) || exit;

return apply_filters( 'infixs_correios_automatico_settings',
	[ 
		'general' => [ 
			'autofill_address' => 'yes',
			'calculate_shipping_product_page' => 'yes',
			'calculate_shipping_product_page_position' => 'description_before',
			'show_order_tracking_form' => 'yes',
			'show_order_label_form' => 'yes',
			'show_order_prepost_form' => 'yes',
			'mask_postcode' => 'yes',
			'send_email_prepost' => 'no',
			'tracking_compatiblity' => 'no',
			'simple_cart_shipping_calculator' => 'yes',
			'cart_shipping_calculator_always_visible' => 'no',
			'auto_calculate_cart_shipping_postcode' => 'no',
			'auto_calculate_product_shipping_postcode' => 'no',
			'terms_and_conditions_of_use_accepted' => 'no',
			'enable_order_status' => 'no', //Deprecated in 1.4.3
			'active_preparing_to_ship' => 'no',
			'active_in_transit' => 'no',
			'active_waiting_pickup' => 'no',
			'active_returning' => 'no',
			'active_delivered' => 'no',
			'status_preparing_to_ship' => 'Preparando para envio',
			'status_in_transit' => 'Em transporte',
			'status_waiting_pickup' => 'Aguardando retirada',
			'status_returning' => 'Em devolução',
			'status_delivered' => 'Entregue',
			'change_preparing_to_ship' => 'manual',
			'change_in_transit' => 'manual',
			'change_waiting_pickup' => 'manual',
			'change_returning' => 'manual',
			'change_delivered' => 'manual',
			'email_preparing_to_ship' => 'yes',
			'email_in_transit' => 'yes',
			'email_waiting_pickup' => 'yes',
			'email_returning' => 'yes',
			'email_delivered' => 'yes',
			'auto_change_order_to_completed' => 'no',
			'show_full_address_calculate_product' => 'no',
		],
		'auth' => [ 
			'active' => 'no',
			'environment' => 'production',
			'user_name' => '',
			'access_code' => '',
			'postcard' => '',
			'token' => '',
			'contract_number' => '',
			'contract_type' => '',
			'contract_document' => '',
		],
		'sender' => [ 
			'name' => '',
			'legal_name' => '',
			'email' => '',
			'phone' => '',
			'celphone' => '',
			'document' => '',
			'address_postalcode' => '',
			'address_street' => '',
			'address_complement' => '',
			'address_number' => '',
			'address_neighborhood' => '',
			'address_city' => '',
			'address_state' => '',
			'address_country' => 'BR',
		],
		'label' => [ 
			'profiles' => [ 
				'default' => [ 
					"id" => "default",
				],
				'unit' => [ 
					"id" => "unit"
				]
			]
		],
		'return' => [ 
			'active' => 'yes',
			'days' => '7',
			'auto_return' => 'no',
			'same_service' => 'yes',
		],
		'debug' => [ 
			'active' => 'yes',
			'debug_log' => 'no',
			'info_log' => 'no',
			'notice_log' => 'no',
			'warning_log' => 'no',
			'error_log' => 'yes',
			'critical_log' => 'yes',
			'alert_log' => 'yes',
			'emergency_log' => 'yes',
		],
		'preferences' => [ 
			'order' => [ 
				'per_page' => 10,
				'status' => [ 
					'wc-pending',
					'wc-processing',
					'wc-on-hold',
					'wc-completed',
					'wc-cancelled',
					'wc-refunded',
					'wc-failed',
					'wc-preparing-to-ship',
					'wc-in-transit',
				],
			]
		]
	] );