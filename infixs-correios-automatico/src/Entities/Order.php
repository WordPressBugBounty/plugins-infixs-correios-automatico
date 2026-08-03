<?php

namespace Infixs\CorreiosAutomatico\Entities;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Shipping\LinkedShippingMethod;
use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\PrepostService;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Package;
use Infixs\CorreiosAutomatico\Utils\NumberHelper;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;
use Infixs\CorreiosAutomatico\Utils\TextHelper;

defined( 'ABSPATH' ) || exit;

class Order {
	/**
	 * Order instance.
	 * 
	 * @var \WC_Order
	 */
	private $order;


	/**
	 * Shipping items.
	 * 
	 * @var array{
	 * 		instance_id: int,
	 * 		width: float,
	 * 		height: float,
	 * 		lenght: float,
	 * 		weight: float,
	 * 		delivery_time: int,
	 * 		original_cost: float|null,
	 * 		shipping_product_code: string|null
	 * }[] $shipping_items
	 */
	private $shipping_items = [];

	/**
	 * Portion of the order this instance represents.
	 *
	 * Defaults to the whole order. A marketplace extension narrows it down to a
	 * single vendor through the `infixs_correios_automatico_order_scope` filter,
	 * so labels and preposts of a shared order carry only that vendor's items,
	 * shipment and contract.
	 *
	 * @since 1.8.2
	 *
	 * @var array{
	 * 		vendor_id: int,
	 * 		shipping_item_id: int,
	 * 		item_ids: int[],
	 * 		contract_number: string|null,
	 * 		postcard: string|null
	 * }
	 */
	private $scope = [
		'vendor_id' => 0,
		'shipping_item_id' => 0,
		'item_ids' => [],
		'contract_number' => null,
		'postcard' => null,
	];

	/**
	 * Order constructor.
	 *
	 * @param \WC_Order $order
	 */
	public function __construct( $order ) {
		$this->order = $order;

		$this->initializeShippingItems();
	}

	/**
	 * Build the scope of an order and let extensions narrow it down.
	 *
	 * The vendor is taken from the caller's context and never guessed from the
	 * current user: an admin side call passes no context, so the scope stays
	 * empty and the whole order is used, exactly as before 1.8.2.
	 *
	 * @since 1.8.2
	 *
	 * @param Order $order   Order being scoped.
	 * @param array $context Caller context, with `for` and optionally `vendor_id`.
	 *
	 * @return array
	 */
	public static function resolveScope( $order, $context = [] ) {
		$scope = [
			'vendor_id' => isset( $context['vendor_id'] ) ? (int) $context['vendor_id'] : 0,
			'shipping_item_id' => isset( $context['shipping_item_id'] ) ? absint( $context['shipping_item_id'] ) : 0,
			'item_ids' => [],
			'contract_number' => null,
			'postcard' => null,
		];

		/**
		 * Filters the portion of an order a label or prepost is built from.
		 *
		 * Marketplace extensions answer which items, which shipping line and
		 * which contract belong to the vendor being served. Everything else —
		 * the totals, the weights, the declared contents — stays owned by the
		 * base plugin.
		 *
		 * A caller naming a `shipping_item_id` already gets that line honoured
		 * without any extension: the item ownership is what only a marketplace
		 * knows, so `item_ids` is left for it to fill in.
		 *
		 * @since 1.8.2
		 *
		 * @param array $scope   Scope, all defaults meaning the whole order.
		 * @param Order $order   Order being scoped.
		 * @param array $context Caller context, with `for` and optionally `vendor_id` or `shipping_item_id`.
		 */
		return apply_filters( 'infixs_correios_automatico_order_scope', $scope, $order, $context );
	}

