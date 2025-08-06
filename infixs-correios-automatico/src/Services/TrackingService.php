<?php

namespace Infixs\CorreiosAutomatico\Services;

use Infixs\CorreiosAutomatico\Container;
use Infixs\CorreiosAutomatico\Core\Admin\WooCommerce\Tracking;
use Infixs\CorreiosAutomatico\Core\Support\Config;
use Infixs\CorreiosAutomatico\Core\Support\Log;
use Infixs\CorreiosAutomatico\Models\TrackingCode;
use Infixs\CorreiosAutomatico\Models\TrackingRangeCode;
use Infixs\CorreiosAutomatico\Repositories\ConfigRepository;
use Infixs\CorreiosAutomatico\Repositories\TrackingRepository;
use Infixs\CorreiosAutomatico\Services\Correios\CorreiosService;
use Infixs\CorreiosAutomatico\Services\Correios\Enums\APIServiceCode;
use Infixs\CorreiosAutomatico\Utils\Helper;
use Infixs\WordpressEloquent\Collection;

defined( 'ABSPATH' ) || exit;

/**
 * Tracking service.
 * 
 * @package Infixs\CorreiosAutomatico
 * @since   1.0.0
 */
class TrackingService {

	/**
	 * Tracking repository.
	 * 
	 * @var TrackingRepository
	 * @since 1.0.0
	 */
	protected $trackingRepository;


	/**
	 * Correios Service
	 * 
	 * @var CorreiosService
	 */
	protected $correiosService;

	/**
	 * Config repository.
	 * 
	 * @var ConfigRepository
	 */
	protected $configRepository;

	/**
	 * Tracking Service constructor.
	 * 
	 * @param TrackingRepository $trackingRepository
	 * @since 1.0.0
	 */
	public function __construct( TrackingRepository $trackingRepository, CorreiosService $correiosService, ConfigRepository $configRepository ) {
		$this->trackingRepository = $trackingRepository;
		$this->correiosService = $correiosService;
		$this->configRepository = $configRepository;
	}

	/**
	 * Add a tracking code.
	 * 
	 * This method is responsible for adding a tracking code to the order.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $order_id Order ID.
	 * @param string $code Tracking code.
	 * @param bool $customer_send_email Send email to customer.
	 * 
	 * @return TrackingCode|\WP_Error The TrackingCode or false on error.
	 */
	public function add( $order_id, $code, $customer_send_email = false, $data = [] ) {

		Log::debug( "Adicionando código de rastreio ao pedido {$order_id}." );

		$code = trim( sanitize_text_field( $code ) );

		if ( empty( $code ) ) {
			return new \WP_Error( 'tracking_code_empty', 'O código de rastreio não pode ser vazio.' );
		}

		if ( $this->trackingRepository->exists( [ 'order_id' => $order_id, 'code' => $code ] ) ) {
			Log::debug( "Add tracking code: Já existe o código de rastreio {$code} para o pedido {$order_id}." );
			return new \WP_Error( 'tracking_code_exists', 'Já existe o mesmo código de rastreio neste pedido, tente adicionar outro.' );
		}

		$created = $this->trackingRepository->create( [ 
			'order_id' => $order_id,
			'code' => $code,
			'user_id' => get_current_user_id(),
			'tracking_range_code_id' => $data['tracking_range_code_id'] ?? null,
		] );

		if ( ! $created ) {
			return new \WP_Error( 'tracking_code_not_created', 'Não foi possível adicionar o código de rastreio ao pedido.' );
		}

		$order = wc_get_order( $order_id );

		if ( Config::boolean( 'general.tracking_compatiblity' ) ) {
			$tracking_codes = $order->get_meta( '_correios_tracking_code' );
			$tracking_codes = array_filter( explode( ',', $tracking_codes ) );

			if ( ! in_array( $code, $tracking_codes ) ) {
				$tracking_codes[] = $code;
				$order->update_meta_data( '_correios_tracking_code', implode( ',', $tracking_codes ) );
			}
		}

		do_action( 'infixs_correios_automatico_after_add_tracking_code', $code, $order_id );

		$order->add_order_note(
			sprintf(
				/* translators: %1$s - Tracking code */
				__( 'Correios Automático - Novo código de rastreio adicionado ao pedido: %1$s', 'infixs-correios-automatico' ),
				$code
			),
			false
		);
		$order->save();

		Log::debug( "Código de rastreio {$code} adicionado ao pedido {$order_id} com sucesso." );

		if ( $customer_send_email ) {
			if ( $this->sendTrackingNotification( $order_id, $code ) ) {
				$created->customer_email_at = current_time( 'mysql' );
				$created->save();
			}
		}

		return $created;
	}


