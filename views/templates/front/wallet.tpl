{*
*
* Payline module for PrestaShop
*
* @author    Monext <support@payline.com>
* @copyright Monext - http://www.payline.com
*
*}

{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='My wallet' mod='payline'}
{/block}

{block name='page_content'}
  <div id="PaylineWidget" data-token="{$payline_token}" data-template="{$payline_ux_mode}"></div>
{/block}
