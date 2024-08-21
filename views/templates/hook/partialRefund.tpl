<script>
  {literal}

  $(document).ready(() => {

    var payline_custom_amount_refund = '{/literal}{$payline_custom_amount_refund}{literal}';
    var payline_custom_amount_refund_indication = '{/literal}{$payline_custom_amount_refund_indication}{literal}';
    var payline_custom_amount_refund_shipping = '{/literal}{$payline_custom_amount_refund_shipping}{literal}';


    $(document).on('click', '.partial-refund-display', function () {
      if ($('#doPartialRefundPayline').length == 0) {
        let paylineRefundCheckBox = `
                        <div class="cancel-product-element form-group" style="display: block;">
                                <div class="checkbox">
                                    <div class="md-checkbox md-checkbox-inline">
                                      <label>
                                          <input type="checkbox" id="doPartialRefundPayline" name="doPartialRefundPayline" material_design="material_design" value="1">
                                          <i class="md-checkbox-control"></i>
                                            ${payline_custom_amount_refund}
                                            <br><small>${payline_custom_amount_refund_indication}</small>
                                            <div class="paylineShippingCheckboxContainer"></div>
                                        </label>
                                    </div>
                                </div>
                         </div>`;

        $('.refund-checkboxes-container').append(paylineRefundCheckBox);
      }
    });

    $(document).on('click', '#doPartialRefundPayline', function () {
      // Create checkbox and insert for Payline refund
      if ($('#doPartialRefundPaylineIncludeShipping').length == 0) {
        let paylineRefundTextInput = `<div id="doPartialRefundPaylineIncludeShipping" class="cancel_order_amount_group form-group">
<div class="input-group checkbox">
    <div class="md-checkbox md-checkbox-inline">
        <label>
            <input type="checkbox" id="doPartialRefundPaylineIncludeShippingValue" name="doPartialRefundPaylineIncludeShippingValue" material_design="material_design">
                <i class="md-checkbox-control"></i>
                ${payline_custom_amount_refund_shipping}
        </label>
    </div>
</div>
</div>`;

        $('.paylineShippingCheckboxContainer').append(paylineRefundTextInput);
        $('#doPartialRefundPaylineIncludeShipping').hide();
      }

      $('#doPartialRefundPaylineIncludeShipping').toggle();
    });
  });
  {/literal}
</script>
