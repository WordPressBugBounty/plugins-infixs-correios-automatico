<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Shipping\LinkedShippingMethod;
use Infixs\CorreiosAutomatico\Utils\ShippingLineName;

defined( 'ABSPATH' ) || exit;

/**
 * Gives each volume of a shipment its own shipping line.
 *
 * A shipping line leaves the quote with the volumes it was priced in, and the
 * order becomes one line per volume wherever it is created: the checkout of the
 * store, or another system writing the order through the REST API.
 *
 * @package Infixs\CorreiosAutomatico
 * @since   1.11.0
 */
class VolumeLineExpander {

	/**
	 * Metas holding what a shipping line cost, besides its total and taxes.
	 *
	 * @var string[]
	 */
	const COST_META_KEYS = [ '_final_cost', '_original_cost', '_insurance_cost' ];

	/**
	 * Give each volume of a shipment its own shipping line.
	 *
	 * A cart over the limits of the service is quoted volume by volume, and the
	 * customer chooses the sum as a single delivery option. The order, though,
	 * is what the store ships: one line per volume, each carrying the weight and
	 * the measurements of its own box, so it gets its own label, its own prepost
	 * and its own tracking code.
	 *
	 * The money is shared in the proportion the carrier charged for each volume
	 * and the remainder lands on the first line, so the lines always add up to
	 * exactly what the customer was charged.
	 *
	 * Nothing is saved here: the caller saves the order.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order being created.
	 *
	 * @return bool Whether any line was expanded.
	 */
	public function expand( $order ) {
		$expanded = false;

		/** @var \WC_Order_Item_Shipping $line_item */
		foreach ( $order->get_items( 'shipping' ) as $line_item ) {
			if ( ! $line_item->meta_exists( CorreiosShippingMethod::VOLUMES_META_KEY ) ) {
				continue;
			}

			$volumes = $this->readVolumes( $line_item );

			$line_item->delete_meta_data( CorreiosShippingMethod::VOLUMES_META_KEY );

			if ( ! $volumes || count( $volumes ) < 2 ) {
				continue;
			}

			$this->splitLine( $order, $line_item, $volumes );
			$expanded = true;
		}

		return $expanded;
	}

	/**
	 * Give each volume of one shipping line its own line.
	 *
	 * The line is already on the order, and the volumes are the ones its Correios
	 * method describes. The volumes after the first become lines of that method:
	 * the method of the line itself or, for a line of another method — the freight
	 * an external checkout writes, for instance — the Correios method linked to it,
	 * whose title then names the volumes.
	 *
	 * By default the money is shared as the checkout shares it. Keeping the value
	 * leaves the line exactly as it was written — the same name, total and taxes —
	 * as the first volume, and the other volumes follow at no cost, for a freight
	 * another system charged and still reads back by that line. Either way the
	 * order is charged the same freight.
	 *
	 * A line that already is a volume is left alone, so writing the same order
	 * again never splits it twice. Nothing is saved here: the caller saves the order.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order               $order      Order the line belongs to.
	 * @param \WC_Order_Item_Shipping $line_item  Line of the whole shipment.
	 * @param array|string            $volumes    Volumes of the shipment, as CorreiosShippingMethod::linked_volumes_meta()
	 *                                            describes them.
	 * @param bool                    $keep_value Whether the line keeps its name, total and taxes, the other volumes
	 *                                            following at no cost.
	 *
	 * @return bool Whether the line was expanded.
	 */
	public function expandLine( $order, $line_item, $volumes, $keep_value = false ) {
		$volumes = $this->decodeVolumes( $volumes );

		if ( ! $volumes || count( $volumes ) < 2 || $line_item->meta_exists( CorreiosShippingMethod::VOLUME_INDEX_META_KEY ) ) {
			return false;
		}

		$linked_method = null;

		if ( $line_item->get_method_id() !== 'infixs-correios-automatico' ) {
			$linked_method = LinkedShippingMethod::resolve_from_item( $line_item );

			if ( ! $linked_method ) {
				return false;
			}
		}

		$line_item->delete_meta_data( CorreiosShippingMethod::VOLUMES_META_KEY );
		$this->splitLine( $order, $line_item, $volumes, $keep_value, $linked_method );

		return true;
	}

