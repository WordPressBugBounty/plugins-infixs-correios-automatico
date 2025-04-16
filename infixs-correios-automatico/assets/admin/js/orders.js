/// <reference types="jquery" />

/**
 * Infixs Correios Automático - Orders JS.
 *
 * @global
 * @name infixsCorreiosAutomaticoOrdersParams
 * @type {string}
 */
jQuery( function ( $ ) {
	/**
	 * Admin class.
	 */
	const InfixsCorreiosAutomaticoOrders = {
		/**
		 * Initialize the class.
		 */
		init() {
			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-tracking-box .infixs-correios-automatico-add-tracking-code',
				this.addTrackingCode.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-tracking-box .infixs-correios-automatico-remove-code',
				this.removeTrackingCode.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-print-orders',
				this.printOrders.bind( this )
			);

			$( document.body ).on(
				'click',
				'#infixs-correios-automatico-create-prepost-declaration',
				this.createPrepostDeclaration.bind( this )
			);

			$( document.body ).on(
				'click',
				'#infixs-correios-automatico-print-label',
				this.printLabel.bind( this )
			);

			$( document.body ).on(
				'click',
				'#infixs-correios-automatico-create-prepost-invoice',
				this.choosePrepostInvoice.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-create-prepost-invoice-cancel',
				this.closeConfirmPrepostQuestion.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-prepost-cancel',
				this.cancelPrepost.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-create-prepost-invoice-create',
				this.createPrepostInvoice.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-tracking-update-button',
				this.showUpdateTrackingInput.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-tracking-cancel-button',
				this.cancelUpdateTracking.bind( this )
			);

			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-tracking-edit-form',
				this.updateTrackingForm.bind( this )
			);
			$( document.body ).on(
				'click',
				'.column-infixs-correios-automatico-actions-column',
				this.updateTrackingForm.bind( this )
			);
			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-tracking-confirm-button',
				this.confirmTrackingCodeForm.bind( this )
			);
			$( document.body ).on(
				'change',
				'#order_shipping_line_items .edit select.shipping_method',
				this.editShippingMethodChanged.bind( this )
			);

			$( document.body ).on(
				'change',
				'.infixs-edit-shipping-options-service',
				this.changeShippingInstance.bind( this )
			);

			$( document.body ).on(
				'click',
				'input#doaction',
				this.bulkActionsPrintOrders.bind( this )
			);

			this.showEditShippingMethodInputs();
		},

		/**
		 * Block meta boxes.
		 *
		 * @param {string} element Element.
		 */
		block( element ) {
			$( element ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );
		},

		/**
		 * Unblock meta boxes.
		 *
		 * @param {string} element Element.
		 */
		unblock( element ) {
			$( element ).unblock();
		},

		/**
		 * Add tracking code.
		 *
		 * @param {Event} event Event object.
		 */
		addTrackingCode( event ) {
			event.preventDefault();
			const orderId = $( '#infixs-correios-automatico-order-id' ).val();
			const sendMail = $(
				'#infixs-correios-automatico-tracking-code-email-sendmail'
			).is( ':checked' );
			const trackingCode = $(
				'#infixs-correios-automatico-tracking-code-input'
			).val();

			if ( ! orderId || ! trackingCode ) return;

			InfixsCorreiosAutomaticoOrders.block(
				'#infixs-correios-automatico-tracking-code'
			);

			this.postTrackingCode(
				trackingCode,
				orderId,
				sendMail,
				( response ) => {
					InfixsCorreiosAutomaticoOrders.addTableLine(
						trackingCode,
						response.data.id,
						orderId
					);
				},
				null,
				() => {
					InfixsCorreiosAutomaticoOrders.unblock(
						'#infixs-correios-automatico-tracking-code'
					);
				}
			);
		},

		postTrackingCode(
			code,
			orderId,
			sendmail = false,
			successCallback = null,
			errorCallback = null,
			completeCallback = null
		) {
			const restUrl = infixsCorreiosAutomaticoOrdersParams.restUrl;
			const nonce = infixsCorreiosAutomaticoOrdersParams.nonce;

			const data = {
				code: code,
				order_id: orderId,
				sendmail: sendmail,
			};

			$.ajax( {
				url: `${ restUrl }/trackings`,
				type: 'POST',
				data: JSON.stringify( data ),
				contentType: 'application/json',
				headers: {
					'X-WP-Nonce': nonce,
				},
				success: function ( response ) {
					if ( typeof successCallback === 'function' ) {
						successCallback( response );
					}
				},
				complete: function ( data ) {
					if ( typeof completeCallback === 'function' ) {
						completeCallback( data );
					}
				},
				error: function ( response ) {
					if ( typeof errorCallback === 'function' ) {
						errorCallback( response );
					}
				},
			} );
		},

		/**
		 * Add table line.
		 *
		 * @param {string} trackingCode Tracking code.
		 * @param {number} trackingId Tracking ID.
		 */
		addTableLine( trackingCode, trackingId, orderId ) {
			const headerAction = $(
				'.infixs-correios-automatico-header-action-column'
			);

			const trackingHtml = $( '<a>', {
				href: '#',
				class: 'infixs-correios-automatico-tracking-code-modal-view',
				'data-order-id': orderId,
				'data-tracking-codes': JSON.stringify( [
					{
						id: trackingId,
						code: trackingCode,
					},
				] ),
			} ).text( trackingCode );

			$( '<div>', {
				class: 'infixs-correios-automatico-action-column',
			} )
				.append(
					$( '<a>', {
						href: '#',
						class: 'infixs-correios-automatico-tracking-code-modal-view',
						'data-order-id': trackingId,
					} ).html(
						'<svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 32 32"><circle cx="16" cy="16" r="4" /><path d="M30.94 15.66A16.69 16.69 0 0 0 16 5A16.69 16.69 0 0 0 1.06 15.66a1 1 0 0 0 0 .68A16.69 16.69 0 0 0 16 27a16.69 16.69 0 0 0 14.94-10.66a1 1 0 0 0 0-.68M16 22.5a6.5 6.5 0 1 1 6.5-6.5a6.51 6.51 0 0 1-6.5 6.5" /></svg>'
					)
				)
				.append(
					$( '<a>', {
						href: '#',
						class: 'infixs-correios-automatico-remove-code',
						'data-id': trackingId,
					} ).html(
						'<svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24"><path d="M20 6a1 1 0 0 1 .117 1.993L20 8h-.081L19 19a3 3 0 0 1-2.824 2.995L16 22H8c-1.598 0-2.904-1.249-2.992-2.75l-.005-.167L4.08 8H4a1 1 0 0 1-.117-1.993L4 6zm-6-4a2 2 0 0 1 2 2a1 1 0 0 1-1.993.117L14 4h-4l-.007.117A1 1 0 0 1 8 4a2 2 0 0 1 1.85-1.995L10 2z" /></svg>'
					)
				)
				.insertAfter( headerAction );
			$( '<div>' ).html( trackingHtml ).insertAfter( headerAction );
		},

		/**
		 * Remove tracking code.
		 *
		 * @param {Event} event Event object.
		 */
		removeTrackingCode( event ) {
			event.preventDefault();

			const element = $( event.currentTarget );
			const restUrl = infixsCorreiosAutomaticoOrdersParams.restUrl;
			const nonce = infixsCorreiosAutomaticoOrdersParams.nonce;
			const orderId = $( '#infixs-correios-automatico-order-id' ).val();
			const trackingCodeId = element.data( 'id' );

			if ( ! orderId || ! nonce || ! trackingCodeId ) return;

			InfixsCorreiosAutomaticoOrders.block(
				'#infixs-correios-automatico-tracking-code'
			);

			$.ajax( {
				url: `${ restUrl }/trackings/${ trackingCodeId }`,
				type: 'DELETE',
				headers: {
					'X-WP-Nonce': nonce,
				},
				success: function () {
					$(
						`.infixs-correios-automatico-action-column a[data-id=${ trackingCodeId }]`
					)
						.parent()
						.prev()
						.remove();
					$(
						`.infixs-correios-automatico-action-column a[data-id=${ trackingCodeId }]`
					)
						.parent()
						.remove();
				},
				complete: function () {
					InfixsCorreiosAutomaticoOrders.unblock(
						'#infixs-correios-automatico-tracking-code'
					);
				},
			} );
		},

		bulkActionsPrintOrders( event ) {
			if (
				$( '#bulk-action-selector-top' ).val() !==
				'infixs_correios_automatico_print_labels'
			)
				return;

			this.printOrders( event );
		},
		/**
		 * Print orders.
		 *
		 * @param {Event} event Event object.
		 */
		printOrders( event ) {
			event.preventDefault();
			const selectedOrders = [];
			const notPrintedOrders = [];
			let hasPrinted = false;
			let hasSelection = false;
			$( '.wp-list-table tbody tr th input[type=checkbox]' ).each(
				function () {
					if ( $( this ).is( ':checked' ) ) {
						hasSelection = true;
						const orderId = $( this ).val();
						selectedOrders.push( orderId );
						const printed =
							$( this )
								.closest( 'tr' )
								.find(
									'.infixs-correios-automatico-actions-buttons-column'
								)
								.first()
								.attr( 'data-order-printed' ) ?? '0';

						if ( Number( printed ) ) {
							hasPrinted = true;
						} else {
							notPrintedOrders.push( orderId );
						}
					}
				}
			);

			if ( ! hasSelection ) {
				alert( 'Selecione pelo menos um pedido para imprimir.' );
				return;
			}

			if ( hasPrinted ) {
				$( document.body ).trigger( 'printAlertEvent', {
					allOrders: selectedOrders,
					notPrintedOrders: notPrintedOrders,
				} );
			} else {
				window.location.href = `${
					infixsCorreiosAutomaticoOrdersParams.adminUrl
				}&path=/print&orders=${ selectedOrders.join( ',' ) }`;
			}
		},

		choosePrepostInvoice( event ) {
			event.preventDefault();
			$( '.infixs-correios-automatico-prepost-invoice-box' ).css(
				'display',
				'flex'
			);
			$( '.infixs-correios-automatico-prepost-box' ).css(
				'display',
				'none'
			);
		},

		closeConfirmPrepostQuestion( event ) {
			event.preventDefault();
			$( '.infixs-correios-automatico-prepost-invoice-box' ).css(
				'display',
				'none'
			);
			$( '.infixs-correios-automatico-prepost-box' ).css(
				'display',
				'flex'
			);
		},

		cancelPrepost( event ) {
			event.preventDefault();
			InfixsCorreiosAutomaticoOrders.confirmAlert(
				'#infixs-correios-automatico-prepost',
				'Deseja realmente cancelar essa Pré-Postagem no sistema dos correios?',
				() => {
					const restUrl =
						infixsCorreiosAutomaticoOrdersParams.restUrl;
					const nonce = infixsCorreiosAutomaticoOrdersParams.nonce;
					const prepostId = $(
						'#infixs_correios_automatico_prepost_id'
					).val();

					if ( ! prepostId || ! nonce ) return;

					InfixsCorreiosAutomaticoOrders.block(
						'#infixs-correios-automatico-prepost'
					);

					$.ajax( {
						url: `${ restUrl }/preposts/${ prepostId }/cancel`,
						type: 'PATCH',
						contentType: 'application/json',
						headers: {
							'X-WP-Nonce': nonce,
						},
						success: function () {
							window.location.reload();
						},
						error: function ( response ) {
							InfixsCorreiosAutomaticoOrders.unblock(
								'#infixs-correios-automatico-prepost'
							);
							alert( response.responseJSON.message );
						},
					} );
				}
			);
		},

		createPrepostInvoice( event ) {
			event.preventDefault();

			const restUrl = infixsCorreiosAutomaticoOrdersParams.restUrl;
			const nonce = infixsCorreiosAutomaticoOrdersParams.nonce;
			const orderId = $( '#infixs-correios-automatico-order-id' ).val();
			const invoiceNumber = $(
				'#infixs-correios-automatico-prepost-invoice-number'
			).val();
			const invoiceKey = $(
				'#infixs-correios-automatico-prepost-invoice-key'
			).val();

			if ( invoiceNumber.trim().length === 0 ) {
				alert( 'O número da nota fiscal é obrigatório.' );
				return;
			}

			if ( invoiceKey.trim().length !== 44 ) {
				alert( 'A chave da nota fiscal deve conter 44 caracteres.' );
				return;
			}

			if ( ! orderId || ! nonce ) return;

			InfixsCorreiosAutomaticoOrders.block(
				'#infixs-correios-automatico-prepost'
			);

			$.ajax( {
				url: `${ restUrl }/preposts`,
				type: 'POST',
				data: JSON.stringify( {
					order_id: orderId,
					invoice_number: invoiceNumber,
					invoice_key: invoiceKey,
				} ),
				contentType: 'application/json',
				headers: {
					'X-WP-Nonce': nonce,
				},
				success: function () {
					window.location.reload();
				},
				error: function ( response ) {
					InfixsCorreiosAutomaticoOrders.unblock(
						'#infixs-correios-automatico-prepost'
					);
					alert( response.responseJSON.message );
				},
			} );
		},

		/**
		 * Create prepost declaration.
		 *
		 * @param {Event} event Event object.
		 */
		createPrepostDeclaration( event ) {
			event.preventDefault();
			InfixsCorreiosAutomaticoOrders.confirmAlert(
				'#infixs-correios-automatico-prepost',
				'Deseja realmente criar a Pré-Postagem com declaração de conteúdo?',
				() => {
					const restUrl =
						infixsCorreiosAutomaticoOrdersParams.restUrl;
					const nonce = infixsCorreiosAutomaticoOrdersParams.nonce;
					const orderId = $(
						'#infixs-correios-automatico-order-id'
					).val();

					if ( ! orderId || ! nonce ) return;

					InfixsCorreiosAutomaticoOrders.block(
						'#infixs-correios-automatico-prepost'
					);

					$.ajax( {
						url: `${ restUrl }/preposts`,
						type: 'POST',
						data: JSON.stringify( { order_id: orderId } ),
						contentType: 'application/json',
						headers: {
							'X-WP-Nonce': nonce,
						},
						success: function () {
							window.location.reload();
						},
						error: function ( response ) {
							InfixsCorreiosAutomaticoOrders.unblock(
								'#infixs-correios-automatico-prepost'
							);
							alert( response.responseJSON.message );
						},
					} );
				}
			);
		},

		/**
		 * Confirm alert.
		 *
		 * @param {HTMLElement} element Element.
		 * @param {string} message Message.
		 */
		confirmAlert( element, message, callback ) {
			const overlay = $( '<div>' ).addClass(
				'infixs-correios-automatico-alert-overlay'
			);

			const wrapper = $( '<div>' )
				.addClass( 'infixs-correios-automatico-alert-wrapper' )
				.html( message );

			const buttons = $( '<div>' ).addClass(
				'infixs-correios-automatico-alert-buttons'
			);

			const confirmButton = $( '<button>' )
				.addClass( 'button button-primary' )
				.text( 'Sim' );

			confirmButton.on( 'click', function () {
				overlay.remove();
				if ( typeof callback === 'function' ) {
					callback();
				}
			} );

			const cancelButton = $( '<button>' )
				.addClass( 'button' )
				.text( 'Não' );

			cancelButton.on( 'click', function () {
				overlay.remove();
			} );

			buttons.append( confirmButton, cancelButton );

			wrapper.append( buttons );

			overlay.append( wrapper );

			$( element ).append( overlay );
		},

		printLabel( event ) {
			event.preventDefault();
			const orderId = $( '#infixs-correios-automatico-order-id' ).val();
			window.open(
				`${ infixsCorreiosAutomaticoOrdersParams.adminUrl }&path=/print&orders=${ orderId }`,
				'_blank'
			);
		},

		/** Update Tracking Column */

		getTrackingElements( event ) {
			const element = $( event.target );
			const wrapper = element.closest(
				'.infixs-correios-automatico-tracking-column-wrapper'
			);
			const form = wrapper.find(
				'.infixs-correios-automatico-tracking-edit-form'
			);
			const confirmButton = form.find(
				'.infixs-correios-automatico-tracking-confirm-button'
			);

			const cancelButton = form.find(
				'.infixs-correios-automatico-tracking-cancel-button'
			);

			const loading = form.find(
				'.infixs-correios-automatico-spin-animation'
			);

			const orderId = wrapper.data( 'order-id' );
			return {
				element,
				wrapper,
				form,
				orderId,
				confirmButton,
				cancelButton,
				loading,
			};
		},

		showUpdateTrackingInput( event ) {
			event.preventDefault();
			const { form } = this.getTrackingElements( event );
			form.show();
		},

		cancelUpdateTracking( event ) {
			event.preventDefault();
			const { form } = this.getTrackingElements( event );
			form.hide();
		},

		confirmTrackingCodeForm( event ) {
			event.preventDefault();
			const { form, orderId, confirmButton, cancelButton, loading } =
				this.getTrackingElements( event );
			const trackingCode = form.find( 'input' ).val();
			if ( ! trackingCode ) return;

			confirmButton.attr( 'disabled', true );
			cancelButton.attr( 'disabled', true );
			loading.css( 'display', 'flex' );

			this.postTrackingCode(
				trackingCode,
				orderId,
				false,
				( response ) => {
					const trackingCodeElement = $( '<a>' )
						.attr( 'href', `#` )
						.addClass(
							'infixs-correios-automatico-tracking-code-modal-view'
						)
						.attr( 'data-order-id', orderId )
						.attr(
							'data-tracking-codes',
							JSON.stringify( [
								{
									id: response.data.id,
									code: trackingCode,
								},
							] )
						)
						.text( trackingCode );

					const maybeTrackingElement = form
						.closest( 'tr' )
						.find(
							'.infixs-correios-automatico-tracking-code-link'
						);
					if ( maybeTrackingElement.length ) {
						maybeTrackingElement.html( trackingCodeElement );
					}
				},
				null,
				() => {
					form.hide();
					confirmButton.attr( 'disabled', false );
					cancelButton.attr( 'disabled', false );
					loading.hide();
				}
			);
		},

		showEditShippingMethodInputs() {
			$( '.infixs-edit-shipping-options-service' ).select2();
		},

		item_meta: {
			removeMetas: function () {
				$( '.infixs-edit-shipping-options-meta-container' ).empty();
			},
			getRowsCount: function () {
				return (
					$( '.infixs-edit-shipping-options-meta-container' ).find(
						'.infixs-edit-shipping-options-meta-container-item'
					).length + 1
				);
			},
			/**
			 * Add Shipping Service Select meta.
			 *
			 * @param {string} itemId Item id.
			 * @param {string} metaKey Meta key.
			 */
			addShippingServiceMeta: function (
				itemId,
				metaKey,
				metas = null,
				instances = []
			) {
				const totalRows = this.getRowsCount();
				const entries = Object.entries( metas ).find(
					( meta ) => meta[ 1 ].key === metaKey
				);

				const $label = $( '<label>', {
					for: 'infixs-edit-shipping-options-shipping-instance',
					text: `Método de entrega:`,
				} );

				const $select = $( '<select>', {
					class: 'infixs-edit-shipping-options-service',
					id: `infixs-edit-shipping-options-shipping-instance`,
					name: `instance_id[${ itemId }]`,
					style: 'width: 100%;',
				} );

				const $optgroup = $( '<optgroup>', {
					label: 'Métodos de entrega disponíveis no endereço do cliente',
				} );

				$.each( instances, function ( key, value ) {
					const $option = $( '<option>', {
						value: key,
						text: value.description,
					} );
					$optgroup.append( $option );
				} );

				$select.append( $optgroup );

				const $hiddenInput = $( '<input>', {
					type: 'hidden',
					name: `meta_key[${ itemId }][new-infixs-${ totalRows }]`,
					value: metaKey,
				} );

				const $hiddenInputValue = $( '<input>', {
					type: 'hidden',
					name: `meta_value[${ itemId }][new-infixs-${ totalRows }]`,
					value: '',
				} );

				const $container = $( '<div>', {
					class: 'infixs-edit-shipping-options-meta-container-item',
				} );
				$container
					.append( $label )
					.append( $select )
					.append( $hiddenInput )
					.append( $hiddenInputValue );

				$( '.infixs-edit-shipping-options-meta-container' ).append(
					$container
				);

				$select.select2();
			},

			/**
			 * Add meta.
			 *
			 * @param {string} itemId Item id.
			 * @param {string} metaKey Meta key.
			 * @param {string} metaValue Meta value.
			 * @param {string} title Meta title.
			 * @param {object} metas Metas.
			 */
			addMeta: function (
				itemId,
				metaKey,
				metaValue = '',
				title = '',
				metas = null,
				hidden = false
			) {
				const totalRows = this.getRowsCount();
				const entries = Object.entries( metas ).find(
					( meta ) => meta[ 1 ].key === metaKey
				);

				const $label = $( '<label>', {
					for: 'infixs-edit-shipping-options-meta-' + metaKey,
					text: `${ title }:`,
				} );

				const $inputText = $( '<input>', {
					type: hidden ? 'hidden' : 'text',
					class: 'infixs-edit-shipping-options-meta',
					id: `infixs-edit-shipping-options-meta-${ metaKey }`,
					name: `meta_value[${ itemId }][${
						entries?.[ 0 ]
							? entries[ 0 ]
							: `new-infixs-${ totalRows }`
					}]`,
					value: entries?.[ 1 ]?.value ?? metaValue,
				} );

				const $hiddenInput = $( '<input>', {
					type: 'hidden',
					name: `meta_key[${ itemId }][${
						entries?.[ 0 ]
							? entries[ 0 ]
							: `new-infixs-${ totalRows }`
					}]`,
					value: metaKey,
				} );

				const $container = $( '<div>', {
					class: 'infixs-edit-shipping-options-meta-container-item',
				} );

				if ( ! hidden ) {
					$container.append( $label );
				}

				$container.append( $inputText ).append( $hiddenInput );

				$( '.infixs-edit-shipping-options-meta-container' ).append(
					$container
				);
			},
		},

		/**
		 * Edit shipping method changed.
		 * @param {Event} event Event object.
		 * @returns {void}
		 */
		editShippingMethodChanged( event ) {
			const element = $( event.target );
			const shippingMethod = element.val();
			const $lineItemElement = element.closest( 'tr' );
			const orderItemId = $lineItemElement.attr( 'data-order_item_id' );
			const metas =
				$lineItemElement
					.find( '.infixs-edit-shipping-options' )
					.data( 'metas' ) ?? {};

			const instances =
				$lineItemElement
					.find( '.infixs-edit-shipping-options' )
					.data( 'instances' ) ?? [];

			if ( shippingMethod !== 'infixs-correios-automatico' ) {
				$( `.infixs-edit-shipping-options` ).hide();
				this.item_meta.removeMetas();
				return;
			}
			this.showEditShippingMethodInputs();
			$( `.infixs-edit-shipping-options` ).show();

			if (
				$lineItemElement.find(
					'#infixs-edit-shipping-options-shipping-instance'
				).length === 0
			) {
				this.item_meta.addShippingServiceMeta(
					orderItemId,
					'shipping_product_code',
					metas,
					instances
				);
			}

			if (
				$lineItemElement.find(
					'#infixs-edit-shipping-options-meta-shipping_product_code'
				).length === 0
			) {
				this.item_meta.addMeta(
					orderItemId,
					'shipping_product_code',
					'03298',
					'Product Code',
					metas,
					true
				);
			}

			if (
				$lineItemElement.find(
					'#infixs-edit-shipping-options-meta-_weight'
				).length === 0
			) {
				this.item_meta.addMeta(
					orderItemId,
					'_weight',
					'0.1',
					'Peso (kg)',
					metas
				);
			}

			if (
				$lineItemElement.find(
					'#infixs-edit-shipping-options-meta-_length'
				).length === 0
			) {
				this.item_meta.addMeta(
					orderItemId,
					'_length',
					'16',
					'Comprimento (cm)',
					metas
				);
			}

			if (
				$lineItemElement.find(
					'#infixs-edit-shipping-options-meta-_width'
				).length === 0
			) {
				this.item_meta.addMeta(
					orderItemId,
					'_width',
					'11',
					'Largura (cm)',
					metas
				);
			}

			if (
				$lineItemElement.find(
					'#infixs-edit-shipping-options-meta-_height'
				).length === 0
			) {
				this.item_meta.addMeta(
					orderItemId,
					'_height',
					'2',
					'Altura (cm)',
					metas
				);
			}
		},

		changeShippingInstance( event ) {
			const element = $( event.target );
			const shippingInstance = element.val();
			const $lineItemElement = element.closest( 'tr' );
			const $inputName = $lineItemElement.find( '.shipping_method_name' );
			const instances =
				$lineItemElement
					.find( '.infixs-edit-shipping-options' )
					.data( 'instances' ) ?? [];

			if ( instances[ shippingInstance ] )
				$inputName.val( instances[ shippingInstance ][ 'title' ] );
		},

		/**
		 * Update tracking form.
		 *
		 * @param {Event} event Event object.
		 */
		updateTrackingForm( event ) {
			event.stopPropagation();
		},
	};

	InfixsCorreiosAutomaticoOrders.init();
} );
