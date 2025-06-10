<?php

namespace Infixs\CorreiosAutomatico\Entities;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Shipping\CorreiosShippingMethod;
use Infixs\CorreiosAutomatico\Models\Prepost;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
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
	 * Order constructor.
	 * 
	 * @param \WC_Order $order
	 */
	public function __construct( $order ) {
		$this->order = $order;

		$this->initializeShippingItems();
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
			if ( ! $item instanceof \WC_Order_Item_Shipping || $item->get_method_id() !== 'infixs-correios-automatico' ) {
				$this->shipping_items[] = [ 
					'instance_id' => $item->get_instance_id(),
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
				'instance_id' => $item->get_instance_id(),
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
			if ( strlen( $address_number ) <= 0 ) {
				$address_number = TextHelper::extractAddressNumber( $address );
			}

			return new Address(
				Sanitizer::numeric_text( $this->order->get_shipping_postcode() ),
				$address,
				$address_number,
				$this->order->get_meta( '_shipping_neighborhood' ),
				$this->order->get_shipping_city(),
				$this->order->get_shipping_state(),
				$this->order->get_shipping_address_2(),
			);
		} else {
			$address = $this->order->get_billing_address_1();
			$address_number = $this->order->get_meta( '_billing_number' );
			if ( strlen( $address_number ) <= 0 ) {
				$address_number = TextHelper::extractAddressNumber( $address );
			}

			return new Address(
				Sanitizer::numeric_text( $this->order->get_billing_postcode() ),
				$address,
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
		return TrackingCode::where( 'order_id', $this->order->get_id() )->get();
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
		return $this->order->get_shipping_total();
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
		$first_name = empty( $this->order->get_shipping_first_name() ) ? $this->order->get_billing_first_name() : $this->order->get_shipping_first_name();
		$last_name = empty( $this->order->get_shipping_last_name() ) ? $this->order->get_billing_last_name() : $this->order->get_shipping_last_name();
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
		return $this->order->get_items();
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
	 * @return CorreiosShippingMethod|false
	 */
	public function getShippingMethod() {
		foreach ( $this->order->get_shipping_methods() as $shipping_method ) {
			if ( strpos( $shipping_method->get_method_id(), 'infixs-correios-automatico' ) === 0 ) {
				$instance_id = $shipping_method->get_instance_id();
				return \WC_Shipping_Zones::get_shipping_method( $instance_id );
			}
		}
		return false;
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
		$shipping_method = $this->getShippingMethod();
		if ( $shipping_method ) {
			return $shipping_method->get_product_code();
		}

		$first_shipping_item = $this->getFirstShippingItemData();
		$shipping_product_code = $first_shipping_item['shipping_product_code'];

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

	public function toArray() {
		$address = $this->getAddress()->toArray();
		$customer = $this->getCustomer()->toArray();
		$customer['id'] = $this->order->get_customer_id();
		$customer['address'] = $address;

		$items = array_map( function ($item) {
			return [ 
				'id' => $item->get_id(),
				'name' => $item->get_name(),
				'quantity' => intval( $item->get_quantity() ),
				'price' => NumberHelper::to100( $item->get_total() ),
			];
		}, $this->order->get_items() );

		$items = array_values( $items );

		$shipping_product_code = $this->getShippingProductCode();

		$shipping_metadata = $this->getFirstShippingItemData();

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
			],
			'printed' => $this->order->get_meta( '_infixs_correios_automatico_printed', true ) ?: null,
			'email_tracking_sent' => $this->order->get_meta( '_infixs_correios_automatico_email_tracking_sent', true ) ?: null,
			'email_preparing_sent' => $this->order->get_meta( '_infixs_correios_automatico_email_preparing_sent', true ) ?: null,
			'customer' => $customer,
			'created_at' => $this->order->get_date_created()->date( 'Y-m-d H:i:s' ),
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
			$data['tracking_codes'] = array_map( function ($tracking_code) {
				return [ 
					'id' => $tracking_code['id'],
					'code' => $tracking_code['code']
				];
			}, $tracking_codes->toArray() );
		}

		$data = apply_filters( 'infixs_correios_automatico_order_data', $data, $this->order );

		return $data;
	}
}