<?php

namespace Infixs\CorreiosAutomatico\Prepaid\Rest;

use Infixs\CorreiosAutomatico\Prepaid\PrepaidConnector;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints of the connection with the Infixs Ship.
 *
 * The dashboard ones need `manage_woocommerce`. The redirect link is public,
 * as the browser comes back to it from the Infixs Ship, and is guarded by its
 * single use state. No answer carries the token of the connection.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidController {

	/**
	 * Values the Infixs Ship sends back on the redirect link.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const PARAM_PATTERN = '/^[\x21-\x7E]{1,1024}$/';

	/**
	 * Connection with the Infixs Ship.
	 *
	 * @var PrepaidConnector
	 */
	private $connector;

	/**
	 * Page shown when coming back from the Infixs Ship.
	 *
	 * @var PrepaidCallbackPage
	 */
	private $page;

	/**
	 * @param PrepaidConnector    $connector Connection with the Infixs Ship.
	 * @param PrepaidCallbackPage $page      Page shown when coming back from the Infixs Ship.
	 */
	public function __construct( PrepaidConnector $connector, PrepaidCallbackPage $page ) {
		$this->connector = $connector;
		$this->page = $page;
	}

	/**
	 * Register the routes.
	 *
	 * @since 1.14.0
	 *
	 * @param string $namespace REST namespace of the plugin.
	 *
	 * @return void
	 */
	public function register_routes( $namespace ) {
		$admin = function () {
			return current_user_can( 'manage_woocommerce' );
		};

		register_rest_route( $namespace, '/prepaid/connect', [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $this, 'connect' ],
			'permission_callback' => $admin,
		] );

		register_rest_route( $namespace, '/prepaid/status', [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $this, 'status' ],
			'permission_callback' => $admin,
		] );

		register_rest_route( $namespace, '/prepaid/disconnect', [
			'methods' => \WP_REST_Server::CREATABLE,
			'callback' => [ $this, 'disconnect' ],
			'permission_callback' => $admin,
		] );

		register_rest_route( $namespace, PrepaidConnector::CALLBACK_ROUTE, [
			'methods' => \WP_REST_Server::READABLE,
			'callback' => [ $this, 'callback' ],
			'permission_callback' => '__return_true',
		] );

		add_filter( 'rest_pre_serve_request', [ $this, 'serveCallbackPage' ], 10, 3 );
	}

	/**
	 * POST /prepaid/connect — address of the authorization page.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_REST_Request $request Request, with `rest_route_fallback` true when the
	 *                                  dashboard reaches the site through `?rest_route=`.
	 *
	 * @return \WP_REST_Response
	 */
	public function connect( $request ) {
		return rest_ensure_response( [
			'authorize_url' => $this->connector->start( rest_sanitize_boolean( $request->get_param( 'rest_route_fallback' ) ) ),
		] );
	}

	/**
	 * GET /prepaid/status — the connection, the account and its balance.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_REST_Request $request Request, with `refresh` to read the account again.
	 *
	 * @return \WP_REST_Response
	 */
	public function status( $request ) {
		return rest_ensure_response( $this->connector->status( rest_sanitize_boolean( $request->get_param( 'refresh' ) ) ) );
	}

	/**
	 * POST /prepaid/disconnect — end the connection.
	 *
	 * @since 1.14.0
	 *
	 * @return \WP_REST_Response
	 */
	public function disconnect() {
		$this->connector->disconnect();

		return rest_ensure_response( $this->connector->status() );
	}

	/**
	 * GET /prepaid/callback — finish the authorization and show how it went.
	 *
	 * Answers an HTML page, never JSON, with a link back to the Contrato page
	 * carrying the outcome in `prepaid_result`.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function callback( $request ) {
		$result = $this->connector->complete(
			self::param( $request, 'code' ),
			self::param( $request, 'state' ),
			self::oauthError( $request )
		);

		return new \WP_REST_Response(
			$this->page->render( $result, $this->connector->dashboardUrl( $result ) ),
			200,
			$this->page->headers()
		);
	}

	/**
	 * Serve the page of the redirect link as HTML instead of a JSON string.
	 *
	 * Hooked to `rest_pre_serve_request`, which runs after the headers of the
	 * answer — its HTML content type included — were sent.
	 *
	 * @since 1.14.0
	 *
	 * @param bool              $served  Whether the answer was already served.
	 * @param \WP_HTTP_Response $result  Answer.
	 * @param \WP_REST_Request  $request Request.
	 *
	 * @return bool
	 */
	public function serveCallbackPage( $served, $result, $request ) {
		if ( $served || ! $result instanceof \WP_HTTP_Response || ! $request instanceof \WP_REST_Request ) {
			return $served;
		}

		$route = (string) $request->get_route();

		if ( PrepaidConnector::CALLBACK_ROUTE !== substr( $route, -strlen( PrepaidConnector::CALLBACK_ROUTE ) ) || ! is_string( $result->get_data() ) ) {
			return $served;
		}

		$headers = $result->get_headers();

		if ( ! isset( $headers['Content-Type'] ) || 0 !== strpos( $headers['Content-Type'], 'text/html' ) ) {
			return $served;
		}

		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped by PrepaidCallbackPage::render().

		return true;
	}

	/**
	 * Value of a parameter of the redirect link, empty when it is not one the Infixs Ship would send.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param string           $name    Parameter.
	 *
	 * @return string
	 */
	private static function param( $request, $name ) {
		$value = $request->get_param( $name );

		return is_string( $value ) && preg_match( self::PARAM_PATTERN, $value ) ? $value : '';
	}

	/**
	 * Error sent back on the redirect link, empty for none.
	 *
	 * `error` is a query var of the WordPress, which drops it from `$_GET`
	 * while parsing a request to the site itself, so a cancelled
	 * authorization would come back without it. It is read again from the
	 * query string the browser sent.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return string
	 */
	private static function oauthError( $request ) {
		$error = self::param( $request, 'error' );

		if ( '' !== $error || ! isset( $_SERVER['QUERY_STRING'] ) || ! is_string( $_SERVER['QUERY_STRING'] ) ) {
			return $error;
		}

		parse_str( wp_unslash( $_SERVER['QUERY_STRING'] ), $query ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched against PARAM_PATTERN below.

		return isset( $query['error'] ) && is_string( $query['error'] ) && preg_match( self::PARAM_PATTERN, $query['error'] ) ? $query['error'] : '';
	}
}
