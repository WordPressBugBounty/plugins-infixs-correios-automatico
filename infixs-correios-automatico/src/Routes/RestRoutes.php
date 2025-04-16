<?php

namespace Infixs\CorreiosAutomatico\Routes;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Controllers\Rest\LabelController;
use Infixs\CorreiosAutomatico\Controllers\Rest\OrderController;
use Infixs\CorreiosAutomatico\Controllers\Rest\PrepostController;
use Infixs\CorreiosAutomatico\Controllers\Rest\SettingsAuthController;
use Infixs\CorreiosAutomatico\Controllers\Rest\SettingsGeneralController;
use Infixs\CorreiosAutomatico\Controllers\Rest\SettingsLabelController;
use Infixs\CorreiosAutomatico\Controllers\Rest\SettingsReturnController;
use Infixs\CorreiosAutomatico\Controllers\Rest\SettingsSenderController;
use Infixs\CorreiosAutomatico\Controllers\Rest\ShippingController;
use Infixs\CorreiosAutomatico\Controllers\Rest\TrackingController;
use Infixs\CorreiosAutomatico\Controllers\Rest\UnitController;

defined( 'ABSPATH' ) || exit;
class RestRoutes {

	/**
	 * Rest namespace
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'infixs-correios-automatico/v1';

	/**
	 * RestRoutes constructor.
	 * 
	 * @since 1.0.0
	 */
	public function register_routes() {
		$settings_controller = new SettingsAuthController();

		register_rest_route( $this->namespace, '/settings/auth', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $settings_controller, 'save' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/auth', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $settings_controller, 'retrieve' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		$general_controller = new SettingsGeneralController();

		register_rest_route( $this->namespace, '/settings/general', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $general_controller, 'save' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/general', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $general_controller, 'retrieve' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/general/terms', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $general_controller, 'terms' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		$sender_controller = new SettingsSenderController();

		register_rest_route( $this->namespace, '/settings/sender', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $sender_controller, 'save' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/sender', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $sender_controller, 'retrieve' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		$label_settings_controller = new SettingsLabelController( Container::labelService() );

		register_rest_route( $this->namespace, '/settings/label/profiles', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $label_settings_controller, 'saveProfile' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/label/profiles', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $label_settings_controller, 'getProfile' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/label/ranges', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $label_settings_controller, 'createRange' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/label/ranges', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $label_settings_controller, 'getRanges' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/label/ranges/(?P<id>\d+)', [ 
			'methods' => \WP_REST_Server::DELETABLE,
			'callback' => [ $label_settings_controller, 'deleteRanges' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/settings/label/ranges-available', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $label_settings_controller, 'getRangesAvailable' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		$return_settings_controller = new SettingsReturnController();

		register_rest_route( $this->namespace, '/settings/return', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $return_settings_controller, 'save' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );


		register_rest_route( $this->namespace, '/settings/return', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $return_settings_controller, 'retrieve' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );


		$tracking_controller = new TrackingController( Container::trackingService() );

		register_rest_route( $this->namespace, '/trackings', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $tracking_controller, 'create' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );


		register_rest_route( $this->namespace, '/trackings/(?P<id>\d+)', [ 
			'methods' => \WP_REST_Server::DELETABLE,
			'callback' => [ $tracking_controller, 'delete' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'args' => [ 
				'id' => [ 
					'required' => true,
					'validate_callback' => function ($param) {
						return is_numeric( $param );
					}
				],
			],
		] );

		register_rest_route( $this->namespace, '/trackings/(?P<id>\d+)', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $tracking_controller, 'retrieve' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'args' => [ 
				'id' => [ 
					'required' => true,
					'validate_callback' => function ($param) {
						return is_numeric( $param );
					}
				],
			],
		] );

		register_rest_route( $this->namespace, '/trackings/(?P<id>\d+)/suspend', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $tracking_controller, 'suspend_shipping' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'args' => [ 
				'id' => [ 
					'required' => true,
					'validate_callback' => function ($param) {
						return is_numeric( $param );
					}
				],
			],
		] );

		register_rest_route( $this->namespace, '/trackings', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $tracking_controller, 'list' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/trackings/notification/(?P<order_id>\d+)', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $tracking_controller, 'tracking_notification' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'args' => [ 
				'order_id' => [ 
					'required' => true,
					'validate_callback' => function ($param) {
						return is_numeric( $param );
					}
				],
			],
		] );

		$shipping_controller = new ShippingController( Container::shippingService() );

		register_rest_route( $this->namespace, '/shipping/methods', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $shipping_controller, 'list_shipping_methods' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/shipping/methods', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $shipping_controller, 'add_shipping_methods' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/shipping/methods/batch', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $shipping_controller, 'add_batch_shipping_methods' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/shipping/methods/import', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $shipping_controller, 'import_shipping_methods' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/shipping/methods/available', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $shipping_controller, 'available_shipping_methods' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		$label_controller = new LabelController( Container::labelService() );

		register_rest_route( $this->namespace, '/labels', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $label_controller, 'list' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		$prepost_controller = new PrepostController( Container::prepostService() );

		register_rest_route( $this->namespace, '/preposts', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $prepost_controller, 'list' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/preposts', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $prepost_controller, 'createFromOrder' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		/**
		 * Delete prepost from order
		 * 
		 * @since 1.0.0
		 * @deprecated
		 */
		register_rest_route( $this->namespace, '/preposts/(?P<id>\d+)', [ 
			'methods' => \WP_REST_Server::DELETABLE,
			'callback' => [ $prepost_controller, 'delete' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/preposts/(?P<id>\d+)/cancel', [ 
			'methods' => \WP_REST_Server::EDITABLE,
			'callback' => [ $prepost_controller, 'cancel' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		$unit_controller = new UnitController( Container::unitService(), Container::trackingService() );

		register_rest_route( $this->namespace, '/units', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $unit_controller, 'list' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/units/(?P<id>\d+)', [ 
			'methods' => \WP_REST_Server::EDITABLE,
			'callback' => [ $unit_controller, 'update' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/units/(?P<id>\d+)/trackings', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $unit_controller, 'trackings' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/units/(?P<id>\d+)/register', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $unit_controller, 'register' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/units/(?P<id>\d+)/trackings/(?P<tracking_id>\d+)', [ 
			'methods' => \WP_REST_Server::DELETABLE,
			'callback' => [ $unit_controller, 'remove_tracking' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );


		$order_controller = new OrderController( Container::orderService() );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)', [ 
			'methods' => \WP_REST_Server::EDITABLE,
			'callback' => [ $order_controller, 'update' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders/batch', [ 
			'methods' => \WP_REST_Server::EDITABLE,
			'callback' => [ $order_controller, 'batch_update' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders/units', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $order_controller, 'unit' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders/preferences', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $order_controller, 'save_preferences' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/shipping/calculate', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $order_controller, 'calculate_shipping' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/shipping/update', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $order_controller, 'update_shipping' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/attach-range', [ 
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $order_controller, 'attach_range' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders/(?P<id>\d+)/preposts/(?P<prepost_id>\d+)', [ 
			'methods' => \WP_REST_Server::DELETABLE,
			'callback' => [ $order_controller, 'delete_prepost' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			}
		] );

		register_rest_route( $this->namespace, '/orders', [ 
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $order_controller, 'list' ],
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'args' => [ 
				'page' => [ 
					'default' => 1,
					'sanitize_callback' => 'absint',
				],
				'per_page' => [ 
					'default' => 10,
					'sanitize_callback' => 'absint',
				],
				'search' => [ 
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		do_action( 'infixs_correios_automatico_register_routes', $this->get_namespace() );
	}

	/**
	 * Get the namespace.
	 *
	 * @since 1.0.0
	 * 
	 * @return string
	 */
	public function get_namespace() {
		return $this->namespace;
	}

	/**
	 * Get the rest url.
	 *
	 * @since 1.0.0
	 * 
	 * @return string
	 */
	public function get_rest_url() {
		return get_rest_url( null, $this->namespace );
	}
}