<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 * @version   2.4.2
 */

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

if (!defined('_PS_VERSION_')) {
    exit;
}

$moduleAutoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($moduleAutoloadPath)) {
    require_once $moduleAutoloadPath;
}

require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'PaylineToken.php');
require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'PaylinePaymentGateway.php');
require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'PaylineWallet.php');
require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'PaylinePayment.php');
require_once(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'payline' . DIRECTORY_SEPARATOR . 'class' . DIRECTORY_SEPARATOR . 'PaylineCallbacks.php');

class payline extends PaymentModule
{
    /**
     * Order state for Payline specific processes
     * @var array
     */
    protected $customOrderStateList = array(
        'PAYLINE_ID_STATE_AUTOR' => array(
            'name' => array(
                'en' => 'Authorized payment',
                'fr' => 'Paiement autorisé',
            ),
            'send_email' => true,
            'color' => '#dfe0ff',
            'hidden' => false,
            'module_name' => 'payline',
            'unremovable' => true,
            'delivery' => false,
            'logable' => true,
            'invoice' => true,
            'template' => 'payment',
        ),
        'PAYLINE_ID_STATE_PENDING' => array(
            'name' => array(
                'en' => 'Waiting for payment confirmation',
                'fr' => 'En attente de confirmation de paiement',
            ),
            'send_email' => false,
            'color' => '#4169e1',
            'hidden' => false,
            'module_name' => 'payline',
            'unremovable' => true,
            'delivery' => false,
            'logable' => true,
            'invoice' => false,
            'template' => 'payment',
        ),
        'PAYLINE_ID_ORDER_STATE_NX' => array(
            'name' => array(
                'en' => 'REC/NX payment by Monext',
                'fr' => 'Paiement REC/NX par Monext',
            ),
            'send_email' => false,
            'color' => '#bbddee',
            'hidden' => false,
            'module_name' => 'payline',
            'unremovable' => true,
            'delivery' => false,
            'logable' => true,
            'invoice' => true,
            'template' => 'payment',
        ),
        'PAYLINE_ID_STATE_ALERT_SCHEDULE' => array(
            'name' => array(
                'en' => 'Alert scheduler',
                'fr' => 'Alerte échéancier',
            ),
            'send_email' => false,
            'color' => '#ffcdcf',
            'hidden' => false,
            'module_name' => 'payline',
            'unremovable' => true,
            'delivery' => false,
            'logable' => true,
            'invoice' => true,
        ),
    );

    // Errors constants
    const INVALID_AMOUNT = 1;

    const INVALID_CART_ID = 2;

    const SUBSCRIPTION_ERROR = 3;

    const ORDER_CREATION_ERROR = 4;

    const PAYLINE_WIDGET_CTA_MAX_LENGTH = 255;

    const PAYLINE_WIDGET_TEXT_UNDER_CTA_MAX_LENGTH = 255;

    protected $is_eu_compatible;

    protected $limited_currencies;

    protected $order_already_refund;

    protected $partialRefund = false;

    /**
     * Module __construct
     * @since 2.0.0
     * @return void
     */
    public function __construct()
    {
        $this->name = 'payline';
        $this->tab = 'payments_gateways';
        $this->module_key = '';
        $this->version = '2.4.2';
        $this->ps_versions_compliancy = array('min' => '1.7.8', 'max' => _PS_VERSION_);
        $this->author = 'Monext';

        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->need_instance = true;

        $this->bootstrap = true;

        parent::__construct();
        $this->displayName = 'Monext';
        $this->description = $this->l('Pay with secure Monext gateway');
        $this->confirmUninstall = $this->l('Do you really want to remove the module?');
        $this->limited_countries = array();
        $this->limited_currencies = array();

        // if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            // Set minimum compliancy for PrestaShop 1.7
        //     $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
        // }
    }