	/**
	 * Send tracking email notification.
	 * 
	 * @since 1.2.3
	 * 
	 * @param int $order_id Order ID.
	 * @param string|array $tracking_code Tracking code string or array.
	 * 
	 * @return bool
	 */
	public function sendTrackingNotification( $order_id, $tracking_code ) {
		$success = Tracking::trigger_tracking_code_email( $order_id, $tracking_code );

		if ( $success ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( '_infixs_correios_automatico_email_tracking_sent', current_time( 'mysql' ) );
			$order->add_order_note(
				__( 'Correios Automático - Email de código de rastreio enviado ao cliente', 'infixs-correios-automatico' ),
				false
			);
			$order->save();
		}

		return $success;
	}

	/**
	 * Send waiting pickup.
	 * 
	 * @since 1.4.4
	 * 
	 * @param int $order_id Order ID.
	 * @param string|array $tracking_code Tracking code string or array.
	 * 
	 * @return bool
	 */
	public function sendWaitingPickupNotification( $order_id ) {
		$tracking_codes = TrackingCode::query()->with( 'events' )->whereHas( 'events', function ($query) {
			$query->where( 'code', 'LDI' );
		} )->where( 'order_id', $order_id )->get();

		if ( $tracking_codes->isEmpty() ) {
			return false;
		}

		/**
		 * @var TrackingCode $tracking_code
		 */
		$tracking_code = $tracking_codes->first();

		/**
		 * @var \Infixs\CorreiosAutomatico\Models\TrackingCodeEvent $last_pickup_event
		 */
		$last_pickup_event = $tracking_code->events->firstWhere( 'code', 'LDI' );

		if ( ! $last_pickup_event ) {
			return false;
		}

		$full_address = $last_pickup_event->location_type . ': ' . $last_pickup_event->location_address . ', ' . $last_pickup_event->location_number . ', ' . $last_pickup_event->location_neighborhood . ', ' . $last_pickup_event->location_city . ', ' . $last_pickup_event->location_state . ', ' . $last_pickup_event->location_postcode;

		$success = Tracking::trigger_waiting_pickup_email( $order_id, $tracking_code->code, $full_address );

		if ( $success ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( '_infixs_correios_automatico_email_waiting_pickup_sent', current_time( 'mysql' ) );
			$order->add_order_note(
				__( 'Correios Automático - Email de "Aguardando retirada" enviado para o cliente', 'infixs-correios-automatico' ),
				false
			);
			$order->save();
		}

		return $success;
	}

	/**
	 * Send preparing to ship email notification.
	 * 
	 * @since 1.2.3
	 * 
	 * @param \WC_Order $order Order data.
	 * @param string|array $tracking_code Tracking code string or array.
	 * 
	 * @return bool
	 */
	public function sendPreparingToShipNotification( $order_id, $tracking_code = [] ) {
		$success = Tracking::trigger_preparing_to_ship_email( $order_id, $tracking_code );

		if ( $success ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( '_infixs_correios_automatico_email_preparing_sent', current_time( 'mysql' ) );
			$order->add_order_note(
				__( 'Correios Automático - Email de "Pedido sendo preparado" foi enviado para o cliente', 'infixs-correios-automatico' ),
				false
			);
			$order->save();
		}

		return $success;
	}

	/**
	 * Send return notification.
	 * 
	 * @since 1.4.4
	 * 
	 * @param int $order_id Order ID.
	 * @param string|array $tracking_code Tracking code string or array.
	 * 
	 * @return bool
	 */
	public function sendReturningNotification( $order_id, $tracking_code, $pickup_address ) {
		$success = Tracking::trigger_returning_email( $order_id, $tracking_code, $pickup_address );

		if ( $success ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( '_infixs_correios_automatico_email_returning_sent', current_time( 'mysql' ) );
			$order->add_order_note(
				__( 'Correios Automático - Email de Devolução enviado para o cliente', 'infixs-correios-automatico' ),
				false
			);
			$order->save();
		}

		return $success;
	}

	/**
	 * Get trackings codes.
	 * 
	 * @since 1.2.3
	 * 
	 * @param int $order_id Order ID.
	 * 
	 * @return Collection|false
	 */
	public function getTrackings( $order_id, $with_events = false ) {
		return $this->trackingRepository->findBy( [ 'order_id' => $order_id ], $with_events ? [ 'with_events' => true ] : [] );
	}

