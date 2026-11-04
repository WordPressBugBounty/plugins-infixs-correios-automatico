<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Config\VendorSettings;
use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Core\Support\Plugin;
use Infixs\CorreiosAutomatico\Database\Migration;
use Infixs\CorreiosAutomatico\Entities\Order;
use Infixs\CorreiosAutomatico\Models\Prepost as PrepostModel;
use Infixs\CorreiosAutomatico\Prepaid\ContractMode;
use Infixs\CorreiosAutomatico\Repositories\PrepostRepository;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosService;
use Infixs\CorreiosAutomatico\Services\Correios\DceRejection;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\AddicionalServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\DeliveryServiceCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\ObjectFormatCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\PaymentTypeCode;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\PrepostStatusCode;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Address;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Person;
use Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost;
use Infixs\CorreiosAutomatico\Utils\AccessKey;
use Infixs\CorreiosAutomatico\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

class PrepostService {

	/**
	 * Meta key that stores the list of prepost errors on an order.
	 *
	 * @since 1.8.0
	 *
	 * @var string
	 */
	const PREPOST_ERRORS_META_KEY = '_infixs_correios_automatico_prepost_errors';

	/**
	 * Shipping line meta with the prepost created for that volume.
	 *
	 * @var string
	 */
	const PREPOST_ITEM_META_KEY = '_infixs_correios_automatico_prepost_id';

	/**
	 * Order meta with the reverse preposts of the order, the newest per shipping line.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const REVERSE_PREPOSTS_META_KEY = '_infixs_correios_automatico_reverse_preposts';

	/**
	 * Transient prefix that holds the DACE read of a prepost after a failure.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const DCE_RETRY_TRANSIENT_PREFIX = 'infixs_ca_dce_retry_';

	/**
	 * Transient that records the DC-e columns are already in the table.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const DCE_COLUMNS_TRANSIENT = 'infixs_ca_dce_columns';

	/**
	 * Transient that records the column of the contract of a prepost is already in the table.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const CONTRACT_SOURCE_COLUMN_TRANSIENT = 'infixs_ca_contract_source_column';

	/**
	 * Transient that records the column of the reason a prepost was cancelled is already in the table.
	 *
	 * @since 1.14.0
	 *
	 * @var string
	 */
	const CANCEL_REASON_COLUMN_TRANSIENT = 'infixs_ca_cancel_reason_column';

	/**
	 * How long a prepost is left alone after a failed DACE read, in seconds.
	 *
	 * @since 1.11.0
	 *
	 * @var int
	 */
	const DCE_RETRY_HOLD = 300;

	/**
	 * How many preposts the DC-e backfill reads in a single request.
	 *
	 * Each one costs a call to the Correios, and that call waits up to twenty
	 * seconds, so the batch is what fits in the execution time of a normal
	 * PHP request instead of what fits on the print screen.
	 *
	 * @since 1.11.0
	 *
	 * @var int
	 */
	const DCE_REFRESH_BATCH_LIMIT = 5;

	/**
	 * HTTP statuses of a DACE answer that will not change on the next print.
	 *
	 * The Correios refuse the DACE of an object that has no DC-e with a
	 * business error, and that verdict is final: asking again tomorrow costs
	 * another call for the same answer. Authentication (401/403), throttling
	 * (429) and server errors say nothing about the object itself, so those
	 * are held for a few minutes and tried again.
	 *
	 * @since 1.11.0
	 *
	 * @var int[]
	 */
	const DCE_FINAL_ERROR_STATUSES = [ 400, 404, 409, 422 ];

	/**
	 * Reason of a failed DC-e read: the prepost is not on the store.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const DCE_REASON_NOT_FOUND = 'not_found';

	/**
	 * Reason of a failed DC-e read: the prepost shows no sign of a DC-e.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const DCE_REASON_NO_DCE = 'no_dce';

	/**
	 * Reason of a failed DC-e read: the access key is not to be had.
	 *
	 * The object carries a DC-e, and the Correios still refuse its DACE: the
	 * key of a document already closed is never reissued, so asking again
	 * answers the same thing.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const DCE_REASON_UNAVAILABLE = 'dce_unavailable';

	/**
	 * Reason of a failed DC-e read: the DC-e was not authorised yet.
	 *
	 * The read worked — or there was nothing to read, because the DC-e is
	 * still pendente — and the SEFAZ has not authorised the document, so it
	 * has no access key to print. The authorisation arrives on its own, so
	 * printing later is worth it.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const DCE_REASON_NOT_AUTHORIZED = 'not_authorized';

	/**
	 * Reason of a failed DC-e read: it is worth asking again later.
	 *
	 * @since 1.11.0
	 *
	 * @var string
	 */
	const DCE_REASON_TEMPORARY = 'temporary';

	/**
	 * Prepost repository.
	 * 
	 * @since 1.0.0
	 * 
	 * @var PrepostRepository
	 */
	protected $prepostRepository;

	/**
	 * Correios service.
	 * 
	 * @since 1.1.3
	 * 
	 * @var CorreiosService
	 */
	protected $correiosService;

	/**
	 * Create a new instance of the service.
	 * 
	 * @since 1.0.0
	 * 
	 * @param PrepostRepository $prepostRepository Prepost repository.
	 * @param CorreiosService $correiosService Correios service.
	 */
	public function __construct( PrepostRepository $prepostRepository, CorreiosService $correiosService ) {
		$this->prepostRepository = $prepostRepository;
		$this->correiosService = $correiosService;
		add_action( 'infixs_correios_automatico_prepost_sync_schedule', [ $this, 'sync_schedule' ], 10 );
	}

	/**
	 * Build the store sender used on preposts.
	 *
	 * @since 1.8.1
	 *
	 * @param int $order_id Order ID the prepost belongs to.
	 *
	 * @return Person
	 */
	protected function buildSender( $order_id, $vendor_id = 0 ) {
		$sender = new Person(
			Config::string( 'sender.name' ),
			new Address(
				Sanitizer::numeric_text( Config::string( 'sender.address_postalcode' ) ),
				Config::string( 'sender.address_street' ),
				Config::string( 'sender.address_number' ),
				Config::string( 'sender.address_complement' ),
				Config::string( 'sender.address_neighborhood' ),
				Config::string( 'sender.address_city' ),
				Config::string( 'sender.address_state' ),
				Config::string( 'sender.address_country' )
			),
			Config::string( 'sender.document' ),
			Config::string( 'sender.phone' ),
			Config::string( 'sender.celphone' ),
			Config::string( 'sender.email' )
		);

		/**
		 * Filters the sender used on the prepost.
		 *
		 * Marketplace extensions use this to replace the store sender with the
		 * vendor's own store address.
		 *
		 * @since 1.8.1
		 *
		 * @param Person $sender
		 * @param int $order_id
		 * @param int $vendor_id Vendor the prepost belongs to, zero for the store. @since 1.8.2
		 */
		return apply_filters( 'infixs_correios_automatico_prepost_sender', $sender, $order_id, $vendor_id );
	}

	/**
	 * Check the store address against what Correios demands of it.
	 *
	 * Same rule already applied to the customer address: the neighborhood is
	 * only mandatory on a postcode ending in `000`. Answering here keeps a
	 * store — or a marketplace vendor — from having to read a raw Correios
	 * rejection to find out which field is missing. Deliberately not asked when
	 * the contract is registered: Correios does not need the neighborhood to
	 * authenticate one, only to move a parcel.
	 *
	 * @since 1.10.1
	 *
	 * @param Person $store    Store side of the prepost, sender on a shipment and recipient on a return.
	 * @param int    $order_id Order ID, for the log.
	 *
	 * @return \WP_Error|null Null when the address is usable.
	 */
	protected function validateStoreAddress( $store, $order_id ) {
		$address = $store->getAddress();

		if ( ! $address ) {
			return null;
		}

		$postal_code = Sanitizer::numeric_text( trim( (string) $address->getPostalCode() ) );

		if ( ! preg_match( '/000$/', $postal_code ) || trim( (string) $address->getNeighborhood() ) !== '' ) {
			return null;
		}

		Log::notice( 'Bairro do endereço da loja é obrigatório para CEP finalizado em 000.', [
			'order_id' => $order_id,
			'postal_code' => $postal_code,
		] );

		return new \WP_Error(
			'invalid_store_neighborhood',
			'O campo Bairro do endereço da loja é obrigatório quando o CEP termina com 000. Preencha o bairro e tente novamente.',
			[ 'status' => 400 ]
		);
	}

	/**
	 * Resolve the Correios user a prepost is declared under.
	 *
	 * Mirrors the rule that decides which client signs the request, so the
	 * `idCorreios` of the payload never contradicts the credential in the
	 * header.
	 *
	 * @see \Infixs\CorreiosAutomatico\Services\Correios\VendorApiFactory::forVendor()
	 *
	 * @since 1.10.1
	 *
	 * @param int $vendor_id Vendor the prepost belongs to, zero for the store.
	 *
	 * @return string
	 */
	protected function resolveCorreiosId( $vendor_id ) {
		if ( $vendor_id && VendorSettings::hasActiveContract( $vendor_id ) ) {
			return (string) VendorSettings::get( $vendor_id )->auth->user_name;
		}

		return Config::string( 'auth.user_name' );
	}

	/**
	 * Check the store document Correios demands on every prepost.
	 *
	 * Confirmed against the live API: a sender without `cpfCnpj` is refused
	 * with HTTP 400 and an empty body (`{"msgs":["null"]}`), which reaches the
	 * store as an unreadable error. A marketplace vendor is the common way to
	 * get here — the document may have no home on the vendor panel, and the
	 * store contract is only lent when the marketplace says so.
	 *
	 * @since 1.10.1
	 *
	 * @param Person $store    Store side of the prepost.
	 * @param int    $order_id Order ID, for the log.
	 * @param int    $vendor_id Vendor the prepost belongs to, zero for the store.
	 *
	 * @return \WP_Error|null Null when the document is usable.
	 */
	protected function validateStoreDocument( $store, $order_id, $vendor_id = 0 ) {
		if ( Sanitizer::numeric_text( $store->getCpfCnpj() ) !== '' ) {
			return null;
		}

		Log::notice( 'Documento do remetente ausente ao criar a prépostagem.', [
			'order_id' => $order_id,
			'vendor_id' => $vendor_id,
		] );

		if ( $vendor_id ) {
			return new \WP_Error(
				'missing_store_document',
				'O CNPJ/CPF do remetente é obrigatório nos Correios e o vendedor deste pedido está sem documento. Cadastre o contrato próprio do vendedor, preencha o documento dele ou ative o uso do contrato da loja pelos vendedores.',
				[ 'status' => 400 ]
			);
		}

		return new \WP_Error(
			'missing_store_document',
			'O CNPJ/CPF do remetente é obrigatório nos Correios. Preencha o documento do remetente nas configurações e tente novamente.',
			[ 'status' => 400 ]
		);
	}

	/**
	 * Record on the shipping line that its volume was already preposted.
	 *
	 * The order wide meta answers for the order as a whole and cannot tell a
	 * shipment of two volumes apart, so an automation driven by it would stop
	 * after the first one.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order            Order the prepost belongs to.
	 * @param int       $shipping_item_id Shipping line of the volume, zero when the whole order was preposted.
	 * @param int       $prepost_id       Prepost created.
	 *
	 * @return void
	 */
	private function markShippingItemAsPreposted( $order, $shipping_item_id, $prepost_id ) {
		if ( ! $shipping_item_id ) {
			return;
		}

		$item = $order->get_item( $shipping_item_id );

		if ( ! $item ) {
			return;
		}

		$item->update_meta_data( self::PREPOST_ITEM_META_KEY, $prepost_id );
		$item->save();
	}

	/**
	 * Create prepost.
	 *
	 * Records the failure on the order when it errors, or clears the previous
	 * errors it settles when it succeeds, so the order list can flag pending
	 * problems.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 Errors are recorded and cleared per shipping line.
	 *
	 * @param int $order_id Order ID.
	 * @param array{
	 * 		invoice_number: string,
	 * 		invoice_key: string,
	 * 		shipping_item_id?: int,
	 * } $data Data.
	 *
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function createPrepost( $order_id, $data = [] ) {
		$shipping_item_id = isset( $data['shipping_item_id'] ) ? absint( $data['shipping_item_id'] ) : 0;
		$result = $this->doCreatePrepost( $order_id, $data );

		if ( is_wp_error( $result ) ) {
			$this->recordOrderPrepostError( $order_id, $result, $shipping_item_id );
		} else {
			$this->clearOrderPrepostErrors( $order_id, $shipping_item_id );
		}

		return $result;
	}

	/**
	 * Create a reverse logistics prepost (Logística Reversa).
	 *
	 * Records the failure on the order when it errors, or clears the previous
	 * errors it settles when it succeeds, so the order list can flag pending
	 * problems.
	 *
	 * @since 1.8.0
	 * @since 1.11.0 Errors are recorded and cleared per shipping line.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Optional overrides (used by the manual reverse prepost screen).
	 *
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function createReversePrepost( $order_id, $data = [] ) {
		$shipping_item_id = isset( $data['shipping_item_id'] ) ? absint( $data['shipping_item_id'] ) : 0;
		$result = $this->doCreateReversePrepost( $order_id, $data );

		if ( is_wp_error( $result ) ) {
			$this->recordOrderPrepostError( $order_id, $result, $shipping_item_id );
		} else {
			$this->clearOrderPrepostErrors( $order_id, $shipping_item_id );
		}

		return $result;
	}

	/**
	 * Record a prepost error on the order.
	 *
	 * Deduplicates by code + message + shipping line: an identical error only
	 * refreshes its date instead of piling up on every retry. Keeps at most the
	 * last 20 errors.
	 *
	 * @since 1.8.0
	 * @since 1.11.0 Records the shipping line the error belongs to.
	 *
	 * @param int              $order_id         Order ID.
	 * @param \WP_Error|string $error            Error to record.
	 * @param int              $shipping_item_id Shipping line the prepost was for, zero for the whole order.
	 *
	 * @return void
	 */
	public function recordOrderPrepostError( $order_id, $error, $shipping_item_id = 0 ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$message = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
		$code = is_wp_error( $error ) ? $error->get_error_code() : '';
		$shipping_item_id = absint( $shipping_item_id );

		if ( '' === trim( $message ) ) {
			return;
		}

		// Marca o WP_Error (devolvido ao cliente REST) como registrado na meta:
		// o front só insere o alerta otimista na linha quando esta marca existe,
		// evitando erro fantasma para falhas que não passam por aqui.
		if ( is_wp_error( $error ) ) {
			$data = $error->get_error_data();
			$data = is_array( $data ) ? $data : ( null === $data ? [] : [ 'previous_data' => $data ] );
			$data['prepost_error_recorded'] = true;
			$error->add_data( $data );
		}

		$errors = $this->getOrderPrepostErrors( $order );

		foreach ( $errors as $index => $existing ) {
			if ( isset( $existing['message'], $existing['code'] ) && $existing['message'] === $message && $existing['code'] === $code && $this->getErrorShippingItemId( $existing ) === $shipping_item_id ) {
				$errors[ $index ]['date'] = current_time( 'mysql' );
				$order->update_meta_data( self::PREPOST_ERRORS_META_KEY, array_values( $errors ) );
				$order->save();
				$this->notifyPrepostError( $order, $message, $code, $shipping_item_id );
				return;
			}
		}

		$errors[] = [
			'id' => md5( uniqid( (string) $order_id, true ) ),
			'code' => $code,
			'message' => $message,
			'shipping_item_id' => $shipping_item_id ?: null,
			'date' => current_time( 'mysql' ),
		];

		if ( count( $errors ) > 20 ) {
			$errors = array_slice( $errors, -20 );
		}

		$order->update_meta_data( self::PREPOST_ERRORS_META_KEY, array_values( $errors ) );
		$order->save();

		$this->notifyPrepostError( $order, $message, $code, $shipping_item_id );
	}

