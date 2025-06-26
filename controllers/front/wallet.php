<?php
/**
 * Payline module for PrestaShop
 *
 * @author    Monext <support@payline.com>
 * @copyright Monext - http://www.payline.com
 */

class paylineWalletModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $authRedirection = 'my-account';
    public $ssl = true;

    /**
     * @see FrontController::setMedia()
     */
    public function setMedia()
    {
        parent::setMedia();

        $assets = PaylinePaymentGateway::getAssetsToRegister();
        foreach ($assets['js'] as $file) {
            $this->context->controller->registerJavascript('modules-'.$this->module->name.'-payment-js-1', $file, array('server' => 'remote', 'position' => 'bottom', 'priority' => 150));
        }
        foreach ($assets['css'] as $file) {
            $this->context->controller->registerStylesheet('modules-'.$this->module->name.'-payment-css-1', $file, array('server' => 'remote', 'media' => 'all', 'priority' => 900));
        }
    }

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $contracts = [];
        foreach (PaylinePaymentGateway::getContractsForCurrentPos() as $contract) {
            if(!empty($contract['wallet'])) {
                $contracts[] = $contract['contractNumber'];
            }
        }

        $walletId = (PaylineWallet::getWalletByIdCustomer($this->context->customer->id))?:PaylineWallet::generateWalletId($this->context->customer->id);

        $params = array(
            'version' => PaylinePaymentGateway::API_VERSION,
            'contractNumber' => current($contracts),
            'contracts' => $contracts,
            'buyer' => array(
                'lastName' => $this->context->customer->lastname,
                'firstName' => $this->context->customer->firstname,
                'walletId' => $walletId,
            ),
            'updatePersonalDetails' => 0,

            'notificationURL' => $this->context->link->getModuleLink('payline', 'notification', array(), true),
            'returnURL' => $this->context->link->getModuleLink('payline', 'validation', array(), true),
            'cancelURL' => $this->context->link->getPageLink('order'),

        );

        $manageWebWalletRequest = PaylinePaymentGateway::createManageWebWalletRequest($params);

        $this->context->smarty->assign(array(
            'payline_token' => $manageWebWalletRequest['token'],
            'payline_ux_mode' => Configuration::get('PAYLINE_WEB_CASH_UX'),
        ));

        $this->setTemplate('module:payline/views/templates/front/wallet.tpl');
    }
}
