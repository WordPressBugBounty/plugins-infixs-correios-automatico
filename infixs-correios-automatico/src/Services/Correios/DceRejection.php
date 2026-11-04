<?php

namespace Infixs\CorreiosAutomatico\Services\Correios;

defined( 'ABSPATH' ) || exit;

/**
 * Reads why the SEFAZ refused the DC-e of a prepost.
 *
 * The Correios cancel the prepost and write the answer of the SEFAZ in
 * `motivoCancelamento`, as in "Erro API DCe: DCE-009: Rejeitado pela SEFAZ:
 * Situação: 812 Motivo: Usuario emitente bloqueado para emissao de DCe.
 * [Contribuinte de ICMS]".
 *
 * @since 1.14.0
 */
class DceRejection {

	/**
	 * Whether the DC-e was refused because the sender is an ICMS taxpayer.
	 *
	 * Since 2 November 2026 those senders ship with an NF-e instead.
	 *
	 * @since 1.14.0
	 *
	 * @param string|null $reason Reason the Correios gave for cancelling the prepost.
	 *
	 * @return bool
	 */
	public static function isIcmsTaxpayer( $reason ) {
		$reason = (string) $reason;

		return false !== stripos( $reason, 'Contribuinte de ICMS' ) || 1 === preg_match( '/Situa\S*o:\s*812\b/u', $reason );
	}
}
