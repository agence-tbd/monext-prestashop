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

    const ctaLabel = "{$payline_widget_customization.cta_label.1|strip_tags|escape:'js'}";
    const textUnderCta = "{$payline_widget_customization.text_under_cta.1|strip_tags|escape:'js'}";


      if ( event.state !== 'PAYMENT_METHODS_LIST' ) {
          return;
      }

  // Cocher les cgv au chargement du module
  //   const termsCheckbox = document.querySelector('input[name="conditions_to_approve[terms-and-conditions]"]');
  //   if (termsCheckbox && !termsCheckbox.checked) {
  //       termsCheckbox.checked = true;
  //       termsCheckbox.dispatchEvent(new Event('change'));
  //       termsCheckbox.dataset.paylineAutoChecked = 'true';
  //   }

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

    if (ctaLabel !== "") {
        document.querySelectorAll('.PaylineWidget .pl-pay-btn, .PaylineWidget .pl-btn').forEach(paylineCTA => {
            paylineCTA.innerHTML = ctaLabel.replace("[[amount]]", Payline.Api.getContextInfo("PaylineFormattedAmount"));
        });
    }

    if (textUnderCta) {
        document.querySelectorAll('.PaylineWidget .pl-pay-btn, .PaylineWidget .pl-btn').forEach(function(btn) {
            const p = document.createElement('p');
            p.innerHTML = textUnderCta;
            p.classList.add('pl-text-under-cta');
            btn.parentNode.insertBefore(p, btn.nextSibling);
        });
    }
  }

  function onFinalStateHasBeenReached (e) {
    if ( e.state === "PAYMENT_SUCCESS" ) {
      //--> Redirect to success page
      //--> Ticket is hidden by CSS
      //--> Wait for DOM update to simulate a click on the ticket confirmation button
      window.setTimeout(() => {
        const ticketConfirmationButton = document.getElementById("pl-ticket-default-ticket_btn");
        if ( ticketConfirmationButton ) {
          ticketConfirmationButton.click();
        }
      }, 0);
    }
  }


</script>

<style type="text/css">
    #PaylineWidget .pl-text-under-cta { text-align: center; margin-top: 26px; }

{if $payline_widget_customization.cta_bg_color == 'hexadecimal'}
    #PaylineWidget .pl-pay-btn { background-color: {{$payline_widget_customization.cta_bg_color_hexadecimal}}; }
{elseif $payline_widget_customization.cta_bg_color}
    #PaylineWidget .pl-pay-btn { background-color: {{$payline_widget_customization.cta_bg_color}}; }
{/if}

{if $payline_widget_customization.cta_bg_color_hover_darker }
    #PaylineWidget .pl-pay-btn:hover { background-color: {{$payline_widget_customization.cta_bg_color_hover_darker}}; }
{/if}

{if $payline_widget_customization.cta_bg_color_hover_lighter }
    #PaylineWidget .pl-pay-btn:hover { background-color: {{$payline_widget_customization.cta_bg_color_hover_lighter}}; }
{/if}

{if $payline_widget_customization.cta_bg_color_hover_lighter === '' && $payline_widget_customization.cta_bg_color_hover_darker === ''}
    #PaylineWidget .pl-pay-btn:hover { background-color: #1c7b27; }
{/if}

{if $payline_widget_customization.cta_text_color }
    #PaylineWidget .pl-pay-btn { color: {{$payline_widget_customization.cta_text_color}}; }
{/if}

{assign var="fontSize" value=""}
    {if $payline_widget_customization.font_size == 'small'}
        {assign var="fontSize" value="14px"}
    {elseif $payline_widget_customization.font_size == 'average'}
        {assign var="fontSize" value="20px"}
    {elseif $payline_widget_customization.font_size == 'big'}
        {assign var="fontSize" value="24px"}
{/if}

{if $fontSize }
    #PaylineWidget .pl-pay-btn { font-size: {{$fontSize}}; }
{/if}

{assign var="borderRadius" value=""}
{if $payline_widget_customization.border_radius == 'none'}
    {assign var="borderRadius" value="0"}
{elseif $payline_widget_customization.border_radius == 'small'}
    {assign var="borderRadius" value="6px"}
{elseif $payline_widget_customization.border_radius == 'average'}
    {assign var="borderRadius" value="8px"}
{elseif $payline_widget_customization.border_radius == 'big'}
    {assign var="borderRadius" value="24px"}
{/if}
{if $borderRadius }
    #PaylineWidget .pl-pay-btn { border-radius: {{$borderRadius}}; }
{/if}

{assign var="widgetBgColor" value=""}
{if $payline_widget_customization.bg_color == 'lighter'}
    {assign var="widgetBgColor" value="#fefefe"}
{elseif $payline_widget_customization.bg_color == 'darker'}
    {assign var="widgetBgColor" value="#dfdfdf"}
{/if}

{if $widgetBgColor }
    #PaylineWidget.PaylineWidget.pl-layout-tab .pl-paymentMethods { background-color: {{$widgetBgColor}}; }
    #PaylineWidget.PaylineWidget.pl-container-default .pl-pmContainer { background-color: {{$widgetBgColor}}; }
    #PaylineWidget.PaylineWidget.pl-layout-tab .pl-tab.pl-active { background-color: {{$widgetBgColor}}; }
{/if}
</style>

<section id="content" data-js-selector="{$jsSelector}">
      <div
        id="PaylineWidget"
        data-auto-init="true"
        data-token="{$payline_token}"
        data-template="{$payline_ux_mode}"
        data-embeddedredirectionallowed="true"
        data-event-didshowstate="onDidShowState"
        data-event-finalstatehasbeenreached="onFinalStateHasBeenReached"
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