	/**
	 * Narrow this instance down to a portion of the order.
	 *
	 * @since 1.8.2
	 *
	 * @param array $scope Scope as built by resolveScope().
	 *
	 * @return $this
	 */
	public function applyScope( $scope ) {
		$this->scope = [
			'vendor_id' => isset( $scope['vendor_id'] ) ? (int) $scope['vendor_id'] : 0,
			'shipping_item_id' => isset( $scope['shipping_item_id'] ) ? (int) $scope['shipping_item_id'] : 0,
			'item_ids' => isset( $scope['item_ids'] ) && is_array( $scope['item_ids'] ) ? array_map( 'absint', $scope['item_ids'] ) : [],
			'contract_number' => isset( $scope['contract_number'] ) ? $scope['contract_number'] : null,
			'postcard' => isset( $scope['postcard'] ) ? $scope['postcard'] : null,
		];

		$this->shipping_items = [];
		$this->initializeShippingItems();

		return $this;
	}

	/**
	 * Get the scope currently applied.
	 *
	 * @since 1.8.2
	 *
	 * @return array
	 */
	public function getScope() {
		return $this->scope;
	}

	/**
	 * Get the vendor this instance is scoped to.
	 *
	 * @since 1.8.2
	 *
	 * @return int Zero when the whole order is in scope.
	 */
	public function getScopedVendorId() {
		return $this->scope['vendor_id'];
	}

	/**
	 * Get the shipping line this instance is scoped to.
	 *
	 * @since 1.8.3
	 *
	 * @return int Zero when every shipping line is in scope.
	 */
	public function getScopedShippingItemId() {
		return $this->scope['shipping_item_id'];
	}


	/**
	 * Get order from id.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $order Order id.
	 * 
	 * @return Order|false
	 */
	public static function fromId( $order ) {
		$order = wc_get_order( $order );
		return $order ? new self( $order ) : false;
	}

	public function getOrder() {
		return $this->order;
	}

	protected function initializeShippingItems() {
		$line_items_shipping = $this->order->get_items( 'shipping' );
		foreach ( $line_items_shipping as $item ) {
			if ( $this->scope['shipping_item_id'] && (int) $item->get_id() !== $this->scope['shipping_item_id'] ) {
				continue;
			}

			if ( ! $item instanceof \WC_Order_Item_Shipping || ( $item->get_method_id() !== 'infixs-correios-automatico' && ! LinkedShippingMethod::resolve_from_item( $item ) ) ) {
				$this->shipping_items[] = [
					'item_id' => $item->get_id(),
					'instance_id' => $item->get_instance_id(),
					'method_title' => TextHelper::removeShippingTime( $item->get_name() ),
					'cost' => $item->get_total(),
					'vendor_id' => (int) $item->get_meta( 'vendor_id' ) ?: null,
					'is_correios' => false,
					'width' => 0,
					'height' => 0,
					'lenght' => 0,
					'weight' => 0,
					'delivery_time' => 0,
					'original_cost' => null,
					'insurance_cost' => 0,
					'shipping_product_code' => null,
				];
				continue;
			}

			$this->shipping_items[] = [
				'item_id' => $item->get_id(),
				'instance_id' => $item->get_instance_id(),
				'method_title' => TextHelper::removeShippingTime( $item->get_name() ),
				'cost' => $item->get_total(),
				'vendor_id' => (int) $item->get_meta( 'vendor_id' ) ?: null,
				'is_correios' => true,
				'width' => $item->get_meta( '_width' ) ?: 0,
				'height' => $item->get_meta( '_height' ) ?: 0,
				'lenght' => $item->get_meta( '_length' ) ?: 0,
				'weight' => $item->get_meta( '_weight' ) ?: 0,
				'delivery_time' => $item->get_meta( 'delivery_time' ) ?: 0,
				'original_cost' => $item->get_meta( '_original_cost' ) ?: null,
				'insurance_cost' => $item->get_meta( '_insurance_cost' ) ?: 0,
				'shipping_product_code' => $item->get_meta( 'shipping_product_code' ) ?: null,
			];
		}
	}

