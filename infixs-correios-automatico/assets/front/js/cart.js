/**
 * Infixs Correios Automático - Cart JS Front-End.
 *
 * @since   1.2.9
 */

/**
 * @global {Object} infxsCorreiosAutomaticoCart - Global object for Infixs Correios Automático.
 */

jQuery(function ($) {
  /**
   * Cart class.
   */
  const InfixsCorreiosAutomaticoFrontCart = {
    /**
     * Initialize the class.
     */
    init() {
      if (
        infxsCorreiosAutomaticoCart?.options?.autoCalculateCartShippingPostcode
      ) {
        this.applyAutoCalculateCartShippingPostcode();
      }
    },

    /**
     * Apply auto calculate cart shipping postcode.
     */
    applyAutoCalculateCartShippingPostcode() {
      $(document).on(
        "input",
        "#calc_shipping_postcode",
        this.updateCartShippingPostcode.bind(this)
      );
    },

    /**
     * Update cart shipping postcode.
     *
     * @param {Event} event - Event object.
     */
    updateCartShippingPostcode(event) {
      $element = $(event.target);
      const postcode = $element.val().replace(/\D/g, "");

      if (postcode.length === 8) {
        $element.closest("form").trigger("submit");
      }
    },
  };

  InfixsCorreiosAutomaticoFrontCart.init();
});