    /**
     * Create Payline-related tables
     * @since 2.1.0
     * @return bool
     */
    protected function createTables()
    {
        $sql = [];

        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'payline_token` (
            `id_order` int(10) UNSIGNED NOT NULL,
            `id_cart` int(10) UNSIGNED NOT NULL,
            `token` varchar(255) NULL,
            `payment_record_id` varchar(12),
            `transaction_id` varchar(50),
            UNIQUE `id_order` (`id_order`),
            UNIQUE `id_cart` (`id_cart`)
        ) ENGINE='._MYSQL_ENGINE_.' CHARSET=utf8';

        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'payline_wallet_id` (
            `id_customer` int(10) NOT NULL,
            `wallet_id` varchar(50) NOT NULL,
            `date_add` datetime NOT NULL,
            UNIQUE `id_customer` (`id_customer`),
            UNIQUE `wallet_id` (`wallet_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        $sql[]  = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'payline_web_payment` (
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

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Declare that this module uses the new translation system (XLF catalogues).
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Create custom order state
     * @since 2.0.0
     * @return bool
     */
    public function createCustomOrderState()
    {
        foreach ($this->customOrderStateList as $configurationKey => $customOrderState) {
            $idOrderState = Configuration::get($configurationKey);
            if (!empty($idOrderState)) {
                // Check if order state needs update...
                $orderState = new OrderState($idOrderState);
                if (!Validate::isLoadedObject($orderState)) {
                    $idOrderState = false;
                }
            }
            if (empty($idOrderState)) {
                // Order state has to be created
                $orderState = new OrderState($idOrderState);
                $orderState->logo = 'paylineLogo';
                foreach ($customOrderState as $k => $v) {
                    if ($k != 'name') {
                        $orderState->{$k} = $v;
                    } else {
                        $orderState->name = array();
                        foreach ($v as $isoLang => $name) {
                            $idLang = Language::getIdByIso($isoLang);
                            if (!empty($idLang)) {
                                $orderState->name[$idLang] = $name;
                            }
                        }
                    }
                }
                if ($orderState->save()) {
                    // Save id_order_state
                    Configuration::updateValue($configurationKey, $orderState->id);
                    // Associate icon
                    $sourceLogo = _PS_MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR . 'logo.gif';
                    if (file_exists($sourceLogo) && is_readable($sourceLogo)) {
                        if (is_writable(_PS_IMG_DIR_ . 'os')) {
                            copy($sourceLogo, _PS_IMG_DIR_ . 'os' . DIRECTORY_SEPARATOR . (int)$orderState->id. '.gif');
                        }
                    }
                } else {
                    return false;
                }
            } else {
                // Update order state if needed
                $orderState = new OrderState($idOrderState);
                if (Validate::isLoadedObject($orderState)) {
                    $dirty = false;
                    foreach ($customOrderState as $k => $v) {
                        if ($k != 'name' && $k != 'color') {
                            if ($orderState->{$k} != $v) {
                                $dirty = true;
                                $orderState->{$k} = $v;
                            }
                        }
                    }
                    if ($dirty) {
                        try {
                            $orderState->save();
                        } catch (Exception $e) {
                            PrestaShopLogger::addLog('payline::createCustomOrderState - Cannot save Order State', 3, null, 'OrderState', $idOrderState);
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Install this Ajax controller
     * @since 2.3.6
     * @return bool
     */
    public function installTab()
    {
        $tab = new Tab();
        $tab->class_name = 'AdminPaylineLogsAjax';
        $tab->module = $this->name;
        $tab->active = true;
        $tab->id_parent = -1;
        $tab->name = array_fill_keys(
            Language::getIDs(false),
            $this->displayName
        );
        return $tab->add();
    }

    /**
     * Module install
     * @since 2.0.0
     * @return bool
     */
    public function install()
    {
        // Init some configuration values
        Configuration::updateValue('PAYLINE_API_STATUS', false);
        Configuration::updateValue('PAYLINE_LIVE_MODE', false);
        Configuration::updateValue('PAYLINE_MERCHANT_ID', false);
        Configuration::updateValue('PAYLINE_ACCESS_KEY', false);
        Configuration::updateValue('PAYLINE_POS', false);
        Configuration::updateValue('PAYLINE_SMARTDISPLAY_PARAM', false);
        Configuration::updateValue('PAYLINE_PROXY_HOST', false);
        Configuration::updateValue('PAYLINE_PROXY_PORT', false);
        Configuration::updateValue('PAYLINE_PROXY_LOGIN', false);
        Configuration::updateValue('PAYLINE_PROXY_PASSWORD', false);
        Configuration::updateValue('PAYLINE_CONTRACTS', false);
        Configuration::updateValue('PAYLINE_ALT_CONTRACTS_AS_MAIN', false);
        Configuration::updateValue('PAYLINE_ALT_CONTRACTS', false);
        Configuration::updateValue('PAYLINE_ERROR_REFUSED', 'Your payment has been refused');
        Configuration::updateValue('PAYLINE_ERROR_CANCELLED', 'Your payment has been cancelled');
        Configuration::updateValue('PAYLINE_ERROR_ERROR', 'Your payment is in error');


        // Run parent install process, register to hooks, then force update module position
        if (!parent::install()
            // Generic hooks
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('displayHeader')
            || !$this->registerHook('displayAdminOrderMain')
            || !$this->registerHook('displayCustomerAccount')
            || !$this->registerHook('actionAdminOrdersListingResultsModifier')
            || !$this->registerHook('actionObjectOrderSlipAddBefore')
            || !$this->registerHook('actionOrderStatusUpdate')
            || ($this->prestaVersionCompare('<') && !$this->registerHook('displayPayment'))
            || !$this->registerHook('displayPaymentReturn')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('actionObjectOrderDetailUpdateAfter')
            // Install custom order state
            || !$this->createCustomOrderState()
            // Install tables
            || !$this->createTables()
            || !$this->installTab()
        ) {
            return false;
        }

        return true;
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the FO.
     * @since 2.0.0
     * @return void
     */
    public function hookDisplayHeader()
    {
        // Display alert if payment failed
        if (($this->context->controller instanceof OrderController || $this->context->controller instanceof OrderOpcController || $this->context->controller instanceof paylinePaymentModuleFrontController) && Tools::getIsset('paylineError') && Tools::getValue('paylinetoken')) {

            $errorMessage = Configuration::get('PAYLINE_ERROR_'. Tools::getValue('paylineError'));
            if(!empty($errorMessage)){
                $this->context->controller->errors[] = $this->l($errorMessage);
            }else{
                $this->context->controller->errors[] = $this->l('There was an error while processing your previous payment.');
                $this->context->controller->errors[] = $this->l('Please try to use another payment method or another credit card.');
            }

            if (Tools::getIsset('paylineErrorCode')) {
                $errorCode = (int)Tools::getValue('paylineErrorCode');
                $humanErrorCode = $this->getHumanErrorCode($errorCode);
                if (!empty($humanErrorCode)) {
                    $this->context->controller->errors[] = $humanErrorCode;
                }
            }
        }
        // Add front.css on OPC
        if ($this->isPaymentAvailable()) {
            if ($this->context->controller instanceof OrderController ||
                ($this->prestaVersionCompare('<') && $this->context->controller instanceof OrderOpcController)) {
                $this->context->controller->addCSS($this->_path . 'views/css/front.css');
            }
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     * @since 2.0.0
     * @return void
     */
    public function hookDisplayBackOfficeHeader()
    {

        // Check for legacy module configuration or Symfony routes
        $isPaylineConfiguration = Tools::getValue('configure') == $this->name
            || Tools::getValue('module_name') == $this->name
            || (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/payline/configuration') !== false);

        if ($isPaylineConfiguration) {
            $this->context->controller->addJqueryUi('ui.sortable');
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
        if (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order')) {
            $this->context->controller->addJS($this->_path.'views/js/order.js');
        }
        if (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineCapture')) {
            $idTransaction = Tools::getValue('paylineCapture');
            $idOrder = (int)Tools::getValue('id_order');
            $order = new Order($idOrder);
            if (!empty($idTransaction) && Validate::isLoadedObject($order)) {
                // Process capture of a specific transaction
                $this->processTransactionCapture($order, $idTransaction);
            }
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineReset')) {
            $idTransaction = Tools::getValue('paylineReset');
            $idOrder = (int)Tools::getValue('id_order');
            $order = new Order($idOrder);
            if (!empty($idTransaction) && Validate::isLoadedObject($order)) {
                // Process reset of a specific transaction
                $this->processTransactionReset($order, $idTransaction);
            }
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineProcessFullRefund')) {
            $idOrder = (int)Tools::getValue('id_order');
            $order = new Order($idOrder);
            if (Validate::isLoadedObject($order)) {
                //If partial refund exist block process. todo modif l348 and add specificAmount
                $ordersSlip = OrderSlip::getOrdersSlip($order->id_customer, (int)$order->id);
                if(!count($ordersSlip)) {
                    // Process full refund of a specific order
                    $this->processFullOrderRefund($order);
                } else {
                    $this->context->controller->errors[] = $this->l('Partial refund exist, please continue with partial refund button');
                }
            }
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineCaptureOK')) {
            // Capture OK, show confirmation message
            $this->context->controller->confirmations[] = $this->l('Order was successfully captured');
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineResetOK')) {
            // Reset OK, show confirmation message
            $this->context->controller->confirmations[] = $this->l('Order was successfully cancelled (authorization reset)');
        } elseif (Tools::getValue('controller') == 'AdminOrders' && Tools::getValue('id_order') && Tools::getValue('paylineFullRefundOK')) {
            // Full refund OK, show confirmation message
            $this->context->controller->confirmations[] = $this->l('Order was successfully refunded');
        }
    }

    /**
     * Process full refund on an order (from BO)
     * @param Order $order
     * @return void
     */
    protected function processFullOrderRefund(Order $order)
    {
        try {
            // Check if transaction ID is the same
            $orderPayments = OrderPayment::getByOrderReference($order->reference);
            if (sizeof($orderPayments)) {
                // Retrieve transaction ID
                $paylineTransaction = current($orderPayments );
                $idTransaction = $paylineTransaction->transaction_id;

                $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                if (PaylinePaymentGateway::isValidResponse($transaction)) {

                    $amountToRefund = $transaction['payment']['amount'];
                    $remainingRefundAmount = $this->getRemainingRefundAmountFromTransaction($transaction);
                    if($remainingRefundAmount<$amountToRefund) {
                        $amountToRefund = $remainingRefundAmount;
                    }

                    if($amountToRefund) {
                        $refund = PaylinePaymentGateway::refundTransaction($idTransaction, $amountToRefund/100, $this->l('Manual full refund from PrestaShop BackOffice'));
                        if (!PaylinePaymentGateway::isValidResponse($refund)) {
                            // Refund NOK
                            $errors = PaylinePaymentGateway::getErrorResponse($refund);
                            $errorsMessage = sprintf($this->l('Unable to process the refund, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                            throw new Exception($errorsMessage);
                        }
                    }

                    $orderSlipDetailsList = array();
                    $products = $order->getProducts(false, false, true, false);
                    foreach ($products as $product) {
                        $orderSlipDetailsList[$product['id_order_detail']] = [
                            "quantity" => $product['product_quantity'],
                            "id_order_detail" => $product['id_order_detail'],
                            "unit_price" => $product['unit_price_tax_excl'],
                            "amount" => $product['unit_price_tax_incl'] * $product['product_quantity'],
                        ];
                    }

                    // Create order slip (available since PS 1.6.0.11)
                    if (method_exists('OrderSlip', 'create')) {
                        OrderSlip::create($order, $orderSlipDetailsList, null);
                    }

                    // Wait 1s because Payline API may take some time to be updated after a refund
                    sleep(1);

                    // Refund OK
                    // Change order state
                    $history = new OrderHistory();
                    $history->id_order = (int)$order->id;
                    $history->changeIdOrderState(_PS_OS_REFUND_, (int)$order->id);
                    $history->addWithemail();

                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder&paylineFullRefundOK=1');
                } else {
                    $errors = PaylinePaymentGateway::getErrorResponse($transaction);
                    $errorsMessage = sprintf($this->l('Unable to process the refund, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                    throw new Exception($errorsMessage);
                }
            } else {
                $errorsMessage =  $this->l('Unable to find any Monext transaction ID on this order');
                throw new Exception($errorsMessage);
            }
        } catch (Exception $e) {

            //Gestion d'erreur dans un hook
            $container = PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            $request = $container->get('request_stack')->getCurrentRequest();
            if ($request && $request->hasSession()) {
                $request->getSession()
                    ->getFlashBag()
                    ->add('error', $e->getMessage());
            }

            $orderViewUrl = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => $order->id, 'vieworder' => 1]);
            Tools::redirectAdmin($orderViewUrl);
        }
    }

    /**
     * Process transaction capture (from BO)
     * @param Order $order
     * @param int $idTransaction
     * @param bool $doRedirect
     * @return void
     */
    protected function processTransactionCapture(Order $order, $idTransaction, $doRedirect = true)
    {
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        if (PaylinePaymentGateway::isValidResponse($transaction)) {
            $capture = PaylinePaymentGateway::captureTransaction($idTransaction, 'CPT', $this->l('Manual capture from PrestaShop BackOffice'));
            if (PaylinePaymentGateway::isValidResponse($capture)) {
                // Capture OK
                if (Configuration::get('PAYLINE_WEB_CASH_VALIDATION') != _PS_OS_PAYMENT_) {
                    // Change order state
                    $history = new OrderHistory();
                    $history->id_order = (int)$order->id;
                    $history->changeIdOrderState(_PS_OS_PAYMENT_, (int)$order->id);
                    $history->addWithemail();
                }

                if ($doRedirect) {
                    Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder&paylineCaptureOK=1');
                }
            } else {
                // Capture NOK
                $errors = PaylinePaymentGateway::getErrorResponse($capture);
                $this->context->controller->errors[] = sprintf($this->l('Unable to process the capture, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
            }
        } else {
            $errors = PaylinePaymentGateway::getErrorResponse($transaction);
            $this->context->controller->errors[] = sprintf($this->l('Unable to process the capture, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
        }
    }

    /**
     * Process transaction reset (from BO)
     * @param Order $order
     * @param int $idTransaction
     * @return void
     */
    protected function processTransactionReset(Order $order, $idTransaction)
    {
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        if (PaylinePaymentGateway::isValidResponse($transaction)) {
            $capture = PaylinePaymentGateway::resetTransaction($idTransaction, $this->l('Manual reset from PrestaShop BackOffice'));
            if (PaylinePaymentGateway::isValidResponse($capture)) {
                // Reset OK
                // Change order state
                $history = new OrderHistory();
                $history->id_order = (int)$order->id;
                $history->changeIdOrderState(_PS_OS_ERROR_, (int)$order->id);
                $history->addWithemail();

                // Wait 1s because Payline API may take some time to be updated after a capture
                sleep(1);

                Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $order->id . '&vieworder&paylineResetOK=1');
            } else {
                // Reset NOK
                $errors = PaylinePaymentGateway::getErrorResponse($capture);
                $this->context->controller->errors[] = sprintf($this->l('Unable to process the reset, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
            }
        } else {
            $errors = PaylinePaymentGateway::getErrorResponse($transaction);
            $this->context->controller->errors[] = sprintf($this->l('Unable to process the reset, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
        }
    }

    /**
     * Display payment result on confirmation page
     * @since 2.1.0
     * @param array $params
     * @return void
     */
    public function hookActionAdminOrdersListingResultsModifier($params)
    {
        $idOrderStateNx = (int)Configuration::get('PAYLINE_ID_ORDER_STATE_NX');
        if (!empty($idOrderStateNx) && !empty($params['list']) && is_array($params['list'])) {
            foreach ($params['list'] as $orderListRow) {
                $idOrderList[] = (int)$orderListRow['id_order'];
            }
            // Get id_order list with the right order state
            $idOrderWaitingList = array();
            $idOrderWaitingListResult = Db::getInstance()->executeS('SELECT `id_order` FROM `'._DB_PREFIX_.'orders` WHERE `current_state`=' . (int)$idOrderStateNx . ' AND `id_shop` IN ('.implode(', ', Shop::getContextListShopID()).')');
            if (is_array($idOrderWaitingListResult)) {
                foreach ($idOrderWaitingListResult as $row) {
                    $idOrderWaitingList[] = (int)$row['id_order'];
                }
            }

            foreach ($params['list'] as &$orderListRow) {
                if (in_array((int)$orderListRow['id_order'], $idOrderWaitingList)) {
                    // Retrieve info from Payline
                    $order = new Order((int)$orderListRow['id_order']);
                    if (Validate::isLoadedObject($order) && $order->module == 'payline') {
                        // Retrieve original transaction via token
                        $token = PaylineToken::getTokenByIdOrder($order->id);
                        if (!empty($token)) {
                            $originalTransaction = PaylinePaymentGateway::getPaymentInformations($token);
                            if (!empty($originalTransaction['paymentRecordId']) && !empty($originalTransaction['payment']['mode']) &&
                                $originalTransaction['payment']['mode'] == 'NX' &&
                                isset($originalTransaction['billingRecordList']) && is_array($originalTransaction['billingRecordList']) &&
                                isset($originalTransaction['billingRecordList']['billingRecord']) && is_array($originalTransaction['billingRecordList']['billingRecord'])
                            ) {
                                $paymentRecord = PaylinePaymentGateway::getPaymentRecord($originalTransaction['payment']['contractNumber'], $originalTransaction['paymentRecordId']);
                                if (!empty($paymentRecord['recurring'])) {
                                    // Retrieve validated payment count
                                    $validTransactionCount = PaylinePaymentGateway::getValidatedRecurringPayment($paymentRecord);
                                    // Change order state name
                                    $orderListRow['osname'] = sprintf($this->l('Scheduler %s/%s paid'), (int)$validTransactionCount, (int)$paymentRecord['recurring']['billingLeft']);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Display subscribe information into customer account
     * @since 2.2.0
     * @param array $params
     * @return string
     */
    public function hookDisplayCustomerAccount($params)
    {
        $output = '';
        $themeName = $this->context->shop->theme->getName();

        $this->context->smarty->assign(array(
            'subscriptionControllerLink' => $this->context->link->getModuleLink('payline', 'subscriptions', array(), true),
            'walletControllerLink' => $this->context->link->getModuleLink('payline', 'wallet', array(), true),
            'walletIsEnable' => Configuration::get('PAYLINE_WEB_CASH_BY_WALLET'),
        ));

        /* @TODO Best solution for the moment, update when it will be available */
        if ($themeName === 'hummingbird') {
            $output .= $this->context->smarty->fetch($this->local_path.'views/templates/hook/hummingbird/customer_account.tpl');
        } else {
            $output .= $this->context->smarty->fetch($this->local_path.'views/templates/hook/customer_account.tpl');
        }

        return $output;
    }

    /**
     * Display payment result on confirmation page
     * @since 2.0.0
     * @param array $params
     * @return string
     */
    public function hookDisplayAdminOrderMain($params)
    {
        // Check if module is enabled
        if (!$this->active) {
            return;
        }

        $output = '';
        if (!empty($params['id_order'])) {
            $order = new Order($params['id_order']);
            if (Validate::isLoadedObject($order) && $order->module == 'payline') {
                // Retrieve original transaction via token
                $token = PaylineToken::getTokenByIdOrder($order->id);
                if (!empty($token)) {
                    $originalTransaction = PaylinePaymentGateway::getPaymentInformations($token);
                } else {
                    $idTransaction = PaylineToken::getIdTransactionByIdOrder($order->id);
                    if (!empty($idTransaction)) {
                        $originalTransaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                    }
                }
                if (!empty($originalTransaction['formatedPrivateDataList']['payment_method']) && $originalTransaction['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
                    // Subscription, get payment recordId
                    $paymentRecordId = PaylineToken::getPaymentRecordIdByIdOrder($order->id);
                    if (!empty($paymentRecordId)) {
                        // Get payment record
                        $paymentRecord = PaylinePaymentGateway::getPaymentRecord($originalTransaction['payment']['contractNumber'], $paymentRecordId);
                        if (PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                            // Add Order ID to each rows
                            foreach ($paymentRecord['billingRecordList']['billingRecord'] as &$billingRecord) {
                                if (isset($billingRecord['transaction']['id'])) {
                                    $linkedIdOrder = PaylineToken::getIdOrderByIdTransaction($billingRecord['transaction']['id']);
                                    if (!empty($linkedIdOrder)) {
                                        $billingRecord['pl_linkedIdOrder'] = $linkedIdOrder;
                                        $billingRecord['pl_linkToOrder'] = $this->context->link->getAdminLink('AdminOrders') . '&id_order=' . $linkedIdOrder . '&vieworder';
                                    }
                                }
                            }

                            // Display all records
                            $this->context->smarty->assign(array(
                                'id_order' => (int)$params['id_order'],
                                'billingListRec' => $paymentRecord['billingRecordList']['billingRecord'],
                                'paymentRecordId' => $paymentRecordId,
                            ));
                        }
                    }
                } elseif (!empty($originalTransaction['payment']['mode']) && $originalTransaction['payment']['mode'] == 'NX') {
                    if (isset($originalTransaction['billingRecordList']) && is_array($originalTransaction['billingRecordList']) && isset($originalTransaction['billingRecordList']['billingRecord']) && is_array($originalTransaction['billingRecordList']['billingRecord'])) {
                        // Display all records
                        $this->context->smarty->assign(array(
                            'id_order' => (int)$params['id_order'],
                            'billingList' => $originalTransaction['billingRecordList']['billingRecord'],
                            'paymentRecordId' => $originalTransaction['paymentRecordId'],
                        ));
                    }
                }

                // Retrieve order payments
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                $sameTransactionID = false;
                $transactionsList = array();
                foreach ($orderPayments as $orderPayment) {
                    if (preg_match('/payline/i', $orderPayment->payment_method) && !empty($orderPayment->transaction_id)) {
                        $transaction = PaylinePaymentGateway::getTransactionInformations($orderPayment->transaction_id);
                        if (!empty($transaction['associatedTransactionsList']['associatedTransactions'])) {
                            $associatedTransactions = $transaction['associatedTransactionsList']['associatedTransactions'];
                            if (array_key_exists('transactionId', $associatedTransactions)) {
                                $transactionsList[$associatedTransactions['transactionId']] = $associatedTransactions;
                                $transactionsList[$associatedTransactions['transactionId']]['originalTransaction'] = $transaction;
                            } else {
                                foreach ($associatedTransactions as $associatedTransaction) {
                                    $transactionsList[$associatedTransaction['transactionId']] = $associatedTransaction;
                                    $transactionsList[$associatedTransaction['transactionId']]['originalTransaction'] = $transaction;
                                }
                            }
                        }
                    }
                }
                // Do we allow capture action ?
                $allowCapture = !count($order->getHistory((int)$this->context->language->id, false, true, OrderState::FLAG_PAID));
                // Do we allow refund action ?
                $allowRefund = (!$allowCapture
                    && !count($order->getHistory((int)$this->context->language->id, _PS_OS_REFUND_, true))
                    && !count(OrderSlip::getOrdersSlip($order->id_customer, $order->id)));
                // Do we allow reset action
                $allowReset = $allowCapture;

                $this->context->smarty->assign(array(
                    'id_order' => (int)$params['id_order'],
                    'transactionsList' => $transactionsList,
                    'allowCapture' => $allowCapture,
                    'allowRefund' => $allowRefund,
                    'allowReset' => $allowReset,
                    'currency ' => $this->context->currency->iso_code,
                ));

                if ($this->prestaVersionCompare()) {
                    $output .= $this->context->smarty->fetch($this->local_path.'views/templates/hook/admin_order.tpl');
                } else {
                    $output .= $this->display(__FILE__, 'admin_order.tpl');
                }
            }
        }

        return $output;
    }

    /**
     * Process capture when order enter in a specific state
     * @since 2.0.0
     * @param array $params
     * @return void
     */
    public function hookActionOrderStatusUpdate($params)
    {
        if (!empty($params['id_order']) && !empty($params['newOrderStatus']) && Validate::isLoadedObject($params['newOrderStatus']) && $params['newOrderStatus']->id == Configuration::get('PAYLINE_WEB_CASH_VALIDATION') && '100' == Configuration::get('PAYLINE_WEB_CASH_ACTION')) {
            // We have to trigger capture here
            $idTransaction = null;
            $order = new Order((int)$params['id_order']);
            if (Validate::isLoadedObject($order)) {
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                if (sizeof($orderPayments)) {
                    // Retrieve transaction ID
                    $paylineTransaction = current($orderPayments);
                    $idTransaction = $paylineTransaction->transaction_id;
                }
            }
            if (!empty($idTransaction) && Validate::isLoadedObject($order)) {
                // Process capture of a specific transaction
                $this->processTransactionCapture($order, $idTransaction, false);
            }
        }elseif (!empty($params['id_order']) && !empty($params['newOrderStatus'])
            && Validate::isLoadedObject($params['newOrderStatus'])
            && ($params['newOrderStatus']->id == Configuration::get('PS_OS_CANCELED'))
        ) {
            $idTransaction = null;
            $order = new Order((int)$params['id_order']);
            if (Validate::isLoadedObject($order)) {
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                if (sizeof($orderPayments)) {
                    // Retrieve transaction ID
                    $paylineTransaction = current($orderPayments);
                    $idTransaction = $paylineTransaction->transaction_id;
                }
            }
            PaylinePaymentGateway::resetTransaction($idTransaction, $this->l('Manual reset from PrestaShop BackOffice'));
        }elseif (!empty($params['id_order']) && !empty($params['newOrderStatus'])
            && Validate::isLoadedObject($params['newOrderStatus'])
            && ($params['newOrderStatus']->id == Configuration::get('PS_OS_REFUND'))
        ) {
            if ($this->partialRefund || Tools::getValue('paylineProcessFullRefund') ){
                return;
            }

            $order = new Order((int)$params['id_order']);

            $orderPayments = OrderPayment::getByOrderReference($order->reference);
            if (sizeof($orderPayments)) {
                // Retrieve transaction ID
                $paylineTransaction = current($orderPayments);
                $idTransaction = $paylineTransaction->transaction_id;

                $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                if (PaylinePaymentGateway::isValidResponse($transaction)) {

                    $refundIsOk = false;
                    $remainingRefundAmount = $this->getRemainingRefundAmountFromTransaction($transaction);
                    if($remainingRefundAmount>0) {
                        $refund = PaylinePaymentGateway::refundTransaction($idTransaction, $remainingRefundAmount/100, $this->l('Manual refund from PrestaShop BackOffice'));
                        $refundIsOk = PaylinePaymentGateway::isValidResponse($refund);
                        $refundMessage = $this->l('Manual refund from PrestaShop BackOffice');
                    } else {
                        $refundIsOk = true;
                        $refundMessage = $this->l('The Monext transaction had already been refunded');
                    }


                    $this->order_already_refund = true;
                    if ($refundIsOk) {
                        $order_detail_list = $order->getOrderDetailList();
                        foreach ($order_detail_list as $order_detail) {
                            $order_detail = new OrderDetail((int)$order_detail['id_order_detail']);
                            $order_detail->product_quantity_refunded = $order_detail->product_quantity;
                            $order_detail->save();
                        }

                        $this->addMessageToOrder($order, $refundMessage);
                    } else {
                        // Refund NOK
                        $errors = PaylinePaymentGateway::getErrorResponse($refund);
                        $errorMessage = sprintf($this->l('Unable to process the refund, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                        $this->context->controller->errors[] = $errorMessage;
                        throw new Exception($errorMessage);
                    }
                }
            }
        }
    }

    /**
     * Process partial refund on card used for the payment
     * @since 2.0.0
     * @param array $params
     * @return void
     */
    public function hookActionObjectOrderSlipAddBefore($params)
    {
        // Prevent order slip creation in case we are into a full refund process
        if (Tools::getValue('paylineProcessFullRefund')) {
            return;
        }
        $this->partialRefund = true;

        $order = new Order($params['object']->id_order);

        $amountToRefund = (float)$params['object']->total_products_tax_incl + (float)$params['object']->total_shipping_tax_incl;

        try {
            if (Context::getContext()->employee->isLoggedBack()
                && Validate::isLoadedObject($order)
                && $order->module == $this->name
                && $order->hasBeenPaid()
                && $amountToRefund > 0
                && !Tools::getValue('generateDiscount') && !Tools::getValue('generateDiscountRefund'))
            {
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                if (sizeof($orderPayments)) {
                    // Retrieve transaction ID
                    $paylineTransaction = current($orderPayments);
                    $idTransaction = $paylineTransaction->transaction_id;

                    // Get transaction informations
                    $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
                    if (PaylinePaymentGateway::isValidResponse($transaction)) {
                        $remainingRefundAmount = $this->getRemainingRefundAmountFromTransaction($transaction);
                        if($remainingRefundAmount<$amountToRefund) {
                            $amountToRefund = $remainingRefundAmount;
                        }

                        if($amountToRefund) {
                            $refund = PaylinePaymentGateway::refundTransaction($idTransaction, $amountToRefund, $this->l('Manual partial refund from PrestaShop BackOffice'));
                            if (!PaylinePaymentGateway::isValidResponse($refund)) {
                                // Refund NOK
                                $errors = PaylinePaymentGateway::getErrorResponse($refund);
                                $errorsMessage = sprintf($this->l('Unable to process the refund, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                                $this->context->controller->errors[] = $errorsMessage;
                                throw new Exception($errorsMessage);
                            }
                        }

                        // Refund OK
                        $orderInvoice = new OrderInvoice($order->invoice_number);
                        if (!Validate::isLoadedObject($orderInvoice)) {
                            $orderInvoice = null;
                        }

                        // Wait 1s because Payline API may take some time to be updated after a refund
                        sleep(1);

                        $confirmMessage = $this->l('Order was successfully partially refunded');
                        if($amountToRefund<=0) {
                            $confirmMessage = $this->l('The Monext transaction had already been refunded');
                            $this->addMessageToOrder($order, $confirmMessage);
                        }

                        // Partial refund OK, show confirmation message.
                        // Unshowed msg, override by src/PrestaShopBundle/Controller/Admin/Sell/Order/OrderController.php:603
                        $this->context->controller->confirmations[] = $confirmMessage;
                    } else {
                        $errors = PaylinePaymentGateway::getErrorResponse($transaction);
                        $errorsMessage = sprintf($this->l('Unable to process the refund, Monext reported the following error: “%s“ (code %s)'), $errors['longMessage'], $errors['code']);
                        $this->context->controller->errors[] = $errorsMessage;
                        throw new Exception($errorsMessage);
                    }
                } else {
                    $errorsMessage = $this->l('Unable to find any Monext transaction ID on this order');
                    $this->context->controller->errors[] = $errorsMessage;
                    throw new Exception($errorsMessage);
                }
            }
        } catch (Exception $e) {

            //Gestion d'erreur dans un hook
            $container = PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            $request = $container->get('request_stack')->getCurrentRequest();
            if ($request && $request->hasSession()) {
                $request->getSession()
                    ->getFlashBag()
                    ->add('error', $e->getMessage());
            }

            $orderViewUrl = $this->context->link->getAdminLink('AdminOrders', true, [], ['id_order' => $order->id, 'vieworder' => 1]);
            Tools::redirectAdmin($orderViewUrl);
        }

    }

    /**
     * Sets order status to "Refunded" if all products are refunded
     * @since 2.3.2
     * @param $params
     * @return void
     */
    public function hookActionObjectOrderDetailUpdateAfter($params)
    {
        if(!empty($this->order_already_refund)) {
            return;
        }

        $order = new Order($params['object']->id_order);
        $products = $order->getProducts(false, false, false, false);
        $totallyRefund = true;
        foreach ($products as $product) {
            if ($product['product_quantity_refunded'] < $product['product_quantity']) {
                $totallyRefund = false;
            }
        }

        //Set State REFUND IF all product are refunds
        if($totallyRefund) {
            $history = new OrderHistory();
            $history->id_order = (int)$order->id;
            $history->changeIdOrderState(_PS_OS_REFUND_, (int)$order->id);
            $history->addWithemail();
        }
    }

    /**
     * Display payment result on confirmation page
     * @since 2.0.0
     * @param string $params
     * @return array
     */
    public function hookDisplayPaymentReturn($params)
    {
        // Check if module is enabled and PS < 1.7
        if (!$this->active || $this->prestaVersionCompare()) {
            return;
        }

        // Order
        $order = $params['objOrder'];
        // Last order state
        // $state = $order->getCurrentState();

        $idTransaction = null;
        $orderPayments = OrderPayment::getByOrderReference($order->reference);
        if (sizeof($orderPayments)) {
            // Retrieve transaction ID
            $paylineTransaction = current($orderPayments);
            $idTransaction = $paylineTransaction->transaction_id;
        }

        $this->smarty->assign(array(
            'payline_transaction_id' => $idTransaction,
        ));

        return $this->display(__FILE__, 'payment_return.tpl');
    }

    /**
     * Display payment button into payment module list (last order step)
     * @since 2.0.0
     * @param array $params
     * @return array of PaymentOption
     */
    public function hookPaymentOptions($params)
    {
        // Check if module is enabled and payment gateway is configured for at least one payment method
        // Check if current cart currency is allowed
        if (!$this->isPaymentAvailable()) {
            return;
        }

        $paymentMethodList = array();
        // Assign to template enabled cards/contracts
        $contractsList = PaylinePaymentGateway::getContractsForCurrentPos();
        $this->smarty->assign(array(
            'payline_contracts' => $contractsList,
        ));

        // Web payment
        if (Configuration::get('PAYLINE_WEB_CASH_ENABLE')
        )
        {
            $uxMode = Configuration::get('PAYLINE_WEB_CASH_UX');

            $webCash = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $webCashTitle = Configuration::get('PAYLINE_WEB_CASH_TITLE', $this->context->language->id);
            $webCashSubTitle = Configuration::get('PAYLINE_WEB_CASH_SUBTITLE', $this->context->language->id);
            if (!strlen($webCashTitle)) {
                $webCashTitle = $this->l('Simple payment');
            }
            $webCash->setModuleName($this->name)->setCallToActionText($webCashTitle);
            // Add additionnal information text
            $this->smarty->assign(array(
                'payline_title' => $webCashTitle,
                'payline_subtitle' => $webCashSubTitle,
            ));
            $webCash->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_additional_information.tpl'));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_title' => $webCashTitle,
                        'payline_subtitle' => $webCashSubTitle,
                        'payline_widget_customization' => $this->getWidgetCustomizations()
                    ));
                    $webCash->setAction('javascript:Payline.Api.init()');
                    $webCash->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/lightbox.tpl'));
                } else {
                    $webCash = null;
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
                if (!empty($paymentRequest['redirectURL'])) {
                    $webCash->setAction($paymentRequest['redirectURL']);
                } else {
                    $webCash = null;
                }
            } elseif ($uxMode == 'column' || $uxMode == 'tab') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {

                    $this->smarty->assign(array(
                        'payline_title' => $webCashTitle,
                        'payline_subtitle' => $webCashSubTitle,
                        'payline_token' => $paymentRequest['token'],
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                        'jsSelector' => 'paylineWidgetColumn',
                        'payline_widget_customization' => $this->getWidgetCustomizations()
                    ));

                    $webCash->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment.tpl'));
                } else {
                    $webCash = null;
                }

            } else {
                $webCash->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true));
            }

            if ($webCash !== null) {
                $paymentMethodList[] = $webCash;
            }
        }

        // Recurring payment
        if (Configuration::get('PAYLINE_RECURRING_ENABLE')
            && (!Configuration::get('PAYLINE_RECURRING_TRIGGER') || ($this->context->cart->getOrderTotal() > Configuration::get('PAYLINE_RECURRING_TRIGGER')))
        )
        {
            $uxMode = Configuration::get('PAYLINE_RECURRING_UX');

            $recurringPayment = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $recurringTitle = Configuration::get('PAYLINE_RECURRING_TITLE', $this->context->language->id);
            $recurringSubTitle = Configuration::get('PAYLINE_RECURRING_SUBTITLE', $this->context->language->id);
            if (!strlen($recurringTitle)) {
                $recurringTitle = $this->l('Nx payment');
            }
            $recurringPayment->setModuleName($this->name)->setCallToActionText($recurringTitle);
            // Add additionnal information text
            $this->smarty->assign(array(
                'payline_title' => $recurringTitle,
                'payline_subtitle' => $recurringSubTitle,
            ));
            $recurringPayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_additional_information.tpl'));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_ux_mode' => Configuration::get('PAYLINE_RECURRING_UX'),
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_title' => $recurringTitle,
                        'payline_subtitle' => $recurringSubTitle,
                    ));
                    $recurringPayment->setAction('javascript:Payline.Api.init()');
                    $recurringPayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/lightbox.tpl'));
                } else {
                    $recurringPayment = null;
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['redirectURL'])) {
                    $recurringPayment->setAction($paymentRequest['redirectURL']);
                } else {
                    $recurringPayment = null;
                }
            } else {
                $recurringPayment->setAction($this->context->link->getModuleLink($this->name, 'payment_nx', array(), true));
            }

            if ($recurringPayment !== null) {
                $paymentMethodList[] = $recurringPayment;
            }
        }

        // Subscribe payment (must be logged customer, not guest)
        if (Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')
            && !empty($this->context->cookie->id_customer)
            && $this->context->customer->isLogged()
        )
        {
            $subscribePayment = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $subscribeTitle = Configuration::get('PAYLINE_SUBSCRIBE_TITLE', $this->context->language->id);
            $subscribeSubTitle = Configuration::get('PAYLINE_SUBSCRIBE_SUBTITLE', $this->context->language->id);
            if (!strlen($subscribeTitle)) {
                $subscribeTitle = $this->l('Recurring payment');
            }
            $subscribePayment->setModuleName($this->name)->setCallToActionText($subscribeTitle);
            // Add additionnal information text
            $this->smarty->assign(array(
                'payline_title' => $subscribeTitle,
                'payline_subtitle' => $subscribeSubTitle,
            ));
            $subscribePayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_additional_information.tpl'));

            list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD);
            if (!empty($paymentRequest['redirectURL'])) {
                $subscribePayment->setAction($paymentRequest['redirectURL']);
            } else {
                $subscribePayment = null;
            }

            if ($subscribePayment !== null) {
                // Retrieve exclusive product list
                $exclusiveProductList = $this->getSubscribeProductList();

                if (!Configuration::get('PAYLINE_SUBSCRIBE_EXCLUSIVE')) {
                    // Non-exclusive method, check if products in cart are correct and eligible
                    if (is_array($exclusiveProductList) && sizeof($exclusiveProductList)) {
                        $cartProductList = $this->context->cart->getProducts();
                        if (is_array($cartProductList)) {
                            foreach ($cartProductList as $cartProduct) {
                                // We have to disable this method, no product are eligible
                                if (!in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                    $subscribePayment = null;
                                    break;
                                }
                            }
                        }
                    }

                    if ($subscribePayment !== null) {
                        $paymentMethodList[] = $subscribePayment;
                    }
                } else {
                    // Exclusive method, check if products in cart are correct
                    $cartProductList = $this->context->cart->getProducts();
                    $cartIntegrity = false;
                    $cartFullIntegrity = true;
                    $breakingIntegrityList = array();
                    // We have at least, one product OK
                    if (is_array($cartProductList)) {
                        foreach ($cartProductList as $cartProduct) {
                            if (in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                $cartIntegrity = true;
                            } else {
                                $cartFullIntegrity = false;
                                $breakingIntegrityList[] = $cartProduct['id_product'];
                            }
                        }
                    }

                    if (!$cartIntegrity) {
                        // We have to disable this method, no product are eligible
                        $subscribePayment = null;
                    } elseif (!$cartFullIntegrity) {
                        // We have to disable payment via Payline, wrong cart content
                        $breakingProductList = array();
                        foreach ($breakingIntegrityList as $idProduct) {
                            $product = new Product($idProduct, false, $this->context->cookie->id_lang);
                            $breakingProductList[] = $product->name;
                        }

                        $this->smarty->assign(array(
                            'paylineBreakingProductList' => $breakingProductList
                        ));

                        $subscribePayment->setAdditionalInformation($this->fetch('module:payline/views/templates/front/1.7/payment_sub_error.tpl'));
                        if ($subscribePayment !== null) {
                            $paymentMethodList[] = $subscribePayment;
                        }
                    } elseif ($cartIntegrity && $cartFullIntegrity) {
                        // We have to hide any other methods...
                        $paymentMethodList = array($subscribePayment);
                    }
                }
            }
        }

        return $paymentMethodList;
    }

    /**
     * Display payment button into payment module list (last order step)
     * @since 2.0.0
     * @param array $params
     * @return string
     */
    public function hookDisplayPayment($params)
    {
        // Check if module is enabled and payment gateway is configured for at least one payment method
        // Check if PS < 1.7
        if (!$this->isPaymentAvailable() || $this->prestaVersionCompare()) {
            return;
        }

        // Assign to template enabled cards/contracts
        $contractsList = PaylinePaymentGateway::getContractsForCurrentPos();
        $this->smarty->assign(array(
            'payline_contracts' => $contractsList,
        ));

        $this->context->controller->addCSS($this->_path.'views/css/front.css');

        $paymentReturn = '';

        if (Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            $uxMode = Configuration::get('PAYLINE_WEB_CASH_UX');
            $webCashTitle = Configuration::get('PAYLINE_WEB_CASH_TITLE', $this->context->language->id);
            if (!strlen($webCashTitle)) {
                $webCashTitle = $this->l('Simple payment');
            }
            $webCashSubTitle = Configuration::get('PAYLINE_WEB_CASH_SUBTITLE', $this->context->language->id);

            $this->smarty->assign(array(
                'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
                'payline_title' => $webCashTitle,
                'payline_subtitle' => $webCashSubTitle,
            ));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                        'payline_href' => 'javascript:Payline.Api.init()',
                    ));

                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams)= PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::WEB_PAYMENT_METHOD);
                if (!empty($paymentRequest['redirectURL'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_href' => $paymentRequest['redirectURL'],
                    ));

                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
                }
            } else {
                $this->smarty->assign(array(
                    'payline_href' => $this->context->link->getModuleLink($this->name, 'payment', array(), true),
                ));

                $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
            }
        }

        // Recurring payment
        if (Configuration::get('PAYLINE_RECURRING_ENABLE') && (!Configuration::get('PAYLINE_RECURRING_TRIGGER') || ($this->context->cart->getOrderTotal() > Configuration::get('PAYLINE_RECURRING_TRIGGER')))) {
            $uxMode = Configuration::get('PAYLINE_RECURRING_UX');
            $recurringTitle = Configuration::get('PAYLINE_RECURRING_TITLE', $this->context->language->id);
            if (!strlen($recurringTitle)) {
                $recurringTitle = $this->l('Nx payment');
            }
            $recurringSubTitle = Configuration::get('PAYLINE_RECURRING_SUBTITLE', $this->context->language->id);

            $this->smarty->assign(array(
                'payline_ux_mode' => $uxMode,
                'payline_title' => $recurringTitle,
                'payline_subtitle' => $recurringSubTitle,
            ));

            if ($uxMode == 'lightbox') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['token'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_assets' => PaylinePaymentGateway::getAssetsToRegister(),
                    ));
                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_nx.tpl');
                }
            } elseif ($uxMode == 'redirect') {
                list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::RECURRING_PAYMENT_METHOD);
                if (!empty($paymentRequest['redirectURL'])) {
                    $this->smarty->assign(array(
                        'payline_token' => $paymentRequest['token'],
                        'payline_href' => $paymentRequest['redirectURL'],
                    ));

                    $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_nx.tpl');
                }
            } else {
                $this->smarty->assign(array(
                    'payline_href' => $this->context->link->getModuleLink($this->name, 'payment_nx', array(), true),
                ));

                $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_nx.tpl');
            }
        }

        // Subscribe payment (must be logged customer, not guest)
        if (Configuration::get('PAYLINE_SUBSCRIBE_ENABLE') && !empty($this->context->cookie->id_customer) && $this->context->customer->isLogged()) {
            $subscribeTitle = Configuration::get('PAYLINE_SUBSCRIBE_TITLE', $this->context->language->id);
            if (!strlen($subscribeTitle)) {
                $subscribeTitle = $this->l('Recurring payment');
            }
            $subscribeSubTitle = Configuration::get('PAYLINE_SUBSCRIBE_SUBTITLE', $this->context->language->id);

            $this->smarty->assign(array(
                'payline_title' => $subscribeTitle,
                'payline_subtitle' => $subscribeSubTitle,
            ));

            list($paymentRequest, $paymentRequestParams) = PaylinePaymentGateway::createPaymentRequest($this->context, PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD);
            if (!empty($paymentRequest['redirectURL'])) {
                $this->smarty->assign(array(
                    'payline_token' => $paymentRequest['token'],
                    'payline_href' => $paymentRequest['redirectURL'],
                ));

                // Retrieve exclusive product list
                $exclusiveProductList = $this->getSubscribeProductList();

                if (!Configuration::get('PAYLINE_SUBSCRIBE_EXCLUSIVE')) {
                    // Non-exclusive method, check if products in cart are correct and eligible
                    $cartIntegrity = true;
                    if (is_array($exclusiveProductList) && sizeof($exclusiveProductList)) {
                        $cartProductList = $this->context->cart->getProducts();
                        if (is_array($cartProductList)) {
                            foreach ($cartProductList as $cartProduct) {
                                // We have to disable this method, no product are eligible
                                if (!in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                    $cartIntegrity = false;
                                    break;
                                }
                            }
                        }
                    }
                    if ($cartIntegrity) {
                        $paymentReturn .= $this->display(__FILE__, 'views/templates/hook/payment_sub.tpl');
                    }
                } else {
                    // Exclusive method, check if products in cart are correct
                    $cartProductList = $this->context->cart->getProducts();
                    $cartIntegrity = false;
                    $cartFullIntegrity = true;
                    $breakingIntegrityList = array();
                    // We have at least, one product OK
                    if (is_array($cartProductList)) {
                        foreach ($cartProductList as $cartProduct) {
                            if (in_array($cartProduct['id_product'], $exclusiveProductList)) {
                                $cartIntegrity = true;
                            } else {
                                $cartFullIntegrity = false;
                                $breakingIntegrityList[] = $cartProduct['id_product'];
                            }
                        }
                    }

                    if (!$cartIntegrity) {
                        // We have to disable this method, no product are eligible
                        // Nothing to do here
                    } elseif (!$cartFullIntegrity) {
                        // We have to disable payment via Payline, wrong cart content
                        $breakingProductList = array();
                        foreach ($breakingIntegrityList as $idProduct) {
                            $product = new Product($idProduct, false, $this->context->cookie->id_lang);
                            $breakingProductList[] = $product->name;
                        }

                        // Reset payment method list
                        $paymentReturn = '';

                        $this->context->controller->errors[] = $this->l('Your cart contains mixed products (recurring products and classic products).');
                        $this->context->controller->errors[] = $this->l('In order to be able to pay with Monext, please remove these products:');
                        foreach ($breakingProductList as $productName) {
                            $this->context->controller->errors[] = $productName;
                        }
                    } elseif ($cartIntegrity && $cartFullIntegrity) {
                        // We have to hide any other methods...
                        $paymentReturn = $this->display(__FILE__, 'views/templates/hook/payment_sub.tpl');
                    }
                }
            }
        }

        return $paymentReturn;
    }

    /**
     * Check if the payment is available
     * @since 2.0.0
     * @param int $paymentMethod
     * @return bool
     */
    public function isPaymentAvailable($paymentMethod = null)
    {
        if (!$this->active) {
            return;
        }
        // Check for module and API state
        if (!Configuration::get('PAYLINE_API_STATUS')) {
            return false;
        }
        // Check if at least one contract is enabled
        if ($paymentMethod == null && sizeof(PaylinePaymentGateway::getEnabledContracts()) == 0) {
            return false;
        }
        // Check if at least one payment method is available
        if ($paymentMethod == null && !Configuration::get('PAYLINE_WEB_CASH_ENABLE') && !Configuration::get('PAYLINE_RECURRING_ENABLE') && !Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')) {
            return false;
        }
        // Check if web payment method is available
        if ($paymentMethod == PaylinePaymentGateway::WEB_PAYMENT_METHOD && !Configuration::get('PAYLINE_WEB_CASH_ENABLE')) {
            return false;
        }
        // Check if recurring payment method is available
        if ($paymentMethod == PaylinePaymentGateway::RECURRING_PAYMENT_METHOD && !Configuration::get('PAYLINE_RECURRING_ENABLE')) {
            return false;
        }
        // Check if subscribe payment method is available
        if ($paymentMethod == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD && !Configuration::get('PAYLINE_SUBSCRIBE_ENABLE')) {
            return false;
        }
        // Check if current cart currency is allowed
        if ($this->prestaVersionCompare() && !$this->checkAllowedCurrency($this->context->cart)) {
            return false;
        }

        // Payment gateway configuration is OK and module is enabled
        return true;
    }

    /**
     * Check if the current cart currency is allowed
     * @since 2.0.0
     * @param Cart $cart
     * @return bool
     */
    protected function checkAllowedCurrency(Cart $cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check module requirements and dependencies
     * @since 2.0.0
     * @return bool
     */
    private function checkModuleRequirements()
    {
        $moduleRequirements = true;

        // Check PHP version
        if (PHP_VERSION_ID < 50400) {
            $this->context->controller->errors[] = $this->l('Your PHP version is too old, you must run at least PHP version 5.4.0');
            $moduleRequirements = false;
        }
        // Check curl PHP extension
        if (!extension_loaded('curl') || !function_exists('curl_init')) {
            $this->context->controller->errors[] = $this->l('PHP curl extension is missing, please ask your technical contact to enable it before continue.');
            $moduleRequirements = false;
        }
        // Check soap PHP extension
        if (!extension_loaded('soap')) {
            $this->context->controller->errors[] = $this->l('PHP soap extension is missing, please ask your technical contact to enable it before continue.');
            $moduleRequirements = false;
        }
        // Check openssl PHP extension
        if (!extension_loaded('openssl')) {
            $this->context->controller->errors[] = $this->l('PHP openssl extension is missing, please ask your technical contact to enable it before continue.');
            $moduleRequirements = false;
        }

        return $moduleRequirements;
    }

    /**
     * Load the configuration form
     * @since 2.0.0
     */
    public function getContent()
    {
        Tools::redirectAdmin(
            SymfonyContainer::getInstance()->get('router')->generate('payline_configuration_landing')
        );
    }

    /**
     * Get list of product id allowed by subscription method
     * @since 2.2.0
     * @return array
     */
    protected function getSubscribeProductList()
    {
        $subscribeProductListId = array();
        $subscribeProductListIdValue = Configuration::get('PAYLINE_SUBSCRIBE_PLIST');
        if (!empty($subscribeProductListIdValue)) {
            $subscribeProductListId = array_map('intval', explode(',', $subscribeProductListIdValue));
        }

        return $subscribeProductListId;
    }

    /**
     * Get multilang values for a specific input
     * @since 2.1.0
     * @param string $configKey
     * @return array
     */
    protected function getConfigLangValue($configKey)
    {
        $languages = Language::getLanguages(false);

        $langValues = array();
        foreach ($languages as $lang) {
            $langValues[(int)$lang['id_lang']] = Configuration::get($configKey, (int)$lang['id_lang']);
        }

        return $langValues;
    }

    /**
     * Check if a multilang values has an empty value
     * @since 2.2.0
     * @param string $configKey
     * @return array
     */
//    protected function langConfigHaveAtLeastOneEmptyValue($configKey)
//    {
//        foreach ($this->getConfigLangValue($configKey) as $langValue) {
//            if (!strlen($langValue)) {
//                return true;
//            }
//        }
//
//        return false;
//    }

    /**
     * Get readable human code from error code
     * @param int $errorCode
     * @return string
     */
    protected function getHumanErrorCode($errorCode)
    {
        switch ($errorCode) {
            case payline::INVALID_AMOUNT:
                return $this->l('Order can\'t be created because paid amount is different than total cart amount.');
            case payline::INVALID_CART_ID:
                return $this->l('Order can\'t be created because related cart does not exists.');
            case payline::SUBSCRIPTION_ERROR:
                return $this->l('Order can\'t be created because subscription failed.');
            default:
                return null;
        }
    }

    /**
     * @param string $key
     * @return string
     */
    private function maskAccessKey($key)
    {
        if (empty($key)) {
            return '';
        }
        $length = strlen($key);
        if ($length <= 3) {
            return $key;
        }
        $visible = substr($key, -3);
        $masked = str_repeat('*', $length - 3);
        return $masked . $visible;
    }

    /**
     * @param string $operator
     * @param string $versionToCompare
     * @return bool
     */
    protected function prestaVersionCompare($operator = ">=", $versionToCompare = '1.7.0.0')
    {
        if(version_compare(_PS_VERSION_, $versionToCompare, $operator)){
            return true;
        }
        return false;
    }

    protected function changeColor($hex, $strenght)
    {
        $strenght = (integer)$strenght;

        $hex = ltrim($hex, '#');

        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $factor = 1 - ($strenght / 100);

        $r = max(0, min(255, intval($r * $factor)));
        $g = max(0, min(255, intval($g * $factor)));
        $b = max(0, min(255, intval($b * $factor)));

        $newHex = sprintf("#%02x%02x%02x", $r, $g, $b);

        return $newHex;
    }

    protected function getWidgetCustomizations()
    {
        $widgetCustomization = [];
        if(Configuration::get('PAYLINE_WEB_WIDGET_CUSTOM')) {
            $widgetCustomization = array(
                'cta_label' => Configuration::get('PAYLINE_WEB_WIDGET_CTA_LABEL', $this->context->language->id),
                'cta_bg_color' => Configuration::get('PAYLINE_WEB_WIDGET_CSS_CTA_BG_COLOR'),
                'cta_bg_color_hexadecimal' => Configuration::get('PAYLINE_WEB_WIDGET_CSS_CTA_BG_COLOR_HEXADECIMAL'),
                'cta_bg_color_hover' => Configuration::get('PAYLINE_WEB_WIDGET_CSS_CTA_BG_COLOR_HOVER'),
                'cta_text_color' => Configuration::get('PAYLINE_WEB_WIDGET_CSS_CTA_TEXT_COLOR'),
                'font_size' => Configuration::get('PAYLINE_WEB_WIDGET_CSS_FONT_SIZE'),
                'border_radius' => Configuration::get('PAYLINE_WEB_WIDGET_CSS_BORDER_RADIUS'),
                'bg_color' => Configuration::get('PAYLINE_WEB_WIDGET_CSS_BG_COLOR'),
                'text_under_cta' => Configuration::get('PAYLINE_WEB_WIDGET_TEXT_UNDER_CTA', $this->context->language->id)
            );

            // Déterminer la couleur de base à utiliser entre cta_bg_color et cta_bg_color_hexadecimal
            $baseColor = !empty($widgetCustomization['cta_bg_color_hexadecimal'])
                ? $widgetCustomization['cta_bg_color_hexadecimal']
                : $widgetCustomization['cta_bg_color'];

            if(empty($baseColor)) {
                $baseColor= '#26a434';
            }


            $widgetCustomization['cta_bg_color_hover'] = $this->changeColor(
                $baseColor,
                $widgetCustomization['cta_bg_color_hover']
            );

        }
        return $widgetCustomization;
    }

    /**
     * @param array $transaction
     * @return mixed
     */
    public function getRemainingRefundAmountFromTransaction(array $transaction): mixed
    {
        $remainingRefundAmount = $transaction['payment']['amount'];
        if (!empty($transaction["associatedTransactionsList"]["associatedTransactions"])
            //Mix data in associatedTransactions, Grrr :-(
            && empty($transaction["associatedTransactionsList"]["associatedTransactions"]["transactionId"])
        ) {
            foreach ($transaction['associatedTransactionsList']['associatedTransactions'] as $associatedTransaction) {
                if ($associatedTransaction['status'] == 'OK' && $associatedTransaction['type'] == 'REFUND') {
                    $remainingRefundAmount -= $associatedTransaction['amount'];
                }
            }
        }
        return $remainingRefundAmount;
    }

    /**
     * @param Order $order
     * @param string $refundMessage
     * @return void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function addMessageToOrder(Order $order, string $refundMessage): void
    {
        $customer = new Customer($order->id_customer);
        $customerMessage = new CustomerMessage();
        $idCustomerThread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $order->id);

        if (!$idCustomerThread) {
            $customerThread = new CustomerThread();
            $customerThread->id_contact = 0;
            $customerThread->id_customer = (int)$order->id_customer;
            $customerThread->id_shop = (int)$this->context->shop->id;
            $customerThread->id_order = (int)$order->id;
            $customerThread->id_lang = (int)$this->context->language->id;
            $customerThread->email = $customer->email;
            $customerThread->status = 'open';
            $customerThread->token = Tools::passwdGen(12);
            $customerThread->add();
        } else {
            $customerThread = new CustomerThread((int)$idCustomerThread);
            $customerThread->status = 'open';
            $customerThread->update();
        }

        $customerMessage->id_customer_thread = $customerThread->id;
        $customerMessage->id_employee = $this->context->employee->id;
        $customerMessage->message = $refundMessage;
        $customerMessage->add();
    }
}
