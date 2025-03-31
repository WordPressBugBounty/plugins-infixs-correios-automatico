<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Models\Unit;
use Infixs\CorreiosAutomatico\Repositories\UnitRepository;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\CeintCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;


defined( 'ABSPATH' ) || exit;

class UnitService {

	/**
	 * @var UnitRepository
	 */
	private $unitRepository;

	public function __construct( UnitRepository $unitRepository ) {
		$this->unitRepository = $unitRepository;
	}

	public function getUnits( $params ) {
		$params = [ 
			'order_by' => 'id',
			'order' => 'desc',
			'relations' => [ 'codes' ]
		];

		return $this->unitRepository->paginate( $params, [ $this, 'prepareData' ] );
	}

	public function prepareData( Unit $data ) {
		$ceint = CeintCode::getCeintById( $data->ceint_id );

		return [ 
			'id' => $data->id,
			'status' => $data->status,
			'dispatch_number' => $data->dispatch_number,
			'service_name' => DeliveryServiceCode::getShortDescription( $data->service_code ),
			'total_codes' => $data->codes->count(),
			'ceint_name' => $ceint ? $ceint['name'] : 'Não definido',
		];
	}
}