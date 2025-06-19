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

function upgrade_module_2_3_9($module)
{
    Configuration::updateValue('PAYLINE_ERROR_REFUSED', 'Your payment has been refused');
    Configuration::updateValue('PAYLINE_ERROR_CANCELLED', 'Your payment has been cancelled');
    Configuration::updateValue('PAYLINE_ERROR_ERROR', 'Your payment is in error');

    return true;
}
