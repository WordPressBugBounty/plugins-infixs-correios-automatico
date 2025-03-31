<?php
namespace Infixs\CorreiosAutomatico\Traits;

trait HttpTrait {
	/**
	 * Post request
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $endpoint
	 * @param array $data
	 * @param array $headers
	 * 
	 * @return array|\WP_Error
	 */
	protected function post( $url, $data, $headers = [] ) {
		$response = wp_safe_remote_post( $url, [ 
			'body' => wp_json_encode( $data ),
			'timeout' => 20,
			'headers' => array_merge( [ 
				'Content-Type' => 'application/json',
			], $headers ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( ! in_array( wp_remote_retrieve_response_code( $response ), [ 200, 201 ], true ) ) {
			$message = 'Erro ao enviar solicitação post.';
			if ( isset( $data['msgs'] ) && is_array( $data['msgs'] ) ) {
				$message = join( '. ', $data['msgs'] );
			} elseif ( isset( $data['message'] ) ) {
				$message = $data['message'];
			}
			return new \WP_Error( "http_error", $message, [ 'status' => $code ] );
		}

		return $data;
	}

	/**
	 * Join URL
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $url
	 * @param string $path
	 * 
	 * @return string
	 */
	public function join_url( $url, $path ) {
		return join( '/', [ rtrim( $url, '/' ), ltrim( $path, '/' ) ] );
	}

	/**
	 * Get request
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 * 
	 * @return array|\WP_Error
	 */
	protected function get( $url, $params = [], $headers = [] ) {

		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_safe_remote_get( $url,
			[ 
				'timeout' => 20,
				'headers' => $headers,
			]
		);

		return $response;
	}

	/**
	 * Send HTTP DELETE.
	 *
	 * @since 1.1.3
	 * 
	 * @param string $url
	 * @param array $params
	 * 
	 * @return array|\WP_Error
	 */
	public function delete( $url, $params = [], $headers = [] ) {
		$default_args = [ 
			'method' => 'DELETE',
			'headers' => array_merge(
				[ 
					'Content-Type' => 'application/json',
				],
				$headers
			),
			'body' => null,
		];

		$params = wp_parse_args( $params, $default_args );
		$response = wp_remote_request( $url, $params );

		return $response;
	}
}