	/**
	 * Build the per shipping line payload of the order.
	 *
	 * The `shipping` object of `toArray()` describes the order as a single
	 * shipment, which is what a store with one freight per order needs. A
	 * marketplace order carries one line per vendor, so the dashboard also gets
	 * every line separately here.
	 *
	 * @since 1.8.3
	 *
	 * @return array<int, array>
	 */
	public function getShippingItemsPayload() {
		$items = [];

		foreach ( $this->getShippingItemsData() as $shipping_item ) {
			$product_code = $shipping_item['shipping_product_code'];

			$items[] = [
				'item_id' => (int) $shipping_item['item_id'],
				'instance_id' => (int) $shipping_item['instance_id'],
				'method_title' => (string) $shipping_item['method_title'],
				'cost' => NumberHelper::numericToCents( $shipping_item['cost'] ),
				'original_cost' => $shipping_item['original_cost'] !== null ? NumberHelper::numericToCents( $shipping_item['original_cost'] ) : null,
				'insurance_cost' => NumberHelper::numericToCents( $shipping_item['insurance_cost'] ),
				'delivery_time' => (int) $shipping_item['delivery_time'],
				'width' => $shipping_item['width'],
				'height' => $shipping_item['height'],
				'length' => $shipping_item['lenght'],
				'weight' => $shipping_item['weight'],
				'shipping_product_code' => $product_code,
				'shipping_product_title' => $product_code ? DeliveryServiceCode::getDescription( $product_code ) : null,
				'vendor_id' => $shipping_item['vendor_id'],
				'is_correios' => (bool) $shipping_item['is_correios'],
			];
		}

		return $items;
	}

	/**
	 * Extract address from order.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WC_Order $order
	 * 
	 * @return Address
	 */
	public function getAddress() {
		if ( $this->order->has_shipping_address() ) {
			$address = $this->order->get_shipping_address_1();
			$address_number = $this->order->get_meta( '_shipping_number' );
			$hasNumberField = strlen( $address_number ) > 0;

			if ( ! $hasNumberField ) {
				$address_number = TextHelper::extractAddressNumber( $address );
			}

			return new Address(
				Sanitizer::numeric_text( $this->order->get_shipping_postcode() ),
				$hasNumberField ? $address : TextHelper::removeAddressNumber( $address ),
				$address_number,
				$this->order->get_meta( '_shipping_neighborhood' ),
				$this->order->get_shipping_city(),
				$this->order->get_shipping_state(),
				$this->order->get_shipping_address_2(),
			);
		} else {
			$address = $this->order->get_billing_address_1();
			$address_number = $this->order->get_meta( '_billing_number' );
			$hasNumberField = strlen( $address_number ) > 0;

			if ( ! $hasNumberField ) {
				$address_number = TextHelper::extractAddressNumber( $address );
			}

			return new Address(
				Sanitizer::numeric_text( $this->order->get_billing_postcode() ),
				$hasNumberField ? $address : TextHelper::removeAddressNumber( $address ),
				$address_number,
				$this->order->get_meta( '_billing_neighborhood' ),
				$this->order->get_billing_city(),
				$this->order->get_billing_state(),
				$this->order->get_billing_address_2()
			);
		}
	}

	public function get_id() {
		return $this->order->get_id();
	}

	/**
	 * Get last tracking code.
	 * 
	 * @since 1.0.0
	 * 
	 * @return string|null
	 */
	public function getLastTrackingCode() {
		$model = TrackingCode::where( 'order_id', $this->order->get_id() )->orderBy( 'id', 'desc' )->first();
		if ( ! $model ) {
			return null;
		}
		return $model->code;
	}

	//TODO: use getTrackings in TrackingService
	public function getTrackingCodes() {
		return TrackingCode::with( 'unit' )->where( 'order_id', $this->order->get_id() )->get();
	}