	/**
	 * Bring a freight of another method split by expandLine() back to its single line.
	 *
	 * The volumes that followed the line at no cost leave the order, and the line
	 * loses the box it was given, so it is left as it was written and can be split
	 * again for what the order ships now. A line of this plugin is never merged,
	 * since it carries the measures of its whole shipment, which a merge cannot
	 * bring back; neither is a shipment with a volume charged on its own, whose
	 * value would leave the order.
	 *
	 * Nothing is saved here: the caller saves the order.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order               $order     Order the line belongs to.
	 * @param \WC_Order_Item_Shipping $line_item First volume of the freight.
	 *
	 * @return bool Whether the freight was merged.
	 */
	public function mergeLine( $order, $line_item ) {
		if ( 'infixs-correios-automatico' === $line_item->get_method_id() || 1 !== (int) $line_item->get_meta( CorreiosShippingMethod::VOLUME_INDEX_META_KEY ) ) {
			return false;
		}

		$linked_method = LinkedShippingMethod::resolve_from_item( $line_item );

		if ( ! $linked_method ) {
			return false;
		}

		$volume_ids = [];

		/** @var \WC_Order_Item_Shipping $item */
		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			if (
				'infixs-correios-automatico' !== $item->get_method_id()
				|| (int) $item->get_instance_id() !== (int) $linked_method->get_instance_id()
				|| (int) $item->get_meta( CorreiosShippingMethod::VOLUME_INDEX_META_KEY ) < 2
			) {
				continue;
			}

			if ( 0.0 !== (float) $item->get_total() ) {
				return false;
			}

			$volume_ids[] = $item_id;
		}

		foreach ( $volume_ids as $item_id ) {
			$order->remove_item( $item_id );
		}

		$this->clearVolume( $line_item );

