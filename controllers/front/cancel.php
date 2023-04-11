<?php

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
            PrestaShopLogger::addLog("Exception while recovering cart: ". $exception->getMessage());
            $this->errors[] = Tools::displayError($exception->getMessage());
        }

        if (count($this->errors) > 0) {
            $this->redirectWithNotifications(__PS_BASE_URI__);
        }

        Tools::redirect($conversionStatus['redirectUrl']);
    }
}