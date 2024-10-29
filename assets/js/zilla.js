jQuery(function ($) {
  var zilla = {
    init: function () {
      jQuery("#zilla-payment-gateway-button").prop("disabled", true);
      //initialise make payment
      var connect = new Connect();
      var config = {
        publicKey: wc_zilla_params.public_key,
        onSuccess: (data) => {
          if (data.status == "SUCCESSFUL") {
            //Implement what happens when transaction is completed.
            window.location.href =
              wc_zilla_params.api_verify_url +
              "&zillaOrderCode=" +
              data.zillaOrderCode;
            //Disable button
            jQuery("#zilla-payment-gateway-button").prop("disabled", true);
            jQuery("#zilla-payment-gateway-button").remove();
            jQuery("#cancel-btn").remove();
            jQuery("#yes-add").html(
              `<p class="woocommerce-notice woocommerce-notice--success woocommerce-thankyou-order-received">Please keep the page open while we process your order</p>`
            );
          }
        },
        onError: () => {
          jQuery("#zilla-payment-gateway-button").prop("disabled", false);
        },
        onClose: () => {
          jQuery("#zilla-payment-gateway-button").prop("disabled", false);
        },
        clientOrderReference: wc_zilla_params.txnref,
        title: wc_zilla_params.order_title,
        amount: wc_zilla_params.amount
      };

      connect.openNew(config);
    }
  };

  $("#zilla-payment-gateway-button").click(function (e) {
    e.preventDefault();
    zilla.init();
  });
});
