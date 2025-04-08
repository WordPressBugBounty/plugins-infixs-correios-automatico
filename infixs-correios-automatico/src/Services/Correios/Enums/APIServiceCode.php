<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

class APIServiceCode {
	public const AGENCIA = 76;
	public const ARE_ELETRONICO = 392;
	public const ENDERECO_CEP_V3 = 41;
	public const ERP_PAIS = 586;
	public const FATURAS = 587;
	public const MENSAGEM_DIGITAL_EXT = 83;
	public const MENSAGENS_TELEMATICAS_REST = 426;
	public const MEU_CONTRATO = 566;
	public const PACKET = 80;
	public const PMA_PRE_POSTAGEM = 36;
	public const PRAZO = 35;
	public const PRECO = 34;
	public const PRO_JUS_CADASTRO = 37;
	public const SRO_INTERATIVIDADE = 93;
	public const SRO_RASTRO = 87;
	public const TOKEN = 5;
	public const WEBHOOK = 78;

	private static $descriptions = [ 
		self::AGENCIA => 'Serviço de Agência',
		self::ARE_ELETRONICO => 'ARE Eletrônico',
		self::ENDERECO_CEP_V3 => 'Endereço CEP V3',
		self::ERP_PAIS => 'ERP País',
		self::FATURAS => 'Faturas',
		self::MENSAGEM_DIGITAL_EXT => 'Mensagem Digital Ext',
		self::MENSAGENS_TELEMATICAS_REST => 'Mensagens Telemáticas REST',
		self::MEU_CONTRATO => 'Meu Contrato',
		self::PACKET => 'Packet',
		self::PMA_PRE_POSTAGEM => 'PMA Pré-Postagem',
		self::PRAZO => 'Prazo',
		self::PRECO => 'Preço',
		self::PRO_JUS_CADASTRO => 'Pro Jus Cadastro',
		self::SRO_INTERATIVIDADE => 'SRO Interatividade',
		self::SRO_RASTRO => 'SRO Rastro',
		self::TOKEN => 'Token',
		self::WEBHOOK => 'Webhook',
	];

	/**
	 * Get the description of the additional service.
	 * 
	 * @param string $item Additional service code.
	 * 
	 * @return string
	 */
	public static function getValue( $item ) {
		return self::$descriptions[ $item ] ?? 0;
	}

	/**
	 * Get the description of the additional service.
	 * 
	 * @param string $item Additional service code.
	 * 
	 * @return string
	 */
	public static function getDescription( $item ) {
		return self::$descriptions[ $item ] ?? "Desconhecido ($item)";
	}
}