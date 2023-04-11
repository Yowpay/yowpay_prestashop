<?php

class YowPaymentSuccessModuleFrontController extends ModuleFrontController
{
    /**
     * @return void
     * @throws PrestaShopException
     */
    public function initContent()
    {
        parent::initContent();

        $this->context->cookie->wentToYp = false;

        $cart = $this->context->cart;

        $customer = new Customer($cart->id_customer);

        PrestaShopLogger::addLog("We got the success link for the transaction " . $this->module->currentOrder);
        $this->registerStylesheet('module-yowpayment-style', 'modules/' . $this->module->name . '/css/yowpayment.css');

        $orderListUrl = $this->context->link->getPageLink('order-confirmation', null, $this->context->language->id, 'id_module=' . $this->module->id . '&id_order=' . $this->context->cookie->lastOrderId . '&key=' . $customer->secure_key);

        $this->context->smarty->assign([
            'continueShoppingUrl' => '/',
            'orderListUrl' => $orderListUrl
        ]);

        $this->setTemplate("module:yowpayment/views/templates/front/success.tpl");
    }

    /**
     * @return void
     */
    public function setMedia()
    {
        parent::setMedia();
        $this->registerStylesheet('module-yowpayment-style', 'modules/' . $this->module->name . '/css/yowpayment.css');
    }
}