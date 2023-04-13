<?php
/**
 * MIT License
 * Copyright (c) 2023 Yowpay - Peer to Peer SEPA Payments made easy

 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author   YowPay SARL
 * @copyright  YowPay SARL
 * @license  MIT License
 */
use YowPayment\Services\Api\ApiService;
use YowPayment\Services\YowTransaction\YowTransactionService;

class YowPaymentValidationModuleFrontController extends ModuleFrontController
{
    /**
     * Handles post process of validation controller
     *
     * @throws Exception
     */
    public function postProcess()
    {
        if (!$this->context->employee instanceof Employee && version_compare(_PS_VERSION_, '8.0.1', '<')) {
            $cookie = new Cookie('psAdmin', '', (int) Configuration::get('PS_COOKIE_LIFETIME_BO'));
            $idEmployee = (int) $cookie->id_employee;
            if (!$idEmployee) {
                $employees = Employee::getEmployeesByProfile(_PS_ADMIN_PROFILE_);
                $idEmployee = reset($employees)['id_employee'];
            }
            $employee = new Employee($idEmployee);

            $this->context->employee = $employee; // older PS versions require employee set in context
        }

        $cart = $this->context->cart;
        $this->context->cookie->__set('cart_id', (int) $cart->id);

        $orderTotal = (float) $cart->getOrderTotal();
        $currency = $this->context->currency;

        $customer = new Customer($cart->id_customer);

        if (!$this->createOrder($orderTotal, $cart, $currency, $customer)) {
            PrestaShopLogger::addLog("Failed to create order $cart->id");

            Tools::redirect($this->context->link->getPageLink('cart', null, null, 'action=show'));
        }

        $currentOrderId = (int) $this->module->currentOrder;

        $apiService = new ApiService();
        $response = $apiService->createTransaction($currentOrderId);

        $yowTransactionService = new YowTransactionService();

        if (isset($response['redirect_url']) && !empty($response['redirect_url'])) {
            $redirectUrl = $response['redirect_url'];
            $this->context->cookie->__set('lastOrderId', $currentOrderId);

            PrestaShopLogger::addLog("Api responded the redirect link $currentOrderId");

            $responseParts = explode('/', $redirectUrl);
            $transactionId = (int) $responseParts[count($responseParts) - 2];
            $transactionCode = (string) $responseParts[count($responseParts) - 1];

            if (!$yowTransactionService->saveTransaction($transactionId, $transactionCode, $orderTotal, $currentOrderId)) {
                PrestaShopLogger::addLog("Could not save transaction with data: $currentOrderId");
                Tools::redirect($this->context->link->getPageLink('order-detail', null, null, 'id_order=' . $currentOrderId));
            }
            $this->context->cookie->wentToYp = true;

            PrestaShopLogger::addLog("transaction saved with order id $currentOrderId");

            Tools::redirect($redirectUrl);
        } else {
            $redirectPage = $this->context->link->getPageLink('order-detail', null, null, 'id_order=' . $currentOrderId);

            if (!$yowTransactionService->cancelTransaction($orderTotal, $currentOrderId)) {
                PrestaShopLogger::addLog("Could not cancel transaction with order id : $currentOrderId");

                Tools::redirect($redirectPage);
            }

            PrestaShopLogger::addLog('Api responded an error for the order id ' . $currentOrderId);

            Tools::redirect($redirectPage);
        }
    }

    /**
     * @param float $total
     * @param Cart $cart
     * @param string $currency
     * @param Customer $customer
     *
     * @return bool
     */
    private function createOrder($total, $cart, $currency, $customer)
    {
        try {
            $this->module->validateOrder(
                (int) $cart->id,
                (int) Configuration::get('YOWPAY_OS_WAITING'),
                $total,
                $this->module->displayName,
                null,
                [],
                (int) $currency->id,
                false,
                $customer->secure_key
            );
        } catch (PrestaShopException $prestaShopException) {
            PrestaShopLogger::addLog("Failed to validate order for cart $cart->id");

            return false;
        }

        return true;
    }
}
