xcart.bind("checkout.shippingAddress.submitted", function () {
  var address = {};
  var className = "step-shipping-address";

  jQuery("." + className + " .form :input").each(function () {
    var inp = jQuery(this);
    var name = inp.attr("name");
    if (name) {
      address[name.replace(/(?:shipping|billing)Address/, "address")] =
        inp.val();
    }
  });

  var checkAddressData = _.defaults(address, {
    xcart_form_id: xliteConfig.form_id,
  });

  xcart.post(
    { target: "checkout", action: "check_tax_cloud_address" },
    "",
    checkAddressData,
    {
      dataType: "json",
      success: function (data) {
        if (_.size(data.errors) > 0) {
          _.each(data.errors, function (message) {
            xcart.trigger("message", { type: "error", message: message });
          });
        }
      },
    }
  );
});