	/**
	 * Get customer from order.
	 * 
	 * @since 1.0.0
	 * 
	 * @return Customer
	 */
	public function getCustomer() {
		$customer_info = $this->isBusinessCustomer() ?
			$this->getBillingCustomerInfo() :
			$this->getShippingCustomerInfo();


		$recipient_phone = $this->getPhone();
		$recipient_cellphone = $this->getCellphone();

		return new Customer(
			$customer_info['name'],
			$this->order->get_billing_email(),
			empty( $recipient_cellphone ) ? $recipient_phone : $recipient_cellphone,
			$customer_info['document'],
		);
	}

	public function getCustomerFullName() {
		$customer_info = $this->isBusinessCustomer() ?
			$this->getBillingCustomerInfo() :
			$this->getShippingCustomerInfo();

		return $customer_info['name'];
	}

	public function getCustomerEmail() {
		return $this->order->get_billing_email();
	}

	public function getCustomerDocument() {
		$customer_info = $this->isBusinessCustomer() ?
			$this->getBillingCustomerInfo() :
			$this->getShippingCustomerInfo();

		return $customer_info['document'];
	}

	public function getCellphone() {
		return Sanitizer::celphone( empty( $this->order->get_meta( '_billing_cellphone' ) ) ? $this->order->get_billing_phone() : $this->order->get_meta( '_billing_cellphone' ) );
	}

	public function getPhone() {
		return Sanitizer::phone( empty( $this->order->get_shipping_phone() ) ? $this->order->get_billing_phone() : $this->order->get_shipping_phone() );
	}

	public function getAlwaysPhone() {
		return empty( $this->getCellphone() ) ? $this->getPhone() : $this->getCellphone();
	}

	public function getShippingTotal() {
		if ( ! $this->scope['shipping_item_id'] ) {
			return $this->order->get_shipping_total();
		}

		$item = $this->order->get_item( $this->scope['shipping_item_id'] );

		return $item ? $item->get_total() : $this->order->get_shipping_total();
	}

	/**
	 * Get billing customer info.
	 * 
	 * This method is responsible for getting billing customer info.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WC_Order $order Order.
	 * 
	 * @return array{
	 *      string cpfCnpj,
	 *      string name
	 * }
	 */
	public function getBillingCustomerInfo() {
		$document = Sanitizer::numeric_text( empty( $this->order->get_meta( '_billing_cnpj' ) ) ? $this->order->get_meta( '_billing_cpf' ) : $this->order->get_meta( '_billing_cnpj' ) );
		$name = empty( $this->order->get_shipping_company() ) ? $this->order->get_billing_company() : $this->order->get_shipping_company();
		return [
			'document' => $document,
			'name' => $name
		];
	}

	/**
	 * Get shipping customer info.
	 * 
	 * This method is responsible for getting shipping customer info.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WC_Order $order Order.
	 * 
	 * @return array{
	 *      string cpfCnpj,
	 *      string name
	 * }
	 */
	public function getShippingCustomerInfo() {
		$cpf = $this->order->get_meta( '_billing_cpf' );
		$document = empty( $cpf ) ? '' : Sanitizer::numeric_text( $cpf );

		if ( ! empty( $this->order->get_shipping_first_name() ) ) {
			$first_name = $this->order->get_shipping_first_name();
			$last_name = $this->order->get_shipping_last_name();
		} else {
			$first_name = $this->order->get_billing_first_name();
			$last_name = $this->order->get_billing_last_name();
		}

		$name = trim( "$first_name $last_name" );
		return [
			'document' => $document,
			'name' => $name
		];
	}


	public function isBusinessCustomer() {
		return $this->order->meta_exists( '_billing_persontype' ) && $this->order->get_meta( '_billing_persontype' ) == '2';
	}

	public function getItems() {
		$items = $this->order->get_items();

		if ( ! $this->scope['item_ids'] ) {
			return $items;
		}

		return array_intersect_key( $items, array_flip( $this->scope['item_ids'] ) );
	}

