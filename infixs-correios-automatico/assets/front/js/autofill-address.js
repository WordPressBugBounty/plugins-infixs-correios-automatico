/**
 * Infixs Correios Automático - Autofill Address JS Modeule Front-End.
 *
 * @since   1.0.0
 */

jQuery(function ($) {
  /**
   * Autofill class.
   */
  const InfixsCorreiosAutomaticoAutofillAddress = {
    /**
     * Initialize actions.
     */
    init() {
      $(document.body).on(
        "blur",
        "#billing_postcode",
        this.autofill.bind(this, "billing")
      );
      $(document.body).on(
        "blur",
        "#shipping_postcode",
        this.autofill.bind(this, "shipping")
      );
    },

    /**
     * Autofill address.
     *
     * @param {string} field
     * @param {Event} event
     *
     * @return {void}
     */
    autofill(field, event) {
      const target = $(event.target);
      const postcode = target.val().replace(/\D/g, "");

      if (!postcode) {
        return;
      }

      $.ajax({
        url: woocommerce_params.ajax_url,
        type: "POST",
        data: {
          action: "infixs_correios_automatico_autofill_address",
          postcode: postcode,
          nonce: infxsCorreiosAutomatico.nonce,
        },
        success: (response) => {
          if (!response.success) {
            return;
          }

          this.fillFields(field, response.data);
        },
        error: (error) => {},
        complete: () => {},
      });
    },

    /**
     * Fill fields.
     *
     * @param {string} field
     * @param {Object} data
     */
    fillFields(field, data) {
      if (data.address) {
        $("#" + field + "_address_1")
          .val(data.address)
          .trigger("change");
      }

      // Neighborhood
      if (data.neighborhood) {
        if ($("#" + field + "_neighborhood").length) {
          $("#" + field + "_neighborhood")
            .val(data.neighborhood)
            .trigger("change");
        } else {
          $("#" + field + "_address_2")
            .val(data.neighborhood)
            .trigger("change");
        }
      }

      // City
      $("#" + field + "_city")
        .val(data.city)
        .trigger("change");

      // State
      $("#" + field + "_state")
        .val(data.state)
        .trigger("change");
    },
  };

  InfixsCorreiosAutomaticoAutofillAddress.init();
});
