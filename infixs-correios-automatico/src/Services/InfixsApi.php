<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Traits\HttpTrait;

defined( 'ABSPATH' ) || exit;

class InfixsApi {
	use HttpTrait;
	protected $api_url = 'https://api.infixs.io';
	protected $api_version = 'v1';

	/**
	 * Send plugin deactivation data to Infixs API.
	 *
	 * @since 1.0.0
	 * 
	 * @return \WP_Error|array The response or WP_Error on failure.
	 */
	public function postDeactivationPlugin( $data ) {
		return wp_safe_remote_post( $this->getApiUrl( 'plugin/deactivate' ), [ 
			"body" => wp_json_encode( $data ),
			'headers' => [ 
				'Content-Type' => 'application/json',
			]
		] );
	}

	public function getApiUrl( $endpoint = '' ) {
		return $this->joinUrl( "{$this->api_url}/{$this->api_version}", $endpoint );
	}

	protected function joinUrl( $url, $path ) {
		return join( '/', [ rtrim( $url, '/' ), ltrim( $path, '/' ) ] );
	}

	public function fetchAddress( $postcode ) {
		$response = wp_safe_remote_get( $this->getApiUrl( "postcode/{$postcode}" ), [ 
			'headers' => [ 
				'Content-Type' => 'application/json',
			]
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ], true ) ) {
			return new \WP_Error( "http_error", 'Erro ao buscar endereço', [ 'status' => wp_remote_retrieve_response_code( $response ) ] );
		}

		return $data;
	}

	/**
	 * Get tracking history
	 * 
	 * @param string $tracking_code
	 * 
	 * @return array|\WP_Error
	 */
	public function getTrackingHistory( $tracking_code ) {
		$query_params = [ 
			'format' => 'cws',
		];

		$url = add_query_arg( $query_params, $this->getApiUrl( "shipping/tracking/correios/{$tracking_code}" ) );

		$response = wp_safe_remote_get( $url, [ 
			'headers' => [ 
				'Content-Type' => 'application/json',
			]
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ], true ) ) {
			return new \WP_Error( "http_error", 'Erro ao buscar histórico de rastreamento', [ 'status' => wp_remote_retrieve_response_code( $response ) ] );
		}

		return $data;
	}

	public function calculateShipping( $data ) {
		return $this->post(
			'https://api.infixs.io/v1/shipping/calculate/correios',
			$data,
			[],
		);
	}

	/**
	 * Get currency rate
	 * 
	 * @param string $from
	 * @param string $to
	 * 
	 * @return array|\WP_Error
	 */
	public function getCurrencyRate( $from, $to ) {
		$response = wp_safe_remote_get( $this->getApiUrl( "currency/rate/{$from}-{$to}" ), [ 
			'headers' => [ 
				'Content-Type' => 'application/json',
			]
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ], true ) ) {
			return new \WP_Error( "http_error", 'Erro ao buscar taxa de câmbio', [ 'status' => wp_remote_retrieve_response_code( $response ) ] );
		}

		return $data;
	}

	public function acceptTerms( $license_key, $token ) {
		$response = wp_safe_remote_post( $this->getApiUrl( "plugin/accept-terms" ), [ 
			"body" => wp_json_encode( [ 
				"license_key" => $license_key,
				"token" => $token
			] ),
			'headers' => [ 
				'Content-Type' => 'application/json',
			]
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ], true ) ) {
			return false;
		}

		return true;
	}
}