		return true;
	}

	/**
	 * Whether the lines are volumes of a split shipment that still hold the products of the order.
	 *
	 * Only a line that says which products travel in its box counts: lines the store
	 * split by hand say nothing about their contents. The boxes also have to hold
	 * exactly the products of the order, since an order whose products were edited
	 * afterwards has boxes that no longer match what is being shipped.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order                 $order Order the lines belong to.
	 * @param \WC_Order_Item_Shipping[] $lines Shipping lines of the shipment.
	 *
	 * @return bool
	 */
	public function describesOrder( $order, $lines ) {
		if ( ! $lines ) {
			return false;
		}

		$packed = [];

		foreach ( $lines as $line ) {
			$volume_items = $line->get_meta( CorreiosShippingMethod::VOLUME_ITEMS_META_KEY );

			if ( is_string( $volume_items ) ) {
				$volume_items = json_decode( $volume_items, true );
			}

			if ( ! is_array( $volume_items ) || ! $volume_items ) {
				return false;
			}

			foreach ( $volume_items as $volume_item ) {
				if ( ! isset( $volume_item['id'] ) ) {
					continue;
				}

				$product_id = absint( $volume_item['id'] );
				$quantity = isset( $volume_item['qty'] ) ? absint( $volume_item['qty'] ) : 0;
				$packed[ $product_id ] = isset( $packed[ $product_id ] ) ? $packed[ $product_id ] + $quantity : $quantity;
			}
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}

			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$quantity = (int) $item->get_quantity();

			if ( ! isset( $packed[ $product_id ] ) || $packed[ $product_id ] < $quantity ) {
				return false;
			}

			$packed[ $product_id ] -= $quantity;
		}

		foreach ( $packed as $left ) {
			if ( $left > 0 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Read the volumes a shipping line was quoted in.
	 *
	 * A line of another method that kept the description — a native method
	 * linked to a Correios service that was not converted, for instance — has
	 * nothing to expand, and the description is dropped by the caller.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $line_item Shipping line.
	 *
	 * @return array|false
	 */
	private function readVolumes( $line_item ) {
		if ( $line_item->get_method_id() !== 'infixs-correios-automatico' ) {
			return false;
		}

		return $this->decodeVolumes( $line_item->get_meta( CorreiosShippingMethod::VOLUMES_META_KEY ) );
	}

	/**
	 * Read a description of volumes, encoded or not.
	 *
	 * @since 1.11.0
	 *
	 * @param array|string $volumes Volumes, as a list or as the JSON a shipping rate carries.
	 *
	 * @return array|false
	 */
	private function decodeVolumes( $volumes ) {
		if ( is_string( $volumes ) ) {
			$volumes = json_decode( $volumes, true );
		}

		return is_array( $volumes ) && $volumes ? array_values( $volumes ) : false;
	}

	/**
	 * Replace one shipping line by one line per volume.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order                   $order         Order being created.
	 * @param \WC_Order_Item_Shipping     $line_item     Line quoted in volumes.
	 * @param array                       $volumes       Volumes of the shipment.
	 * @param bool                        $keep_value    Whether the line keeps its name, total and taxes.
	 * @param CorreiosShippingMethod|null $linked_method Correios method linked to a line of another method,
	 *                                                   null for a line of this plugin.
	 *
	 * @return void
	 */
	private function splitLine( $order, $line_item, $volumes, $keep_value = false, $linked_method = null ) {
		$name = $linked_method ? $linked_method->get_title() : $line_item->get_name();
		$instance_id = $linked_method ? $linked_method->get_instance_id() : $line_item->get_instance_id();
		$count = count( $volumes );
		$shares = $keep_value ? [] : $this->shareValue( $line_item, $volumes );

		foreach ( $volumes as $index => $volume ) {
			$item = 0 === $index ? $line_item : $this->cloneLine( $line_item, $instance_id );

			$this->describeVolume( $item, $volume, $index + 1, $count );

			if ( $keep_value && 0 === $index ) {
				continue;
			}

			if ( $keep_value ) {
				$this->clearValue( $item );
			} else {
				$this->applyShare( $item, $shares, $index );
			}

			$item->set_name( ShippingLineName::build( $item, $name ) );

			if ( 0 !== $index ) {
				$order->add_item( $item );
			}
		}
	}

	/**
	 * Write on a shipping line the box it carries.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $item   Line of the volume.
	 * @param array                   $volume Volume the line carries.
	 * @param int                     $number Position of the volume, from 1.
	 * @param int                     $count  Number of volumes of the shipment.
	 *
	 * @return void
	 */
	private function describeVolume( $item, $volume, $number, $count ) {
		$item->update_meta_data( '_weight', $volume['weight'] );
		$item->update_meta_data( '_length', $volume['length'] );
		$item->update_meta_data( '_width', $volume['width'] );
		$item->update_meta_data( '_height', $volume['height'] );
		$item->update_meta_data( CorreiosShippingMethod::VOLUME_INDEX_META_KEY, $number );
		$item->update_meta_data( CorreiosShippingMethod::VOLUME_TOTAL_META_KEY, $count );

		if ( isset( $volume['items'] ) ) {
			$item->update_meta_data( CorreiosShippingMethod::VOLUME_ITEMS_META_KEY, wp_json_encode( $volume['items'] ) );
			$item->update_meta_data( __( 'Items', 'woocommerce' ), $this->describeItems( $volume['items'] ) );
			$item->update_meta_data( 'package_qty', array_sum( wp_list_pluck( $volume['items'], 'qty' ) ) );
		}

		if ( ! empty( $volume['packaging_id'] ) ) {
			$item->update_meta_data( CorreiosShippingMethod::PACKAGING_ID_META_KEY, $volume['packaging_id'] );
			$item->update_meta_data( CorreiosShippingMethod::PACKAGING_NAME_META_KEY, isset( $volume['packaging_name'] ) ? $volume['packaging_name'] : '' );
		} else {
			$item->delete_meta_data( CorreiosShippingMethod::PACKAGING_ID_META_KEY );
			$item->delete_meta_data( CorreiosShippingMethod::PACKAGING_NAME_META_KEY );
		}
	}

	/**
	 * Take from a shipping line the box describeVolume() wrote on it.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $item Line of the volume.
	 *
	 * @return void
	 */
	private function clearVolume( $item ) {
		$meta_keys = [
			'_weight',
			'_length',
			'_width',
			'_height',
			CorreiosShippingMethod::VOLUME_INDEX_META_KEY,
			CorreiosShippingMethod::VOLUME_TOTAL_META_KEY,
			CorreiosShippingMethod::VOLUME_ITEMS_META_KEY,
			__( 'Items', 'woocommerce' ),
			'package_qty',
			CorreiosShippingMethod::PACKAGING_ID_META_KEY,
			CorreiosShippingMethod::PACKAGING_NAME_META_KEY,
		];

		foreach ( $meta_keys as $meta_key ) {
			$item->delete_meta_data( $meta_key );
		}
	}

	/**
	 * Share what a shipping line cost between its volumes.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $line_item Line being split.
	 * @param array                   $volumes   Volumes of the shipment.
	 *
	 * @return array{total: float[], taxes: array[], metas: array<string, float[]>}
	 */
	private function shareValue( $line_item, $volumes ) {
		$metas = [];

		foreach ( self::COST_META_KEYS as $meta_key ) {
			if ( $line_item->meta_exists( $meta_key ) ) {
				$metas[ $meta_key ] = $this->share( (float) $line_item->get_meta( $meta_key ), $volumes );
			}
		}

		return [
			'total' => $this->share( (float) $line_item->get_total(), $volumes ),
			'taxes' => $this->shareTaxes( $line_item->get_taxes(), $volumes ),
			'metas' => $metas,
		];
	}

	/**
	 * Give a volume its share of what the shipment cost.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $item   Line of the volume.
	 * @param array                   $shares What each volume is charged, as shareValue() returns it.
	 * @param int                     $index  Position of the volume, from 0.
	 *
	 * @return void
	 */
	private function applyShare( $item, $shares, $index ) {
		$item->set_total( $shares['total'][ $index ] );
		$item->set_taxes( $shares['taxes'][ $index ] );

		foreach ( $shares['metas'] as $meta_key => $amounts ) {
			$item->update_meta_data( $meta_key, $amounts[ $index ] );
		}
	}

	/**
	 * Leave a volume without any of what the shipment cost.
	 *
	 * The first line keeps all of it, and the metas copied from that line would
	 * repeat the cost of the whole shipment on every box.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $item Line of the volume.
	 *
	 * @return void
	 */
	private function clearValue( $item ) {
		$item->set_total( 0 );
		$item->set_taxes( [] );

		foreach ( self::COST_META_KEYS as $meta_key ) {
			$item->delete_meta_data( $meta_key );
		}
	}

	/**
	 * Describe what travels in a volume the way WooCommerce does.
	 *
	 * The shipping line shows its contents on the order screen, and a volume
	 * that repeated the contents of the whole shipment would tell the store to
	 * put four products in a box that holds two.
	 *
	 * @since 1.11.0
	 *
	 * @param array $items Products of the volume.
	 *
	 * @return string
	 */
	private function describeItems( $items ) {
		$described = [];

		foreach ( $items as $item ) {
			$product = isset( $item['id'] ) ? wc_get_product( $item['id'] ) : null;

			if ( ! $product ) {
				continue;
			}

			$described[] = $product->get_name() . ' &times;' . ( isset( $item['qty'] ) ? (int) $item['qty'] : 1 );
		}

		return implode( ', ', $described );
	}

	/**
	 * Copy a shipping line, metas included, to hold another volume.
	 *
	 * The copy is a line of this plugin even when the line belongs to another
	 * method: the volume ships with the Correios method that answers for it.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Shipping $line_item   Line being copied.
	 * @param int                     $instance_id Correios method the volume ships with.
	 *
	 * @return \WC_Order_Item_Shipping
	 */
	private function cloneLine( $line_item, $instance_id ) {
		$item = new \WC_Order_Item_Shipping();

		$item->set_props( [
			'method_title' => $line_item->get_name(),
			'method_id' => 'infixs-correios-automatico',
			'instance_id' => $instance_id,
			'total' => 0,
		] );

		foreach ( $line_item->get_meta_data() as $meta ) {
			$item->update_meta_data( $meta->key, $meta->value );
		}

		$item->delete_meta_data( CorreiosShippingMethod::VOLUMES_META_KEY );

		return $item;
	}

	/**
	 * Share an amount between the volumes, in the proportion each one cost.
	 *
	 * Volumes that cost nothing — a shipment priced by someone else — share it
	 * in equal parts.
	 *
	 * @since 1.11.0
	 *
	 * @param float $amount  Amount being shared.
	 * @param array $volumes Volumes of the shipment.
	 *
	 * @return float[]
	 */
	private function share( $amount, $volumes ) {
		$decimals = wc_get_price_decimals();
		$quoted = array_sum( wp_list_pluck( $volumes, 'cost' ) );
		$shares = [];

		foreach ( $volumes as $index => $volume ) {
			$portion = $quoted > 0 ? (float) $volume['cost'] / $quoted : 1 / count( $volumes );
			$shares[ $index ] = round( $amount * $portion, $decimals );
		}

		$shares[0] = round( $shares[0] + $amount - array_sum( $shares ), $decimals );

		return $shares;
	}

	/**
	 * Share the taxes of a shipping line between the volumes.
	 *
	 * @since 1.11.0
	 *
	 * @param array $taxes   Taxes of the line being split.
	 * @param array $volumes Volumes of the shipment.
	 *
	 * @return array[]
	 */
	private function shareTaxes( $taxes, $volumes ) {
		$shared = array_fill( 0, count( $volumes ), [ 'total' => [] ] );

		if ( empty( $taxes['total'] ) || ! is_array( $taxes['total'] ) ) {
			return $shared;
		}

		foreach ( $taxes['total'] as $rate_id => $amount ) {
			foreach ( $this->share( (float) $amount, $volumes ) as $index => $share ) {
				$shared[ $index ]['total'][ $rate_id ] = $share;
			}
		}

		return $shared;
	}
}
