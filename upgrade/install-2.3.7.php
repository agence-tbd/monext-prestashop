<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_3_7($module)
{

    if ($module->isRegisteredInHook('PaymentReturn')) {
        $module->unregisterHook('PaymentReturn');
    }

    if ($module->isRegisteredInHook('displayAdminOrderLeft')) {
        $module->unregisterHook('displayAdminOrderLeft');
    }

    $module->registerHook('displayAdminOrderMain');
    $module->registerHook('displayPaymentReturn');

    return true;
}