	public function getContents() {
		$contents = [];
		foreach ( $this->getItems() as $item ) {
			if ( ! $item->get_product() )
				continue;

			$item_id = $item->get_id();
			if ( empty( $item_id ) ) {
				$contents[] = [
					'quantity' => $item->get_quantity(),
					'data' => $item->get_product(),
					'line_total' => $item->get_total(),
				];
			} else {
				$contents[ $item_id ] = [
					'quantity' => $item->get_quantity(),
					'data' => $item->get_product(),
					'line_total' => $item->get_total(),
				];
			}
		}

		return $contents;
	}

	/**
	 * Get package from order.
	 * 
	 * @since 1.0.0
	 * 
	 * @param CorreiosShippingMethod|null $shipping_method
	 * 
	 * @return Package
	 */
	public function getPackage( $shipping_method = null ) {
		$package_data = [];

		$package_data['contents'] = $this->getContents();

		if ( ! $shipping_method ) {
			$shipping_method = $this->getShippingMethod();

			if ( ! $shipping_method ) {
				return new Package( $package_data );
			}
		}

		return $shipping_method->get_package( $package_data );
	}

	public function getPackageData() {
		$address = $this->getAddress();

		return [
			'contents' => $this->getContents(),
			'contents_cost' => $this->order->get_subtotal(),
			'applied_coupons' => false,
			'user' => [
				'ID' => get_current_user_id(),
			],
			'destination' => [
				'country' => $address->getCountry(),
				'state' => $address->getState(),
				'postcode' => $address->getPostCode(),
				'city' => $address->getCity(),
				'address' => $address->getStreet(),
			],
			'is_product_page' => false,
		];
	}

