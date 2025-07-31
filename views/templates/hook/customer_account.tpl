{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

<li>
	<a href="{$subscriptionControllerLink|escape:'html':'UTF-8'}">
		<i class="icon-refresh"></i>
		<span>{l s='Subscriptions' mod='payline'}</span>
	</a>
</li>
{if $walletIsEnable}
<li>
  <a href="{$walletControllerLink|escape:'html':'UTF-8'}">
    <i class="account_balance_wallet"></i>
    <span>{l s='My wallet' mod='payline'}</span>
  </a>
</li>
{/if}