	/**
	 * Get trackings by codes.
	 * 
	 * @since 1.2.3
	 * 
	 * @param array $codes Object codes.
	 * 
	 * @return Collection
	 */
	public function getTrackingsByCodes( $codes, $with_events = false ) {
		return $this->trackingRepository->whereIn( 'code', $codes, $with_events ? [ 'with_events' => true ] : [] );
	}

	/**
	 * Get tracking by code
	 * 
	 * @since 1.5.3
	 * 
	 * @param string $code.
	 * 
	 * @return TrackingCode|null
	 */
	public function getTrackingByCode( $code ) {
		return $this->trackingRepository->retrieveByTrackingCode( $code );
	}

	/**
	 * Delete tracking by code
	 * 
	 * @since 1.5.3
	 * 
	 * @param string $code.
	 * 
	 * @return bool|int
	 */
	public function deleteTrackingByCode( $code ) {
		$tracking = $this->getTrackingByCode( $code );

		return $tracking ? $this->delete( $tracking->id ) : false;
	}

	/**
	 * Get trackings by ids.
	 * 
	 * @since 1.2.3
	 * 
	 * @param array $ids Tracking codes ids.
	 * 
	 * @return Collection
	 */
	public function getTrackingsByIds( $ids, $with_events = false ) {
		return $this->trackingRepository->whereIn( 'id', $ids, $with_events ? [ 'with_events' => true ] : [] );
	}

	/**
	 * Delete a tracking code.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $tracking_id Tracking ID.
	 * 
	 * @return int|bool The number of rows affected or false on error.
	 */
	public function delete( $tracking_id ) {
		/**
		 * @var TrackingCode
		 */
		$tracking = $this->trackingRepository->retrieve( $tracking_id );

		if ( Config::boolean( 'general.tracking_compatiblity' ) ) {
			if ( $tracking ) {
				$order = wc_get_order( $tracking->order_id );
				if ( $order ) {
					$tracking_codes = $order->get_meta( '_correios_tracking_code' );
					$tracking_codes = array_filter( explode( ',', $tracking_codes ) );
					if ( in_array( $tracking->code, $tracking_codes ) ) {
						$key = array_search( $tracking->code, $tracking_codes, true );
						if ( false !== $key ) {
							unset( $tracking_codes[ $key ] );
						}
						$order->update_meta_data( '_correios_tracking_code', implode( ',', $tracking_codes ) );
						$order->save();
					}
				}
			}
		}

		if ( $tracking->tracking_range_code_id ) {
			$tracking_code = TrackingRangeCode::find( $tracking->tracking_range_code_id );

			if ( $tracking_code ) {
				$tracking_code->is_used = false;
				$tracking_code->save();
			}
		}

		return $this->trackingRepository->delete( $tracking_id );
	}

	/**
	 * List tracking codes.
	 * 
	 * @param mixed $order_id
	 * @param array $config {
	 * 		@type array $order {
	 * 			@type string $column Column name.
	 * 			@type string $order Order direction "asc" or "desc".
	 * 		}
	 * }
	 * 
	 * @return \Infixs\WordpressEloquent\Collection
	 */
	public function list( $order_id, $config = [] ) {
		return $this->trackingRepository->findBy( [ 'order_id' => $order_id ], $config );
	}

	public function getByUnit( $unit_id ) {
		return $this->trackingRepository->paginate( [ 
			'relations' => [ 'events' ],
			'where' => [ 'unit_id' => $unit_id ],
		],
			[ $this, 'prepare_tracking_data' ]
		);
	}

