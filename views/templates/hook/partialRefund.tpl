<script>
  {literal}

  $(document).ready(() => {

    var payline_total_paid  = {/literal}{$payline_total_paid}{literal}.toFixed(2);
    var payline_custom_amount_refund = '{/literal}{$payline_custom_amount_refund}{literal}';
    var payline_custom_amount_refund_error = '{/literal}{$payline_custom_amount_refund_error}{literal}';


    $("form[name='cancel_product']").on('submit', function (event) {
      if($("#doPartialRefundPayline").is(":checked") && $("#doPartialRefundPaylineAmountValue").val()){
        var doPartialRefundPaylineAmountValue = parseFloat($("#doPartialRefundPaylineAmountValue").val()).toFixed(2);

        if(doPartialRefundPaylineAmountValue > 0 && doPartialRefundPaylineAmountValue <= payline_total_paid){
          return true;
        }else {
          $('#error-payline-partial-refund').show();
          return false
        }
      }
    })

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
                                        </label>
                                    </div>
                                </div>
                         </div>`;

        $('.refund-checkboxes-container').append(paylineRefundCheckBox);
      }
    });

    $(document).on('click', '#doPartialRefundPayline', function () {
      // Create checkbox and insert for Payline refund
      if ($('#doPartialRefundPaylineAmount').length == 0) {
        let paylineRefundTextInput = `<div id="doPartialRefundPaylineAmount" class="cancel_order_amount_group form-group">
  <label class="form-control-label" for="cancel_order_amount">Montant (TTC)</label>
  <div class="input-group">
    <input type="number" id="doPartialRefundPaylineAmountValue" name="doPartialRefundPaylineAmountValue" min="0.01" max="${payline_total_paid}"
           class="refund-amount form-control" aria-label="cancel_order_amount saisie" value="0.00" step=".01"/>
    <div class="input-group-append">
      <div class="input-group-text">€</div>
    </div>
    <small class="max-refund text-left">
      (Max ${payline_total_paid} € TTC)
    </small>
  </div>
    <div class="voucher-refund-type-negative-error" id="error-payline-partial-refund">${payline_custom_amount_refund_error}</div>
</div>`;

        $('.refund-checkboxes-container').append(paylineRefundTextInput);
        $('#doPartialRefundPaylineAmount').hide();
      }

      $('#doPartialRefundPaylineAmount').toggle();
    });
  });
  {/literal}
</script>
