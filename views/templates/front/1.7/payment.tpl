{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<script>
  function waitForElement(selector, callback) {
    const observer = new MutationObserver((mutations, observer) => {
      const element = document.querySelector(selector);
      if (element) {
        callback(element);
        observer.disconnect();
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  function onDidShowState(event) {

    if ( event.state !== 'PAYMENT_METHODS_LIST' ) {
      return;
    }

    const paylineParentID = document.querySelector('[data-js-selector="{$jsSelector}"]').parentElement.id;
    const paylineOptionID = paylineParentID.replaceAll('-additional-information', '');
    const paymentConfirmation = document.querySelector('#payment-confirmation button[type="submit"]');
    const agreements = document.querySelectorAll('input[name="conditions_to_approve[terms-and-conditions]"]');

    //---> Wait agreements to be available
    let paylinePaymentsButton = Array.from(document.querySelectorAll('.pl-pay-btn'));
    let amazonPaymentButton = null;

    //--> Amazon is not a button, we do specific
    //--> Wait for Amazon image to be available
    waitForElement('.pl-amazon-pay .pl-pay-btn-container img', element => {
      amazonPaymentButton = element;

      amazonPaymentButton.addEventListener("click", e => {
        const acceptedAgreements = areAggreementsAccepted();
        if (!acceptedAgreements) {
          e.preventDefault();
          e.stopImmediatePropagation();
          return false;
        }
      }, true);

      //--> Disable attribute does nothing on images. Here it's just for CSS
      paylinePaymentsButton.push(amazonPaymentButton);
      setPaylinePaymentButtonsState();
    });

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
      paylinePaymentsButton.forEach(button => {
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
