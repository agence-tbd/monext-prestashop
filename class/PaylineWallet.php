<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class PaylineWallet
{
    /**
     * Insert wallet id into table
     * @param int $idCustomer
     * @param string $walletId
     * @return bool
     */
    public static function insert($idCustomer, $walletId)
    {
        return Db::getInstance()->execute('
            INSERT IGNORE INTO `'._DB_PREFIX_.'payline_wallet_id` (`id_customer`, `wallet_id`, `date_add`)
            VALUES('.$idCustomer .',"'. $walletId.'", NOW())
            ');
    }

    /**
     * Retrieve wallet_id by id_customer
     * @param int $idCustomer
     * @return string
     */
    public static function getWalletByIdCustomer($idCustomer)
    {
        $result = Db::getInstance()->getValue('SELECT `wallet_id` FROM `'._DB_PREFIX_.'payline_wallet_id` WHERE `id_customer`='.(int)$idCustomer);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Retrieve id_customer by wallet_id
     * @param string $walletId
     * @return string
     */
    public static function getIdCustomerByWalletId($walletId)
    {
        $result = Db::getInstance()->getValue('SELECT `id_customer` FROM `'._DB_PREFIX_.'payline_wallet_id` WHERE `wallet_id`='.(int)$walletId);
        if (!empty($result)) {
            return $result;
        }

        return null;
    }

    /**
     * Generate a new wallet_id for customer and save it
     * @param int $idCustomer
     * @return string|null
     */
    public static function generateWalletId($idCustomer)
    {
        $walletId = Tools::passwdGen(50);
        $insert = self::insert($idCustomer, $walletId);

        if ($insert) {
            return $walletId;
        }
        return null;
    }
}
