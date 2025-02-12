{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<script>
  function onDidShowState(event) {

    const paylineParentID = document.querySelector('[data-js-selector="{$jsSelector}"]').parentElement.id;
    const paylineOptionID = paylineParentID.replaceAll('-additional-information', '');
    const paymentConfirmation = document.querySelector('#payment-confirmation button[type="submit"]');
    const agreements = document.querySelectorAll('input[name="conditions_to_approve[terms-and-conditions]"]');
    const paylinePaymentsButton = document.querySelectorAll('.pl-pay-btn');

    let paymentConfirmationOriginalVisibity = '';
    let wasPaylineBefore = false;

    if (paymentConfirmation) {
      paymentConfirmationOriginalVisibity = paymentConfirmation.style.visibility;
    }


    const areAggreementsAccepted = () => {
      let isChecked = true;
      Array.from(agreements).forEach(agreement => {
        if (agreement.checked === false) {
          isChecked = false;
        }
      });
      return isChecked;
    }

    const setPaylinePaymentButtonsState = () => {
      const acceptedAgreements = areAggreementsAccepted();
      Array.from(paylinePaymentsButton).forEach(button => {
        if (!acceptedAgreements) {
          button.setAttribute('disabled', 'disabled');
        } else {
          button.removeAttribute('disabled');
        }
      });
    }

    Array.from(document.querySelectorAll('.payment-options input[type="radio"]')).forEach(paymentMethodRadio => {
      paymentMethodRadio.addEventListener('change', (e) => {
        if (e.target.getAttribute('id') === paylineOptionID) {
          wasPaylineBefore = true;

          //--> Hide the command button
          if (paymentConfirmation) {
            paymentConfirmation.style.visibility = "hidden";
          }

          //--> Init payment buttons state
          setPaylinePaymentButtonsState();

          //--> Add event listener to agreements
          Array.from(agreements).forEach(agreement => {
            agreement.addEventListener('change', setPaylinePaymentButtonsState);
          });
        } else {
          //--> Clean up
          if ( wasPaylineBefore === true ) {

            //--> Restore the command button
            if (paymentConfirmation) {
              paymentConfirmation.style.visibility = paymentConfirmationOriginalVisibity;
            }

            //--> Remove event listener to agreements
            Array.from(agreements).forEach(agreement => {
              agreement.removeEventListener('change', setPaylinePaymentButtonsState);
            });
            wasPaylineBefore = false;
          }
        }
      });
    });
  }
</script>

<section id="content" data-js-selector="{$jsSelector}">
      <div
        id="PaylineWidget"
        data-auto-init="true"
        data-token="{$payline_token}"
        data-template="{$payline_ux_mode}"
        data-embeddedredirectionallowed="false"
        data-event-didshowstate="onDidShowState"
      >
      </div>
</section>

{foreach from=$payline_assets item=paylineAssetsUrls key=assetType}
  {foreach from=$paylineAssetsUrls item=paylineAssetsUrl}
    {if $assetType == 'js'}
      <script src="{$paylineAssetsUrl}"></script>
    {elseif $assetType == 'css'}
      <link href="{$paylineAssetsUrl}" rel="stylesheet" />
    {/if}
  {/foreach}
{/foreach}
