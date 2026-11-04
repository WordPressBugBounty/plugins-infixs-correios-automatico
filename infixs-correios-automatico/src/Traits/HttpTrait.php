<?php
namespace Infixs\CorreiosAutomatico\Traits;

use Infixs\CorreiosAutomatico\Core\Support\Log;

trait HttpTrait {
	/**
	 * Post request
	 * 
	 * @since 1.0.0
	 * @since 1.14.0 A refusal carries the code of the error the API answered (`error.code`) in `error_code`.
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

		$accepted_codes = [ 200, 201, 202, 204 ];
		if ( ! in_array( wp_remote_retrieve_response_code( $response ), $accepted_codes, true ) ) {
			$message = $this->extract_error_message( $data );

			Log::error( 'A API respondeu com erro.', [
				'url' => $url,
				'status' => $code,
				'body' => is_string( $body ) ? substr( $body, 0, 2000 ) : '',
			] );

			$error_data = [ 'status' => $code ];

			if ( is_array( $data ) && isset( $data['error']['code'] ) && is_string( $data['error']['code'] ) ) {
				$error_data['error_code'] = $data['error']['code'];
			}

			return new \WP_Error( "http_error", $message, $error_data );
		}

		return $data;
	}

	/**
	 * Extract a human readable message out of an API error body.
	 *
	 * The Correios APIs answer in more than one shape depending on the service
	 * and on which gateway rejected the call: `msgs` (strings ou objetos),
	 * `message`, `mensagem`, `causa` e `erros[].mensagem` são os conhecidos.
	 * Anything else falls back to the generic message, with the raw body
	 * already recorded in the log by post().
	 *
	 * @since 1.10.1
	 *
	 * @param mixed $data Decoded response body, null when it was not JSON.
	 *
	 * @return string
	 */
	protected function extract_error_message( $data ) {
		if ( ! is_array( $data ) ) {
			return 'Erro ao enviar solicitação post.';
		}

		if ( isset( $data['msgs'] ) && is_array( $data['msgs'] ) ) {
			$messages = [];

			foreach ( $data['msgs'] as $msg ) {
				if ( is_string( $msg ) && $this->is_usable_message( $msg ) ) {
					$messages[] = trim( $msg );
				} elseif ( is_array( $msg ) && isset( $msg['mensagem'] ) && $this->is_usable_message( $msg['mensagem'] ) ) {
					$messages[] = trim( $msg['mensagem'] );
				}
			}

			if ( $messages ) {
				return join( '. ', $messages );
			}
		}

		if ( isset( $data['erros'] ) && is_array( $data['erros'] ) ) {
			$messages = [];

			foreach ( $data['erros'] as $error ) {
				if ( is_array( $error ) && isset( $error['mensagem'] ) && $this->is_usable_message( $error['mensagem'] ) ) {
					$messages[] = trim( $error['mensagem'] );
				}
			}

			if ( $messages ) {
				return join( '. ', $messages );
			}
		}

		foreach ( [ 'message', 'mensagem', 'causa' ] as $key ) {
			if ( isset( $data[ $key ] ) && is_string( $data[ $key ] ) && $this->is_usable_message( $data[ $key ] ) ) {
				return trim( $data[ $key ] );
			}
		}

		return 'Erro ao enviar solicitação post.';
	}

	/**
	 * Check whether an API message says anything to the person reading it.
	 *
	 * The Correios prepost endpoint answers a rejected sender with
	 * `{"msgs":["null"],"causa":"ApiNegocioRuntimeException: "}` — placeholders
	 * that read worse than the generic message, since they look like an answer
	 * while carrying none. The raw body is in the log either way.
	 *
	 * @since 1.10.1
	 *
	 * @param mixed $message Candidate message.
	 *
	 * @return bool
	 */
	protected function is_usable_message( $message ) {
		if ( ! is_string( $message ) ) {
			return false;
		}

		$message = trim( $message );

		if ( $message === '' || strtolower( $message ) === 'null' ) {
			return false;
		}

		return ! preg_match( '/^[A-Za-z_\\\\]*Exception:?$/', $message );
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

		$response = wp_safe_remote_get( $url, [
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

		$accepted_codes = [ 200, 201, 202, 204 ];
		if ( ! in_array( wp_remote_retrieve_response_code( $response ), $accepted_codes, true ) ) {
			$message = 'Erro ao enviar solicitação get.';
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
	 * Send HTTP DELETE.
	 *
	 * @since 1.1.3
	 * 
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 * 
	 * @return array|\WP_Error
	 */
	public function delete( $url, $params = [], $headers = [] ) {
		$default_args = [
			'method' => 'DELETE',
			'timeout' => 20,
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

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		$code = wp_remote_retrieve_response_code( $response );

		$accepted_codes = [ 200, 201, 202, 204 ];
		if ( ! in_array( wp_remote_retrieve_response_code( $response ), $accepted_codes, true ) ) {
			$message = 'Erro ao enviar solicitação delete.';
			if ( isset( $data['msgs'] ) && is_array( $data['msgs'] ) ) {
				$message = join( '. ', $data['msgs'] );
			} elseif ( isset( $data['message'] ) ) {
				$message = $data['message'];
			}
			return new \WP_Error( "http_error", $message, [ 'status' => $code ] );
		}

		return $data;
	}
}