	/**
	 * Register a central notification for a prepost error.
	 *
	 * @since 1.8.0
	 * @since 1.11.0 One notification per shipping line.
	 *
	 * @param \WC_Order $order
	 * @param string    $message
	 * @param string    $code
	 * @param int       $shipping_item_id Shipping line the error belongs to, zero for the whole order.
	 *
	 * @return void
	 */
	private function notifyPrepostError( $order, $message, $code, $shipping_item_id = 0 ) {
		$order_id = $order->get_id();

		Container::notificationService()->notify(
			'prepost_error',
			'error',
			sprintf( 'Erro de pré-postagem no pedido #%s', $order->get_order_number() ),
			$message,
			[
				'order_id' => (int) $order_id,
				'order_number' => (string) $order->get_order_number(),
				'code' => (string) $code,
				'shipping_item_id' => $shipping_item_id ?: null,
				'url' => $order->get_edit_order_url(),
			],
			$this->getErrorNotificationKey( $order_id, $shipping_item_id, $code )
		);
	}

	/**
	 * Build the key that deduplicates the notification of a prepost error.
	 *
	 * The error of a shipping line is keyed under the prefix of that line, so
	 * settling one volume does not resolve the notification of another.
	 *
	 * @since 1.11.0
	 *
	 * @param int    $order_id         Order ID.
	 * @param int    $shipping_item_id Shipping line of the error, zero for the whole order.
	 * @param string $code             Error code, empty for the prefix of the line.
	 *
	 * @return string
	 */
	private function getErrorNotificationKey( $order_id, $shipping_item_id, $code ) {
		if ( ! $shipping_item_id ) {
			return sprintf( 'prepost_error:%d:%s', $order_id, $code );
		}

		return sprintf( 'prepost_error:%d:%d:%s', $order_id, $shipping_item_id, $code );
	}

	/**
	 * Get the shipping line a recorded prepost error belongs to.
	 *
	 * Errors recorded before 1.11.0 name no line.
	 *
	 * @since 1.11.0
	 *
	 * @param array $error Recorded error.
	 *
	 * @return int Zero when the error belongs to the whole order.
	 */
	private function getErrorShippingItemId( $error ) {
		return isset( $error['shipping_item_id'] ) ? absint( $error['shipping_item_id'] ) : 0;
	}

	/**
	 * Get the prepost errors recorded on an order.
	 *
	 * @since 1.8.0
	 *
	 * @param int|\WC_Order $order Order or order ID.
	 *
	 * @return array<int, array{id: string, code: string, message: string, shipping_item_id?: int|null, date: string}>
	 */
	public function getOrderPrepostErrors( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return [];
		}

		$errors = $order->get_meta( self::PREPOST_ERRORS_META_KEY );

