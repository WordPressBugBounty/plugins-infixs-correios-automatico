<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Packing;

defined( 'ABSPATH' ) || exit;

/**
 * Decides what goes together in a volume and how that volume ships.
 *
 * The PackageSplitter walks the units of a shipment and asks the filler whether
 * each one still fits the volume it is trying. Once the shipment is packed, the
 * filler tells the measures of each volume and the packaging it travels in.
 *
 * @since 1.11.0
 */
interface VolumeFiller {

	/**
	 * Whether these units travel together in a single volume.
	 *
	 * The splitter asks with the units already in the volume followed by the one
	 * it is trying to add, and keeps the volume whenever the answer is yes.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units of the candidate volume, as PackingUnits::explode() builds them.
	 * @param int     $index Position of the volume in the shipment.
	 *
	 * @return bool
	 */
	public function fits( $units, $index );

	/**
	 * Describe how a packed volume ships.
	 *
	 * @since 1.11.0
	 *
	 * @param array[] $units Units of the volume.
	 * @param int     $index Position of the volume in the shipment.
	 *
	 * @return array{length: float, width: float, height: float, packaging: array{id: string, name: string, empty_weight: float}|null}|null
	 *         Measures in cm and empty weight in kg, or null when the volume ships
	 *         with the measures estimated from its products.
	 */
	public function describe( $units, $index );
}
