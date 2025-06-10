<?php

namespace Infixs\CorreiosAutomatico\Services\Correios\Includes;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\ObjectFormatCode;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;
/**
 * Prepost class.
 * 
 * @since 1.0.0
 */
class Prepost {

	/**
	 * ID Correios
	 * 
	 * @since 1.0.0
	 * 
	 * @var Person
	 */
	private $id;

	/**
	 * Sender (Required)
	 * 
	 * @since 1.0.0
	 * 
	 * @var Person
	 */
	private $sender;

	/**
	 * Recipient (Required)
	 * 
	 * @since 1.0.0
	 * 
	 * @var Person
	 */
	private $recipient;

	/**
	 * Service code (Required)
	 * 
	 * use enum DeliveryServiceCode  - maxLength: 8
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $service_code;

	/**
	 * Object format code (Required)
	 * 
	 * Formats: 1 - Letter, 2 - Package; 3 - Cilindrical/Roll
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $object_format_code = 2;

	/**
	 * Confirm non prohibited object (Required)
	 * 
	 * @since 1.0.0
	 * 
	 * @var int
	 */
	private $confirm_non_prohibited = 1;


	/**
	 * Additional service
	 * 
	 * @since 1.0.0
	 * 
	 * @var array{
	 * 			array{
	 * 				code: string,
	 * 				declaredValue: string
	 * 			}
	 * }
	 */
	private $addicional_service = [];

	/**
	 * Sets the sender.
	 *
	 * @param array{
	 *		code: string,
	 * 		declaredValue: string
	 * } $service
	 * 
	 * @since 1.0.0
	 */

	/**
	 * Package 
	 * 
	 * @since 1.0.0
	 * 
	 * @var Package
	 */
	private $package;

	/**
	 * Payment type
	 * 
	 * Payment method: 1 - cash, 2 - to invoice, 3 - cash and to invoice
	 * 
	 * @since 1.0.0
	 * 
	 * @var int
	 */
	private $payment_type = 2;

	/**
	 * Height in cm
	 * 
	 * Max length 8 characters
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $height;

	/**
	 * Width in cm
	 * 
	 * Max length 8 characters
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $width;

	/**
	 * Length in cm
	 * 
	 * Max length 8 characters
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $length;

	/**
	 * Weight in KG
	 * 
	 * Max length 10 characters
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $weight;

	/**
	 * Content items
	 * 
	 * @since 1.0.0
	 * 
	 * @var array{
	 * 			array{
	 *  			ncm: string,
	 * 				content: string,
	 * 				quantity: string,
	 * 				total: string,
	 * 			}
	 * }
	 */
	private $content_items = [];

	/**
	 * Invoice number (Nota fiscal)
	 * 
	 * Optional
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $invoice_number = '';

	/**
	 * Invoice key (Chave da nota fiscal)
	 * 
	 * Optional or length 44 characters
	 * 
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	private $invoice_key = '';

	private $order_id;


	/**
	 * Tax Payment Method (Required When is packet)
	 * 
	 * @var string "DDU" | "DDP" | "PRC"
	 * 
	 * DDU: pagamento posterior. O destinatário paga os impostos, após o desembaraço aduaneiro brasileiro.
	 * DDP: Antecipação de tributos. O remetente envia os valores referentes aos tributos de forma antecipada.
	 * PRC: Programa Remessa Conforme. Pagamento adiantado conforme novo regramento. Os participantes ativos no programa deverão recolher e informar os valores de Imposto de Importação e ICMS provisionados antecipadamente.
	 */
	private $tax_payment_method = 'PRC';

	/**
	 * Currency
	 * 
	 * @var string
	 * 
	 * USD: Dólar Americano, utilizado para DDU e DDP
	 * BRL or USD - Real Brasileiro, utilizado para PRC (somente para participantes do Programa Remessa Conforme”). Dólar Americano também poderá ser utilizado para os novos Regimes Tributários a partir de 01/08/2024, incluindo o PRC.
	 */
	private $currency = 'BRL';


	/**
	 * Freight paid value
	 * 
	 * (na mesma moeda usada em todos os demais campos – se for PRC, somente BRL)
	 * 
	 * min: 0.01
	 * max: 999999
	 * 
	 * @var string
	 */
	private $freight_paid_value = 0.01;