	/**
	 * Get the Correios shipping method from the order
	 *
	 * Falls back to the Correios method the merchant linked to a native shipping
	 * method (e.g. "Frete Grátis"), so those orders can be pre-posted too.
	 *
	 * @return CorreiosShippingMethod|false
	 */
	public function getShippingMethod() {
		$shipping_methods = $this->getScopedShippingMethods();

		foreach ( $shipping_methods as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'infixs-correios-automatico' ) === 0 ) {
				$instance_id = $shipping_method->get_instance_id();
				return \WC_Shipping_Zones::get_shipping_method( $instance_id );
			}
		}

		foreach ( $shipping_methods as $shipping_method ) {
			$linked_method = LinkedShippingMethod::resolve_from_item( $shipping_method );

			if ( $linked_method ) {
				return $linked_method;
			}
		}

		return false;
	}

	/**
	 * Get the shipping lines in scope.
	 *
	 * @since 1.8.2
	 *
	 * @return \WC_Order_Item_Shipping[]
	 */
	private function getScopedShippingMethods() {
		$shipping_methods = $this->order->get_shipping_methods();

		if ( ! $this->scope['shipping_item_id'] ) {
			return $shipping_methods;
		}

		return array_intersect_key( $shipping_methods, [ $this->scope['shipping_item_id'] => true ] );
	}

	public function getSubtotal() {
		return $this->order->get_subtotal();
	}

	public function getTotal() {
		return $this->order->get_total();
	}

	/**
	 * Get shipping product code.
	 * 
	 * @since 1.1.5
	 * 
	 * @return string|null
	 */
	public function getShippingProductCode() {
		$first_shipping_item = $this->getFirstShippingItemData();
		$shipping_product_code = $first_shipping_item['shipping_product_code'];

		/**
		 * Scoped to a single shipment the frozen value wins: the method instance
		 * resolves its service against the store wide contract flag, which would
		 * quote a vendor on the public table with the contract code, and vice
		 * versa. It is also the only value that is per shipping line.
		 */
		if ( ( $this->scope['vendor_id'] || $this->scope['shipping_item_id'] ) && $shipping_product_code ) {
			return $shipping_product_code;
		}

		$shipping_method = $this->getShippingMethod();
		if ( $shipping_method ) {
			return $shipping_method->get_product_code();
		}

		if ( $shipping_product_code )
			return $shipping_product_code;

		return null;
	}

	public function isCompleted() {
		return $this->order->get_status() === 'completed';
	}

	/**
	 * Get first shipping item.
	 * 
	 * @since 1.0.0
	 * 
	 * @return array{
	 * 		width: float,
	 * 		height: float,
	 * 		lenght: float,
	 * 		weight: float,
	 * 		delivery_time: int,
	 * 		shipping_product_code: string|null
	 * }|null
	 */
	public function getFirstShippingItemData() {
		return $this->shipping_items[0] ?? [
			'width' => 0,
			'height' => 0,
			'lenght' => 0,
			'weight' => 0,
			'delivery_time' => 0,
			'insurance_cost' => 0,
			'original_cost' => null,
			'shipping_product_code' => null,
		];
	}

	public function getShippingItemsData() {
		return $this->shipping_items;
	}

	/**
	 * Get the shipping package dimensions.
	 *
	 * Prefers the values frozen on the shipping item at checkout. Orders placed before
	 * a Correios method was linked to their shipping method carry no such meta, so the
	 * package is computed on demand from the resolved method's settings.
	 *
	 * @since 1.8.0
	 *
	 * @return array{ width: float, height: float, lenght: float, weight: float }
	 */
	public function getShippingPackageDimensions() {
		$shipping_item = $this->getFirstShippingItemData();

		if ( $shipping_item['weight'] > 0 || $shipping_item['width'] > 0 || $shipping_item['height'] > 0 || $shipping_item['lenght'] > 0 ) {
			return [
				'width' => $shipping_item['width'],
				'height' => $shipping_item['height'],
				'lenght' => $shipping_item['lenght'],
				'weight' => $shipping_item['weight'],
			];
		}

		$shipping_method = $this->getShippingMethod();

		if ( ! $shipping_method ) {
			return [
				'width' => 0,
				'height' => 0,
				'lenght' => 0,
				'weight' => 0,
			];
		}

		$package_data = $this->getPackage( $shipping_method )->get_data();

		return [
			'width' => $package_data['width'],
			'height' => $package_data['height'],
			'lenght' => $package_data['length'],
			'weight' => $package_data['weight'],
		];
	}

	public function toArray() {
		$address = $this->getAddress()->toArray();
		$customer = $this->getCustomer()->toArray();
		$customer['id'] = $this->order->get_customer_id();
		$customer['address'] = $address;

		$items = array_map( function ( $item ) {
			return [
				'id' => $item->get_id(),
				'name' => $item->get_name(),
				'quantity' => intval( $item->get_quantity() ),
				'price' => NumberHelper::to100( $item->get_total() ),
			];
		}, $this->order->get_items() );

		$items = array_values( $items );

		$shipping_product_code = $this->getShippingProductCode();
		$shipping_method = $this->getShippingMethod();

		$has_dangerous_product = false;
		foreach ( $this->order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			if ( 'yes' === get_post_meta( $item->get_product_id(), '_infixs_correios_automatico_dangerous_product', true ) ) {
				$has_dangerous_product = true;
				break;
			}
		}

		$shipping_metadata = $this->getFirstShippingItemData();

		$prepost_errors = $this->order->get_meta( PrepostService::PREPOST_ERRORS_META_KEY );
		$prepost_errors = is_array( $prepost_errors ) ? array_values( $prepost_errors ) : [];

		$data = [
			'id' => $this->order->get_id(),
			'order_url' => $this->order->get_edit_order_url(),
			'status' => $this->order->get_status(),
			'status_label' => wc_get_order_status_name( $this->order->get_status() ),
			'total_amount' => NumberHelper::to100( $this->order->get_total() ),
			'items' => $items,
			'shipping' => [
				'shipping_amount' => Sanitizer::money100( $this->order->get_shipping_total(), '.' ),
				'original_cost' => $shipping_metadata['original_cost'] ? Sanitizer::money100( $shipping_metadata['original_cost'], '.' ) : null,
				'shipping_method' => TextHelper::removeShippingTime( $this->order->get_shipping_method() ),
				'instance_id' => $shipping_metadata['instance_id'] ?? 0,
				'shipping_product_code' => $shipping_product_code,
				'shipping_product_title' => DeliveryServiceCode::getDescription( $shipping_product_code, true ),
				'shipping_product_short_title' => DeliveryServiceCode::getShortDescription( $shipping_product_code ),
				'delivery_time' => $shipping_metadata['delivery_time'],
				'width' => $shipping_metadata['width'],
				'height' => $shipping_metadata['height'],
				'length' => $shipping_metadata['lenght'],
				'weight' => Sanitizer::weight( $shipping_metadata['weight'] ),
				'insurance_cost' => $shipping_metadata['insurance_cost'] ? Sanitizer::money100( $shipping_metadata['insurance_cost'], '.' ) : 0,
				'additional_services' => [
					'own_hands' => $shipping_method ? $shipping_method->is_own_hands() : false,
					'receipt_notice' => $shipping_method ? $shipping_method->is_receipt_notice() : false,
					'receipt_notice_electronic' => $shipping_method ? $shipping_method->is_receipt_notice_electronic() : false,
					'dangerous_product' => $has_dangerous_product,
				],
			],
			'printed' => $this->order->get_meta( '_infixs_correios_automatico_printed', true ) ?: null,
			'email_tracking_sent' => $this->order->get_meta( '_infixs_correios_automatico_email_tracking_sent', true ) ?: null,
			'email_preparing_sent' => $this->order->get_meta( '_infixs_correios_automatico_email_preparing_sent', true ) ?: null,
			'customer' => $customer,
			'created_at' => $this->order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'meta_data' => array_values( array_filter( [
				( $invoice_number = $this->order->get_meta( '_infixs_correios_automatico_invoice_number', true ) ) !== '' && $invoice_number !== false ? [ 'key' => '_infixs_correios_automatico_invoice_number', 'value' => $invoice_number ] : null,
				( $invoice_key = $this->order->get_meta( '_infixs_correios_automatico_invoice_key', true ) ) !== '' && $invoice_key !== false ? [ 'key' => '_infixs_correios_automatico_invoice_key', 'value' => $invoice_key ] : null,
			] ) ),
			'shipping_items' => $this->getShippingItemsPayload(),
			'prepost_errors' => $prepost_errors,
			'preposts' => []
		];


		$preposts = Prepost::where( 'order_id', $this->order->get_id() )->orderBy( "created_at", "desc" )->get();

		if ( $preposts ) {
			foreach ( $preposts->all() as $prepost ) {
				$data['preposts'][] = Container::prepostService()->prepareData( $prepost );
			}
		}

		$tracking_codes = $this->getTrackingCodes();
		if ( ! empty( $tracking_codes ) ) {
			$data['tracking_codes'] = array_map( function ( $tracking_code ) {
				$tracking_code_data = [
					'id' => $tracking_code['id'],
					'code' => $tracking_code['code'],
					'vendor_id' => ! empty( $tracking_code['vendor_id'] ) ? (int) $tracking_code['vendor_id'] : null,
					'shipping_item_id' => ! empty( $tracking_code['shipping_item_id'] ) ? (int) $tracking_code['shipping_item_id'] : null,
				];
				if ( isset( $tracking_code['unit'] ) ) {
					$tracking_code_data['unit'] = Container::unitService()->prepareData( $tracking_code['unit'] );
				}

				return $tracking_code_data;
			}, $tracking_codes->toArray() );
		}

		$data = apply_filters( 'infixs_correios_automatico_order_data', $data, $this->order );

		return $data;
	}
}
