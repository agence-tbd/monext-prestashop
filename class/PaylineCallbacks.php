<?php

use PrestaShop\PrestaShop\Core\Domain\Order\Exception\DuplicateOrderCartException;

class PaylineCallbacks
{
    /** @var payline */
    public $module;
    /** @var Context */
    protected $context;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->module = Module::getInstanceByName('payline');
    }

    /**
     * Process payment validation (customer shop return)
     * @param Cart $cart
     * @param array $paymentInfos
     * @param string $token
     * @param string $paymentRecordId
     * @return array
     * @throws Exception
     * @since 2.0.0
     */
    public function createOrder(Cart $cart, $paymentInfos, $token, $paymentRecordId = null)
    {
        $baseAmountPaid = $paymentInfos['payment']['amount'];

        // Added to support TRD and ANCV payments with multiple transactions (#40213)
        if(!empty($paymentInfos['paymentAdditionalList']['paymentAdditional']['payment']['amount'])) {
            $additionalAmount = $paymentInfos['paymentAdditionalList']['paymentAdditional']['payment']['amount'];
            $baseAmountPaid += $additionalAmount;
        }

        $amountPaid = ($baseAmountPaid / 100);

        // Set right order state depending on defined payment action
        if ($paymentInfos['payment']['action'] == 100) {
            $idOrderState = (int)Configuration::get('PAYLINE_ID_STATE_AUTOR');
        } else {
            if (PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$approvedResponseCode)) {
                // Transaction accepted
                $idOrderState = (int)Configuration::get('PS_OS_PAYMENT');
            } else if (PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$pendingResponseCode)) {
                // Transaction is pending
                $idOrderState = (int)Configuration::get('PAYLINE_ID_STATE_PENDING');
            }else{
                if($paymentInfos['result']['shortMessage'] == 'CANCELLED'){
                    $idOrderState = (int)Configuration::get('PS_OS_CANCELED');
                }else{
                    // Transaction error
                    $idOrderState = (int)Configuration::get('PS_OS_ERROR');
                }
            }
        }
        $paymentMethod = 'Payline';
        $orderMessage = 'Transaction #' . $paymentInfos['transaction']['id'];
        $extraVars = array(
            'transaction_id' => $paymentInfos['transaction']['id'],
        );
        $idCurrency = (int)$cart->id_currency;
        $secureKey = $paymentInfos['formatedPrivateDataList']['secure_key'];

        // Always clean Cart::orderExists cache before trying to create the order
        if (class_exists('Cache', false) && method_exists('Cache', 'clean')) {
            Cache::clean('Cart::orderExists_' . $cart->id);
        }

        $validateOrderResult = false;
        $order = null;
        $errorMessage = null;
        $errorCode = null;
        $orderExists = $cart->OrderExists();

        $checkAmountToPay = true;
        $fixOrderPayment = false;
        $totalAmountPaid = Tools::ps_round((float)$amountPaid, 2);

        if ($paymentInfos['payment']['mode'] == 'NX'
            || $paymentInfos['payment']['mode'] == 'REC'
        ) {
            $checkAmountToPay = false;
            $idOrderState = (int)Configuration::get('PAYLINE_ID_ORDER_STATE_NX');

            // Fake $amountPaid in order to create the order without payment error, we will fix the order payment after order creation
            $amountPaid = $cart->getOrderTotal();

        } else {
            // Web payment
            $totalAmountToPay = (float)Tools::ps_round((float)$cart->getOrderTotal(true, Cart::BOTH), 2);
        }

        // Unset pl_try cookie value
        if (isset($this->context->cookie->pl_try)) {
            unset($this->context->cookie->pl_try);
        }

        if (!$orderExists) {
            // Validate the order
            try {
                $validateOrderResult = $this->module->validateOrder(
                    $cart->id,
                    $idOrderState,
                    $amountPaid,
                    $paymentMethod,
                    $orderMessage,
                    $extraVars,
                    $idCurrency,
                    false,
                    $secureKey
                );

                if ($validateOrderResult) {
                    $order = new Order($this->module->currentOrder);
                    if (Validate::isLoadedObject($order)) {

                        $idOrderErrorState = [Configuration::get('PS_OS_ERROR'), Configuration::get('PS_OS_CANCELED')];
                        if(in_array($order->getCurrentState(),$idOrderErrorState)) {
                            throw new Exception('payline::createOrder - order is in error state');
                        }

                        $transactionId = $paymentInfos['transaction']['id'];
                        if(isset($paymentInfos['billingRecordList']['billingRecord'][0]['transaction']['id'])){
                            $transactionId = $paymentInfos['billingRecordList']['billingRecord'][0]['transaction']['id'];
                        }

                        // Save token and payment record id (if defined) for later usage
                        PaylineToken::insert($order, $cart, $token, $paymentRecordId, $transactionId);

                        if ($fixOrderPayment) {
                            // We need to fix the total paid real amount here
                            $order->total_paid_real = 0;
                            $order->save();
                            // Remove the previous order payment
                            $orderPayments = OrderPayment::getByOrderReference($order->reference);
                            foreach ($orderPayments as $orderPayment) {
                                $orderPayment->delete();
                            }
                            // Add the fixed order payment
                            $this->addOrderPaymentToOrder($order, $totalAmountPaid, $paymentInfos['transaction']['id']);
                        }
                    }
                }
            } catch (Exception $e) {
                $errorCode = payline::ORDER_CREATION_ERROR;
                if(!empty($paymentInfos['transaction']['id']) && $paymentInfos['result']['shortMessage'] == 'ACCEPTED') {
                    $idTransaction = $paymentInfos['transaction']['id'];
                    $reset = PaylinePaymentGateway::resetTransaction($idTransaction, $this->module->l('Automatic reset on error order creation'));
                    if (!PaylinePaymentGateway::isValidResponse($reset)) {
                        $refund = PaylinePaymentGateway::refundTransaction($idTransaction, null, $this->module->l('Automatic refund on error order creation'));
                    }
                }

                $result = $cart->duplicate();
                if (false === $result || !isset($result['cart'])) {
                    throw new DuplicateOrderCartException(sprintf('Cannot duplicate cart from order "%s"', $order->reference));
                } else {
                    $this->context->cart = $result['cart'];
                    $this->context->cookie->id_cart = $result['cart']->id;
                    $this->context->cookie->write();
                }

                $validateOrderResult = false;
                $errorMessage = $e->getMessage();
                PrestaShopLogger::addLog('payline::createOrder - Failed to create order: ' . $errorMessage, 1, null, 'Cart', $cart->id);
            } catch (DuplicateOrderCartException $e) {
                $errorMessage = $e->getMessage();
            }
        } elseif ($cart->secure_key == $paymentInfos['formatedPrivateDataList']['secure_key']) {
            // Secure key is OK
            $idOrder = Order::getIdByCartId($cart->id);
            $order = new Order($idOrder);
            // Retrieve order
            if (Validate::isLoadedObject($order)) {
                // Save token for later usage (if needed)
                PaylineToken::insert($order, $cart, $token, $paymentRecordId, $paymentInfos['transaction']['id']);

                // Check if transaction ID is the same
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                $sameTransactionID = false;
                foreach ($orderPayments as $orderPayment) {
                    if ($orderPayment->transaction_id == $paymentInfos['transaction']['id']) {
                        $sameTransactionID = true;
                    }
                }
                if (!$sameTransactionID) {
                    // Order already exists, but it looks to be a new transaction - What should we do ?
                    if ($paymentInfos['payment']['mode'] == 'NX') {
                        // New recurring payment, add a new transaction to the current order
                        $this->addOrderPaymentToOrder($order, $totalAmountPaid, $paymentInfos['transaction']['id']);
                    } else {
                        $order = null;
                    }
                }
            } else {
                // Unable to retrieve order ?
                PrestaShopLogger::addLog('payline::createOrder - Unable to retrieve order', 1, null, 'Cart', $cart->id);
            }
            $validateOrderResult = true;
        } elseif ($cart->secure_key != $paymentInfos['formatedPrivateDataList']['secure_key']) {
            // Order already exists for this cart and secure key is different
            // Secure key is NOK
            PrestaShopLogger::addLog('payline::createOrder - Secure key is different', 1, null, 'Cart', $cart->id);
            $validateOrderResult = false;
        }

        return array($order, $validateOrderResult, $errorMessage, $errorCode);
    }


    /**
     * @param $token
     * @return false|string
     */
    protected function acquireLockForToken($token) {
        if(!$token) {
            return false;
        }

        $lockId = 'payline_'.sha1($token);
        if(!Db::getInstance()->execute('SELECT GET_LOCK("'.$lockId.'", 10)', false)) {
            PrestaShopLogger::addLog('payline::cannot acquire lock for token: ' . $token, 1);
            return false;
        }
        return $lockId;
    }


    /**
     * @param $lockId
     * @return bool
     *
     */
    protected function releaseLockForToken($lockId) {
        if ($lockId) {
            return Db::getInstance()->execute('SELECT RELEASE_LOCK("'.$lockId.'")', false);
        }
        return false;
    }

    /**
     * Process payment validation (customer shop return)
     * @param string $token
     * @return void
     * @throws DuplicateOrderCartException
     * @throws Exception
     * @since 2.0.0
     */
    public function processCustomerPaymentReturn($token)
    {
        $paymentInfos = PaylinePaymentGateway::getWebPaymentDetails($token);
        $errorCode = null;
        $order = null;

        $module = $this->module;
        $lockId = $this->acquireLockForToken($token);
        if (!$lockId) {
            return;
        }

        $cart = $this->context->cart;
        $idCart = null;
        if (PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$approvedResponseCode)
            || PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$pendingResponseCode)) {
            // Transaction approved or pending

            // OK we can process the order via customer return
            if($cart instanceof Cart){
                $idCart = $cart->id;
            }
            // Check if cart exists
            $cart = new Cart($idCart);
            if (Validate::isLoadedObject($cart)) {
                list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos, $token);
            } else {
                // Invalid Cart ID
                $errorCode = payline::INVALID_CART_ID;
            }
        } else {
            $errorCode = $paymentInfos['result']['code'];
            if($cart instanceof Cart){
                /** $order Order */
                list($order, $validateOrderResult, $errorMessage) = $this->createOrder($cart, $paymentInfos, $token);
                if($order->getCurrentState() == Configuration::get('PS_OS_ERROR')){
                    PaylineToken::insert($order, $cart, $token, null, $paymentInfos['transaction']['id']);
                    $this->addOrderPaymentToOrder($order, $paymentInfos['payment']['amount']/100, $paymentInfos['transaction']['id']);
                }
            }
        }

        // Order has been created, redirect customer to confirmation page
        if (empty($errorCode) && isset($order) && $order instanceof Order && Validate::isLoadedObject($order)) {
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $idCart . '&id_module=' . $module->id . '&id_order=' . $module->currentOrder . '&key=' . $this->context->customer->secure_key);
        }

        $urlParams = array(
            'paylineError' => $paymentInfos['result']['shortMessage'],
            'paylinetoken' => $token,
        );
        if (isset($errorCode)) {
            $urlParams['paylineErrorCode'] = $errorCode;
        }

        // Refused payment, or any other error case (customer case)
        if (!empty($paymentInfos['formatedPrivateDataList']['payment_method']) && $paymentInfos['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
            Tools::redirect($this->context->link->getPageLink('order', true, $this->context->language->id, $urlParams));
        } else {
            Tools::redirect($this->context->link->getPageLink('order', true, $this->context->language->id, $urlParams));
        }

        $this->releaseLockForToken($lockId);
    }

    /**
     * Process payment from notification
     * @param string $token
     * @return void
     * @throws Exception
     * @since 2.0.0
     */
    public function processNotification($token)
    {
        $lockId = $this->acquireLockForToken($token);
        if(!$lockId) {
            return;
        }

        $validateOrderResult = false;
        $paymentInfos = PaylinePaymentGateway::getWebPaymentDetails($token);
        // Check if id_cart and secure_key are the same
        if (isset($paymentInfos['formatedPrivateDataList']) && is_array($paymentInfos['formatedPrivateDataList'])
            && isset($paymentInfos['formatedPrivateDataList']['id_cart'])
        ) {
            if (PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$approvedResponseCode) || PaylinePaymentGateway::isValidResponse($paymentInfos, PaylinePaymentGateway::$pendingResponseCode)) {
                // Transaction approved or pending
                // OK we can process the order via customer return
                $idCart = (int)$paymentInfos['formatedPrivateDataList']['id_cart'];
                // Check if cart exists
                $cart = new Cart($idCart);
                if (!Validate::isLoadedObject($cart)) {
                    $this->displayNotificationMessageAndStop(array(
                        'result' => $validateOrderResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
                    ), $lockId);
                }

                // Create the recurrent wallet payment
                if (!empty($paymentInfos['formatedPrivateDataList']['payment_method'])
                    && $paymentInfos['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
                    $subscriptionRequest = PaylinePaymentGateway::createSubscriptionRequest($paymentInfos);
                    if (PaylinePaymentGateway::isValidResponse($subscriptionRequest, array('02500', '02501'))) {
                        // Create the order
                        list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos, $token, $subscriptionRequest['paymentRecordId']);
                    } else {
                        // Unable to create subscription
                        $errorCode = payline::SUBSCRIPTION_ERROR;
                        // Cancel the previous transaction
                        $cancelTransactionResult = PaylinePaymentGateway::cancelTransaction($paymentInfos, $this->module->l('Error: automatic cancel (cannot create subscription)'));
                        $this->displayNotificationMessageAndStop(array(
                            'result' => $validateOrderResult,
                            'error' => 'Unable to create subscription',
                            'errorCode' => $subscriptionRequest['result']['code'],
                        ), $lockId);
                    }
                } else {
                    // Create the order
                    list($order, $validateOrderResult, $errorMessage, $errorCode) = $this->createOrder($cart, $paymentInfos, $token);
                }
            } else {
                // Refused payment, or any other error case (customer case)
                $this->displayNotificationMessageAndStop(array(
                    'result' => $validateOrderResult,
                    'error' => 'Transaction was not approved, or any other error case (customer case)',
                    'errorCode' => $paymentInfos['result']['code'],
                ), $lockId);
            }
            if (ob_get_length() > 0) {
                ob_clean();
            }
            $this->displayNotificationMessageAndStop(array('result' => $validateOrderResult), $lockId);
        }

        $this->releaseLockForToken($lockId);
    }


    protected function displayNotificationMessageAndStop($message, $lockId) {
        $this->releaseLockForToken($lockId);
        die(json_encode($message));
    }



    /**
     * Process order update from transaction notification
     * @param string $idTransaction
     * @since 2.0.0
     * @return void
     */
    public function processTransactionNotification($idTransaction)
    {
        $validateOrderResult = false;
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        // Wait for a transaction into pending state
        if (PaylinePaymentGateway::isValidResponse($transaction, PaylinePaymentGateway::$pendingResponseCode)) {
            if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList'])
                && isset($transaction['formatedPrivateDataList']['id_cart'])
                && isset($transaction['formatedPrivateDataList']['id_customer'])
                && isset($transaction['formatedPrivateDataList']['secure_key'])
            ) {
                // OK we can process the order via customer return
                $idCart = (int)$transaction['formatedPrivateDataList']['id_cart'];
                // Check if cart exists
                $cart = new Cart($idCart);
                if (!Validate::isLoadedObject($cart)) {
                    die(json_encode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
                    )));
                }
                // Check secure_key and id_customer on the cart, compare it to the transaction
                if ($cart->secure_key != $transaction['formatedPrivateDataList']['secure_key'] || $cart->id_customer != $transaction['formatedPrivateDataList']['id_customer']) {
                    die(json_encode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Transaction is not linked to the right Customer for Cart ID #'.$idCart,
                    )));
                }
                // Check that the transaction have at least one statusHistoryList items
                if (!isset($transaction['statusHistoryList']) || !is_array($transaction['statusHistoryList']) || !sizeof($transaction['statusHistoryList'])) {
                    die(json_encode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Transaction does not contains any statusHistoryList item',
                    )));
                }
                // Check that the transaction have at least one statusHistory items
                if (!isset($transaction['statusHistoryList']['statusHistory']) || !is_array($transaction['statusHistoryList']['statusHistory']) || !sizeof($transaction['statusHistoryList']['statusHistory'])) {
                    die(json_encode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Transaction does not contains any statusHistory item',
                    )));
                }

                // Always clean Cart::orderExists cache before trying to create the order
                if (class_exists('Cache', false) && method_exists('Cache', 'clean')) {
                    Cache::clean('Cart::orderExists_' . $cart->id);
                }
                $orderExists = $cart->OrderExists();
                if (!$orderExists) {
                    // There is no order for this cart
                    die(json_encode(array(
                        'result' => $validateOrderResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Order does not exists',
                    )));
                }

                // Retrieve order
                $idOrder = Order::getIdByCartId($cart->id);
                $order = new Order($idOrder);
                if (Validate::isLoadedObject($order)) {
                    $statusHistoryList = $transaction['statusHistoryList']['statusHistory'];

                    // Retrieve the latest status (already sorted by date into PaylinePaymentGateway)
                    $statusHistory = current($statusHistoryList);
                    if ($statusHistory['status'] == 'ACCEPTED') {
                        // Transaction accepted
                        if (!$order->hasBeenPaid()) {
                            // Change order state if order has not already been paid
                            $validateOrderResult = true;

                            $history = new OrderHistory();
                            $history->id_order = (int)$order->id;
                            $history->changeIdOrderState(_PS_OS_PAYMENT_, (int)$order->id);
                            $history->addWithemail();
                        }
                    } elseif ($statusHistory['status'] == 'ON_HOLD_PARTNER') {
                        // We are still waiting for the transaction validation, nothing to do here
                    } else {
                        // Transaction refused
                        if ($order->getCurrentState() != _PS_OS_CANCELED_) {
                            // Change order state if order has not already been canceled
                            $validateOrderResult = true;

                            // Change order state
                            $history = new OrderHistory();
                            $history->id_order = (int)$order->id;
                            $history->changeIdOrderState(_PS_OS_CANCELED_, (int)$order->id);
                            $history->addWithemail();
                        }
                    }
                }
            }
        }
        if (ob_get_length() > 0) {
            ob_clean();
        }
        die(json_encode(array('result' => $validateOrderResult)));
    }

    /**
     * Process order update from NX transaction notification
     * @param string $idTransaction
     * @param string $paymentRecordId
     * @since 2.1.0
     * @return void
     */
    public function processNxNotification($idTransaction, $paymentRecordId)
    {
        $notificationResult = false;
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);
        // Wait for a transaction into pending state
        if (!empty($transaction['payment']['contractNumber'])) {
            // Get payment record
            $paymentRecord = PaylinePaymentGateway::getPaymentRecord($transaction['payment']['contractNumber'], $paymentRecordId);
            if (!PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                die(json_encode(array(
                    'result' => $notificationResult,
                    'error' => 'Invalid paymentRecord response',
                )));
            }

            // Retrieve cart
            $idCart = null;
            $cart = null;
            if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList']) && isset($transaction['formatedPrivateDataList']['id_cart'])) {
                $idCart = (int)$transaction['formatedPrivateDataList']['id_cart'];
            } else {
                // Retrieve id_cart from order reference
                $idCart = PaylinePaymentGateway::getCartIdFromOrderReference($transaction['order']['ref']);
            }
            // Check if cart exists
            $cart = new Cart($idCart);
            if (!Validate::isLoadedObject($cart)) {
                die(json_encode(array(
                    'result' => $notificationResult,
                    'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
                )));
            }

            if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList'])
                && isset($transaction['formatedPrivateDataList']['id_customer'])
                && isset($transaction['formatedPrivateDataList']['secure_key'])
            ) {
                // OK we can process the order via customer return
                // Check secure_key and id_customer on the cart, compare it to the transaction
                if ($cart->secure_key != $transaction['formatedPrivateDataList']['secure_key'] || $cart->id_customer != $transaction['formatedPrivateDataList']['id_customer']) {
                    die(json_encode(array(
                        'result' => $notificationResult,
                        'error' => 'Transaction is not linked to the right Customer for Cart ID #'.$idCart,
                    )));
                }
            }

            // Always clean Cart::orderExists cache before trying to create the order
            if (class_exists('Cache', false) && method_exists('Cache', 'clean')) {
                Cache::clean('Cart::orderExists_' . $cart->id);
            }
            $orderExists = $cart->OrderExists();
            if (!$orderExists) {
                // There is no order for this cart
                die(json_encode(array(
                    'result' => $notificationResult,
                    'error' => 'Invalid Cart ID #'.$idCart.' - Order does not exists',
                )));
            }

            // Retrieve order
            $idOrder = Order::getIdByCartId($cart->id);
            $order = new Order($idOrder);
            if (Validate::isLoadedObject($order)) {
                // Update payment_record_id
                if (!PaylineToken::getPaymentRecordIdByIdOrder($order->id)) {
                    PaylineToken::setPaymentRecordIdByIdOrder($order, $paymentRecordId);
                }

                // Check if transaction ID is the same
                $orderPayments = OrderPayment::getByOrderReference($order->reference);
                if (isset($paymentRecord['billingRecordList']) && is_array($paymentRecord['billingRecordList']) &&
                    isset($paymentRecord['billingRecordList']['billingRecord']) && is_array($paymentRecord['billingRecordList']['billingRecord'])) {
                    $validTransactionCount = PaylinePaymentGateway::getValidatedRecurringPayment($paymentRecord);

                    // Check if the recurring is finished and full paid
                    foreach ($paymentRecord['billingRecordList']['billingRecord'] as $kBillingRecord => $billingRecord) {
                        // Delayed
                        if ($billingRecord['calculated_status'] == 4) {
                            continue;
                        }
                        // A transaction has been refused, check if the next transaction has not yet been processed
                        if ($billingRecord['calculated_status'] == 2) {
                            $nextBillingRecord = null;
                            if (isset($paymentRecord['billingRecordList']['billingRecord'][$kBillingRecord+1])) {
                                $nextBillingRecord = $paymentRecord['billingRecordList']['billingRecord'][$kBillingRecord+1];
                            }
                            if ($nextBillingRecord === null || $nextBillingRecord['calculated_status'] != 1) {
                                // The next transaction has not been processed yet, or is also invalid
                                // Or there is no more planned transaction for this billingRecord
                                if (!count($order->getHistory((int)$this->context->language->id, (int)Configuration::get('PAYLINE_ID_STATE_ALERT_SCHEDULE'), true))) {
                                    // Change order state
                                    $history = new OrderHistory();
                                    $history->id_order = (int)$order->id;
                                    $history->changeIdOrderState((int)Configuration::get('PAYLINE_ID_STATE_ALERT_SCHEDULE'), (int)$order->id);
                                    $history->addWithemail();
                                }
                            }
                        }
                    }

                    // Loop on billing list to add payment records on Order
                    foreach ($paymentRecord['billingRecordList']['billingRecord'] as $billingRecord) {
                        if ($billingRecord['calculated_status'] == 1) {
                            // Check if OrderPayment exists for this transaction
                            $orderPaymentExists = false;
                            foreach ($orderPayments as $orderPayment) {
                                if ($orderPayment->transaction_id == $billingRecord['transaction']['id']) {
                                    $orderPaymentExists = true;
                                    break;
                                }
                            }
                            if (!$orderPaymentExists) {
                                // There is OrderPayment for this transaction, add a new order payment to the current order
                                if ($billingRecord['calculated_status'] == 1) {
                                    $notificationResult &= $this->addOrderPaymentToOrder($order, Tools::ps_round($billingRecord['amount'] / 100, 2), $billingRecord['transaction']['id'], date('Y-m-d H:i:s', PaylinePaymentGateway::getTimestampFromPaylineDate($billingRecord['transaction']['date'])));
                                }
                            }
                        }
                    }
                    if ($validTransactionCount == $paymentRecord['recurring']['billingLeft']) {
                        // Order is now 100% paid
                        if (!count($order->getHistory((int)$this->context->language->id, _PS_OS_PAYMENT_, true))) {
                            // Change order state
                            $history = new OrderHistory();
                            $history->id_order = (int)$order->id;
                            $history->changeIdOrderState(_PS_OS_PAYMENT_, (int)$order->id, true);
                            $history->addWithemail();
                        }
                    }
                }
            }
        }
        if (ob_get_length() > 0) {
            ob_clean();
        }
        die(json_encode(array('result' => $notificationResult)));
    }

    /**
     * Process order update from REC transaction notification
     * @param string $idTransaction
     * @param string $paymentRecordId
     * @return void
     * @throws Exception
     * @since 2.2.0
     */
    public function processRecNotification($idTransaction, $paymentRecordId)
    {
        $notificationResult = false;
        $transaction = PaylinePaymentGateway::getTransactionInformations($idTransaction);

        if (!empty($transaction['formatedPrivateDataList']['payment_method']) && $transaction['formatedPrivateDataList']['payment_method'] == PaylinePaymentGateway::SUBSCRIBE_PAYMENT_METHOD) {
            // Wait for a transaction into pending state
            if (!empty($transaction['payment']['contractNumber'])) {
                // Get payment record
                $paymentRecord = PaylinePaymentGateway::getPaymentRecord($transaction['payment']['contractNumber'], $paymentRecordId);
                if (!PaylinePaymentGateway::isValidResponse($paymentRecord, array('02500'))) {
                    die(json_encode(array(
                        'result' => $notificationResult,
                        'error' => 'Invalid paymentRecord response',
                    )));
                }

                // Check if an order has already been created for this transaction id
                $idOrder = PaylineToken::getIdOrderByIdTransaction($transaction['transaction']['id']);
                if (!empty($idOrder)) {
                    die(json_encode(array(
                        'result' => false,
                        'error' => 'An order already exists for transaction ' . $transaction['transaction']['id'],
                    )));
                }

                // Retrieve cart
                $idCart = null;
                $cart = null;
                if (isset($transaction['formatedPrivateDataList']) && is_array($transaction['formatedPrivateDataList']) && isset($transaction['formatedPrivateDataList']['id_cart'])) {
                    $idCart = (int)$transaction['formatedPrivateDataList']['id_cart'];
                }
                // Check if cart original exists
                $cart = new Cart($idCart);
                if (!Validate::isLoadedObject($cart)) {
                    die(json_encode(array(
                        'result' => $notificationResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Cart does not exists',
                    )));
                }

                // Always clean Cart::orderExists cache before trying to create the order
                if (class_exists('Cache', false) && method_exists('Cache', 'clean')) {
                    Cache::clean('Cart::orderExists_' . $cart->id);
                }
                $orderExists = $cart->OrderExists();
                if (!$orderExists) {
                    // There is no order for this cart
                    die(json_encode(array(
                        'result' => $notificationResult,
                        'error' => 'Invalid Cart ID #'.$idCart.' - Original order does not exists',
                    )));
                }

                // Retrieve order
                $idOrder = Order::getIdByCartId($cart->id);
                $order = new Order($idOrder);
                if (Validate::isLoadedObject($order)) {
                    // Let's duplicate original cart as order exists
                    $newCartDuplicate = $cart->duplicate();
                    if (!empty($newCartDuplicate['success']) && isset($newCartDuplicate['cart'])) {
                        $newCart = $newCartDuplicate['cart'];
                        // Create the order
                        list($order, $notificationResult, $errorMessage, $errorCode) = $this->createOrder($newCart, $transaction, '', $paymentRecordId);
                    }
                }
            }
        }
        if (ob_get_length() > 0) {
            ob_clean();
        }
        die(json_encode(array('result' => $notificationResult)));
    }

    /**
     * Use Order::addOrderPayment(), but retrieve invoice first
     * @since 2.1.0
     * @return bool
     */
    protected function addOrderPaymentToOrder(Order $order, $amountPaid, $transactionId, $date = null)
    {
        // Get first invoice
        $invoice = $order->getInvoicesCollection()->getFirst();
        if (!($invoice instanceof OrderInvoice)) {
            $invoice = null;
        }

        return $order->addOrderPayment($amountPaid, null, $transactionId, null, $date, $invoice);
    }
}