	/**
	 * Constructor.
	 *
	 * @param string $order_id
	 * @param Person $sender
	 * @param Person $recipient
	 * @param string $service_code
	 * @param string $object_format_code
	 * 
	 * @since 1.0.0
	 */
	public function __construct( $order_id, $sender, $recipient, $service_code, $object_format_code = 2 ) {
		$this->id = Config::string( 'auth.user_name' );
		$this->order_id = $order_id;
		$this->sender = $sender;
		$this->recipient = $recipient;
		$this->service_code = $service_code;
		$this->object_format_code = $object_format_code;
	}

	/**
	 * Sets the package.
	 *
	 * @param Package $package
	 * 
	 * @since 1.0.0
	 */
	public function setPackage( $package ) {
		$this->package = $package;
		$data = $package->get_data();
		$this->height = $data['height'];
		$this->width = $data['width'];
		$this->length = $data['length'];
		$this->weight = $data['weight'];

		$this->setItemsFromPackage( $package );
	}

	public function setItemsFromPackage( $package ) {
		$order = wc_get_order( $this->order_id );

		/** @var \WC_Order_Item_Product $item */
		foreach ( $order->get_items() as $item ) {
			$product_price = floatval( $item->get_subtotal() );
			$product = $item->get_product();

			if ( $product && ! $product->needs_shipping() ) {
				continue;
			}

			if ( $product_price <= 0 ) {
				$product_price = $product ? floatval( $product->get_price() ) : 1;

				if ( $product_price <= 0 ) {
					$product_price = 1;
				}
			}

			$this->content_items[] = [ 
				'ncm' => $product ? $product->get_meta( '_infixs_correios_automatico_ncm' ) : '',
				'content' => $item->get_name(),
				'quantity' => strval( $item->get_quantity() ),
				'total' => strval( $product_price )
			];
		}
	}

	public function getItemsTotal() {
		$total = 0;
		foreach ( $this->content_items as $item ) {
			$total += floatval( $item['total'] );
		}
		return $total;
	}

	/**
	 * Add additional service.
	 *
	 * @param array{
	 *		code: string,
	 * 		declaredValue: string
	 * } $service
	 * 
	 * @since 1.0.0
	 */
	public function addAdditionalService( $service ) {
		$this->addicional_service[] = [ 
			'code' => $service['code'],
			'declaredValue' => $service['declaredValue']
		];
	}


	public function getData() {
		$addicional_service = [];
		$content_items = [];

		foreach ( $this->addicional_service as $service ) {
			$addicional_service[] = [ 
				"codigoServicoAdicional" => $service['code'],
				"valorDeclarado" => $service['declaredValue']
			];
		}

		foreach ( $this->content_items as $content ) {
			$content_items[] = [ 
				"conteudo" => $content['content'],
				"quantidade" => $content['quantity'],
				"valor" => $content['total']
			];
		}

		$data = [ 
			"idCorreios" => $this->id,
			"remetente" => $this->sender->getData(),
			"destinatario" => $this->recipient->getData(),
			"codigoServico" => $this->service_code,
			"listaServicoAdicional" => $addicional_service,
			"cienteObjetoNaoProibido" => $this->confirm_non_prohibited,
			"codigoFormatoObjetoInformado" => $this->object_format_code,
			"modalidadePagamento" => $this->payment_type,
			"pesoInformado" => Sanitizer::integer_text( $this->getWeight( 'g' ) ),
			"logisticaReversa" => "N",
			"itensDeclaracaoConteudo" => $content_items,
			"solicitarColeta" => "N"
		];

		if ( $this->getObjectFormatCode() === ObjectFormatCode::PACOTE ) {
			$data["alturaInformada"] = Sanitizer::integer_text( $this->getHeight() );
			$data["larguraInformada"] = Sanitizer::integer_text( $this->getWidth() );
			$data["comprimentoInformado"] = Sanitizer::integer_text( $this->getLength() );
		}

		if ( in_array( $this->service_code, [ 
			DeliveryServiceCode::CARTA_COML_REG_B1_CHANC_ETIQ
		] ) ) {
			$data['dataPrevistaPostagem'] = date( 'Y-m-d', strtotime( '+1 day' ) );
		}


		return $data;
	}

