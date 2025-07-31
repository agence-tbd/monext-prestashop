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
    $queries[]  = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'payline_wallet_id` (
            `id_customer` int(10) NOT NULL,
            `wallet_id` varchar(50) NOT NULL,
            `date_add` datetime NOT NULL,
            UNIQUE `id_customer` (`id_customer`),
            UNIQUE `wallet_id` (`wallet_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

    $queries[]  = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'payline_web_payment` (
            `id_cart` int(10) NOT NULL,
            `token` varchar(255) NOT NULL,
            `result_code` varchar(6) NOT NULL,
            `message` varchar(50) NOT NULL,
            `type` varchar(255) NOT NULL,
            `contract_number` varchar(255) NOT NULL,
            `transaction_id` varchar(50) NOT NULL,
            `additional_data` TEXT,
            `date_add` datetime NOT NULL,
            UNIQUE `token` (`token`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

    foreach ($queries as $query){
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
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
