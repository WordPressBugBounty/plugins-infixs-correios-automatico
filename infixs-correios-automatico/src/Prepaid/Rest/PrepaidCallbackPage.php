<?php

namespace Infixs\CorreiosAutomatico\Prepaid\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Page the merchant lands on when coming back from the authorization in the Infixs Ship.
 *
 * Tells what happened and takes the merchant back to the Contrato page — by
 * itself after a connection, by a button after a problem, so the message can
 * be read. The dashboard gets the outcome in `prepaid_result`.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.14.0
 */
class PrepaidCallbackPage {

	/**
	 * Seconds before a connected merchant is taken back to the dashboard.
	 *
	 * @since 1.14.0
	 *
	 * @var int
	 */
	const REDIRECT_DELAY = 3;

	/**
	 * Headers of the page.
	 *
	 * The address of the page carries the authorization code: no referrer
	 * leaves it, and the page is neither cached, indexed nor framed.
	 *
	 * @since 1.14.0
	 *
	 * @return array<string, string>
	 */
	public function headers() {
		return [
			'Content-Type' => 'text/html; charset=UTF-8',
			'Cache-Control' => 'no-store, max-age=0',
			'X-Robots-Tag' => 'noindex, nofollow',
			'Referrer-Policy' => 'no-referrer',
			'X-Frame-Options' => 'DENY',
			'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
		];
	}

	/**
	 * HTML of the page.
	 *
	 * @since 1.14.0
	 *
	 * @param string $result Outcome of the authorization, one of PrepaidConnector::RESULTS.
	 * @param string $url    Address of the Contrato page of the dashboard.
	 *
	 * @return string
	 */
	public function render( $result, $url ) {
		$message = $this->message( $result );
		$success = 'connected' === $result;
		$refresh = $success ? sprintf( '<meta http-equiv="refresh" content="%d;url=%s">', self::REDIRECT_DELAY, esc_url( $url ) ) : '';

		return '<!DOCTYPE html>'
			. '<html lang="pt-BR"><head><meta charset="UTF-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="robots" content="noindex, nofollow">'
			. $refresh
			. '<title>' . esc_html( $message['title'] ) . ' — ' . esc_html__( 'Correios Automático', 'infixs-correios-automatico' ) . '</title>'
			. '<style>'
			. 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#0f172a}'
			. '.card{max-width:440px;margin:24px;padding:32px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(15,23,42,.08);text-align:center}'
			. '.icon{width:56px;height:56px;margin:0 auto 16px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:#fff}'
			. '.ok{background:#16a34a}.fail{background:#dc2626}'
			. 'h1{font-size:20px;margin:0 0 8px}p{font-size:15px;line-height:1.5;color:#475569;margin:0 0 24px}'
			. 'a.button{display:inline-block;padding:10px 20px;border-radius:8px;background:#1d4ed8;color:#fff;text-decoration:none;font-weight:600}'
			. 'small{display:block;margin-top:16px;color:#94a3b8}'
			. '</style></head><body><main class="card">'
			. '<div class="icon ' . ( $success ? 'ok' : 'fail' ) . '" aria-hidden="true">' . ( $success ? '&#10003;' : '!' ) . '</div>'
			. '<h1>' . esc_html( $message['title'] ) . '</h1>'
			. '<p>' . esc_html( $message['description'] ) . '</p>'
			. '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Voltar para a página Contrato', 'infixs-correios-automatico' ) . '</a>'
			. ( $success ? '<small>' . esc_html__( 'Você será levado de volta em instantes.', 'infixs-correios-automatico' ) . '</small>' : '' )
			. '</main></body></html>';
	}

	/**
	 * Title and text of an outcome.
	 *
	 * @since 1.14.0
	 *
	 * @param string $result Outcome of the authorization.
	 *
	 * @return array{title: string, description: string}
	 */
	private function message( $result ) {
		$failed = __( 'Não foi possível conectar a conta Infixs Ship', 'infixs-correios-automatico' );

		$messages = [
			'connected' => [
				__( 'Conta Infixs Ship conectada', 'infixs-correios-automatico' ),
				__( 'As pré-postagens da loja passam a sair pelo Contrato pré-pago, pagas com o saldo da conta.', 'infixs-correios-automatico' ),
			],
			'denied' => [
				$failed,
				__( 'A autorização foi cancelada na Infixs Ship. Clique em Conectar conta Infixs Ship e autorize a loja.', 'infixs-correios-automatico' ),
			],
			'invalid_state' => [
				$failed,
				__( 'O link de autorização expirou ou já foi usado. Clique em Conectar conta Infixs Ship de novo.', 'infixs-correios-automatico' ),
			],
			'error' => [
				$failed,
				__( 'A Infixs Ship não confirmou a autorização. Tente conectar de novo em instantes.', 'infixs-correios-automatico' ),
			],
		];

		$message = isset( $messages[ $result ] ) ? $messages[ $result ] : $messages['error'];

		return [
			'title' => $message[0],
			'description' => $message[1],
		];
	}
}