	/**
	 * Get the packet data.
	 * 
	 * @since 1.1.7
	 * 
	 * @return array
	 */
	public function getPacketData() {
		return apply_filters( 'infixs_correios_automatico_prepost_get_packet_data', [], $this );
	}

	/**
	 * Get the service code.
	 * 
	 * @since 1.0.0
	 * 
	 * @return string 
	 * @see DeliveryServiceCode
	 */
	public function getServiceCode() {
		return $this->service_code;
	}

	public function isPacket() {
		return in_array( $this->getServiceCode(), [ 
			DeliveryServiceCode::PACKET_EXPRESS,
			DeliveryServiceCode::PACKET_STANDARD
		] );
	}

	public function setInvoiceNumber( $invoice_number ) {
		$this->invoice_number = $invoice_number;
	}

	public function setInvoiceKey( $invoice_key ) {
		$this->invoice_key = $invoice_key;
	}

	public function getOrderId() {
		return $this->order_id;
	}

	public function getSender() {
		return $this->sender;
	}

	public function getRecipient() {
		return $this->recipient;
	}

	/**
	 * Get the weight
	 * 
	 * Default is KG
	 * 
	 * @param string $unit  'g', 'kg', 'lbs', 'oz'.
	 * 
	 * @return float
	 */
	public function getWeight( $unit = 'kg' ) {
		$weight = $this->weight > 0 ? $this->weight : 0.1;
		return wc_get_weight( $weight, $unit, 'kg' );
	}

	/**
	 * Get the height.
	 * 
	 * @since 1.1.7
	 * 
	 * @return string
	 */
	public function getHeight() {
		return $this->height;
	}

	/**
	 * Get the width.
	 * 
	 * @since 1.1.7
	 * 
	 * @return string
	 */
	public function getWidth() {
		return $this->width;
	}

	/**
	 * Get the length.
	 * 
	 * @since 1.1.7
	 * 
	 * @return string
	 */
	public function getLength() {
		return $this->length;
	}

	/**
	 * Tax Payment Method (Required When is packet)
	 * 
	 * @return string "DDU" | "DDP" | "PRC"
	 */
	public function getTaxPaymentMethod() {
		return $this->tax_payment_method;
	}

	/**
	 * Tax Payment Method (Required When is packet)
	 * 
	 * @param string $tax_payment_method "DDU" | "DDP" | "PRC"
	 */
	public function setTaxPaymentMethod( $tax_payment_method ) {
		$this->tax_payment_method = $tax_payment_method;
	}

	/**
	 * Currency
	 * 
	 * @return string "USD" | "BRL"
	 */
	public function getCurrency() {
		return $this->currency;
	}

	/**
	 * Currency
	 * 
	 * @param string $currency "USD" | "BRL"
	 */
	public function setCurrency( $currency ) {
		$this->currency = $currency;
	}

	/**
	 * Freight paid value
	 * 
	 * @return string
	 */
	public function getFreightPaidValue() {
		return $this->freight_paid_value;
	}

	/**
	 * Freight paid value
	 * 
	 * @param string $freight_paid_value
	 */
	public function setFreightPaidValue( $freight_paid_value ) {
		$this->freight_paid_value = $freight_paid_value;
	}

	/**
	 * Get the content items.
	 * 
	 * @since 1.1.7
	 * 
	 * @return array{
	 * 			array{
	 *  			ncm: string,
	 * 				content: string,
	 * 				quantity: string,
	 * 				total: string,
	 * 			}
	 * }
	 */
	public function getItems() {
		return $this->content_items;
	}

	public function setObjectFormatCode( $object_format_code ) {
		$this->object_format_code = $object_format_code;
	}

	public function getObjectFormatCode() {
		return $this->object_format_code;
	}

	/**
	 * Set the length in cm
	 * 
	 * @param string $length
	 */
	public function setLength( $length ) {
		$this->length = $length;
	}

	/**
	 * Set the width in cm
	 * 
	 * @param string $width
	 */
	public function setWidth( $width ) {
		$this->width = $width;
	}

	/**
	 * Set the height in cm
	 * 
	 * @param string $height
	 */
	public function setHeight( $height ) {
		$this->height = $height;
	}

	/**
	 * Set the weight in KG
	 * 
	 * @param string $weight
	 */
	public function setWeight( $weight ) {
		$this->weight = $weight;
	}
}