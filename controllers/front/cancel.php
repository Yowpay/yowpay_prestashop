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
use YowPayment\Services\YowTransaction\YowTransactionService;

class YowPaymentCancelModuleFrontController extends ModuleFrontController
{
    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
    public function initContent()
    {
        parent::initContent();

        $cartId = $this->context->cookie->cart_id;

        try {
            /** @var YowTransactionService $yowTransactionService */
            $yowTransactionService = $this->container->get('yow_transactions_service');

            $conversionStatus = $yowTransactionService->convertOrderToCart($cartId);

            if (isset($conversionStatus['errors'])) {
                $this->errors = $conversionStatus['errors'];
            }
        } catch (PrestaShopException|PrestaShopDatabaseException|Exception $exception) {
            PrestaShopLogger::addLog('Exception while recovering cart: ' . $exception->getMessage());
            $this->errors[] = Tools::displayError($exception->getMessage());
        }

        if (count($this->errors) > 0) {
            $this->redirectWithNotifications(__PS_BASE_URI__);
        }

        Tools::redirect($conversionStatus['redirectUrl']);
    }
}
