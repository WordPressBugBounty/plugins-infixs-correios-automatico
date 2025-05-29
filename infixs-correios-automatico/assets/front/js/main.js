/**
 * Infixs Correios Automático - Main JS Front-End.
 *
 * @version 1.0.0
 * @since   1.0.0
 */

/**
 * @global {Object} infxsCorreiosAutomatico - Global object for Infixs Correios Automático.
 * @property {string} product_id - The product ID.
 * @property {string} nonce - The nonce for AJAX requests.
 */

jQuery( function ( $ ) {
	/**
	 * Infixs Correios Automático Input Text.
	 *
	 * @since 1.0.0
	 * @version 1.0.0
	 *
	 * @param {JQuery<HTMLElement>} element
	 */
	const InfixsCorreiosAutomaticoInputText = ( inputSelector ) => {
		const element = $( inputSelector );

		return {
			element: element,
			input: element.find( 'input' ),
			/**
			 * Error element.
			 * @type {JQuery<HTMLElement>|null}
			 * @default null
			 */
			error: null,
			getValue() {
				return this.input.val();
			},
			setValue( value ) {
				this.input.val( value );
			},
			setLoading() {
				this.element
					.find( '.infixs-correios-automatico-loading' )
					.show();
			},
			unsetLoading() {
				this.element
					.find( '.infixs-correios-automatico-loading' )
					.hide();
			},
			/**
			 * Set the input as invalid.
			 *
			 * @param {string} message
			 * @param {JQuery<HTMLElement>|null} element
			 */
			setError( message, element = null ) {
				this.unsetError();
				this.element.addClass( 'infixs-correios-automatico-invalid' );
				$( '<div>' )
					.addClass( 'infixs-correios-automatico-error-message' )
					.text( message )
					.css( {
						opacity: '0',
						'font-size': '12px',
						'max-height': '0',
						color: '#ff0000',
					} )
					.insertAfter( element ?? this.element )
					.animate(
						{
							opacity: '1',
							'max-height': '100px',
						},
						300
					);
			},
			unsetError() {
				this.element.removeClass(
					'infixs-correios-automatico-invalid'
				);
				$( '.infixs-correios-automatico-error-message' ).remove();
			},
		};
	};

	function maskPostcodeByElement( element ) {
		let value = $( element ).val().replace( /\D/g, '' );
		if ( value.length > 8 ) {
			value = value.substring( 0, 8 );
		}
		if ( value.length > 5 ) {
			value = value.replace( /^(\d{5})(\d)/, '$1-$2' );
		}
		$( element ).val( value );
	}

	function postcodeMask() {
		maskPostcodeByElement( this );
	}

	/**
	 * Admin class.
	 */
	const InfixsCorreiosAutomaticoFront = {
		/**
		 * Initialize the class.
		 */
		init() {
			this.applyListners();
			if ( $( 'select#billing_country' ).length > 0 ) {
				$( 'select#billing_country' ).trigger( 'change' );
			}
			if ( $( 'select#shipping_country' ).length > 0 ) {
				$( 'select#shipping_country' ).trigger( 'change' );
			}
			this.applyPostcodeMask();
		},

		/**
		 * Apply listners.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 *
		 * @return {void}
		 */
		applyListners() {
			$( document.body ).on(
				'click',
				'.infixs-correios-automatico-calculate-submit',
				this.calculateShipping.bind( this )
			);

			$( document.body ).on(
				'change',
				'select#billing_country',
				this.changeCountry.bind( this, 'billing' )
			);

			$( document.body ).on(
				'change',
				'select#shipping_country',
				this.changeCountry.bind( this, 'shipping' )
			);

			if (
				infxsCorreiosAutomatico.options
					?.autoCalculateProductShippingPostcode
			) {
				$( document.body ).on(
					'input',
					'input.infixs-correios-automatico-input',
					this.updateProductShippingPostcode.bind( this )
				);
			}

			const self = this;

			$( document.body ).on(
				'keydown',
				'input.infixs-correios-automatico-input',
				function ( e ) {
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						self.calculateShipping( e );
					}
				}
			);

			$( document ).on(
				'wc_variation_form',
				function ( event, variationFormInstance ) {
					self.changeVariation( variationFormInstance );
					$( document ).on(
						'change.wc-variation-form',
						'.variations select',
						self.changeVariation.bind( self, variationFormInstance )
					);
				}
			);
		},

		changeVariation( form ) {
			if ( form && form.variationData ) {
				const variationData = form.variationData;
				const currentVariationId = this.getVariantion();

				if ( currentVariationId ) {
					const found = variationData.find(
						( variation ) =>
							variation.variation_id == currentVariationId
					);
					if ( found && found.is_virtual === true ) {
						$( '.infixs-correios-automatico-calculator' ).hide();
					}
					if ( found && ! found.is_virtual ) {
						$( '.infixs-correios-automatico-calculator' ).show();
					}
				}
			}
		},

		/**
		 * Apply PostCode mask to inputs with the class 'infixs-correios-automatico-postcode-mask'.
		 */
		applyPostcodeMask() {
			$( document.body ).on(
				'input',
				'.infixs-correios-automatico-postcode-mask',
				postcodeMask
			);

			$( document.body ).on(
				'input',
				'#calc_shipping_postcode',
				postcodeMask
			);

			//this.bindPostcodeMask("input#shipping-postcode");
			//this.bindPostcodeMask("input#0-postcode");
		},

		bindPostcodeMask( selector ) {
			$( document.body ).on( 'input', selector, postcodeMask );
			$( document.body ).on( 'blur', selector, postcodeMask );
		},

		unbindPostcodeMask( selector ) {
			$( document.body ).off( 'input', selector, postcodeMask );
			$( document.body ).off( 'blur', selector, postcodeMask );
		},

		updateProductShippingPostcode( event ) {
			$element = $( event.target );
			const postcode = $element.val().replace( /\D/g, '' );

			if ( postcode.length === 8 ) {
				this.calculateShipping( event );
			}
		},

		/**
		 * Submit and Calculate the shipping.
		 *
		 * @since 1.0.0
		 * @version 1.0.0
		 *
		 * @param {Event} event
		 *
		 * @return {void}
		 */
		calculateShipping( event ) {
			event.preventDefault();

			if ( typeof woocommerce_params === 'undefined' ) {
				console.error( 'woocommerce_params.ajax_url is undefined.' );
				return;
			}

			const box = $( event.target ).closest(
				'.infixs-correios-automatico-calculator'
			);
			const postcodeInput = InfixsCorreiosAutomaticoInputText(
				box.find( '.infixs-correios-automatico-input-text' ).first()
			);

			const submitButton = $(
				box
					.find( '.infixs-correios-automatico-calculate-submit' )
					.first()
			);

			const postcode = postcodeInput.getValue().replace( /\D/g, '' );
			if ( postcode.length !== 8 ) {
				postcodeInput.setError(
					'CEP inválido, tente novamente.',
					'.infixs-correios-automatico-calculate-box'
				);
				return;
			}

			postcodeInput.unsetError();

			postcodeInput.setLoading();
			submitButton.prop( 'disabled', true );

			const variationId = this.getVariantion();

			const _quantity = $( '.quantity input[name="quantity"]' ).val();
			const quantity = Number.isFinite( Number( _quantity ) )
				? Number( _quantity )
				: null;

			$.ajax( {
				url: woocommerce_params.ajax_url,
				type: 'POST',
				data: {
					action: 'infixs_correios_automatico_calculate_shipping',
					postcode: postcode,
					product_id: infxsCorreiosAutomatico.productId,
					...( variationId && { variation_id: variationId } ),
					nonce: infxsCorreiosAutomatico.nonce,
					...( quantity && { quantity } ),
				},
				success: ( response ) => {
					box.find( '#infixs-correios-automatico-calculate-results' )
						.first()
						.html( response );
				},
				error: ( error ) => {},
				complete: () => {
					submitButton.prop( 'disabled', false );
					postcodeInput.unsetLoading();
				},
			} );
		},

		changeCountry( field, event ) {
			const target = $( event.target );
			const country = target.val();

			if ( country === 'BR' ) {
				this.bindPostcodeMask( `input#${ field }_postcode` );
			} else {
				this.unbindPostcodeMask( `input#${ field }_postcode` );
			}
		},

		getVariantion() {
			const variationInput = $( 'input[name="variation_id"]' );
			if ( variationInput.length === 0 ) return false;
			const variationId = parseInt( variationInput.val(), 10 );
			return isNaN( variationId ) ? false : variationId;
		},

		getInput( element ) {},
	};

	// Initialize the class.
	InfixsCorreiosAutomaticoFront.init();
} );
