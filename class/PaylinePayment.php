<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class PaylinePayment
{

    /**
     * @param int $idCart
     * @param string $token
     * @param string $resultCode
     * @param string $message
     * @param string $type
     * @param string $contractNumber
     * @param string $transactionId
     * @param array $additionalData
     * @return bool
     */
    public static function insert($idCart, $token, $resultCode, $message, $type, $contractNumber, $transactionId, array $additionalData = [])
    {
        return Db::getInstance()->execute('
            INSERT IGNORE INTO `'._DB_PREFIX_.'payline_web_payment` (`id_cart`, `token`, `result_code`, `message`, `type`, `contract_number`, `transaction_id`, `additional_data`, `date_add`)
            VALUES('.(int)$idCart.',"'.$token.'","'.$resultCode.'","'.$message.'","'.$type.'",'.$contractNumber.',"'.$transactionId.'",\''.json_encode($additionalData).'\', NOW())');
    }

    /**
     * Retrieve payments detail by token
     * @param string $token
     * @return array|bool
     */
    public static function getPaymentByToken($token)
    {
        $queryResult =  Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'payline_web_payment` WHERE `token`="'.$token.'"');

        if($queryResult) {

            $result = [
                'result' => [
                    'code' => $queryResult['result_code'],
                    'shortMessage' => $queryResult['message']
                ],
                'order' => [
                    'ref' => $queryResult['id_cart']
                ],
                'transaction' => [
                    'id' => $queryResult['transaction_id']
                ],
                'token' => $queryResult['token'],
                'contractNumber' => $queryResult['contract_number'],
            ];

            // Because some keys are not always presents
            foreach (json_decode($queryResult['additional_data'], null,512,JSON_OBJECT_AS_ARRAY) as $additionalDataKey => $additionalData) {
                $result[$additionalDataKey] = $additionalData;
            }

            return $result;
        }
        return false;
    }
}