	/**
	 * Sync remote tracking code.
	 * 
	 * @since 1.2.1
	 * 
	 * @param TrackingCode $tracking
	 * 
	 * @return TrackingCode|\WP_Error
	 */
	public function sync_remote_tracking_code( TrackingCode $tracking ) {

		$response = $this->configRepository->boolean( 'auth.active' ) && Helper::contractHasService( APIServiceCode::SRO_RASTRO )
			? $this->correiosService->get_object_tracking( $tracking->code )
			: Container::infixsApi()->getTrackingHistory( $tracking->code );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['objetos'], $response['objetos'][0] ) ) {
			return new \WP_Error( 'tracking_code_not_found', 'Código de rastreio não encontrado.', [ 'status' => 404 ] );
		}

		$object = $response['objetos'][0];

		$process_response = $this->process_object_events( $object, $tracking );

		if ( is_wp_error( $process_response ) ) {
			return $process_response;
		}

		return $tracking;
	}

	/**
	 * Process object events.
	 * 
	 * @since 1.2.1
	 * 
	 * @param array $object
	 * @param TrackingCode $tracking
	 * 
	 * @return TrackingCode|\WP_Error
	 */
	public function process_object_events( $object, TrackingCode $tracking ) {
		if ( isset( $object['mensagem'] ) && strpos( $object['mensagem'], 'Objeto não encontrado na base de dados dos Correios.' ) !== false ) {
			return new \WP_Error( 'tracking_code_not_found', 'Código de rastreio não encontrado na base de dados dos Correios.', [ 'status' => 404 ] );
		}

		if ( isset( $object['mensagem'] ) && strpos( $object['mensagem'], 'Objeto não pertence ao contrato' ) !== false ) {
			return new \WP_Error( 'tracking_code_not_contract', 'Objeto não pertence ao contrato ou ainda não foi postado.', [ 'status' => 404 ] );
		}

		//SRO-019: Objeto inválido TODO: Implmente this

		if ( isset( $object['tipoPostal'], $object['tipoPostal']['categoria'] ) )
			$tracking->category = $object['tipoPostal']['categoria'];

		if ( isset( $object['tipoPostal'], $object['tipoPostal']['descricao'] ) )
			$tracking->description = $object['tipoPostal']['descricao'];

		if ( isset( $object['dtPrevista'] ) )
			$tracking->expected_date = $object['dtPrevista'];

		$tracking->sync_at = current_time( 'mysql' );

		$tracking->save();

		if ( isset( $object['eventos'] ) ) {

			$reverse_events = array_reverse( $object['eventos'] );

			foreach ( $reverse_events as $event ) {

				$newEventDate = new \DateTime( $event['dtHrCriado'] );
				$newEventDateSql = $newEventDate->format( 'Y-m-d H:i:s' );

				if ( ! isset( $tracking->events ) || $tracking->events->contains( 'event_date', $newEventDateSql ) )
					continue;

				$createdEvent = $tracking->events()->create( apply_filters( "infixs_correios_automatico_add_tracking_event", [ 
					'code' => $event['codigo'],
					'type' => $event['tipo'],
					'description' => $event['descricao'],
					'detail' => $event['detalhe'] ?? null,
					'location_type' => $event['unidade']['tipo'] ?? null,
					'location_address' => $event['unidade']['endereco']['logradouro'] ?? null,
					'location_number' => $event['unidade']['endereco']['numero'] ?? null,
					'location_neighborhood' => $event['unidade']['endereco']['bairro'] ?? null,
					'location_city' => $event['unidade']['endereco']['cidade'] ?? null,
					'location_state' => $event['unidade']['endereco']['uf'] ?? null,
					'location_postcode' => $event['unidade']['endereco']['cep'] ?? null,
					'event_date' => $newEventDateSql,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				], $event, $tracking ) );

				do_action( 'infixs_correios_automatico_after_add_tracking_event', $createdEvent, $event, $tracking );

				$tracking->events->push( $createdEvent );
			}
		}

		return $tracking;
	}


	/**
	 * Sync remote tracking code.
	 * 
	 * @since 1.2.1
	 * 
	 * @param Collection $trackings
	 * 
	 * @return TrackingCode|false
	 */
	public function sync_remote_mutiples_tracking_codes( $trackings ) {

		$codes = $trackings->pluck( 'code' )->toArray();

		if ( ! $this->configRepository->boolean( 'auth.active' ) || ! Helper::contractHasService( APIServiceCode::SRO_RASTRO ) ) {
			return false;
		}

		$response = $this->correiosService->get_object_trackings( $codes );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( ! isset( $response['objetos'] ) || empty( $response['objetos'] ) ) {
			return false;
		}

		foreach ( $response['objetos'] as $object ) {
			if ( ! isset( $object['codObjeto'] ) )
				continue;

			$trackings_array = $trackings->where( 'code', $object['codObjeto'] );

			foreach ( $trackings_array as $track ) {
				$this->process_object_events( $object, $track );
			}
		}

		return true;
	}

	/**
	 * Get object tracking by ID.
	 * 
	 * @since 1.2.1
	 * 
	 * @param string $id
	 * 
	 * @return array|\WP_Error
	 */
	public function getObjectTrackingById( $id, $sync = true, $force_sync = false ) {
		$tracking = $this->trackingRepository->retrieve( $id, true );

		if ( ! $tracking ) {
			return new \WP_Error( 'tracking_code_not_found', 'Código de rastreio não encontrado.' );
		}

		return $this->getObjectTracking( $tracking, $sync, $force_sync );
	}

	/**
	 * Get object tracking by Code.
	 * 
	 * @since 1.2.1
	 * 
	 * @param string $code
	 * 
	 * @return array|\WP_Error
	 */
	public function getObjectTrackingByCode( $code, $sync = true ) {
		$tracking = $this->trackingRepository->retrieveByTrackingCode( $code );

		if ( ! $tracking ) {
			return new \WP_Error( 'tracking_code_not_found', 'Código de rastreio não encontrado.' );
		}

		return $this->getObjectTracking( $tracking, $sync );
	}

	/**
	 * Get object tracking events.
	 * 
	 * @since 1.2.1
	 * 
	 * @param TrackingCode $tracking
	 * 
	 * @return array|\WP_Error
	 */
	public function getObjectTracking( TrackingCode $tracking, $sync = true, $force_sync = false ) {
		if ( $sync ) {
			$lastDate = new \DateTime( $tracking->sync_at ?: 'now' );
			$currentDate = new \DateTime( current_time( 'mysql' ) );
			$interval = $lastDate->diff( $currentDate );

			if ( $interval->h >= 3 || ( $interval->d > 0 ) || $tracking->sync_at === null || $force_sync === true ) {
				$response = $this->sync_remote_tracking_code( $tracking );
				if ( is_wp_error( $response ) ) {
					return $response;
				}

				$tracking = $response;
			}

		}

		return $this->prepare_tracking_data( $tracking );
	}

	public function get_order_tracking_history( $order_id, $sync = true ) {
		$tracking_codes = $this->list( $order_id, [ 
			'with_events' => true,
			'order' => [ 
				'column' => 'created_at',
				'order' => 'desc',
			],
		] );

		$history = [];

		foreach ( $tracking_codes as $tracking ) {
			$response = $this->getObjectTrackingById( $tracking->id, $sync );
			if ( ! is_wp_error( $response ) ) {
				$history[] = $response;
			} else {
				$history[] = $this->prepare_tracking_data( $tracking );
			}
		}

		return $history;
	}

	/**
	 * Prepare tracking data.
	 * 
	 * @since 1.2.1
	 * 
	 * @param TrackingCode $tracking
	 * 
	 * @return array
	 */
	public function prepare_tracking_data( TrackingCode $tracking ) {
		$history = [ 
			'id' => $tracking->id,
			'code' => $tracking->code,
			'category' => $tracking->category,
			'expected_date' => $tracking->expected_date,
			'events' => [],
			'last_sync' => $tracking->sync_at,
		];

		if ( ! $tracking->relationLoaded( 'events' ) ) {
			return $history;
		}

		$events_by_date = $tracking->events->sortByDesc( 'event_date' );

		foreach ( $events_by_date as $event ) {
			$history['events'][] = [ 
				'code' => $event->code,
				'type' => $event->type,
				'description' => $event->description,
				'detail' => $event->detail,
				'location' => [ 
					'type' => $event->location_type,
					'address' => $event->location_address,
					'number' => $event->location_number,
					'neighborhood' => $event->location_neighborhood,
					'city' => $event->location_city,
					'state' => $event->location_state,
					'postcode' => $event->location_postcode,
				],
				'event_date' => $event->event_date,
			];
		}

		return $history;
	}

	/**
	 * Get last tracking order event.
	 * 
	 * @since 1.2.1
	 * 
	 * @param int $order_id
	 * 
	 * @return TrackingCode|false
	 */
	public function get_last_tracking_order( $order_id ) {
		$tracking_codes = $this->list( $order_id, [ 
			'with_events' => true,
			'order' => [ 
				'column' => 'created_at',
				'order' => 'desc',
			],
		] );

		if ( $tracking_codes->isEmpty() ) {
			return false;
		}

		return $tracking_codes->first();
	}

	/**
	 * Suspend shipping
	 * 
	 * @param string $id
	 * 
	 * @return array|\WP_Error
	 */
	public function suspend_shipping( $id ) {
		$tracking = $this->trackingRepository->retrieve( $id );

		if ( ! $tracking ) {
			return new \WP_Error( 'tracking_code_not_found', 'Código de rastreio não encontrado.' );
		}

		return $this->correiosService->suspend_shipping( $tracking->code );
	}

	/**
	 * Remove unit tracking.
	 * 
	 * @since 1.5.0
	 * 
	 * @param int $unit_id Unit ID.
	 * @param int $tracking_id Tracking ID.
	 * 
	 * @return bool
	 */
	public function removeUnit( $unit_id, $tracking_id ) {
		$tracking = $this->trackingRepository->retrieve( $tracking_id );

		if ( ! $tracking ) {
			return false;
		}

		if ( $tracking->unit_id != $unit_id ) {
			return false;
		}

		$tracking->unit_id = null;

		return $tracking->save();
	}

}