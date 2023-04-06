{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{if isset($payline_subtitle) && strlen($payline_subtitle)}
	<p>{$payline_subtitle}</p>
{/if}

{if isset($payline_contracts)}
	<ol class="list-unstyled">
	{foreach from=$payline_contracts item=payline_contract}
		{if !empty($payline_contract.enabled)}
			<li class="d-inline">
				<img src="{$urls.base_url}modules/payline/views/img/contracts/{$payline_contract.logo}" alt="{$payline_contract.label|escape:'html':'UTF-8'}" title="{$payline_contract.label|escape:'html':'UTF-8'}" />
			</li>
		{/if}
	{/foreach}
	</ol>
{/if}

<div
	id="PaylineWidget"
	data-auto-init="false"
	data-token="{$payline_token}"
	data-template="{$payline_ux_mode}"
	data-embeddedredirectionallowed="false"
>
</div>
{foreach from=$payline_assets item=paylineAssetsUrls key=assetType}
	{foreach from=$paylineAssetsUrls item=paylineAssetsUrl}
		{if $assetType == 'js'}
			<script src="{$paylineAssetsUrl}"></script>
		{elseif $assetType == 'css'}
			<link href="{$paylineAssetsUrl}" rel="stylesheet" />
		{/if}
	{/foreach}
{/foreach}
