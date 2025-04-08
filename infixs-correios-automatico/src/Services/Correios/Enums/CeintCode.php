<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Enums;

defined( 'ABSPATH' ) || exit;

class CeintCode {
	public const CEINT_SAO_PAULO = 1;
	public const CEINT_VALINHOS = 2;

	public const CEINT_RIO_DE_JANEIRO = 3;

	public const CEINT_CURITIBA = 4;


	public static function getDescription( int $code ): string {
		switch ( $code ) {
			case self::CEINT_SAO_PAULO:
				return 'São Paulo';
			case self::CEINT_VALINHOS:
				return 'Valinhos';
			case self::CEINT_RIO_DE_JANEIRO:
				return 'Rio de Janeiro';
			case self::CEINT_CURITIBA:
				return 'Curitiba';
			default:
				return 'Unknown';
		}
	}

	/**
	 * Get the ceints info.
	 * 
	 * @return array
	 */
	public static function getCeints() {
		return [ 
			[ 
				'id' => self::CEINT_SAO_PAULO,
				'name' => 'Centro Internacional de São Paulo - SE/SPM',
				'address' => 'Rua Mergenthaler, 568',
				'complement' => 'bloco III, 5º andar',
				'neighborhood' => 'Vila Leopoldina',
				'city' => 'São Paulo',
				'state' => 'SP',
				'zipcode' => '05311030',
				'document' => '34028316710585',
			],
			[ 
				'id' => self::CEINT_VALINHOS,
				'name' => 'Centro Internacional em Valinhos - SE/SPIM',
				'address' => 'Rua Clark, 3041',
				'neighborhood' => 'Macuco',
				'city' => 'Valinhos',
				'state' => 'SP',
				'zipcode' => '13279400',
				'document' => '34028316939574',
			],
			[ 
				'id' => self::CEINT_RIO_DE_JANEIRO,
				'name' => 'Centro Internacional do Rio de Janeiro - SE/RJ',
				'address' => 'Ponta do Galeão, s/nº',
				'complement' => '2º andar - TECA Correios Galeão',
				'neighborhood' => 'Galeão',
				'city' => 'Rio de Janeiro',
				'state' => 'RJ',
				'zipcode' => '21941974',
				'document' => '34028316718993',
			],
			[ 
				'id' => self::CEINT_CURITIBA,
				'name' => 'Centro Internacional de Curitiba - SE/PR',
				'address' => 'Rua Salgado Filho, 476',
				'neighborhood' => 'Jardim Amélia',
				'city' => 'Pinhais',
				'state' => 'PR',
				'zipcode' => '83330972',
				'document' => '34028316914822',
			],
		];
	}

	public static function getCeintsOptions() {
		$ceints = self::getCeints();
		$options = [];
		foreach ( $ceints as $ceint ) {
			$options[ $ceint['id'] ] = $ceint['name'];
		}
		return $options;
	}

	/**
	 * Get the ceint by id.
	 * 
	 * @param int $id
	 * 
	 * @return array|null
	 */
	public static function getCeintById( $id ) {
		$ceints = self::getCeints();
		foreach ( $ceints as $ceint ) {
			if ( $ceint['id'] == $id ) {
				return $ceint;
			}
		}
		return null;
	}
}