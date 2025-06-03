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

function upgrade_module_2_3_10($module)
{
    $query = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'payline_wallet_id` (
            `id_customer` int(10) NOT NULL,
            `wallet_id` varchar(50) NOT NULL,
            `date_add` datetime NOT NULL,
            UNIQUE `id_customer` (`id_customer`),
            UNIQUE `wallet_id` (`wallet_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

    if (!Db::getInstance()->execute($query)) {
        return false;
    }

    setWalletForCurrentCustomer();
    return true;
}


function setWalletForCurrentCustomer()
{
    foreach (Customer::getCustomers() as $customer){
        PaylineWallet::insert($customer['id_customer'], Tools::hash((int)$customer['id_customer']));
    }
}
