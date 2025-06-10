<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Models\TrackingRange;
use Infixs\CorreiosAutomatico\Models\TrackingRangeCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Utils\Helper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class LabelService {

	/**
	 * Tracking service
	 * 
	 * @var TrackingService
	 */
	protected $trackingService;

	/**
	 * Shipping service
	 * 
	 * @var ShippingService
	 */
	protected $shippingService;

	public function __construct( TrackingService $trackingService, ShippingService $shippingService ) {
		$this->trackingService = $trackingService;
		$this->shippingService = $shippingService;
	}

	/**
	 * Get labels from orders
	 * 
	 * @param array $orders
	 * @return array
	 */
	public function getLabelsFromOrders( $order_ids ) {
		$labels = [];

		foreach ( $order_ids as $order_id ) {
			$label = $this->getLabelFromOrder( $order_id );
			if ( ! $label ) {
				continue;
			}
			$labels[] = $label;
		}

		return $labels;
	}

	public function getLabelFromOrder( $order_id ) {
		$order = Order::fromId( $order_id );

		if ( ! $order ) {
			return false;
		}

		$address = $order->getAddress();
		$shipping_metadata = $order->getFirstShippingItemData();

		$items = [];
		$products_total_amount = 0;
		//TODO: Deprecated
		$products_total_weight = 0;


		foreach ( $order->getItems() as $item ) {
			$product = $item->get_product();
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}
			$quantity = $item->get_quantity();
			$weight = wc_get_weight( (float) $product->get_weight(), 'kg' );
			$amount = Sanitizer::money100( $item->get_total(), '.' );
			$unit_amount = Sanitizer::money100( $product->get_price(), '.' );

			$items[] = [ 
				'name' => $item->get_name(),
				'sku' => $product->get_sku() ?: '',
				'quantity' => $quantity,
				'weight' => $weight,
				'ncm' => $product->get_meta( '_infixs_correios_automatico_ncm' ) ?? '',
				'amount' => $amount,
				'unit_amount' => $unit_amount,
			];

			$products_total_amount += $amount;
			$products_total_weight += $weight * $quantity;
		}

		$package = $order->getPackage();

		$shipping_total = round( floatval( $order->getShippingTotal() ), 2 ) * 100;
		$declaration_total_amount = $products_total_amount + $shipping_total;

		$shipping_method = $order->getShippingMethod();

		$product_code = $order->getShippingProductCode() ?? ( $shipping_method ? $shipping_method->get_product_code() : '00000' );

		return [ 
			'name' => $order->getCustomerFullName(),
			'document' => $order->getCustomerDocument(),
			'shipping_product_id' => DeliveryServiceCode::getCommonId( $product_code ),
			'shipping_product_code' => $product_code,
			'phone' => $order->getAlwaysPhone() ?: '',
			'address_street' => $address->getStreet(),
			'address_number' => $address->getNumber(),
			'address_complement' => $address->getComplement(),
			'address_neighborhood' => $address->getNeighborhood(),
			'address_city' => $address->getCity(),
			'address_state' => $address->getState(),
			'address_postalcode' => $address->getPostCode(),
			'address_country' => $address->getCountry(),
			'total_weight' => $shipping_metadata['weight'],
			'subtotal_amount' => Sanitizer::money100( $order->getSubtotal(), '.' ),
			'total_amount' => Sanitizer::money100( $order->getTotal(), '.' ),
			'items_count' => $package->get_items_count(),
			'contract_number' => Config::string( 'auth.contract_number' ),
			'postcard' => Config::string( 'auth.postcard' ),
			'tracking_code' => $order->getLastTrackingCode() ?: '',
			'website' => site_url(),
			'order_id' => $order->get_id(),
			'shipping_cost' => round( floatval( $order->getShippingTotal() ), 2 ),
			'products_total_amount' => $products_total_amount,
			'declaration_total_amount' => $declaration_total_amount,
			'invoice_number' => $order->getOrder()->get_meta( '_infixs_correios_automatico_invoice_number', true ) ?: null,
			'items' => $items,
			'ceint' => $this->shippingService->getCeintByPostCode( $address->getPostCode() )
		];
	}

	/**
	 * Create a range
	 * 
	 * @param string $service_code
	 * @param string $range_start
	 * @param string $range_end
	 * 
	 * @return \WP_Error|TrackingRange
	 */
	public function createRange( $service_code, $range_start, $range_end ) {

		$range_start = trim( strtoupper( $range_start ) );
		$range_end = trim( strtoupper( $range_end ) );

		if ( strlen( $range_start ) !== 13 ) {
			return new \WP_Error( 'invalid_range_start', 'O início do intervalo deve ter 13 caracteres.', [ 'status' => 400 ] );
		}

		if ( strlen( $range_end ) !== 13 ) {
			return new \WP_Error( 'invalid_range_end', 'O final do intervalo deve ter 13 caracteres.', [ 'status' => 400 ] );
		}

		$start_prefix = substr( $range_start, 0, 2 );
		$end_prefix = substr( $range_end, 0, 2 );

		if ( $start_prefix !== $end_prefix ) {
			return new \WP_Error( 'invalid_range', 'O início e o final do intervalo devem ter o mesmo prefixo.', [ 'status' => 400 ] );
		}

		$start_suffix = substr( $range_start, -2 );
		$end_suffix = substr( $range_end, -2 );

		if ( $start_suffix !== $end_suffix ) {
			return new \WP_Error( 'invalid_range', 'O início e o final do intervalo devem ter o mesmo sufixo.', [ 'status' => 400 ] );
		}

		$number_start = (int) Helper::extractNumberFromTrackingCode( $range_start, true );
		$number_end = (int) Helper::extractNumberFromTrackingCode( $range_end, true );

		if ( $number_start > $number_end ) {
			return new \WP_Error( 'invalid_range', 'O início do intervalo deve ser menor que o final do intervalo.', [ 'status' => 400 ] );
		}

		/** @var TrackingRange $tracking_range */
		$tracking_range = TrackingRange::create( [ 
			'service_code' => $service_code,
			'range_start' => $range_start,
			'range_end' => $range_end,
			'created_at' => current_time( 'mysql' ),
		] );

		if ( $tracking_range ) {
			for ( $i = $number_start; $i <= $number_end; $i++ ) {

				$number = str_pad( $i, 8, '0', STR_PAD_LEFT );
				$weights = [ 8, 6, 4, 2, 3, 5, 9, 7 ];
				$sum = 0;

				for ( $j = 0; $j < 8; $j++ ) {
					$sum += $number[ $j ] * $weights[ $j ];
				}

				$remainder = $sum % 11;
				$check_digit = ( $remainder == 0 || $remainder == 1 ) ? ( $remainder == 0 ? 5 : 0 ) : ( 11 - $remainder );

				$tracking_code = $start_prefix . $number . $check_digit . $start_suffix;
				$tracking_range->codes()->create( [ 
					'code' => $tracking_code,
				] );
			}
		}

		return $tracking_range;
	}

	/**
	 * Get ranges
	 * 
	 * @param array{
	 * 		'service_code': string,
	 *} $filters
	 * 
	 * @return array
	 */
	public function getRanges( $filters = [] ) {
		$query = TrackingRange::with( 'codes' );

		if ( isset( $filters['service_code'] ) ) {
			$query->where( 'service_code', $filters['service_code'] );
		}

		$tracking_ranges = $query->get();

		return $this->prepareRangesData( $tracking_ranges );
	}

	/**
	 * Delete ranges
	 * 
	 * @param string $range_id
	 * 
	 * @return bool|int
	 */
	public function deleteRanges( $range_id ) {
		TrackingRangeCode::where( 'tracking_range_id', $range_id )->delete();

		if ( TrackingRange::where( 'id', $range_id )->delete() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get ranges available
	 * 
	 * @param string $service_code
	 * 
	 * @return int
	 */
	public function getRangesAvailable( $service_code ) {
		/** @var TrackingRangeCode $tracking */
		$range_count = TrackingRangeCode::where( 'is_used', '0' )->whereHas( 'range', function ($query) use ($service_code) {
			$query->where( 'service_code', $service_code );
		} )->count();

		return $range_count;
	}

	/**
	 * Prepare the data
	 *
	 * @since 1.0.0
	 * @param \Infixs\WordpressEloquent\Collection $tracking_ranges
	 * @return array
	 */
	public function prepareRangesData( $tracking_ranges ) {
		$data = [];

		foreach ( $tracking_ranges as $tracking_range ) {
			$data[] = [ 
				'id' => $tracking_range->id,
				'service_title' => DeliveryServiceCode::getShortDescription( $tracking_range->service_code ) . " (" . $tracking_range->service_code . ")",
				'service_code' => $tracking_range->service_code,
				'range_start' => $tracking_range->range_start,
				'range_end' => $tracking_range->range_end,
				'total_codes' => $tracking_range->codes->count(),
				'used_codes' => count( $tracking_range->codes->where( 'is_used', '1' ) ),
				'created_at' => $tracking_range->created_at,
			];
		}

		return $data;
	}

	/**
	 * Use a range code
	 * 
	 * @param string $service_code
	 * 
	 * @return TrackingRangeCode|bool
	 */
	public function useRangeCode( $service_code ) {
		/** @var TrackingRangeCode $tracking */
		$tracking = TrackingRangeCode::where( 'is_used', '0' )->whereHas( 'range', function ($query) use ($service_code) {
			$query->where( 'service_code', $service_code );
		} )->first();

		if ( $tracking ) {
			$tracking->is_used = true;
			$tracking->save();

			return $tracking;
		}

		return false;
	}

	/**
	 * Attach range to order
	 * 
	 * @param int $order_id
	 * @param string $service_code
	 * 
	 * @return bool|\WP_Error
	 */
	public function attachRangeToOrder( $order_id, $service_code ) {
		$tracking = $this->useRangeCode( $service_code );

		if ( ! $tracking ) {
			return new \WP_Error(
				'no_range_code',
				'Não foi possível encontrar um código de rastreamento disponível para o serviço, cadastre um novo intervalo.'
			);
		}

		$created = $this->trackingService->add( $order_id, $tracking->code, false, [ 
			'tracking_range_code_id' => $tracking->id,
		] );

		if ( is_wp_error( $created ) ) {
			return new \WP_Error(
				'error_attach_range',
				'Não foi possível anexar o código de rastreamento ao pedido.'
			);
		}

		Log::debug( "Etiqueta de rastreamento para módico {$tracking->code} foi usada com sucesso!" );

		return true;
	}
}