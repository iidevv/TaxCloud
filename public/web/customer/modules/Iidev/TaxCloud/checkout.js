xcart.microhandlers.add(
  'taxcloud-blocker',
  '.taxcloud-data',
  function() {
    var taxCloudErrorsFlag = jQuery(this).data('taxclouderrorsflag');

    if ('undefined' == typeof(arguments.callee.handleCheckoutReadyCheck)) {
      arguments.callee.handleCheckoutReadyCheck = function(event, state)
      {
        if (state.result) {
          state.result = !taxCloudErrorsFlag;
        }
      }

      arguments.callee.handleUpdateCart = function(event, data)
      {
        if ('undefined' != typeof(data.taxCloudErrorsFlag)) {
          taxCloudErrorsFlag = data.taxCloudErrorsFlag;
        }
      }
    }

    xcart.bind('checkout.common.readyCheck', _.bind(arguments.callee.handleCheckoutReadyCheck, arguments.callee))
      .bind('updateCart', _.bind(arguments.callee.handleUpdateCart, arguments.callee))

  }
);

xcart.microhandlers.add(
  'taxcloud-check-address',
  '.taxcloud-check',
  function() {
    jQuery(this).click(
      function() {
        var address = {};
        var elm = jQuery(this);
        var className = elm.hasClass('shipping') ? 'step-shipping-address' : 'step-billing-address';
        var inputPrefix = elm.hasClass('shipping') ? 'shippingAddress' : 'billingAddress';
        jQuery('.' + className + ' .form :input').each(
          function() {
            var inp = jQuery(this);
            address[inp.attr('name').replace(/(?:shipping|billing)Address/, 'address')] = inp.val();
          }
        );

        elm.prop('disabled', true).addClass('disabled');

        var checkAddressData = _.defaults(
          address,
          {
            xcart_form_id: xliteConfig.form_id
          }
        );

        xcart.post(
          {'target': 'checkout', 'action': 'checkTaxCloudAddress'},
          function() {
            elm.prop('disabled', false).removeClass('disabled');
          },
          checkAddressData,
          {
            dataType: 'json',
            success: function (data) {
              if (_.size(data.errors) > 0) {
                _.each(
                  data.errors,
                  function(message) {
                    xcart.trigger('message', {'type': 'error', 'message': message});
                  }
                );

              } else {
                var changesCount = 0;
                _.each(
                  data.address,
                  function(v, k) {
                    var inp = jQuery('.' + className + ' .form :input[name="' + inputPrefix + '[' + k + ']"]').eq(0);
                    var orig = inp.val();
                    if (inp.length > 0 && inp.val() != v) {
                      inp.val(v);
                      changesCount++;
                    }
                  }
                );

                if (changesCount > 0) {
                  jQuery('.' + className + ' form').submit();
                }
              }
            }
          }
        );

        return false;
      }
    );
  }
);

xcart.microhandlers.add(
  'taxcloud-same-address',
  '#same_address',
  function() {
    var handler = function() {
      var checked = jQuery('#same_address:checked').length > 0;
      if (checked) {
        jQuery('.taxcloud-check-box.billing').hide();

      } else {
        jQuery('.taxcloud-check-box.billing').show();
      }
    };

    jQuery(this).change(handler);
  }
);
