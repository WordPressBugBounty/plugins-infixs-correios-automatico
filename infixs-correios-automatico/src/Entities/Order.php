<?php

namespace Infixs\CorreiosAutomatico\Entities;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Core\Shipping\LinkedShippingMethod;
use Infixs\CorreiosAutomatico\Marketplace\MarketplaceRegistry;
use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\OrderInvoiceResolver;
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
		'item_quantities' => [],
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
			'item_quantities' => [],
			'contract_number' => null,
			'postcard' => null,
		];

		if ( $scope['shipping_item_id'] ) {
			$scope = self::scopeVolumeItems( $order, $scope );
		}

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
	 * Fill the scope with the products travelling in a volume.
	 *
	 * A shipment broken into volumes writes on each shipping line which
	 * products went into that box, so the label and the prepost of a volume
	 * declare what is inside it instead of the whole order. Only the products
	 * are recorded, never the order item ids: the line is written before the
	 * order is saved, when no item has an id yet.
	 *
	 * @since 1.11.0
	 *
	 * @param Order $order Order being scoped.
	 * @param array $scope Scope built so far.
	 *
	 * @return array
	 */
	private static function scopeVolumeItems( $order, $scope ) {
		$shipping_item = $order->getOrder()->get_item( $scope['shipping_item_id'] );

		if ( ! $shipping_item ) {
			return $scope;
		}

		$volume_items = $shipping_item->get_meta( CorreiosShippingMethod::VOLUME_ITEMS_META_KEY );

		if ( is_string( $volume_items ) ) {
			$volume_items = json_decode( $volume_items, true );
		}

		if ( ! is_array( $volume_items ) || ! $volume_items ) {
			return $scope;
		}

		$wanted = [];

		foreach ( $volume_items as $volume_item ) {
			if ( ! isset( $volume_item['id'] ) ) {
				continue;
			}

			$product_id = absint( $volume_item['id'] );
			$quantity = isset( $volume_item['qty'] ) ? absint( $volume_item['qty'] ) : 0;
			$wanted[ $product_id ] = isset( $wanted[ $product_id ] ) ? $wanted[ $product_id ] + $quantity : $quantity;
		}

		foreach ( $order->getOrder()->get_items() as $item_id => $item ) {
			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

			if ( empty( $wanted[ $product_id ] ) ) {
				continue;
			}

			$quantity = min( (int) $item->get_quantity(), $wanted[ $product_id ] );
			$wanted[ $product_id ] -= $quantity;

			$scope['item_ids'][] = $item_id;
			$scope['item_quantities'][ $item_id ] = $quantity;
		}

		return $scope;
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
			'item_quantities' => isset( $scope['item_quantities'] ) && is_array( $scope['item_quantities'] ) ? array_map( 'absint', $scope['item_quantities'] ) : [],
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
					'vendor_id' => $this->resolveShippingItemVendorId( $item ),
					'is_correios' => false,
					/**
					 * Filters the carrier that owns a shipping line the base plugin
					 * does not recognize.
					 *
					 * A carrier extension answers with its slug (e.g. `pex`) when the
					 * line belongs to its own shipping method, so the dashboard can
					 * offer that carrier's actions instead of the Correios ones.
					 *
					 * @since 1.9.0
					 *
					 * @param string|null                    $carrier Carrier slug, null when unknown.
					 * @param \WC_Order_Item_Shipping|mixed $item    Shipping order item.
					 */
					'carrier' => $item instanceof \WC_Order_Item_Shipping
						? apply_filters( 'infixs_correios_automatico_shipping_item_carrier', null, $item )
						: null,
					'width' => 0,
					'height' => 0,
					'lenght' => 0,
					'weight' => 0,
					'delivery_time' => 0,
					'original_cost' => null,
					'insurance_cost' => 0,
					'shipping_product_code' => null,
					'volume_index' => 0,
					'volume_total' => 0,
					'packaging_name' => '',
				];
				continue;
			}

			$this->shipping_items[] = [
				'item_id' => $item->get_id(),
				'instance_id' => $item->get_instance_id(),
				'method_title' => TextHelper::removeShippingTime( $item->get_name() ),
				'cost' => $item->get_total(),
				'vendor_id' => $this->resolveShippingItemVendorId( $item ),
				'is_correios' => true,
				'carrier' => 'correios',
				'width' => $item->get_meta( '_width' ) ?: 0,
				'height' => $item->get_meta( '_height' ) ?: 0,
				'lenght' => $item->get_meta( '_length' ) ?: 0,
				'weight' => $item->get_meta( '_weight' ) ?: 0,
				'delivery_time' => $item->get_meta( 'delivery_time' ) ?: 0,
				'original_cost' => $item->get_meta( '_original_cost' ) ?: null,
				'insurance_cost' => $item->get_meta( '_insurance_cost' ) ?: 0,
				'shipping_product_code' => $item->get_meta( 'shipping_product_code' ) ?: null,
				'volume_index' => (int) $item->get_meta( CorreiosShippingMethod::VOLUME_INDEX_META_KEY ),
				'volume_total' => (int) $item->get_meta( CorreiosShippingMethod::VOLUME_TOTAL_META_KEY ),
				'packaging_name' => (string) $item->get_meta( CorreiosShippingMethod::PACKAGING_NAME_META_KEY ),
			];
		}
	}

	/**
	 * Get the vendor a shipping line belongs to.
	 *
	 * The `vendor_id` meta is the convention of a marketplace that puts several
	 * vendors on one order and tags each line. A marketplace that splits the
	 * order into one per vendor writes no such meta, and there the vendor of the
	 * line is simply the vendor of the order — which only the extension knows.
	 *
	 * @since 1.10.0
	 *
	 * @param \WC_Order_Item $item Shipping order item.
	 *
	 * @return int|null Null when no vendor owns the line.
	 */
	private function resolveShippingItemVendorId( $item ) {
		$vendor_id = (int) $item->get_meta( 'vendor_id' );

		if ( $vendor_id ) {
			return $vendor_id;
		}

		$adapter = MarketplaceRegistry::forOrder( $this->order );

		if ( ! $adapter ) {
			return null;
		}

		$vendor_id = (int) $adapter->getShippingItemVendorId( $this->order, $item->get_id() );

		return $vendor_id ? $vendor_id : null;
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
	 * @since 1.11.0 Tells whether each shipment carries a dangerous product and the packaging it ships in.
	 *
	 * @return array<int, array>
	 */
	public function getShippingItemsPayload() {
		$items = [];
		$has_dangerous_product = $this->hasDangerousProduct();

		foreach ( $this->getShippingItemsData() as $shipping_item ) {
			$product_code = $shipping_item['shipping_product_code'];
			$volume_total = isset( $shipping_item['volume_total'] ) ? (int) $shipping_item['volume_total'] : 0;

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
				'volume_index' => isset( $shipping_item['volume_index'] ) ? (int) $shipping_item['volume_index'] : 0,
				'volume_total' => $volume_total,
				'vendor_id' => $shipping_item['vendor_id'],
				'is_correios' => (bool) $shipping_item['is_correios'],
				'carrier' => $shipping_item['carrier'] ?? ( $shipping_item['is_correios'] ? 'correios' : null ),
				'dangerous_product' => $volume_total > 1 ? $this->hasDangerousProductInShipment( (int) $shipping_item['item_id'] ) : $has_dangerous_product,
				'packaging_name' => isset( $shipping_item['packaging_name'] ) ? (string) $shipping_item['packaging_name'] : '',
			];
		}

		return $items;
	}

	/**
	 * Whether a product packed in one shipment of the order is dangerous.
	 *
	 * The shipment is scoped the way its prepost is, so the default the
	 * dashboard offers is the one the prepost takes when it is not told.
	 *
	 * @since 1.11.0
	 *
	 * @param int $shipping_item_id Shipping line of the shipment.
	 *
	 * @return bool
	 */
	private function hasDangerousProductInShipment( $shipping_item_id ) {
		$shipment = new self( $this->order );

		$shipment->applyScope( self::resolveScope( $shipment, [
			'for' => 'prepost',
			'shipping_item_id' => $shipping_item_id,
		] ) );

		return $shipment->hasDangerousProduct();
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
	 * @since 1.11.0 Leaves the reverse shipments out: the panel records a manual return on the line of the box it takes back.
	 *
	 * @return string|null
	 */
	public function getLastTrackingCode() {
		$query = TrackingCode::where( 'order_id', $this->order->get_id() );

		if ( $this->scope['shipping_item_id'] ) {
			$query = $query->where( 'shipping_item_id', $this->scope['shipping_item_id'] );
		}

		$reverse_codes = Container::trackingService()->getReverseCodes( $this->order->get_id() );

		foreach ( $query->orderBy( 'id', 'desc' )->get() as $model ) {
			if ( ! in_array( $model->code, $reverse_codes, true ) ) {
				return $model->code;
			}
		}

		return null;
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

	/**
	 * Whether a product in scope has to travel as a dangerous article.
	 *
	 * A volume or a vendor answers only for the products it carries, so the
	 * dangerous goods service is declared only on the objects that need it.
	 *
	 * @since 1.11.0
	 *
	 * @return bool
	 */
	public function hasDangerousProduct() {
		foreach ( $this->getItems() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			if ( 'yes' === get_post_meta( $item->get_product_id(), '_infixs_correios_automatico_dangerous_product', true ) ) {
				return true;
			}
		}

		return false;
	}

	public function getContents() {
		$contents = [];
		foreach ( $this->getItems() as $item ) {
			if ( ! $item->get_product() )
				continue;

			$item_id = $item->get_id();
			$quantity = $this->getScopedQuantity( $item );

			if ( empty( $item_id ) ) {
				$contents[] = [
					'quantity' => $quantity,
					'data' => $item->get_product(),
					'line_total' => $this->getScopedLineTotal( $item, $quantity ),
				];
			} else {
				$contents[ $item_id ] = [
					'quantity' => $quantity,
					'data' => $item->get_product(),
					'line_total' => $this->getScopedLineTotal( $item, $quantity ),
				];
			}
		}

		return $contents;
	}

	/**
	 * Units of an item that belong to the scope in force.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Product $item Order item.
	 *
	 * @return int
	 */
	public function getScopedQuantity( $item ) {
		$item_id = $item->get_id();

		if ( $item_id && isset( $this->scope['item_quantities'][ $item_id ] ) ) {
			return (int) $this->scope['item_quantities'][ $item_id ];
		}

		return (int) $item->get_quantity();
	}

	/**
	 * Share of the line total that belongs to the units in scope.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Product $item     Order item.
	 * @param int|null               $quantity Units in scope, resolved from the scope when null.
	 *
	 * @return float
	 */
	public function getScopedLineTotal( $item, $quantity = null ) {
		$quantity = $quantity === null ? $this->getScopedQuantity( $item ) : $quantity;

		$total = (float) $item->get_total();
		$item_quantity = (int) $item->get_quantity();

		if ( $item_quantity < 1 || $quantity === $item_quantity ) {
			return $total;
		}

		return ( $total / $item_quantity ) * $quantity;
	}

	/**
	 * Share of the line subtotal that belongs to the units in scope.
	 *
	 * The subtotal is the value before discounts, which is what the content
	 * declaration states for the whole order.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order_Item_Product $item     Order item.
	 * @param int|null               $quantity Units in scope, resolved from the scope when null.
	 *
	 * @return float
	 */
	public function getScopedLineSubtotal( $item, $quantity = null ) {
		$quantity = $quantity === null ? $this->getScopedQuantity( $item ) : $quantity;

		$subtotal = (float) $item->get_subtotal();
		$item_quantity = (int) $item->get_quantity();

		if ( $item_quantity < 1 || $quantity === $item_quantity ) {
			return $subtotal;
		}

		return ( $subtotal / $item_quantity ) * $quantity;
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

	/**
	 * Subtotal of the products in scope.
	 *
	 * A scope that names the items — a vendor of a marketplace order, or a
	 * volume of a split shipment — declares only the value of those units, so
	 * the declarations of the boxes add up to the order instead of repeating
	 * it on each one.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 Sums only the items in scope.
	 *
	 * @return float
	 */
	public function getSubtotal() {
		if ( ! $this->scope['item_ids'] ) {
			return $this->order->get_subtotal();
		}

		$subtotal = 0;

		foreach ( $this->getItems() as $item ) {
			$subtotal += $this->getScopedLineSubtotal( $item );
		}

		return $subtotal;
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
		 * Scoped to a vendor the frozen value wins: the method instance resolves
		 * its service against the store wide contract flag, which would quote a
		 * vendor on the public table with the contract code, and vice versa. It
		 * is also the only value that is per shipping line.
		 *
		 * Naming a shipping line is not enough on its own. Without a vendor there
		 * is no contract mismatch to protect against, and preferring the frozen
		 * code would send an order placed before the store connected its contract
		 * to Correios with a retail service the postcard does not accept.
		 */
		if ( $this->scope['vendor_id'] && $shipping_product_code ) {
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
	 * 		shipping_product_code: string|null,
	 * 		volume_index: int,
	 * 		volume_total: int,
	 * 		packaging_name: string
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
			'carrier' => null,
			'volume_index' => 0,
			'volume_total' => 0,
			'packaging_name' => '',
		];
	}

	public function getShippingItemsData() {
		return $this->shipping_items;
	}

	/**
	 * Whether any shipping line of the order is handled by this plugin.
	 *
	 * A line is ours when the base plugin recognises it as Correios — its own
	 * method, or a third party method linked to a Correios service — or when a
	 * carrier extension such as PEX or JadLog claims it through
	 * `infixs_correios_automatico_shipping_item_carrier`. A line shipped by an
	 * unrelated plugin answers neither, so an order served only by those is not
	 * ours to talk about.
	 *
	 * @since 1.10.4
	 *
	 * @return bool
	 */
	public function hasInfixsShippingMethod() {
		foreach ( $this->shipping_items as $shipping_item ) {
			if ( ! empty( $shipping_item['is_correios'] ) || ! empty( $shipping_item['carrier'] ) ) {
				return true;
			}
		}

		return false;
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

	/**
	 * Weight and measurements of the shipment as a whole.
	 *
	 * A shipment broken into volumes has the weight spread over its boxes, so
	 * anything that answers for the whole order — a return, for instance —
	 * would understate it by reading a single line. The boxes add up in weight
	 * and the largest one answers for the measurements.
	 *
	 * @since 1.11.0
	 *
	 * @return array{ width: float, height: float, lenght: float, weight: float }
	 */
	public function getShipmentDimensions() {
		$dimensions = [ 'width' => 0, 'height' => 0, 'lenght' => 0, 'weight' => 0 ];
		$lines = 0;

		foreach ( $this->shipping_items as $shipping_item ) {
			if ( ! $shipping_item['is_correios'] ) {
				continue;
			}

			$lines++;
			$dimensions['weight'] += (float) $shipping_item['weight'];
			$dimensions['width'] = max( $dimensions['width'], (float) $shipping_item['width'] );
			$dimensions['height'] = max( $dimensions['height'], (float) $shipping_item['height'] );
			$dimensions['lenght'] = max( $dimensions['lenght'], (float) $shipping_item['lenght'] );
		}

		if ( $lines < 2 || $dimensions['weight'] <= 0 ) {
			return $this->getShippingPackageDimensions();
		}

		return $dimensions;
	}

	/**
	 * Build the invoice metas the dashboard fills the prepost form with.
	 *
	 * The NF-e of the order, when there is one, takes the place of the metas,
	 * under the same keys; without it the metas go as they were stored.
	 *
	 * @since 1.11.0
	 *
	 * @param array|null $invoice NF-e of the order, as the order invoice resolver answers it.
	 *
	 * @return array<int, array{key: string, value: string}>
	 */
	private function getInvoiceMetaData( $invoice ) {
		$values = [
			OrderInvoiceResolver::NUMBER_META_KEY => $invoice ? $invoice['invoice_number'] : $this->order->get_meta( OrderInvoiceResolver::NUMBER_META_KEY, true ),
			OrderInvoiceResolver::KEY_META_KEY => $invoice ? $invoice['invoice_key'] : $this->order->get_meta( OrderInvoiceResolver::KEY_META_KEY, true ),
		];

		$meta_data = [];

		foreach ( $values as $key => $value ) {
			if ( '' !== $value && false !== $value && null !== $value ) {
				$meta_data[] = [ 'key' => $key, 'value' => $value ];
			}
		}

		return $meta_data;
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

		$shipping_metadata = $this->getFirstShippingItemData();

		$prepost_errors = $this->order->get_meta( PrepostService::PREPOST_ERRORS_META_KEY );
		$prepost_errors = is_array( $prepost_errors ) ? array_values( $prepost_errors ) : [];

		$invoice = Container::orderInvoiceResolver()->resolve( $this->order, [
			'for' => 'display',
			'vendor_id' => $this->getScopedVendorId(),
		] );

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
					'dangerous_product' => $this->hasDangerousProduct(),
				],
			],
			'printed' => $this->order->get_meta( '_infixs_correios_automatico_printed', true ) ?: null,
			'email_tracking_sent' => $this->order->get_meta( '_infixs_correios_automatico_email_tracking_sent', true ) ?: null,
			'email_preparing_sent' => $this->order->get_meta( '_infixs_correios_automatico_email_preparing_sent', true ) ?: null,
			'customer' => $customer,
			'created_at' => $this->order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'meta_data' => $this->getInvoiceMetaData( $invoice ),
			'invoice' => $invoice,
			'waiting_invoice' => Container::orderInvoiceResolver()->isWaiting( $this->order ),
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
					'carrier' => ! empty( $tracking_code['carrier'] ) ? $tracking_code['carrier'] : 'correios',
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
