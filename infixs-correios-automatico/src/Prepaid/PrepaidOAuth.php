<?php

namespace Infixs\CorreiosAutomatico\Prepaid;

defined( 'ABSPATH' ) || exit;

/**
 * Authorization of the store in the Infixs Ship account, with PKCE.
 *
 * The state and the PKCE verifier stay on the server, with the redirect link
 * and the address of the site the authorization was asked with; only the
 * challenge of the verifier goes to the browser. The state is used once:
 * claimed by deleting it, so of two requests carrying it — the same link
 * opened twice — only one exchanges the code. A new authorization replaces
 * the one in progress.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidOAuth {

	/**
	 * Transient holding the authorization in progress.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const STATE_TRANSIENT = 'infixs_correios_automatico_prepaid_oauth_state';

	/**
	 * Seconds the merchant has to authorize the store.
	 *
	 * @since 1.14.0
	 *
	 * @var int
	 */
	const STATE_TTL = 600;

	/**
	 * Length of the PKCE verifier, within the 43 to 128 characters the RFC 7636 asks.
	 *
	 * @since 1.14.0
	 *
	 * @var int
	 */
	const VERIFIER_LENGTH = 64;

	/**
	 * Address of the authorization page, where the store asks to be connected.
	 *
	 * Starts a new authorization: the state of the previous one stops working.
	 *
	 * @since 1.14.0
	 *
	 * @param string $site_url     Address of the site.
	 * @param string $site_name    Name of the site, shown on the authorization page.
	 * @param string $redirect_uri Link the site comes back to.
	 *
	 * @return string
	 */
	public function authorizeUrl( $site_url, $site_name, $redirect_uri ) {
		$state = wp_generate_password( 32, false );
		$verifier = wp_generate_password( self::VERIFIER_LENGTH, false );

		set_transient( self::STATE_TRANSIENT, [
			'hash' => hash( 'sha256', $state ),
			'verifier' => $verifier,
			'redirect_uri' => (string) $redirect_uri,
			'site_url' => (string) $site_url,
			'user_id' => get_current_user_id(),
			'created_at' => time(),
		], self::STATE_TTL );

		return add_query_arg( array_map( 'rawurlencode', [
			'site_url' => (string) $site_url,
			'site_name' => (string) $site_name,
			'redirect_uri' => (string) $redirect_uri,
			'state' => $state,
			'code_challenge' => self::challenge( $verifier ),
			'code_challenge_method' => 'S256',
		] ), PrepaidApi::appUrl( 'connect' ) );
	}

	/**
	 * Check the state the site sent back, and use it up.
	 *
	 * Only the right state uses it up, so a request with a wrong state, or
	 * none, leaves the authorization in progress working.
	 *
	 * @since 1.14.0
	 *
	 * @param mixed $state State sent back.
	 *
	 * @return array{verifier: string, redirect_uri: string, site_url: string}|null Null when the state is not the one in progress.
	 */
	public function consumeState( $state ) {
		if ( ! is_string( $state ) || '' === $state ) {
			return null;
		}

		$stored = get_transient( self::STATE_TRANSIENT );

		if ( ! is_array( $stored ) || empty( $stored['hash'] ) || ! hash_equals( (string) $stored['hash'], hash( 'sha256', $state ) ) ) {
			return null;
		}

		if ( ! delete_transient( self::STATE_TRANSIENT ) ) {
			return null;
		}

		return [
			'verifier' => isset( $stored['verifier'] ) ? (string) $stored['verifier'] : '',
			'redirect_uri' => isset( $stored['redirect_uri'] ) ? (string) $stored['redirect_uri'] : '',
			'site_url' => isset( $stored['site_url'] ) ? (string) $stored['site_url'] : '',
		];
	}

	/**
	 * PKCE challenge of a verifier: `base64url( sha256( verifier ) )`, without padding.
	 *
	 * @since 1.14.0
	 *
	 * @param string $verifier Verifier.
	 *
	 * @return string
	 */
	public static function challenge( $verifier ) {
		return rtrim( strtr( base64_encode( hash( 'sha256', (string) $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
}
