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
