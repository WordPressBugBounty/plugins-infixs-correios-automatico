/**
 * Infixs Correios Automático - Tracking Component JS.
 *
 * @version 1.2.0
 * @since   1.2.0
 */

jQuery(function ($) {
  const InfixsCorreiosAutomaticoOrder = {
    /**
     * Initialize the class.
     */
    init() {
      $(document.body).on(
        "click",
        ".infixs-caref-show-more-button",
        this.showMoreButton.bind(this)
      );

      $(document.body).on(
        "click",
        ".infixs-caref-order-tracking-tab",
        this.switchTab.bind(this)
      );

      $(document.body).on(
        "click",
        ".infixs-caref-return-button",
        this.openReturnModal.bind(this)
      );

      $(document.body).on(
        "click",
        ".infixs-caref-return-cancel",
        this.closeReturnModal.bind(this)
      );

      $(document.body).on(
        "click",
        ".infixs-caref-return-confirm",
        this.confirmReturn.bind(this)
      );
    },

    /**
     * Open the return confirmation modal.
     *
     * @param {Event} event
     */
    openReturnModal(event) {
      event.preventDefault();
      $(".infixs-caref-return-modal").css("display", "flex");
    },

    /**
     * Close the return confirmation modal.
     *
     * @param {Event} event
     */
    closeReturnModal(event) {
      if (event) event.preventDefault();
      $(".infixs-caref-return-modal").hide();
    },

    /**
     * Confirm the return request.
     *
     * @param {Event} event
     */
    confirmReturn(event) {
      event.preventDefault();

      if (typeof infixsCorreiosAutomaticoTracking === "undefined") {
        return;
      }

      const $confirm = $(event.target);
      const $button = $(".infixs-caref-return-button");
      const $message = $(".infixs-caref-return-modal-message");
      const orderId = $button.data("order-id");
      const orderKey = $button.data("order-key");

      $confirm.prop("disabled", true).text("Enviando...");
      $message.removeClass("error success").text("");

      fetch(infixsCorreiosAutomaticoTracking.restUrl + "customer-returns", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": infixsCorreiosAutomaticoTracking.nonce,
        },
        body: JSON.stringify({ order_id: orderId, order_key: orderKey }),
      })
        .then((response) =>
          response.json().then((data) => ({ ok: response.ok, data }))
        )
        .then(({ ok, data }) => {
          if (!ok) {
            throw new Error(
              (data && data.message) || "Erro ao solicitar a devolução."
            );
          }

          $message
            .addClass("success")
            .text((data && data.message) || "Devolução solicitada com sucesso.");
          $button.hide();

          setTimeout(function () {
            window.location.reload();
          }, 2500);
        })
        .catch((error) => {
          $message.addClass("error").text(error.message);
          $confirm.prop("disabled", false).text("Confirmar Devolução");
        });
    },

    /**
     * Switch tab.
     *
     * @param {Event} event
     */
    switchTab(event) {
      event.preventDefault();

      const $tab = $(event.target);
      const $container = $tab.closest(".infixs-caref-order-tracking-history");

      $container.find(".infixs-caref-order-tracking-tab").removeClass("active");
      $tab.addClass("active");

      const tab = $tab.data("id");

      $container
        .find(".infixs-caref-order-tracking-box")
        .hide()
        .filter(`[data-tab="${tab}"]`)
        .show();
    },

    /**
     * Show more button.
     *
     * @param {Event} event
     */
    showMoreButton(event) {
      event.preventDefault();

      const $button = $(event.target);
      const $container = $button.closest(".infixs-caref-order-tracking-box");

      $container
        .find(".infixs-caref-order-tracking-event-list")
        .css("height", "auto");

      $container
        .find(".infixs-caref-order-tracking-show-more-button-wrap")
        .hide();
    },
  };

  InfixsCorreiosAutomaticoOrder.init();
});