		return is_array( $errors ) ? array_values( $errors ) : [];
	}

	/**
	 * Dismiss a single prepost error from an order.
	 *
	 * @since 1.8.0
	 *
	 * @param int    $order_id Order ID.
	 * @param string $error_id Error ID to dismiss.
	 *
	 * @return bool|\WP_Error
	 */
	public function dismissOrderPrepostError( $order_id, $error_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'invalid_order', 'Pedido inválido.', [ 'status' => 404 ] );
		}

		$errors = $this->getOrderPrepostErrors( $order );

		$filtered = array_values( array_filter( $errors, function ( $item ) use ( $error_id ) {
			return ! isset( $item['id'] ) || $item['id'] !== $error_id;
		} ) );

		if ( count( $filtered ) === count( $errors ) ) {
			return new \WP_Error( 'prepost_error_not_found', 'Erro de pré-postagem não encontrado.', [ 'status' => 404 ] );
		}

		if ( empty( $filtered ) ) {
			$order->delete_meta_data( self::PREPOST_ERRORS_META_KEY );
		} else {
			$order->update_meta_data( self::PREPOST_ERRORS_META_KEY, $filtered );
		}

		$order->save();

		return true;
	}

	/**
	 * Clear the prepost errors recorded on an order.
	 *
	 * Without a shipping line every error goes, as before. A success on a line
	 * only settles the errors of that line, so a volume posted after another
	 * one failed does not hide the failure. An error that names no line was
	 * recorded for the whole order, and only goes once every Correios line of
	 * the order is preposted.
	 *
	 * @since 1.8.0
	 * @since 1.11.0 Accepts the shipping line that was preposted.
	 *
	 * @param int $order_id         Order ID.
	 * @param int $shipping_item_id Shipping line that was preposted, zero for the whole order.
	 *
	 * @return void
	 */
	public function clearOrderPrepostErrors( $order_id, $shipping_item_id = 0 ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( absint( $shipping_item_id ) ) {
			$this->clearShippingItemPrepostErrors( $order, absint( $shipping_item_id ) );
			return;
		}

		Container::notificationService()->resolveByPrefix( sprintf( 'prepost_error:%d:', $order->get_id() ) );

		$existing = $order->get_meta( self::PREPOST_ERRORS_META_KEY );

		if ( empty( $existing ) ) {
			return;
		}

		$order->delete_meta_data( self::PREPOST_ERRORS_META_KEY );
		$order->save();
	}

	/**
	 * Clear the prepost errors settled by a success on one shipping line.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order            Order the prepost belongs to.
	 * @param int       $shipping_item_id Shipping line that was preposted.
	 *
	 * @return void
	 */
	private function clearShippingItemPrepostErrors( $order, $shipping_item_id ) {
		$order_id = $order->get_id();

		Container::notificationService()->resolveByPrefix( $this->getErrorNotificationKey( $order_id, $shipping_item_id, '' ) );

		$errors = $this->getOrderPrepostErrors( $order );

		if ( ! $errors ) {
			return;
		}

		$shipments_preposted = $this->areShipmentsPreposted( $order );
		$kept = [];

		foreach ( $errors as $error ) {
			$error_item_id = $this->getErrorShippingItemId( $error );

			if ( $error_item_id === $shipping_item_id ) {
				continue;
			}

			if ( ! $error_item_id && $shipments_preposted ) {
				Container::notificationService()->resolveByDedupeKey( $this->getErrorNotificationKey( $order_id, 0, isset( $error['code'] ) ? $error['code'] : '' ) );
				continue;
			}

			$kept[] = $error;
		}

		if ( count( $kept ) === count( $errors ) ) {
			return;
		}

		if ( $kept ) {
			$order->update_meta_data( self::PREPOST_ERRORS_META_KEY, $kept );
		} else {
			$order->delete_meta_data( self::PREPOST_ERRORS_META_KEY );
		}

		$order->save();
	}

	/**
	 * Whether every Correios shipping line of an order is preposted.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order being checked.
	 *
	 * @return bool
	 */
	private function areShipmentsPreposted( $order ) {
		$preposted = $this->getShippingItemPreposts( $order );

		foreach ( ( new Order( $order ) )->getShippingItemsData() as $shipping_item ) {
			if ( $shipping_item['is_correios'] && ! isset( $preposted[ (int) $shipping_item['item_id'] ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the prepost each shipping line of an order is posted with.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order being checked.
	 *
	 * @return array<int, int> Prepost ID keyed by shipping line.
	 */
	private function getShippingItemPreposts( $order ) {
		$preposts = [];

		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$prepost_id = (int) $item->get_meta( self::PREPOST_ITEM_META_KEY );

			if ( $prepost_id ) {
				$preposts[ (int) $item_id ] = $prepost_id;
			}
		}

		return $preposts;
	}

	/**
	 * Give a shipment back once its prepost is cancelled.
	 *
	 * The shipping line forgets the prepost, so the volume can be posted again.
	 * The order wide metas say whether the order is preposted, which stays true
	 * while another volume still is: they move to that volume, and only go when
	 * none is left. A line or an order pointing at another prepost, such as a
	 * newer one, keeps pointing at it. A reverse prepost never posted the
	 * shipment, so it only leaves the reverse preposts of the order.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order    $order   Order the prepost belongs to.
	 * @param PrepostModel $prepost Prepost cancelled.
	 *
	 * @return void
	 */
	public function releaseShipmentPrepost( $order, $prepost ) {
		if ( $this->isReversePrepost( $order, $prepost ) ) {
			$this->forgetReversePrepost( $order, $prepost );
			return;
		}

		$shipping_item_id = ! empty( $prepost->shipping_item_id ) ? (int) $prepost->shipping_item_id : 0;
		$shipping_items = $order->get_items( 'shipping' );

		if ( isset( $shipping_items[ $shipping_item_id ] ) && (int) $shipping_items[ $shipping_item_id ]->get_meta( self::PREPOST_ITEM_META_KEY ) === (int) $prepost->id ) {
			$shipping_items[ $shipping_item_id ]->delete_meta_data( self::PREPOST_ITEM_META_KEY );
			$shipping_items[ $shipping_item_id ]->save();
		}

		if ( (int) $order->get_meta( '_infixs_correios_automatico_prepost_id' ) !== (int) $prepost->id ) {
			return;
		}

		$remaining = array_diff( $this->getShippingItemPreposts( $order ), [ (int) $prepost->id ] );

		if ( $remaining ) {
			$order->update_meta_data( '_infixs_correios_automatico_prepost_created', 'yes' );
			$order->update_meta_data( '_infixs_correios_automatico_prepost_id', max( $remaining ) );
		} else {
			$order->delete_meta_data( '_infixs_correios_automatico_prepost_id' );
			$order->delete_meta_data( '_infixs_correios_automatico_prepost_created' );
		}

		$order->save();
	}

	/**
	 * Get the prepost that covers each Correios shipment of an order.
	 *
	 * A shipment is covered by the newest prepost made for its shipping line,
	 * or else by the newest one made for the whole order. Returns are left
	 * out: they bring a shipment back instead of posting it.
	 *
	 * @since 1.11.0
	 *
	 * @param Order $order Order being handled, without a scope.
	 *
	 * @return array<int, array{shipping_item: array, prepost: PrepostModel|null}>
	 */
	public function getShipmentPreposts( $order ) {
		$newest = [];

		foreach ( PrepostModel::where( 'order_id', $order->get_id() )->orderBy( 'id', 'desc' )->get() as $prepost ) {
			$shipping_item_id = (int) $prepost->shipping_item_id;

			if ( isset( $newest[ $shipping_item_id ] ) || DeliveryServiceCode::isReverseService( $prepost->service_code ) ) {
				continue;
			}

			$newest[ $shipping_item_id ] = $prepost;
		}

		$shipments = [];

		foreach ( $order->getShippingItemsPayload() as $shipping_item ) {
			if ( ! $shipping_item['is_correios'] ) {
				continue;
			}

			$line_prepost = isset( $newest[ $shipping_item['item_id'] ] ) ? $newest[ $shipping_item['item_id'] ] : null;

			$shipments[] = [
				'shipping_item' => $shipping_item,
				'prepost' => $line_prepost ? $line_prepost : ( isset( $newest[0] ) ? $newest[0] : null ),
			];
		}

		return $shipments;
	}

	/**
	 * Build and persist a prepost.
	 *
	 * A caller that says nothing about the fiscal document gets the NF-e of
	 * the order, when there is one, instead of a DC-e.
	 *
	 * @since 1.0.0
	 * @since 1.11.0 Uses the NF-e of the order when the caller gives no invoice
	 *               nor `emiteDCe`, and an NF-e key without `emiteDCe` sends no DC-e.
	 *
	 * @param int $order_id Order ID.
	 * @param array{
	 * 		invoice_number?: string,
	 * 		invoice_key?: string,
	 * 		emiteDCe?: string,
	 * } $data Data.
	 *
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	protected function doCreatePrepost( $order_id, $data = [] ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Log::notice( "Pedido inválido ao criar a pré-postagem." );
			return new \WP_Error( 'invalid_order', 'Pedido inválido ao criar a pré-postagem.', [ 'status' => 400 ] );
		}
		$ca_order = new Order( $order );

		$scope = Order::resolveScope( $ca_order, [
			'for' => 'prepost',
			'vendor_id' => isset( $data['vendor_id'] ) ? absint( $data['vendor_id'] ) : 0,
			'shipping_item_id' => isset( $data['shipping_item_id'] ) ? absint( $data['shipping_item_id'] ) : 0,
		] );

		$ca_order->applyScope( $scope );

		$vendor_id = $ca_order->getScopedVendorId();

		$shipping_method = $ca_order->getShippingMethod();

		if ( ! $shipping_method ) {
			Log::notice( "Esse pedido não tem o método de envio dos correios automático, a prepostagem não pode ser criada." );
			return new \WP_Error( 'invalid_shipping_method', 'Esse pedido não tem um método de envio dos Correios Automático. Se o pedido usa um frete nativo do WooCommerce (Frete Grátis ou Preço Fixo), vincule um serviço dos Correios nas configurações desse frete, na zona de entrega.', [ 'status' => 400 ] );
		}

		$ca_address = $ca_order->getAddress();

		$recipient_postal_code = trim( (string) $ca_address->getPostCode() );
		$recipient_postal_code_numeric = Sanitizer::numeric_text( $recipient_postal_code );
		$recipient_neighborhood = trim( (string) $ca_address->getNeighborhood() );

		if ( preg_match( '/000$/', $recipient_postal_code_numeric ) && empty( $recipient_neighborhood ) ) {
			Log::notice( 'Bairro do destinatário é obrigatório para CEP finalizado em 000.', [
				'order_id' => $order_id,
				'postal_code' => $recipient_postal_code,
			] );

			return new \WP_Error(
				'invalid_recipient_neighborhood',
				'O campo Bairro do destinatário é obrigatório quando o CEP termina com 000.',
				[ 'status' => 400 ]
			);
		}

		$recipient = new Person(
			$ca_order->getCustomerFullName(),
			new Address(
				$ca_address->getPostCode(),
				$ca_address->getStreet(),
				$ca_address->getNumber(),
				$ca_address->getComplement(),
				$ca_address->getNeighborhood(),
				$ca_address->getCity(),
				$ca_address->getState()
			),
			$ca_order->getCustomerDocument(),
			$ca_order->getPhone(),
			$ca_order->getCellphone(),
			$order->get_billing_email(),
		);

		$sender = $this->buildSender( $order_id, $vendor_id );

		if ( empty( $sender->getName() ) ) {
			Log::notice( "Dados do remetente inválidos, é necessário preencher os dados do remetente nas configurações para utilizar a pré-postagem." );
			return new \WP_Error( 'invalid_sender_data', 'Dados do remetente inválidos, é necessário preencher os dados do remetente nas configurações para utilizar a pré-postagem.', [ 'status' => 400 ] );
		}

		$sender_error = $this->validateStoreAddress( $sender, $order_id );

		if ( $sender_error ) {
			return $sender_error;
		}

		$sender_error = $this->validateStoreDocument( $sender, $order_id, $vendor_id );

		if ( $sender_error ) {
			return $sender_error;
		}

		$shippingProductCode = $ca_order->getShippingProductCode();

		$prepost = new Prepost(
			$order_id,
			$sender,
			$recipient,
			$shippingProductCode,
			$shipping_method->get_object_type_code()
		);

		$prepost->setVendorId( $vendor_id );
		$prepost->setId( $this->resolveCorreiosId( $vendor_id ) );
		$prepost->setShippingItemId( $ca_order->getScopedShippingItemId() );

		if ( DeliveryServiceCode::isLetter( $shippingProductCode ) ) {
			$prepost->setObjectFormatCode( ObjectFormatCode::ENVELOPE );
		}

		if ( $shippingProductCode === DeliveryServiceCode::IMPRESSO_MODICO ) {
			$prepost->addAdditionalService( [
				'code' => '004',
				'declaredValue' => '0'
			] );
		}

		if ( $shippingProductCode === DeliveryServiceCode::CARTA_COML_REG_B1_CHANC_ETIQ ) {
			$prepost->addAdditionalService( [
				'code' => '025',
				'declaredValue' => '0'
			] );
		}

		$use_receipt_notice = isset( $data['receiptNotice'] )
			? filter_var( $data['receiptNotice'], FILTER_VALIDATE_BOOLEAN )
			: $shipping_method->is_receipt_notice();

		if ( $use_receipt_notice ) {
			$prepost->addAdditionalService( [
				'code' => '001',
				'declaredValue' => '0'
			] );
		}

		$use_receipt_notice_electronic = isset( $data['receiptNoticeElectronic'] )
			? filter_var( $data['receiptNoticeElectronic'], FILTER_VALIDATE_BOOLEAN )
			: $shipping_method->is_receipt_notice_electronic();

		if ( $use_receipt_notice_electronic ) {
			$prepost->addAdditionalService( [
				'code' => '021',
				'declaredValue' => '0'
			] );
		}

		$use_own_hands = isset( $data['ownHands'] )
			? filter_var( $data['ownHands'], FILTER_VALIDATE_BOOLEAN )
			: false;

		if ( $use_own_hands ) {
			$prepost->addAdditionalService( [
				'code' => '002',
				'declaredValue' => '0'
			] );
		}

		$use_dangerous_product = isset( $data['dangerousProduct'] )
			? filter_var( $data['dangerousProduct'], FILTER_VALIDATE_BOOLEAN )
			: $ca_order->hasDangerousProduct();

		if ( $use_dangerous_product ) {
			$prepost->addAdditionalService( [
				'code' => '095',
				'declaredValue' => '0'
			] );
		}

		$prepost->setItemsFromPackage( $ca_order->getPackage() );

		$shippingItem = $ca_order->getFirstShippingItemData();

		if ( isset( $shippingItem['insurance_cost'] ) && $shippingItem['insurance_cost'] > 0 ) {
			$insurance_code = AddicionalServiceCode::getInsuranceCode( $shippingProductCode );
			if ( $insurance_code ) {
				$prepost->addAdditionalService( [
					'code' => $insurance_code,
					'declaredValue' => $prepost->getItemsTotal()
				] );
			}
		}

		$dimensions = $ca_order->getShippingPackageDimensions();

		$prepost->setLength( $dimensions['lenght'] );
		$prepost->setWidth( $dimensions['width'] );
		$prepost->setHeight( $dimensions['height'] );
		$prepost->setWeight( $dimensions['weight'] );

		$data = $this->withOrderInvoice( $data, $order, $ca_order->getScopedShippingItemId(), $vendor_id );

		if ( isset( $data['invoice_number'] ) ) {
			$prepost->setInvoiceNumber( $data['invoice_number'] );
		}

		if ( isset( $data['invoice_key'] ) ) {
			$prepost->setInvoiceKey( $data['invoice_key'] );
		}

		$prepost->setEmitDce( $this->resolveEmitDce( $data ) );

		if ( $prepost->isPacket() ) {
			$prepost->setFreightPaidValue( $ca_order->getShippingTotal() );
			$prepost->setCurrency( get_woocommerce_currency() );
		}

		$created_prepost = $this->processPrespost( $prepost );

		if ( is_wp_error( $created_prepost ) ) {
			Log::notice( "Erro ao criar a pré-postagem.", [
				'message' => $created_prepost->get_error_message(),
			] );
			return $created_prepost;
		}

		$order->update_meta_data( '_infixs_correios_automatico_prepost_created', 'yes' );
		$order->update_meta_data( '_infixs_correios_automatico_prepost_id', $created_prepost->id );

		$this->markShippingItemAsPreposted( $order, $ca_order->getScopedShippingItemId(), $created_prepost->id );

		if ( isset( $data['invoice_number'] ) ) {
			$order->update_meta_data( '_infixs_correios_automatico_invoice_number', $data['invoice_number'] );
		}

		if ( isset( $data['invoice_key'] ) ) {
			$order->update_meta_data( '_infixs_correios_automatico_invoice_key', $data['invoice_key'] );
		}

		$order->save();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 60, 'infixs_correios_automatico_prepost_sync_schedule', [ $created_prepost->id ] );
		} else {
			wp_schedule_single_event( time() + 60, 'infixs_correios_automatico_prepost_sync_schedule', [ $created_prepost->id ] );
		}

		do_action( 'infixs_correios_automatico_prepost_created', $order_id, $created_prepost );

		Log::debug( 'Pré-postagem criada com sucesso.', [
			'prepost_id' => $created_prepost->id,
			'order_id' => $order_id,
		] );

		return $created_prepost;
	}

	/**
	 * Fill the invoice of a prepost with the NF-e of the order.
	 *
	 * Only a caller that left the fiscal document open gets it: an explicit
	 * invoice, or an explicit choice of `emiteDCe` — the DC-e button sends
	 * `S` — is always kept as given.
	 *
	 * @since 1.11.0
	 *
	 * @param array     $data             Data of the prepost.
	 * @param \WC_Order $order            Order.
	 * @param int       $shipping_item_id Shipping line being preposted, zero for the whole order.
	 * @param int       $vendor_id        Marketplace vendor posting the shipment, zero for the store.
	 *
	 * @return array Data, with `invoice_number` and `invoice_key` when the order has an NF-e.
	 */
	private function withOrderInvoice( $data, $order, $shipping_item_id, $vendor_id ) {
		if ( ! empty( $data['invoice_key'] ) || ! empty( $data['invoice_number'] ) || isset( $data['emiteDCe'] ) ) {
			return $data;
		}

		/**
		 * Filters whether a prepost uses the NF-e of the order.
		 *
		 * Return false to keep every prepost without an explicit invoice on the
		 * DC-e, as before the invoice of the order was looked up.
		 *
		 * @since 1.11.0
		 *
		 * @param bool      $use              Whether to use the NF-e of the order.
		 * @param \WC_Order $order            Order.
		 * @param int       $shipping_item_id Shipping line being preposted, zero for the whole order.
		 */
		if ( ! apply_filters( 'infixs_correios_automatico_prepost_use_order_invoice', true, $order, $shipping_item_id ) ) {
			return $data;
		}

		$invoice = Container::orderInvoiceResolver()->resolve( $order, [
			'for' => 'prepost',
			'shipping_item_id' => $shipping_item_id,
			'vendor_id' => $vendor_id,
		] );

		if ( ! $invoice ) {
			return $data;
		}

		$data['invoice_number'] = $invoice['invoice_number'];
		$data['invoice_key'] = $invoice['invoice_key'];

		return $data;
	}

	/**
	 * Decide whether the Correios issue a DC-e for the prepost.
	 *
	 * An explicit `S` or `N` always wins. Without one, an NF-e key means the
	 * object travels with the invoice of the store and needs no declaration;
	 * anything else gets the DC-e.
	 *
	 * @since 1.11.0
	 *
	 * @param array $data Data of the prepost.
	 *
	 * @return string `S` or `N`.
	 */
	private function resolveEmitDce( $data ) {
		if ( isset( $data['emiteDCe'] ) && in_array( $data['emiteDCe'], [ 'S', 'N' ], true ) ) {
			return $data['emiteDCe'];
		}

		if ( ! empty( $data['invoice_key'] ) && AccessKey::isInvoice( $data['invoice_key'] ) ) {
			return 'N';
		}

		return 'S';
	}

	/**
	 * Build and persist a reverse logistics prepost (Logística Reversa).
	 *
	 * The package travels from the customer (sender) back to the store (recipient).
	 * Uses the reverse equivalent of the order's shipping service, falling back to
	 * PAC Reverso when there is no clean reverse.
	 *
	 * @since 1.8.0
	 * @since 1.11.0 Also lists the reverse prepost on the order, per shipping line.
	 * @since 1.14.0 Travels with the fiscal document the shipment went out with.
	 *
	 * @param int $order_id Order ID.
	 * @param array{
	 * 		service?: string,
	 * 		weight?: float,
	 * 		length?: float,
	 * 		width?: float,
	 * 		height?: float,
	 * 		invoice_number?: string,
	 * 		invoice_key?: string,
	 * 		emiteDCe?: string,
	 * } $data Optional overrides (used by the manual reverse prepost screen).
	 *
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	protected function doCreateReversePrepost( $order_id, $data = [] ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Log::notice( "Pedido inválido ao criar a pré-postagem reversa." );
			return new \WP_Error( 'invalid_order', 'Pedido inválido ao criar a devolução.', [ 'status' => 400 ] );
		}

		$ca_order = $this->reverseScopedOrder( $order, $data );

		$vendor_id = $ca_order->getScopedVendorId();

		$shipping_method = $ca_order->getShippingMethod();

		if ( ! $shipping_method ) {
			Log::notice( "Esse pedido não tem o método de envio dos correios automático, a devolução não pode ser criada." );
			return new \WP_Error( 'invalid_shipping_method', 'Esse pedido não tem um método de envio dos Correios Automático. Se o pedido usa um frete nativo do WooCommerce (Frete Grátis ou Preço Fixo), vincule um serviço dos Correios nas configurações desse frete, na zona de entrega.', [ 'status' => 400 ] );
		}

		$ca_address = $ca_order->getAddress();

		$customer_postal_code_numeric = Sanitizer::numeric_text( trim( (string) $ca_address->getPostCode() ) );
		$customer_neighborhood = trim( (string) $ca_address->getNeighborhood() );

		if ( preg_match( '/000$/', $customer_postal_code_numeric ) && empty( $customer_neighborhood ) ) {
			Log::notice( 'Bairro do cliente é obrigatório para CEP finalizado em 000.', [
				'order_id' => $order_id,
			] );

			return new \WP_Error(
				'invalid_customer_neighborhood',
				'O campo Bairro do cliente é obrigatório quando o CEP termina com 000.',
				[ 'status' => 400 ]
			);
		}

		// On a reverse shipment the customer is the sender and the store is the recipient.
		$customer = new Person(
			$ca_order->getCustomerFullName(),
			new Address(
				$ca_address->getPostCode(),
				$ca_address->getStreet(),
				$ca_address->getNumber(),
				$ca_address->getComplement(),
				$ca_address->getNeighborhood(),
				$ca_address->getCity(),
				$ca_address->getState()
			),
			$ca_order->getCustomerDocument(),
			$ca_order->getPhone(),
			$ca_order->getCellphone(),
			$order->get_billing_email(),
		);

		$store = $this->buildSender( $order_id, $vendor_id );

		if ( empty( $store->getName() ) ) {
			Log::notice( "Dados do remetente inválidos, é necessário preencher os dados do remetente nas configurações para utilizar a pré-postagem reversa." );
			return new \WP_Error( 'invalid_sender_data', 'Dados do remetente inválidos, é necessário preencher os dados do remetente nas configurações para utilizar a devolução.', [ 'status' => 400 ] );
		}

		$store_error = $this->validateStoreAddress( $store, $order_id );

		if ( $store_error ) {
			return $store_error;
		}

		$store_error = $this->validateStoreDocument( $store, $order_id, $vendor_id );

		if ( $store_error ) {
			return $store_error;
		}

		$shippingProductCode = $ca_order->getShippingProductCode();

		if ( isset( $data['service'] ) && ! empty( $data['service'] ) ) {
			$service_code = $data['service'];
		} elseif ( Config::boolean( 'return.same_service' ) ) {
			$service_code = DeliveryServiceCode::getReverseServiceCode( $shippingProductCode );
		} else {
			$service_code = DeliveryServiceCode::PAC_REVERSO;
		}

		$prepost = new Prepost(
			$order_id,
			$customer,
			$store,
			$service_code,
			ObjectFormatCode::PACOTE
		);

		$prepost->setVendorId( $vendor_id );
		$prepost->setId( $this->resolveCorreiosId( $vendor_id ) );
		$prepost->setShippingItemId( $ca_order->getScopedShippingItemId() );
		$prepost->setReverseLogistic( true );
		$prepost->setItemsFromPackage( $ca_order->getPackage() );

		$document = $this->reverseFiscalDocument( $order, (int) $ca_order->getScopedShippingItemId(), $vendor_id, $data, $store->getCpfCnpj() );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		if ( ! empty( $document['invoice_number'] ) ) {
			$prepost->setInvoiceNumber( $document['invoice_number'] );
		}

		if ( ! empty( $document['invoice_key'] ) ) {
			$prepost->setInvoiceKey( $document['invoice_key'] );
		}

		$prepost->setEmitDce( $document['emiteDCe'] );

		$shippingItem = $ca_order->getShipmentDimensions();

		$prepost->setLength( isset( $data['length'] ) ? $data['length'] : $shippingItem['lenght'] );
		$prepost->setWidth( isset( $data['width'] ) ? $data['width'] : $shippingItem['width'] );
		$prepost->setHeight( isset( $data['height'] ) ? $data['height'] : $shippingItem['height'] );
		$prepost->setWeight( isset( $data['weight'] ) ? $data['weight'] : $shippingItem['weight'] );

		$created_prepost = $this->processPrespost( $prepost );

		if ( is_wp_error( $created_prepost ) && $this->isRefusedFiscalDocument( $created_prepost, $document ) ) {
			$created_prepost = $this->retryReverseWithDeclaration( $order, $prepost, $document, $created_prepost );
		}

		if ( is_wp_error( $created_prepost ) ) {
			Log::notice( "Erro ao criar a pré-postagem reversa.", [
				'message' => $created_prepost->get_error_message(),
			] );
			return $created_prepost;
		}

		$order->update_meta_data( '_infixs_correios_automatico_reverse_prepost_id', $created_prepost->id );
		$order->update_meta_data( '_infixs_correios_automatico_reverse_prepost_code', $created_prepost->object_code );
		$this->recordReversePrepost( $order, $created_prepost, (int) $ca_order->getScopedShippingItemId() );
		$order->save();

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 60, 'infixs_correios_automatico_prepost_sync_schedule', [ $created_prepost->id ] );
		} else {
			wp_schedule_single_event( time() + 60, 'infixs_correios_automatico_prepost_sync_schedule', [ $created_prepost->id ] );
		}

		do_action( 'infixs_correios_automatico_prepost_created', $order_id, $created_prepost );
		do_action( 'infixs_correios_automatico_reverse_prepost_created', $order_id, $created_prepost );

		Log::debug( 'Pré-postagem reversa criada com sucesso.', [
			'prepost_id' => $created_prepost->id,
			'order_id' => $order_id,
			'service_code' => $service_code,
		] );

		return $created_prepost;
	}

	/**
	 * Tell the fiscal document a reverse prepost of the order would travel with.
	 *
	 * The same decision the reverse prepost takes, so the manual screen can
	 * show it, or ask for one when the shipment has no NF-e.
	 *
	 * @since 1.14.0
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Shipment being returned: `shipping_item_id` and `vendor_id`.
	 *
	 * @return array{emiteDCe: string, invoice_number?: string, invoice_key?: string, source: string}|\WP_Error
	 */
	public function previewReverseFiscalDocument( $order_id, $data = [] ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new \WP_Error( 'invalid_order', 'Pedido inválido ao consultar a devolução.', [ 'status' => 404 ] );
		}

		$ca_order = $this->reverseScopedOrder( $order, $data );
		$vendor_id = $ca_order->getScopedVendorId();
		$store = $this->buildSender( $order_id, $vendor_id );

		return $this->reverseFiscalDocument( $order, (int) $ca_order->getScopedShippingItemId(), $vendor_id, [], $store->getCpfCnpj() );
	}

	/**
	 * The order scoped to the shipment a reverse prepost brings back.
	 *
	 * @since 1.14.0
	 *
	 * @param \WC_Order $order Order being returned.
	 * @param array     $data  Shipment being returned: `shipping_item_id` and `vendor_id`.
	 *
	 * @return Order
	 */
	private function reverseScopedOrder( $order, $data ) {
		$ca_order = new Order( $order );

		$ca_order->applyScope( Order::resolveScope( $ca_order, [
			'for' => 'reverse_prepost',
			'vendor_id' => isset( $data['vendor_id'] ) ? absint( $data['vendor_id'] ) : 0,
			'shipping_item_id' => isset( $data['shipping_item_id'] ) ? absint( $data['shipping_item_id'] ) : 0,
		] ) );

		return $ca_order;
	}

	/**
	 * Decide the fiscal document a reverse prepost travels with.
	 *
	 * A return goes back with the document its shipment went out with, the
	 * NF-e or the DC-e of the outbound prepost, with the same key and number
	 * and no new document. A shipment with a DC-e whose key cannot be found
	 * asks for a new one, so the return is not lost. A shipment the plugin did
	 * not prepost, or one sent before the DC-e, goes back with the NF-e of the
	 * order when there is one, and with the content declaration only otherwise,
	 * as every return did before.
	 *
	 * An NF-e only goes back to the store that issued it, so the return of a
	 * marketplace order never carries the invoice of another vendor. An
	 * invoice or an `emiteDCe` given by the caller always wins.
	 *
	 * @since 1.14.0
	 *
	 * @param \WC_Order $order            Order being returned.
	 * @param int       $shipping_item_id Shipping line being returned, zero for the whole order.
	 * @param int       $vendor_id        Marketplace vendor of the shipment, zero for the store.
	 * @param array     $data             Data given by the caller.
	 * @param string    $store_document   CPF or CNPJ of the store the return goes to, digits only.
	 *
	 * @return array{emiteDCe: string, invoice_number?: string, invoice_key?: string, source: string}|\WP_Error Error when the DC-e of the shipment could not be looked up.
	 */
	private function reverseFiscalDocument( $order, $shipping_item_id, $vendor_id, $data, $store_document ) {
		if ( ! empty( $data['invoice_key'] ) || ! empty( $data['invoice_number'] ) || isset( $data['emiteDCe'] ) ) {
			return [
				'invoice_number' => isset( $data['invoice_number'] ) ? (string) $data['invoice_number'] : '',
				'invoice_key' => isset( $data['invoice_key'] ) ? (string) $data['invoice_key'] : '',
				'emiteDCe' => $this->resolveEmitDce( $data ),
				'source' => 'caller',
			];
		}

		$outbound = $this->outboundPrepost( $order, $shipping_item_id, $vendor_id );
		$outbound_key = $outbound ? AccessKey::normalize( $outbound->invoice_key ) : null;

		if ( null !== $outbound_key && AccessKey::isInvoice( $outbound_key ) && $this->isIssuedBy( $outbound_key, $store_document ) ) {
			$document = $this->invoiceDocument( $outbound_key, (string) $outbound->invoice_number, 'outbound_invoice' );
		} elseif ( $outbound && $this->hasDce( $outbound ) ) {
			$dce_key = $this->outboundDceKey( $outbound );

			if ( is_wp_error( $dce_key ) ) {
				return $dce_key;
			}

			$document = null !== $dce_key
				? $this->invoiceDocument( $dce_key, (string) AccessKey::number( $dce_key ), 'outbound_dce' )
				: [
					'emiteDCe' => 'S',
					'source' => 'outbound_dce',
				];
		} else {
			$invoice = $this->withOrderInvoice( [], $order, $shipping_item_id, $vendor_id );
			$order_key = isset( $invoice['invoice_key'] ) ? AccessKey::normalize( $invoice['invoice_key'] ) : null;

			$document = null !== $order_key && $this->isIssuedBy( $order_key, $store_document )
				? $this->invoiceDocument( $order_key, (string) $invoice['invoice_number'], 'order_invoice' )
				: [
					'emiteDCe' => 'N',
					'source' => 'declaration',
				];
		}

		/**
		 * Filters the fiscal document a reverse prepost travels with.
		 *
		 * A key that is neither of an NF-e nor of a DC-e is dropped, and an
		 * `emiteDCe` other than `S` sends no DC-e.
		 *
		 * @since 1.14.0
		 *
		 * @param array             $document         Document: `emiteDCe`, and `invoice_number` and `invoice_key` for an NF-e or a DC-e.
		 * @param \WC_Order         $order            Order being returned.
		 * @param int               $shipping_item_id Shipping line being returned, zero for the whole order.
		 * @param PrepostModel|null $outbound         Prepost the shipment went out with, null when there is none.
		 */
		$filtered = apply_filters( 'infixs_correios_automatico_reverse_prepost_fiscal_document', $document, $order, $shipping_item_id, $outbound );
		$document = $this->sanitizeReverseDocument( $filtered, $document );

		Log::debug( 'Documento fiscal da pré-postagem reversa definido.', [
			'order_id' => $order->get_id(),
			'shipping_item_id' => $shipping_item_id,
			'source' => $document['source'],
			'emite_dce' => $document['emiteDCe'],
			'nfe' => ! empty( $document['invoice_key'] ),
		] );

		return $document;
	}

	/**
	 * Access key of the DC-e a shipment went out with.
	 *
	 * The key reaches the row with the sync after the prepost is created, so
	 * a row that never got it asks the Correios. Failing to ask does not mean
	 * the key does not exist, so it is an error instead of a reason for a new
	 * DC-e.
	 *
	 * @since 1.14.0
	 *
	 * @param PrepostModel $outbound Prepost the shipment went out with.
	 *
	 * @return string|null|\WP_Error Forty four digits, null when the Correios do not have it, error when they could not be asked.
	 */
	private function outboundDceKey( $outbound ) {
		$key = $this->dceAccessKey( $outbound );

		if ( null !== $key ) {
			return $key;
		}

		$response = $this->correiosService->get_prepost( $outbound->object_code, $outbound );

		if ( is_wp_error( $response ) ) {
			$data = $response->get_error_data();

			if ( is_array( $data ) && isset( $data['status'] ) && 404 === (int) $data['status'] ) {
				return null;
			}

			Log::notice( 'Não foi possível consultar a DC-e da ida nos Correios.', [
				'prepost_id' => (int) $outbound->id,
				'message' => $response->get_error_message(),
			] );

			return new \WP_Error( 'outbound_dce_unavailable', 'Não foi possível consultar a DC-e da ida nos Correios. Tente novamente em instantes.', [ 'status' => 503 ] );
		}

		$key = isset( $response['itens'][0]['chaveNFe'] ) ? $response['itens'][0]['chaveNFe'] : null;

		return AccessKey::isDce( $key ) ? AccessKey::normalize( $key ) : null;
	}

	/**
	 * Document of an NF-e or a DC-e, with the number read from the key when missing.
	 *
	 * @since 1.14.0
	 *
	 * @param string $invoice_key    Access key of the document, digits only.
	 * @param string $invoice_number Number of the document, empty when unknown.
	 * @param string $source         Where the document was found.
	 *
	 * @return array{emiteDCe: string, invoice_number: string, invoice_key: string, source: string}
	 */
	private function invoiceDocument( $invoice_key, $invoice_number, $source ) {
		return [
			'invoice_number' => '' !== $invoice_number ? $invoice_number : (string) AccessKey::number( $invoice_key ),
			'invoice_key' => $invoice_key,
			'emiteDCe' => 'N',
			'source' => $source,
		];
	}

	/**
	 * Keep only what the Correios can take from a filtered document.
	 *
	 * @since 1.14.0
	 *
	 * @param mixed $filtered Document returned by the filter.
	 * @param array $document Document decided before the filter.
	 *
	 * @return array{emiteDCe: string, invoice_number?: string, invoice_key?: string, source: string}
	 */
	private function sanitizeReverseDocument( $filtered, $document ) {
		if ( ! is_array( $filtered ) ) {
			return $document;
		}

		$sanitized = [
			'emiteDCe' => isset( $filtered['emiteDCe'] ) && 'S' === $filtered['emiteDCe'] ? 'S' : 'N',
			'source' => isset( $filtered['source'] ) ? sanitize_key( $filtered['source'] ) : $document['source'],
		];

		$key = isset( $filtered['invoice_key'] ) ? AccessKey::normalize( $filtered['invoice_key'] ) : null;

		if ( null !== $key && ( AccessKey::isInvoice( $key ) || AccessKey::isDce( $key ) ) ) {
			$number = isset( $filtered['invoice_number'] ) ? Sanitizer::numeric_text( $filtered['invoice_number'] ) : '';

			return array_merge( $sanitized, $this->invoiceDocument( $key, $number, $sanitized['source'] ), [ 'emiteDCe' => $sanitized['emiteDCe'] ] );
		}

		return $sanitized;
	}

	/**
	 * Whether an NF-e was issued by the store a return goes to.
	 *
	 * The root of the CNPJ is compared, so an invoice of another branch of the
	 * same company still goes back.
	 *
	 * @since 1.14.0
	 *
	 * @param string $invoice_key    Access key of the NF-e.
	 * @param string $store_document CPF or CNPJ of the store, digits only.
	 *
	 * @return bool
	 */
	private function isIssuedBy( $invoice_key, $store_document ) {
		$issuer = AccessKey::issuerDocument( $invoice_key );
		$store_document = Sanitizer::numeric_text( $store_document );

		if ( null === $issuer || '' === $store_document ) {
			return false;
		}

		if ( 14 === strlen( $store_document ) ) {
			return substr( $issuer, 0, 8 ) === substr( $store_document, 0, 8 );
		}

		return str_pad( $store_document, 14, '0', STR_PAD_LEFT ) === $issuer;
	}

	/**
	 * Get the prepost a shipment of the order went out with.
	 *
	 * The newest prepost of the shipping line being returned, or else the
	 * newest one made for the whole order; any newest one when the whole
	 * order is returned. Only the preposts of the same vendor count, and
	 * neither returns nor preposts that were cancelled, expired or reversed.
	 *
	 * @since 1.14.0
	 *
	 * @param \WC_Order $order            Order being returned.
	 * @param int       $shipping_item_id Shipping line being returned, zero for the whole order.
	 * @param int       $vendor_id        Marketplace vendor of the shipment, zero for the store.
	 *
	 * @return PrepostModel|null
	 */
	private function outboundPrepost( $order, $shipping_item_id, $vendor_id ) {
		$reverse_ids = array_map( 'intval', array_column( $this->getReversePreposts( $order ), 'id' ) );
		$whole_order = null;

		foreach ( PrepostModel::where( 'order_id', $order->get_id() )->orderBy( 'id', 'desc' )->get() as $prepost ) {
			if (
				(int) $prepost->vendor_id !== (int) $vendor_id
				|| ! empty( $prepost->cancelled_at )
				|| in_array( (int) $prepost->status, [ PrepostStatusCode::EXPRIRADO, PrepostStatusCode::CANCELADO, PrepostStatusCode::ESTORNADO ], true )
				|| in_array( (int) $prepost->id, $reverse_ids, true )
				|| $this->isReversePrepost( $order, $prepost )
			) {
				continue;
			}

			$line = (int) $prepost->shipping_item_id;

			if ( 0 === $shipping_item_id || $line === $shipping_item_id ) {
				return $prepost;
			}

			if ( 0 === $line && null === $whole_order ) {
				$whole_order = $prepost;
			}
		}

		return $whole_order;
	}

	/**
	 * Whether the Correios refused the fiscal document of a reverse prepost.
	 *
	 * Only a refusal of the request itself counts, never a failure to reach
	 * the Correios, after which the prepost may have been created anyway.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_Error $error    Error of the reverse prepost.
	 * @param array     $document Fiscal document it was sent with.
	 *
	 * @return bool
	 */
	private function isRefusedFiscalDocument( $error, $document ) {
		if ( 'http_error' !== $error->get_error_code() || ! in_array( $document['source'], [ 'outbound_invoice', 'outbound_dce', 'order_invoice' ], true ) ) {
			return false;
		}

		$data = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		return in_array( $status, [ 400, 422 ], true );
	}

	/**
	 * Create the reverse prepost again without the document of the shipment.
	 *
	 * A return was always accepted without a document, so a store does not
	 * lose it when the Correios refuse the NF-e or the DC-e of the shipment.
	 * With no document in the request they may issue a DC-e of their own, and
	 * the order tells the store both things.
	 *
	 * @since 1.14.0
	 *
	 * @param \WC_Order $order    Order being returned.
	 * @param Prepost   $prepost  Reverse prepost refused.
	 * @param array     $document Fiscal document it was sent with.
	 * @param \WP_Error $refusal  Error of the Correios.
	 *
	 * @return PrepostModel|\WP_Error
	 */
	private function retryReverseWithDeclaration( $order, $prepost, $document, $refusal ) {
		Log::notice( 'Os Correios recusaram o documento fiscal da pré-postagem reversa; nova tentativa sem o documento.', [
			'order_id' => $order->get_id(),
			'source' => $document['source'],
			'message' => $refusal->get_error_message(),
		] );

		$prepost->setInvoiceKey( '' );
		$prepost->setInvoiceNumber( '' );
		$prepost->setEmitDce( 'N' );

		$created = $this->processPrespost( $prepost );

		if ( ! is_wp_error( $created ) ) {
			$order->add_order_note( sprintf(
				/* translators: 1: object code of the return, 2: fiscal document, 3: message of the Correios */
				__( 'Correios Automático - A pré-postagem reversa %1$s saiu sem %2$s da ida, porque os Correios recusaram o documento: %3$s. Sem documento na solicitação, os Correios podem emitir uma DC-e para ela.', 'infixs-correios-automatico' ),
				$created->object_code,
				'outbound_dce' === $document['source'] ? __( 'a DC-e', 'infixs-correios-automatico' ) : __( 'a NF-e', 'infixs-correios-automatico' ),
				$refusal->get_error_message()
			) );
		}

		return $created;
	}

	/**
	 * Get the reverse preposts recorded on an order.
	 *
	 * The `_infixs_correios_automatico_reverse_prepost_code` meta only keeps the
	 * last reverse prepost, while a return in volumes has one per box. Reverse
	 * preposts created before 1.11.0 are not listed.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order $order Order returned.
	 *
	 * @return array<int, array{id: int, code: string, shipping_item_id: int}>
	 */
	public function getReversePreposts( $order ) {
		$reverse_preposts = $order->get_meta( self::REVERSE_PREPOSTS_META_KEY );

		return is_array( $reverse_preposts ) ? array_values( $reverse_preposts ) : [];
	}

	/**
	 * Add a reverse prepost to the list of the order, without saving it.
	 *
	 * A shipping line keeps only its newest reverse prepost, so a box returned
	 * again replaces its previous code instead of being listed twice. A reverse
	 * prepost for the whole order replaces those of the lines, and one for a
	 * line replaces the one for the whole order.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order                                 $order            Order returned.
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost          Reverse prepost created.
	 * @param int                                       $shipping_item_id Shipping line returned, zero for the whole order.
	 *
	 * @return void
	 */
	private function recordReversePrepost( $order, $prepost, $shipping_item_id ) {
		$reverse_preposts = array_filter( $this->getReversePreposts( $order ), function ( $reverse_prepost ) use ( $shipping_item_id ) {
			$line = isset( $reverse_prepost['shipping_item_id'] ) ? (int) $reverse_prepost['shipping_item_id'] : 0;

			return $shipping_item_id && $line && $line !== $shipping_item_id;
		} );

		$reverse_preposts[] = [
			'id' => (int) $prepost->id,
			'code' => (string) $prepost->object_code,
			'shipping_item_id' => $shipping_item_id,
		];

		$order->update_meta_data( self::REVERSE_PREPOSTS_META_KEY, array_values( $reverse_preposts ) );
	}

	/**
	 * Whether a prepost of an order brings a shipment back.
	 *
	 * Recognised by its reverse service, or by the order recording it as one of
	 * its reverse preposts, in case the Correios answered with another service.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order    $order   Order the prepost belongs to.
	 * @param PrepostModel $prepost Prepost.
	 *
	 * @return bool
	 */
	private function isReversePrepost( $order, $prepost ) {
		if ( DeliveryServiceCode::isReverseService( $prepost->service_code ) ) {
			return true;
		}

		if ( (int) $order->get_meta( '_infixs_correios_automatico_reverse_prepost_id' ) === (int) $prepost->id ) {
			return true;
		}

		return in_array( (int) $prepost->id, array_map( 'intval', wp_list_pluck( $this->getReversePreposts( $order ), 'id' ) ), true );
	}

	/**
	 * Take a cancelled reverse prepost out of the order.
	 *
	 * The customer is no longer shown its code. The metas of the last reverse
	 * prepost move to the newest one left, or go when none is.
	 *
	 * @since 1.11.0
	 *
	 * @param \WC_Order    $order   Order returned.
	 * @param PrepostModel $prepost Reverse prepost cancelled.
	 *
	 * @return void
	 */
	private function forgetReversePrepost( $order, $prepost ) {
		$reverse_preposts = array_values( array_filter( $this->getReversePreposts( $order ), function ( $reverse_prepost ) use ( $prepost ) {
			return ! isset( $reverse_prepost['id'] ) || (int) $reverse_prepost['id'] !== (int) $prepost->id;
		} ) );

		$order->update_meta_data( self::REVERSE_PREPOSTS_META_KEY, $reverse_preposts );

		if ( (int) $order->get_meta( '_infixs_correios_automatico_reverse_prepost_id' ) === (int) $prepost->id ) {
			$newest = end( $reverse_preposts );

			if ( $newest ) {
				$order->update_meta_data( '_infixs_correios_automatico_reverse_prepost_id', (int) $newest['id'] );
				$order->update_meta_data( '_infixs_correios_automatico_reverse_prepost_code', (string) $newest['code'] );
			} else {
				$order->delete_meta_data( '_infixs_correios_automatico_reverse_prepost_id' );
				$order->delete_meta_data( '_infixs_correios_automatico_reverse_prepost_code' );
			}
		}

		$order->save();
	}

	/**
	 * Process prepost.
	 *
	 * @since 1.0.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 *
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function processPrespost( $prepost ) {
		if ( $prepost->isPacket() ) {
			return $this->processPacket( $prepost );
		} else {
			return $this->processPrepost( $prepost );
		}
	}

	/**
	 * Process packet.
	 * 
	 * @since 1.1.7
	 * 
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function processPacket( $prepost ) {
		$response = $this->correiosService->create_packet( $prepost );
		if ( is_wp_error( $response ) )
			return $response;

		$object_code = $response['packageResponseList'][0]['trackingNumber'];

		$created_prepost = $this->prepostRepository->create( [
			'external_id' => 0,
			'order_id' => $prepost->getOrderId(),
			'vendor_id' => $prepost->getVendorId() ?: null,
			'shipping_item_id' => $prepost->getShippingItemId() ?: null,
			'object_code' => $object_code,
			'service_code' => $prepost->getServiceCode(),
			'payment_type' => 1,
			'height' => $prepost->getHeight(),
			'width' => $prepost->getWidth(),
			'length' => $prepost->getLength(),
			'weight' => $prepost->getWeight(),
			'request_pickup' => 0,
			'reverse_logistic' => 0,
			'status' => 2,
			'status_label' => 'Pré-postado',
			'updated_at' => current_time( 'mysql' ),
			'created_at' => current_time( 'mysql' ),
		] );

		if ( ! $created_prepost ) {
			Log::notice( "Erro ao salvar a pré-postagem no banco de dados." );
			return new \WP_Error( 'prepost_save_error', 'Erro ao salvar a pré-postagem no banco de dados.', [ 'status' => 400 ] );
		}

		return $created_prepost;
	}


	/**
	 * Posting deadline of a new prepost, as the table stores it.
	 *
	 * The Correios answer a reverse prepost with nanoseconds, which PHP before
	 * 8.1 refuses. The prepost already exists at the Correios by then, so the
	 * fractions are left out, and a deadline that still cannot be read does not
	 * cost the prepost.
	 *
	 * @since 1.14.0
	 *
	 * @param string $deadline Deadline sent by the Correios.
	 *
	 * @return string|null Null when it cannot be read.
	 */
	private function postingDeadline( $deadline ) {
		try {
			return ( new \DateTime( substr( (string) $deadline, 0, 19 ) ) )->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			Log::notice( 'Prazo de postagem ilegível na resposta dos Correios.', [ 'deadline' => $deadline ] );

			return null;
		}
	}

	/**
	 * Process prepost.
	 * 
	 * @since 1.0.0
	 * @since 1.14.0 Records the contract the prepost was created with, in `contract_source`.
	 * 
	 * @param \Infixs\CorreiosAutomatico\Services\Correios\Includes\Prepost $prepost
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function processPrepost( $prepost ) {
		$response = $this->correiosService->create_prepost( $prepost );

		if ( is_wp_error( $response ) )
			return $response;

		$prazoPostagem = $this->postingDeadline( isset( $response['prazoPostagem'] ) ? $response['prazoPostagem'] : '' );

		$data = [
			'external_id' => $response['id'],
			'order_id' => $prepost->getOrderId(),
			'vendor_id' => $prepost->getVendorId() ?: null,
			'shipping_item_id' => $prepost->getShippingItemId() ?: null,
			'object_code' => $response['codigoObjeto'],
			'service_code' => $response['codigoServico'],
			'payment_type' => $response['modalidadePagamento'],
			'height' => $response['alturaInformada'],
			'width' => $response['larguraInformada'],
			'length' => $response['comprimentoInformado'],
			'weight' => $response['pesoInformado'],
			'request_pickup' => $response['solicitarColeta'],
			'reverse_logistic' => $response['logisticaReversa'],
			'status' => $response['statusAtual'],
			'status_label' => $response['descStatusAtual'],
			'invoice_number' => $response['numeroNotaFiscal'] ?? null,
			'invoice_key' => $response['chaveNFe'] ?? null,
			'expire_at' => $prazoPostagem,
			'updated_at' => current_time( 'mysql' ),
			'created_at' => current_time( 'mysql' ),
			'dce' => isset( $response['emiteDCe'] ) && $response['emiteDCe'] === 'S' ? 1 : 0,
		];

		if ( ContractMode::PREPAID === ContractMode::sourceOf( $response ) ) {
			if ( $this->ensureContractSourceColumn() ) {
				$data['contract_source'] = ContractMode::PREPAID;
			} else {
				Log::alert( 'A pré-postagem do Contrato pré-pago foi criada, mas a tabela não tem a coluna da origem do contrato.', [
					'object_code' => $response['codigoObjeto'],
				] );
			}
		}

		$created_prepost = $this->prepostRepository->create( $data );

		if ( ! $created_prepost ) {
			Log::notice( "Erro ao salvar a pré-postagem no banco de dados." );
			return new \WP_Error( 'prepost_save_error', 'Erro ao salvar a pré-postagem no banco de dados.', [ 'status' => 400 ] );
		}



		return $created_prepost;
	}

	/**
	 * Get prepost by ID.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $prepostId
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|null
	 */
	public function getPrepost( $prepostId ) {
		return $this->prepostRepository->findById( $prepostId );
	}

	/**
	 * Get orders
	 * 
	 * @since 1.0.0
	 * 
	 * @param array{
	 * 			page: int,
	 * 			per_page: int
	 * 			search: string
	 * } $query Query parameters.
	 * 
	 * @return array
	 */
	public function listPreposts( $query = [] ) {
		$page = $query['page'] ?? 1;
		$per_page = $query['per_page'] ?? 10;
		$search = $query['search'] ?? null;

		$total_count = $this->prepostRepository->count();
		$data = $this->prepostRepository->paginate( $per_page, $page, $search );

		$items = [];

		foreach ( $data as $prepost ) {
			$items[] = $this->prepareData( $prepost );
		}

		return [
			'page' => $page,
			'per_page' => $per_page,
			'total_results' => count( $items ),
			'total' => $total_count,
			'preposts' => $items,
		];
	}

	/**
	 * Prepare data
	 * 
	 * @since 1.0.0
	 * 
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost
	 */
	public function prepareData( $prepost ) {
		return [
			"id" => (int) $prepost->id,
			"order_id" => (int) $prepost->order_id,
			"vendor_id" => $prepost->vendor_id ? (int) $prepost->vendor_id : null,
			"shipping_item_id" => $prepost->shipping_item_id ? (int) $prepost->shipping_item_id : null,
			"expire_at" => $prepost->expire_at,
			"created_at" => $prepost->created_at,
			"object_code" => $prepost->object_code,
			"service" => DeliveryServiceCode::getShortDescription( $prepost->service_code ),
			"status" => PrepostStatusCode::getStatus( $prepost->status ),
			"status_code" => (int) $prepost->status,
			"payment_type" => PaymentTypeCode::getDescription( $prepost->payment_type ),
			"dce" => (bool) $prepost->dce || null !== $this->dceAccessKey( $prepost ),
			"cancel_reason" => $prepost->cancel_reason ? (string) $prepost->cancel_reason : null,
			"dce_icms_blocked" => DceRejection::isIcmsTaxpayer( $prepost->cancel_reason ),
		];
	}

	/**
	 * Prepare the DC-e data of a prepost for the panel.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 *
	 * @return array
	 */
	public function prepareDceData( $prepost ) {
		$data = $prepost->toArray();
		$dce = $this->resolveDceFields( $prepost );

		return [
			'id' => (int) $prepost->id,
			'order_id' => (int) $prepost->order_id,
			'dce' => $dce['dce'],
			'dce_number' => $dce['dce_number'],
			'dce_series' => $dce['dce_series'],
			'dce_authorization_protocol' => isset( $data['dce_authorization_protocol'] ) ? $data['dce_authorization_protocol'] : null,
			'dce_access_key' => $dce['dce_access_key'],
			'dce_issued_at' => isset( $data['dce_issued_at'] ) ? $data['dce_issued_at'] : null,
		];
	}

	/**
	 * Access key of the DC-e of a prepost.
	 *
	 * The key of the DC-e was always stored: the Correios answer, in
	 * `chaveNFe`, the key of the fiscal document of the object, and on a
	 * shipment with DC-e that document is the DC-e itself — issued by them,
	 * under the CNPJ of the Correios and the model `99`. So the column of the
	 * DC-e is read first, and the key of the prepost answers for every row
	 * written before that column existed.
	 *
	 * An NF-e of the store (model `55`) is another document: it is never the
	 * key of a DC-e, and an object that travels with one has no DACE at all.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 *
	 * @return string|null Forty four digits, null when the prepost proves no DC-e.
	 */
	public function dceAccessKey( $prepost ) {
		$data = $prepost->toArray();
		$stored = AccessKey::normalize( isset( $data['dce_access_key'] ) ? $data['dce_access_key'] : null );

		if ( null !== $stored ) {
			return $stored;
		}

		$invoice_key = isset( $data['invoice_key'] ) ? $data['invoice_key'] : null;

		return AccessKey::isDce( $invoice_key ) ? AccessKey::normalize( $invoice_key ) : null;
	}

	/**
	 * DC-e fields of a prepost as the label has to print them.
	 *
	 * The number and the series of the document are printed inside its own
	 * access key, so a row that never stored them prints them all the same,
	 * and a row whose key only lives in `invoice_key` prints the DACE right
	 * away. Nothing here asks the Correios anything.
	 *
	 * What is deduced is written back on the row, since it is the same value
	 * the next read would deduce again — and the mark of the DC-e is written
	 * with it, or printDce() keeps refusing the DACE of an object whose key
	 * proves it has one.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 *
	 * @return array{dce: bool, dce_access_key: string|null, dce_number: string|null, dce_series: string|null}
	 */
	public function resolveDceFields( $prepost ) {
		$data = $prepost->toArray();
		$access_key = $this->dceAccessKey( $prepost );

		$fields = [
			'dce' => (bool) $prepost->dce || null !== $access_key,
			'dce_access_key' => $access_key,
			'dce_number' => $this->filledValue( $data, 'dce_number' ),
			'dce_series' => $this->filledValue( $data, 'dce_series' ),
		];

		if ( null === $access_key ) {
			return $fields;
		}

		if ( null === $fields['dce_number'] ) {
			$fields['dce_number'] = AccessKey::number( $access_key );
		}

		if ( null === $fields['dce_series'] ) {
			$fields['dce_series'] = AccessKey::series( $access_key );
		}

		$this->storeDceFields( $prepost, $fields );

		return $fields;
	}

	/**
	 * Write on the prepost what its own access key already proves.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 * @param array                                     $fields  Fields resolved for the label.
	 *
	 * @return bool True when the row was written.
	 */
	protected function storeDceFields( $prepost, $fields ) {
		$data = $prepost->toArray();
		$update_data = [];

		foreach ( [ 'dce_access_key', 'dce_number', 'dce_series' ] as $field ) {
			if ( null !== $fields[ $field ] && $this->filledValue( $data, $field ) !== $fields[ $field ] ) {
				$update_data[ $field ] = $fields[ $field ];
			}
		}

		if ( $fields['dce'] && ! $prepost->dce ) {
			$update_data['dce'] = 1;
		}

		if ( empty( $update_data ) || ! $this->ensureDceColumns() ) {
			return false;
		}

		if ( false === $this->prepostRepository->update( $prepost->id, $update_data ) ) {
			Log::notice( 'Erro ao gravar os dados da DC-e deduzidos da chave de acesso.', [ 'prepost_id' => (int) $prepost->id ] );

			return false;
		}

		return true;
	}

	/**
	 * Value of a column of a prepost, null when it is empty or not there.
	 *
	 * @since 1.11.0
	 *
	 * @param array  $data   Prepost as stored.
	 * @param string $column Column name.
	 *
	 * @return string|null
	 */
	protected function filledValue( $data, $column ) {
		if ( ! isset( $data[ $column ] ) || '' === (string) $data[ $column ] ) {
			return null;
		}

		return (string) $data[ $column ];
	}

	/**
	 * Prepare a failed DC-e read for the panel.
	 *
	 * The reason is decided where the read fails, so the print screen tells
	 * the shop owner what happened — and whether trying again is worth
	 * anything — without reading the message of the Correios.
	 *
	 * @since 1.11.0
	 *
	 * @param int       $prepost_id Prepost the read was about.
	 * @param \WP_Error $error      Failure of the read.
	 *
	 * @return array{id: int, order_id: int|null, reason: string, message: string}
	 */
	public function prepareDceFailure( $prepost_id, $error ) {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : [];

		return [
			'id' => (int) $prepost_id,
			'order_id' => empty( $data['order_id'] ) ? null : (int) $data['order_id'],
			'reason' => isset( $data['dce_reason'] ) ? (string) $data['dce_reason'] : self::DCE_REASON_TEMPORARY,
			'message' => isset( $data['dce_message'] ) ? (string) $data['dce_message'] : $error->get_error_message(),
		];
	}

	/**
	 * Sync Prepost
	 * 
	 * Synchronizes the prepost status from Correios API and updates invoice key if present.
	 * 
	 * @since 1.5.0
	 * 
	 * @param int $prepost_id
	 * 
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error
	 */
	public function sync( $prepost_id ) {
		/** @var \Infixs\CorreiosAutomatico\Models\Prepost $prepost */
		$prepost = $this->prepostRepository->findById( $prepost_id );

		if ( ! $prepost ) {
			Log::notice( "Pré-postagem inválida ao sincronizar." );
			return new \WP_Error( 'invalid_prepost_id', 'Pré-postagem inválida.', [ 'status' => 400 ] );
		}

		$response = $this->correiosService->get_prepost( $prepost->object_code, $prepost );

		if ( is_wp_error( $response ) ) {
			Log::notice( "Erro ao sincronizar pré-postagem.", [ 'error' => $response->get_error_message() ] );
			return $response;
		}

		if ( ! isset( $response['itens'] ) || empty( $response['itens'] ) ) {
			Log::notice( "Nenhuma pré-postagem encontrada nos Correios para o código: " . $prepost->object_code );
			return new \WP_Error( 'prepost_not_found', 'Pré-postagem não encontrada nos Correios.', [ 'status' => 404 ] );
		}

		$prepost_data = $response['itens'][0];

		// Prepare update data
		$update_data = [
			'status' => isset( $prepost_data['statusAtual'] ) ? $prepost_data['statusAtual'] : $prepost->status,
			'status_label' => isset( $prepost_data['descStatusAtual'] ) ? $prepost_data['descStatusAtual'] : $prepost->status_label,
		];

		$status = isset( $prepost_data['statusAtual'] ) ? (int) $prepost_data['statusAtual'] : (int) $prepost->status;

		if ( $this->shouldSyncDce( $prepost, $status ) ) {
			/**
			 * The stored key goes in as the tie breaker of the read. The fresh
			 * `chaveNFe` wins over the one on the row, since it is the value
			 * this same sync is about to persist below.
			 */
			$invoice_key = ! empty( $prepost_data['chaveNFe'] ) ? $prepost_data['chaveNFe'] : $prepost->invoice_key;

			$dce_text_data = $this->fetchDceTextData( $prepost, $invoice_key, $status );

			if ( ! is_wp_error( $dce_text_data ) ) {
				$update_data = array_merge( $update_data, $this->dceUpdateData( $prepost, $dce_text_data, $status ) );
			}
		}

		// Update invoice key (chaveNFe) if present in response
		if ( isset( $prepost_data['chaveNFe'] ) && ! empty( $prepost_data['chaveNFe'] ) ) {
			$update_data['invoice_key'] = $prepost_data['chaveNFe'];
		}

		$cancel_reason = isset( $prepost_data['motivoCancelamento'] ) ? trim( (string) $prepost_data['motivoCancelamento'] ) : '';

		if ( '' !== $cancel_reason && $this->ensureCancelReasonColumn() ) {
			$update_data['cancel_reason'] = mb_substr( $cancel_reason, 0, 500 );
		}

		// Update the prepost
		$updated = $this->prepostRepository->update( $prepost_id, $update_data );

		if ( ! $updated ) {
			Log::notice( "Erro ao atualizar pré-postagem." );
			return new \WP_Error( 'update_prepost_error', 'Erro ao atualizar pré-postagem.', [ 'status' => 400 ] );
		}

		// Refresh the prepost data
		$updated_prepost = $this->prepostRepository->findById( $prepost_id );

		if ( '' !== $cancel_reason && PrepostStatusCode::CANCELADO !== (int) $prepost->status && PrepostStatusCode::CANCELADO === $status ) {
			$this->noteCancelReason( $prepost, $cancel_reason );
		}

		Log::debug( 'Pré-postagem sincronizada com sucesso.', [
			'prepost_id' => $prepost_id,
			'object_code' => $prepost->object_code,
		] );

		return $updated_prepost;
	}

	/**
	 * Tell on the order why the Correios cancelled a prepost, with their message.
	 *
	 * A refused DC-e comes back a few seconds after the prepost, already
	 * cancelled, so the automatic prepost has no screen to show it. When the
	 * SEFAZ refused it because the store pays ICMS, the note says what to ship
	 * with instead.
	 *
	 * @since 1.14.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost cancelled.
	 * @param string                                    $reason  Message of the Correios.
	 *
	 * @return void
	 */
	private function noteCancelReason( $prepost, $reason ) {
		$order = wc_get_order( $prepost->order_id );

		if ( ! $order ) {
			return;
		}

		$note = sprintf(
			/* translators: 1: object code of the prepost, 2: message of the Correios */
			__( 'Correios Automático - Os Correios cancelaram a pré-postagem %1$s: %2$s', 'infixs-correios-automatico' ),
			$prepost->object_code,
			$reason
		);

		if ( DceRejection::isIcmsTaxpayer( $reason ) ) {
			$note .= ' ' . __( 'A loja é contribuinte de ICMS: desde 2 de novembro de 2026, a pré-postagem precisa sair com a NF-e. O SquidCore ERP emite a NF-e e já faz a pré-postagem com ela, automaticamente.', 'infixs-correios-automatico' );
		}

		$order->add_order_note( $note );
	}

	/**
	 * Run prepost sync from schedule.
	 *
	 * @since 1.6.57
	 *
	 * @param int $prepost_id
	 *
	 * @return void
	 */
	public function sync_schedule( $prepost_id ) {
		$response = $this->sync( $prepost_id );

		if ( is_wp_error( $response ) ) {
			Log::notice( 'Erro ao sincronizar pré-postagem via agendamento.', [
				'prepost_id' => $prepost_id,
				'error' => $response->get_error_message(),
			] );
		}
	}

	/**
	 * Make sure the DC-e of a prepost is stored whole.
	 *
	 * Three of the fields the DACE prints are inside the access key itself —
	 * the key, the number and the series —, and those are resolved for free.
	 * The date of emission and the authorisation protocol are NOT: only the
	 * text of the DACE carries them, so a row that still lacks them is read
	 * on demand, one call per prepost, and stored for good.
	 *
	 * Nothing else fills the two on an old row: the sync only runs right after
	 * the prepost is created, or by hand on the order screen. Answering the
	 * print screen just because the key is known would leave the DACE of the
	 * whole legacy base printing the time of the print as its emission.
	 *
	 * @since 1.11.0
	 *
	 * @param int $prepost_id Prepost id.
	 *
	 * @return \Infixs\CorreiosAutomatico\Models\Prepost|\WP_Error Prepost as stored, error with a readable reason.
	 */
	public function ensureDceData( $prepost_id ) {
		$this->ensureDceColumns();

		/** @var \Infixs\CorreiosAutomatico\Models\Prepost $prepost */
		$prepost = $this->prepostRepository->findById( $prepost_id );

		if ( ! $prepost ) {
			return $this->dceError( 'invalid_prepost_id', 'Pré-postagem inválida.', self::DCE_REASON_NOT_FOUND );
		}

		/**
		 * What the key of the prepost proves on its own is written before the
		 * Correios are asked anything, so a read that fails — or that never
		 * happens — still leaves the label with the key, the number and the
		 * series of the DC-e.
		 */
		$this->resolveDceFields( $prepost );

		$stored_prepost = $this->prepostRepository->findById( $prepost->id );
		$prepost = $stored_prepost ? $stored_prepost : $prepost;

		/**
		 * A prepost with the whole DC-e stored is answered for free. The one
		 * that still lacks the emission or the protocol is read: they are
		 * only in the text of the DACE, the cost is capped at one call per
		 * prepost by `dce_checked_at` and by the hold, and without the read
		 * the DACE would print the time of the print as the moment the SEFAZ
		 * authorised the document.
		 */
		if ( $this->hasStoredDceData( $prepost ) ) {
			return $prepost;
		}

		/**
		 * The key already prints the block, the number and the series, and
		 * what is still missing — the emission and the protocol — lives only
		 * on the text of the DACE, which the Correios issue while the object
		 * is pre-posted ("PPN-376: Não é possível emitir DACE para NF-e,
		 * objetos que não estejam prepostados ou sem chave DC-e"). Asking for
		 * an object that already left that state spends one call per prepost
		 * of the whole legacy base of the store, on the first print, to add
		 * nothing to the label. The periodic sync keeps filling the two while
		 * the shipment is still pre-posted.
		 */
		if ( null !== $this->dceAccessKey( $prepost ) && PrepostStatusCode::PREPOSTADO !== (int) $prepost->status ) {
			return $prepost;
		}

		/**
		 * The DACE of this object was already read, and a closed document
		 * answers the same text forever: reading it again would spend one
		 * call to the Correios per label, on every print, to be told the
		 * same — and the verdict recorded then is the answer given now.
		 */
		if ( ! empty( $prepost->dce_checked_at ) ) {
			return empty( $prepost->dce_access_key ) ? $this->dceReadFailure( $prepost, true ) : $prepost;
		}

		$dce_text_data = $this->fetchDceTextData( $prepost );

		if ( is_wp_error( $dce_text_data ) ) {
			return $dce_text_data;
		}

		$update_data = $this->dceUpdateData( $prepost, $dce_text_data );

		if ( ! empty( $update_data ) && false === $this->prepostRepository->update( $prepost->id, $update_data ) ) {
			Log::notice( 'Erro ao gravar os dados da DC-e da pré-postagem.', [ 'prepost_id' => (int) $prepost->id ] );

			return $this->dceError( 'update_prepost_error', 'Erro ao atualizar pré-postagem.', self::DCE_REASON_TEMPORARY, $prepost );
		}

		$updated_prepost = $this->prepostRepository->findById( $prepost->id );
		$updated_prepost = $updated_prepost ? $updated_prepost : $prepost;
		$keyless_reason = 'A DACE respondida pelos Correios não trouxe a chave de acesso da DC-e.';

		/**
		 * A DACE that answers without the fields the label needs is held too:
		 * the read worked, so nothing marks it, and the next print would ask
		 * the Correios for the same text all over again. Only while the object
		 * is still pré-postado, where the authorisation may yet arrive: on a
		 * closed document the read above already recorded the verdict.
		 */
		if ( ! $this->isDceVerdictFinal( $prepost ) ) {
			if ( empty( $updated_prepost->dce_access_key ) ) {
				$this->holdDceRetry( $prepost->id, $keyless_reason, self::DCE_REASON_NOT_AUTHORIZED );
			} elseif ( ! $this->hasStoredDceData( $updated_prepost ) ) {
				$this->holdDceRetry( $prepost->id, 'A DACE respondida pelos Correios não trouxe a data de emissão e o protocolo da DC-e.' );
			}
		}

		/**
		 * The DACE was read and still has no access key: the label has no QR
		 * Code to print, so the prepost is reported as a failure instead of
		 * being handed over as ready.
		 */
		if ( empty( $updated_prepost->dce_access_key ) ) {
			return $this->dceReadFailure( $updated_prepost, $this->isDceVerdictFinal( $prepost ) );
		}

		return $updated_prepost;
	}

	/**
	 * Whether the prepost carries everything the DACE prints.
	 *
	 * The key answers for itself, for the number and for the series. The date
	 * of emission and the authorisation protocol are printed only on the text
	 * of the DACE, so they are what still makes a read worth a call to the
	 * Correios.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 *
	 * @return bool
	 */
	protected function hasStoredDceData( $prepost ) {
		$data = $prepost->toArray();

		return null !== $this->dceAccessKey( $prepost )
			&& null !== $this->filledValue( $data, 'dce_issued_at' )
			&& null !== $this->filledValue( $data, 'dce_authorization_protocol' );
	}

	/**
	 * Read the DACE text of a prepost from the Correios API.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost     Prepost the DACE belongs to.
	 * @param string|null                               $invoice_key Key of the prepost to break ties with, the stored one when null.
	 * @param int|null                                  $status      Status to judge the prepost by, the stored one when null.
	 *
	 * @return array|\WP_Error Fields extracted from the text, error with a readable reason.
	 */
	protected function fetchDceTextData( $prepost, $invoice_key = null, $status = null ) {
		$this->ensureDceColumns();

		$available = $this->checkDceAvailable( $prepost, $status );

		if ( is_wp_error( $available ) ) {
			return $available;
		}

		$hold = $this->dceRetryHold( $prepost->id );

		if ( false !== $hold ) {
			return $this->dceError( 'dce_read_on_hold', $hold['message'], $hold['reason'], $prepost, $hold['server_message'] );
		}

		$response = $this->correiosService->printDce( $prepost->object_code, 'T', $prepost );

		if ( is_wp_error( $response ) ) {
			Log::notice( 'Erro ao ler a DACE da pré-postagem.', [
				'prepost_id' => (int) $prepost->id,
				'object_code' => $prepost->object_code,
				'error' => $response->get_error_message(),
			] );

			/**
			 * An object the Correios have no DACE for answers a business
			 * error, and it will answer the same one forever: the refusal is
			 * recorded on the prepost so the print screen stops paying a call
			 * for it. Everything else — token, throttling, server down, or an
			 * object still being pré-postado, whose DC-e the SEFAZ may still
			 * authorise — is held for a few minutes and asked again, and the
			 * panel is told the same thing that was recorded here.
			 */
			$final = $this->isDceFinalError( $response ) && $this->isDceVerdictFinal( $prepost, $status );

			if ( $final ) {
				$this->markDceChecked( $prepost );
			} else {
				$this->holdDceRetry( $prepost->id, $response->get_error_message(), self::DCE_REASON_TEMPORARY, $response->get_error_message() );
			}

			return $this->dceCorreiosError( $response, $prepost, $final );
		}

		if ( empty( $response['dados'] ) || ! is_string( $response['dados'] ) ) {
			$this->holdDceRetry( $prepost->id, 'Os Correios responderam a DACE sem conteúdo.' );

			return $this->dceError( 'dce_text_empty', 'Os Correios responderam a DACE sem conteúdo.', self::DCE_REASON_TEMPORARY, $prepost );
		}

		$invoice_key = null !== $invoice_key ? $invoice_key : $prepost->invoice_key;

		return $this->extractDceTextData( $response['dados'], $invoice_key );
	}

	/**
	 * Build what the DACE text adds to a prepost.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost       Prepost the DACE belongs to.
	 * @param array                                     $dce_text_data Fields read from the DACE text.
	 * @param int|null                                  $status        Status to judge the prepost by, the stored one when null.
	 *
	 * @return array Columns to write, empty when the text adds nothing.
	 */
	protected function dceUpdateData( $prepost, $dce_text_data, $status = null ) {
		$stored = $prepost->toArray();
		$update_data = [];

		/**
		 * A field the text does not carry keeps whatever was read before: the
		 * DACE is printed in more than one layout, and an emission already
		 * stored must not be erased by a shorter reprint.
		 */
		foreach ( $dce_text_data as $field => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			if ( isset( $stored[ $field ] ) && (string) $stored[ $field ] === (string) $value ) {
				continue;
			}

			$update_data[ $field ] = $value;
		}

		/**
		 * The Correios answering a DACE for the object proves the prepost has
		 * a DC-e: the flag is only written when the prepost is created, so a
		 * row created by `createPacket` — or created before the flag existed —
		 * keeps it at zero and has the DACE print refused by printDce().
		 */
		if ( ! empty( $update_data ) && ! $prepost->dce ) {
			$update_data['dce'] = 1;
		}

		/**
		 * The read itself is recorded, so a document that is already closed
		 * is never read twice — including the one that came back without the
		 * key, which is the answer of every prepost of a store that does not
		 * emit DC-e.
		 */
		if ( $this->isDceVerdictFinal( $prepost, $status ) ) {
			$update_data['dce_checked_at'] = current_time( 'mysql' );
		}

		return $update_data;
	}

	/**
	 * Whether the DACE of a prepost answers the same way from now on.
	 *
	 * While the object has not passed `PRÉ-POSTADO` — and `PREATENDIDO`, the
	 * status a prepost is created on, comes before it — the SEFAZ may still
	 * be authorising the DC-e, so a DACE without the key there says only "not
	 * yet" and the short hold asks again on its own. On any other status the
	 * document is closed and reading it again costs a call to the Correios
	 * for the same text.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 * @param int|null                                  $status  Status to judge it by, the stored one when null.
	 *
	 * @return bool
	 */
	protected function isDceVerdictFinal( $prepost, $status = null ) {
		$status = null === $status ? (int) $prepost->status : (int) $status;

		return ! in_array( $status, [ PrepostStatusCode::PREATENDIDO, PrepostStatusCode::PREPOSTADO ], true );
	}

	/**
	 * Whether the Correios refused the DACE for a reason that will not change.
	 *
	 * @since 1.11.0
	 *
	 * @param \WP_Error $error Error answered by the Correios.
	 *
	 * @return bool
	 */
	protected function isDceFinalError( $error ) {
		$data = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;

		return in_array( $status, self::DCE_FINAL_ERROR_STATUSES, true );
	}

	/**
	 * Record that the DACE of a prepost was already asked of the Correios.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 *
	 * @return void
	 */
	protected function markDceChecked( $prepost ) {
		$this->prepostRepository->update( $prepost->id, [ 'dce_checked_at' => current_time( 'mysql' ) ] );
	}

	/**
	 * Whether the sync has a reason to read the DACE of the prepost.
	 *
	 * The DACE exists as soon as the SEFAZ answers, which is every status but
	 * `PENDENTE` and `CANCELADO` — and it stays printable after the object is
	 * posted. Reading it is one call to the Correios, so outside `PREPOSTADO`,
	 * where the panel polls for the authorisation and the fields still change,
	 * it is only read when the key is missing. A row with no sign of a DC-e is
	 * turned down by checkDceAvailable(), which holds that rule for everyone.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost being synced.
	 * @param int                                       $status  Status the Correios just answered.
	 *
	 * @return bool
	 */
	protected function shouldSyncDce( $prepost, $status ) {
		if ( is_wp_error( $this->checkDceAvailable( $prepost, $status ) ) ) {
			return false;
		}

		if ( PrepostStatusCode::PREPOSTADO === (int) $status ) {
			return true;
		}

		return empty( $prepost->dce_access_key ) || empty( $prepost->dce_issued_at );
	}

	/**
	 * Whether the prepost travels with the NF-e of the store and no DC-e.
	 *
	 * The Correios issue no DACE for those objects, so there is nothing to
	 * print — the same rule the print screen follows
	 * (useDceDeclarationData.ts::hasStoreInvoiceOnly).
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 *
	 * @return bool
	 */
	public function hasStoreInvoiceOnly( $prepost ) {
		$data = $prepost->toArray();
		$invoice_key = AccessKey::normalize( isset( $data['invoice_key'] ) ? $data['invoice_key'] : null );

		return null !== $invoice_key && ! AccessKey::isDce( $invoice_key ) && ! $this->hasDce( $prepost );
	}

	/**
	 * Whether the prepost is known to carry a DC-e.
	 *
	 * A key of model `99` settles it on its own: it is the key of a DC-e the
	 * Correios issued for this object, whichever column of the row it is
	 * stored in. The mark and the number of the DC-e only cover the rows
	 * written since 1.7.4 and 1.7.5.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 *
	 * @return bool
	 */
	protected function hasDce( $prepost ) {
		$data = $prepost->toArray();

		return (bool) $prepost->dce
			|| null !== $this->dceAccessKey( $prepost )
			|| null !== $this->filledValue( $data, 'dce_number' )
			|| null !== $this->filledValue( $data, 'dce_authorization_protocol' );
	}

	/**
	 * Whether the DACE of a prepost can be asked of the Correios.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost.
	 * @param int|null                                  $status  Status to judge it by, the stored one when null.
	 *
	 * @return true|\WP_Error True when it can be asked, error with the reason otherwise.
	 */
	protected function checkDceAvailable( $prepost, $status = null ) {
		$status = null === $status ? (int) $prepost->status : (int) $status;

		/**
		 * A prepost with no sign of a DC-e has no DACE to read, and the print
		 * screen asks for it all the same — the mark only exists since 1.7.4,
		 * so an old row is a guess. The guess is answered here, for free,
		 * instead of costing a twenty second call per label on every print.
		 */
		if ( ! $this->hasDce( $prepost ) ) {
			return $this->dceNoDceError( $prepost );
		}

		if ( empty( $prepost->object_code ) ) {
			return $this->dceError( 'prepost_without_object_code', 'Pré-postagem sem código de objeto.', self::DCE_REASON_UNAVAILABLE, $prepost );
		}

		if ( ! empty( $prepost->cancelled_at ) || PrepostStatusCode::CANCELADO === $status ) {
			return $this->dceError( 'prepost_cancelled', 'Pré-postagem cancelada, a DC-e não pode ser consultada.', self::DCE_REASON_UNAVAILABLE, $prepost );
		}

		if ( PrepostStatusCode::PENDENTE === $status ) {
			return $this->dceError( 'dce_pending', 'A DC-e ainda está pendente de autorização na SEFAZ.', self::DCE_REASON_NOT_AUTHORIZED, $prepost );
		}

		return true;
	}

	/**
	 * Build the failure of a DC-e read the print screen can act on.
	 *
	 * @since 1.11.0
	 *
	 * @param string                                         $code           Error code.
	 * @param string                                         $message        Readable reason of the failure.
	 * @param string                                         $reason         Reason of the contract, one of `DCE_REASON_*`.
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost|null $prepost        Prepost the read was about.
	 * @param string                                         $server_message What the Correios answered, empty when they were not asked.
	 *
	 * @return \WP_Error
	 */
	protected function dceError( $code, $message, $reason, $prepost = null, $server_message = '' ) {
		return new \WP_Error( $code, $message, [
			'status' => 400,
			'dce_reason' => $reason,
			'dce_message' => $server_message,
			'order_id' => $prepost ? (int) $prepost->order_id : null,
		] );
	}

	/**
	 * Classify what the Correios answered to a DACE read.
	 *
	 * A business error on a document already closed is the same refusal
	 * tomorrow — no DC-e on the object, object no longer pré-postado, no key
	 * at the SEFAZ — and the key of a closed DC-e is never reissued. The same
	 * refusal on an object still being pré-postado says only that the SEFAZ
	 * has not authorised the DC-e yet, and authentication, throttling and
	 * server errors say nothing about the object: both are asked again.
	 *
	 * @since 1.11.0
	 *
	 * @param \WP_Error                                 $error   Error answered by the Correios.
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost the read was about.
	 * @param bool                                      $final   Whether the refusal answers the same way from now on.
	 *
	 * @return \WP_Error The same error, carrying the reason and the order.
	 */
	protected function dceCorreiosError( $error, $prepost, $final ) {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : [];

		$error->add_data( array_merge( $data, [
			'dce_reason' => $final ? self::DCE_REASON_UNAVAILABLE : self::DCE_REASON_TEMPORARY,
			'dce_message' => $error->get_error_message(),
			'order_id' => (int) $prepost->order_id,
		] ) );

		return $error;
	}

	/**
	 * Build the failure of a prepost left without the access key of its DC-e.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost the read was about.
	 * @param bool                                      $final   Whether the DACE answers the same way from now on.
	 *
	 * @return \WP_Error
	 */
	protected function dceReadFailure( $prepost, $final ) {
		if ( ! $this->hasDce( $prepost ) ) {
			return $this->dceNoDceError( $prepost );
		}

		if ( $final ) {
			return $this->dceError( 'dce_key_unavailable', 'A chave de acesso da DC-e deste objeto não pode mais ser recuperada.', self::DCE_REASON_UNAVAILABLE, $prepost );
		}

		return $this->dceError( 'dce_key_missing', 'A DC-e ainda não foi autorizada pela SEFAZ.', self::DCE_REASON_NOT_AUTHORIZED, $prepost );
	}

	/**
	 * Build the failure of a prepost that shows no sign of a DC-e.
	 *
	 * @since 1.11.0
	 *
	 * @param \Infixs\CorreiosAutomatico\Models\Prepost $prepost Prepost the read was about.
	 *
	 * @return \WP_Error
	 */
	protected function dceNoDceError( $prepost ) {
		return $this->dceError( 'prepost_without_dce', 'Esta pré-postagem não tem DC-e.', self::DCE_REASON_NO_DCE, $prepost );
	}

	/**
	 * Hold the DACE read of a prepost for a while after a failed attempt.
	 *
	 * The print screen asks for every order on it at once, so an object the
	 * Correios have no DACE for — no DC-e, contract without the API, object
	 * too old — would be asked again on every print, a twenty second call
	 * each. The hold is a short transient on purpose: the print after it
	 * expires tries again, and a DC-e authorised meanwhile is picked up
	 * without anyone having to clear anything.
	 *
	 * @since 1.11.0
	 *
	 * @param int    $prepost_id     Prepost id.
	 * @param string $message        Why the read failed.
	 * @param string $reason         Reason of the contract, one of `DCE_REASON_*`.
	 * @param string $server_message What the Correios answered, empty when the phrase is the plugin's.
	 *
	 * @return void
	 */
	protected function holdDceRetry( $prepost_id, $message, $reason = self::DCE_REASON_TEMPORARY, $server_message = '' ) {
		set_transient( self::DCE_RETRY_TRANSIENT_PREFIX . (int) $prepost_id, [
			'message' => $message,
			'reason' => $reason,
			'server_message' => $server_message,
		], self::DCE_RETRY_HOLD );
	}

	/**
	 * Read the hold left by the last failed DACE read of a prepost.
	 *
	 * A print inside the window answers out of the hold, so the failure is
	 * kept whole: the same reason, the same phrase and what the Correios
	 * answered, when they were the ones who said it. Otherwise the same
	 * prepost would come back with one verdict on one print and another on
	 * the next.
	 *
	 * @since 1.11.0
	 *
	 * @param int $prepost_id Prepost id.
	 *
	 * @return array{message: string, reason: string, server_message: string}|false Last failure, false when there is no hold.
	 */
	protected function dceRetryHold( $prepost_id ) {
		$hold = get_transient( self::DCE_RETRY_TRANSIENT_PREFIX . (int) $prepost_id );

		if ( false === $hold ) {
			return false;
		}

		/**
		 * A hold written before the failure was kept whole is the bare
		 * message of the Correios, and it lives in the cache for a few more
		 * minutes after the plugin is updated.
		 */
		if ( ! is_array( $hold ) ) {
			$hold = [ 'message' => (string) $hold, 'server_message' => (string) $hold ];
		}

		return [
			'message' => isset( $hold['message'] ) ? (string) $hold['message'] : '',
			'reason' => isset( $hold['reason'] ) ? (string) $hold['reason'] : self::DCE_REASON_TEMPORARY,
			'server_message' => isset( $hold['server_message'] ) ? (string) $hold['server_message'] : '',
		];
	}

	/**
	 * Make sure the DC-e columns exist before the DC-e path writes to them.
	 *
	 * The migration only runs when the stored version differs from the one in
	 * the plugin, so a store already on 1.11.0 from a build older than these
	 * columns never gets them — and then every prepost update fails, because
	 * the UPDATE writes a column the table does not have. The check sits
	 * behind a transient so `SHOW COLUMNS` is not paid on every request.
	 *
	 * @since 1.11.0
	 *
	 * @return bool True when the columns are in place.
	 */
	protected function ensureDceColumns() {
		return $this->ensureColumns( [ 'dce_access_key', 'dce_issued_at', 'dce_checked_at' ], self::DCE_COLUMNS_TRANSIENT, 'da DC-e' );
	}

	/**
	 * Make sure the column of the reason a prepost was cancelled exists before it is written.
	 *
	 * Same reason as ensureDceColumns(): the migration only runs when the
	 * version changes.
	 *
	 * @since 1.14.0
	 *
	 * @return bool True when the column is in place.
	 */
	protected function ensureCancelReasonColumn() {
		return $this->ensureColumns( [ 'cancel_reason' ], self::CANCEL_REASON_COLUMN_TRANSIENT, 'do motivo do cancelamento' );
	}

	/**
	 * Make sure the column of the contract of a prepost exists before a prepaid prepost is written.
	 *
	 * Same reason as ensureDceColumns(): the migration only runs when the
	 * version changes.
	 *
	 * @since 1.14.0
	 *
	 * @return bool True when the column is in place.
	 */
	protected function ensureContractSourceColumn() {
		return $this->ensureColumns( [ 'contract_source' ], self::CONTRACT_SOURCE_COLUMN_TRANSIENT, 'da origem do contrato' );
	}

	/**
	 * Make sure columns of the prepost table exist, running the migration when they do not.
	 *
	 * The check sits behind a transient so `SHOW COLUMNS` is not paid on every request.
	 *
	 * @since 1.14.0
	 *
	 * @param string[] $columns   Columns.
	 * @param string   $transient Transient that records they exist.
	 * @param string   $label     What the columns are, for the log.
	 *
	 * @return bool True when the columns are in place.
	 */
	private function ensureColumns( array $columns, $transient, $label ) {
		if ( get_transient( $transient ) ) {
			return true;
		}

		if ( ! $this->columnsExist( $columns ) ) {
			Migration::run();

			if ( ! $this->columnsExist( $columns ) ) {
				Log::notice( "As colunas {$label} não existem na tabela de pré-postagens." );

				return false;
			}

			Log::info( "Colunas {$label} criadas na tabela de pré-postagens." );
		}

		set_transient( $transient, 1, DAY_IN_SECONDS );

		return true;
	}

	/**
	 * Whether the prepost table already has the columns.
	 *
	 * @since 1.11.0
	 * @since 1.14.0 Takes the columns to look for.
	 *
	 * @param string[] $columns Columns.
	 *
	 * @return bool
	 */
	private function columnsExist( array $columns ) {
		global $wpdb;

		$table = $wpdb->prefix . 'infixs_correios_automatico_preposts';

		foreach ( $columns as $column ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Extract the DCe data printed on the DACE text.
	 *
	 * The text comes from the Correios API (`tipoDace` `T`) and not from the
	 * DACE PDF, so the same field shows up as `Num:`/`Nº:`, `Série`/`SERIE`
	 * and `Emissão`/`EMISSAO` depending on the layout: every label is matched
	 * without case and with the accent optional.
	 *
	 * @since 1.7.5
	 *
	 * @param string      $dce_text    DACE text.
	 * @param string|null $invoice_key Key stored on the same prepost, when
	 *                                 known, used to break a tie between keys
	 *                                 of an unknown model.
	 *
	 * @return array{dce_number: string|null, dce_series: string|null, dce_authorization_protocol: string|null, dce_access_key: string|null, dce_issued_at: string|null}
	 */
	protected function extractDceTextData( $dce_text, $invoice_key = null ) {
		$result = [
			'dce_number' => null,
			'dce_series' => null,
			'dce_authorization_protocol' => null,
			'dce_access_key' => null,
			'dce_issued_at' => null,
		];

		if ( ! is_string( $dce_text ) || '' === trim( $dce_text ) ) {
			return $result;
		}

		$number_series = $this->extractDceNumberAndSeries( $dce_text );
		$result['dce_number'] = $number_series['dce_number'];
		$result['dce_series'] = $number_series['dce_series'];

		if ( preg_match( '/Protocolo\s*(?:de\s*)?Autoriza[cç][aã]o\s*[:.\-]?\s*([0-9A-Za-z]+)/ui', $dce_text, $matches ) ) {
			$result['dce_authorization_protocol'] = $matches[1];
		}

		$result['dce_access_key'] = $this->extractDceAccessKey( $dce_text, $invoice_key );
		$result['dce_issued_at'] = $this->extractDceIssuedAt( $dce_text );

		return $result;
	}

	/**
	 * Extract the DC-e number and series printed on the DACE text.
	 *
	 * The two share a line, and the separator between them changes with the
	 * layout (`Nº: 1 SERIE: 1`, `Nº: 1 - SÉRIE: 1`). They are read from that
	 * line one at a time so a separator the pattern does not know leaves at
	 * most ONE of them empty, instead of dropping both.
	 *
	 * Reading is restricted to the line that mentions the series on purpose:
	 * `Nº` alone appears in the legal notices too (`Lei Complementar nº 87/96`),
	 * and a loose pattern would store `87` as the number of the document.
	 *
	 * @since 1.11.0
	 *
	 * @param string $dce_text DACE text.
	 *
	 * @return array{dce_number: string|null, dce_series: string|null}
	 */
	protected function extractDceNumberAndSeries( $dce_text ) {
		$result = [ 
			'dce_number' => null,
			'dce_series' => null,
		];

		foreach ( preg_split( '/\R/u', $dce_text ) as $line ) {
			if ( ! preg_match( '/S[ée]rie/ui', $line ) ) {
				continue;
			}

			if ( preg_match( '/N(?:um|[º°o])?\s*[:.\-]?\s*([0-9]+)/ui', $line, $matches ) ) {
				$result['dce_number'] = $matches[1];
			}

			if ( preg_match( '/S[ée]rie\s*[:.\-]?\s*([0-9A-Za-z]+)/ui', $line, $matches ) ) {
				$result['dce_series'] = $matches[1];
			}

			break;
		}

		if ( null !== $result['dce_number'] && null !== $result['dce_series'] ) {
			return $result;
		}

		/**
		 * Layout that breaks the line between the two fields: reads them
		 * together over the whole text, which is how the field was read before
		 * and still covers `Nº: 1\n SERIE: 1`. Only fills what is missing, so
		 * a value already read from the line is never replaced.
		 */
		if ( preg_match( '/N(?:um|[º°o])?\s*[:.\-]?\s*([0-9]+)[\s\-–|]+S[ée]rie\s*[:.\-]?\s*([0-9A-Za-z]+)/ui', $dce_text, $matches ) ) {
			if ( null === $result['dce_number'] ) {
				$result['dce_number'] = $matches[1];
			}

			if ( null === $result['dce_series'] ) {
				$result['dce_series'] = $matches[2];
			}
		}

		return $result;
	}

	/**
	 * Extract the DC-e access key printed on the DACE text.
	 *
	 * The key is printed in groups of four digits, sometimes broken across
	 * lines, and the text may carry MORE than one access key: the prepost also
	 * travels with the NF-e key (`chaveNFe`), and a layout that prints both
	 * would otherwise store the key of the invoice as the key of the DC-e —
	 * which is what the label and the QR Code (`chDCe=`) then point to.
	 *
	 * So the candidates are taken in order of how sure they are:
	 *
	 *   1. a key labelled as the DC-e (`Chave de Acesso DC-e`);
	 *   2. a key under a plain `Chave de Acesso` label;
	 *   3. any run of forty four digits printed on the text.
	 *
	 * The order only breaks ties: what decides is the model printed inside
	 * the key itself, which is the only thing that tells the two documents
	 * apart — see pickDceAccessKey().
	 *
	 * @since 1.11.0
	 *
	 * @param string      $dce_text    DACE text.
	 * @param string|null $invoice_key NF-e key of the same prepost, used to
	 *                                 break a tie between keys of an unknown
	 *                                 model.
	 *
	 * @return string|null Forty four digits, null when the text has no key.
	 */
	protected function extractDceAccessKey( $dce_text, $invoice_key = null ) {
		$candidates = [];

		if ( preg_match( '/Chave\s*de\s*Acesso\s*(?:d[ae]\s*)?DC\s*-?\s*e[^0-9]{0,60}((?:[0-9][\s.\-]*){44})/ui', $dce_text, $matches ) ) {
			$candidates[] = $matches[1];
		}

		if ( preg_match_all( '/Chave\s*de\s*Acesso[^0-9]{0,60}((?:[0-9][\s.\-]*){44})/ui', $dce_text, $matches ) ) {
			$candidates = array_merge( $candidates, $matches[1] );
		}

		foreach ( preg_split( '/\R/u', $dce_text ) as $line ) {
			if ( preg_match_all( '/[0-9][0-9 \t.\-]*[0-9]/u', $line, $matches ) ) {
				$candidates = array_merge( $candidates, $matches[0] );
			}
		}

		return $this->pickDceAccessKey( $candidates, $invoice_key );
	}

	/**
	 * Choose the key of the DC-e among the keys printed on the DACE text.
	 *
	 * The model printed inside the key decides, and nothing else: a key of
	 * model `99` IS the DC-e, and a key of model `55` is the invoice of the
	 * store and never the DC-e.
	 *
	 * Matching the `chaveNFe` of the prepost proves nothing on its own — on a
	 * shipment with DC-e that column holds the key of the DC-e itself, since
	 * the Correios answer the key of the fiscal document of the object, which
	 * is the DC-e they issued. So the comparison is only a tie breaker among
	 * keys whose model is neither of the two.
	 *
	 * @since 1.11.0
	 *
	 * @param string[]    $candidates  Runs of digits read from the text.
	 * @param string|null $invoice_key Key stored on the prepost, when known.
	 *
	 * @return string|null Forty four digits, null when no candidate can be the DC-e.
	 */
	protected function pickDceAccessKey( $candidates, $invoice_key ) {
		$stored_key = AccessKey::normalize( $invoice_key );
		$unknown_model = [];

		foreach ( $candidates as $candidate ) {
			$digits = AccessKey::normalize( $candidate );

			if ( null === $digits ) {
				continue;
			}

			if ( AccessKey::isDce( $digits ) ) {
				return $digits;
			}

			if ( AccessKey::isInvoice( $digits ) || $digits === $stored_key ) {
				continue;
			}

			$unknown_model[] = $digits;
		}

		return isset( $unknown_model[0] ) ? $unknown_model[0] : null;
	}

	/**
	 * Extract the DC-e emission date printed on the DACE text.
	 *
	 * The date is the one the SEFAZ authorized, which is what the label has to
	 * print: the printing date is not the emission date.
	 *
	 * @since 1.11.0
	 *
	 * @param string $dce_text DACE text.
	 *
	 * @return string|null Date as `Y-m-d H:i:s`, null when the text has none.
	 */
	protected function extractDceIssuedAt( $dce_text ) {
		$pattern = '/(?:Data\s*d[eo]\s*)?Emiss[aã]o\s*[:.\-]?\s*([0-9]{1,2})[\/.\-]([0-9]{1,2})[\/.\-]([0-9]{2,4})(?:[^0-9]{0,6}([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?)?/ui';

		if ( ! preg_match( $pattern, $dce_text, $matches ) ) {
			return null;
		}

		$day = (int) $matches[1];
		$month = (int) $matches[2];
		$year = (int) $matches[3];

		if ( $year < 100 ) {
			$year += 2000;
		}

		if ( ! checkdate( $month, $day, $year ) ) {
			return null;
		}

		$hour = isset( $matches[4] ) ? (int) $matches[4] : 0;
		$minute = isset( $matches[5] ) ? (int) $matches[5] : 0;
		$second = isset( $matches[6] ) ? (int) $matches[6] : 0;

		if ( $hour > 23 || $minute > 59 || $second > 59 ) {
			$hour = 0;
			$minute = 0;
			$second = 0;
		}

		return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second );
	}

	/**
	 * Cancel Prepost
	 * 
	 * PRO Feature: https://infixs.com/product/correios-automatico-rastreio-etiqueta-e-frete-versao-pro/
	 * 
	 * @param int $prepost_id
	 * 
	 * @return array|\WP_Error
	 */
	public function cancelPrepost( $prepost_id ) {
		$reponse = apply_filters(
			'infixs_correios_automatico_service_cancel_prepost',
			new \WP_Error(
				'cancel_prepost_pro_feature',
				'Essa funcionalidade é uma feature da versão PRO, considerer adquirir a versão PRO para utilizar essa funcionalidade.',
				[
					'status' => 400,
					'buy_pro_url' => Plugin::PRO_URL
				]
			),
			$prepost_id,
			$this->prepostRepository,
			$this->correiosService,
		);

		return $reponse;
	}

	/**
	 * Delete Prepost
	 * 
	 * @param int $prepost_id
	 * 
	 * @return bool|\WP_Error
	 */
	public function deletePrepost( $prepost_id ) {
		/** @var \Infixs\CorreiosAutomatico\Models\Prepost $prepost */
		$prepost = $this->prepostRepository->findById( $prepost_id );

		if ( ! $prepost ) {
			return new \WP_Error( 'invalid_prepost_id', 'Pré-postagem inválida.', [ 'status' => 400 ] );
		}

		if ( ! $this->prepostRepository->delete( $prepost_id ) ) {
			return new \WP_Error( 'delete_prepost_error', 'Erro ao deletar a pré-postagem.', [ 'status' => 400 ] );
		} else {
			Container::trackingService()->deleteTrackingByCode( $prepost->object_code );
			return true;
		}
	}

	/**
	 * Print DCe (Documento de Coleta Eletrônico) for a prepost.
	 * 
	 * @since 1.6.0
	 * 
	 * @param int $prepost_id
	 * @param string $dace_type 'C' = Completa, 'R' = Resumida
	 * 
	 * @return array|\WP_Error
	 */
	public function printDce( $prepost_id, $dace_type = 'C' ) {
		/** @var \Infixs\CorreiosAutomatico\Models\Prepost $prepost */
		$prepost = $this->prepostRepository->findById( $prepost_id );

		if ( ! $prepost ) {
			return new \WP_Error( 'invalid_prepost_id', 'Pré-postagem inválida.', [ 'status' => 400 ] );
		}

		/**
		 * The mark of the DC-e is only written when the prepost is created,
		 * and only since 1.7.4: a key of model `99` on the row proves the
		 * document exists, so the print is not refused over a flag the row
		 * never had a chance to carry.
		 */
		if ( ! $this->hasDce( $prepost ) ) {
			return new \WP_Error( 'prepost_without_dce', 'Esta pré-postagem não possui DCe.', [ 'status' => 400 ] );
		}

		// Call Correios API to get DCe PDF
		$response = $this->correiosService->printDce( $prepost->object_code, $dace_type, $prepost );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}
}
