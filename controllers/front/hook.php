<?php

use YowPayment\Services\YowTransaction\YowTransactionService;

class YowPaymentHookModuleFrontController extends ModuleFrontController
{
    /**
     * @return void
     */
    public function postProcess()
    {
        PrestaShopLogger::addLog("Request for webhook " . json_encode(array_keys($_POST)));

        $post = file_get_contents('php://input');

        if (!$post) {
            $this->response("Webhook got invalid POST data: " . json_encode($_POST) . " or Headers: " . json_encode($_SERVER));
        }

        $hashedBodyParameters = hash_hmac('sha256', $post, Configuration::get('CHEQUE_APP_SECRET'));
        $headerSignature = $_SERVER['HTTP_X_APP_ACCESS_SIG'];

        if ($hashedBodyParameters !== $headerSignature) {
            PrestaShopLogger::addLog("Webhook got Invalid header signature");
            $this->response("Invalid header signature");
        }

        $postParams = json_decode($post, true);
        if (
            !isset($_SERVER['HTTP_X_APP_ACCESS_TS']) || !isset($postParams['timestamp']) ||
            $_SERVER['HTTP_X_APP_ACCESS_TS'] != $postParams['timestamp']
        ) {
            PrestaShopLogger::addLog("Missing timestamp in hook");
            $this->response("Missing timestamp");
        }

        if(!$this->checkTimestamp((int)$postParams['timestamp'])) {
            PrestaShopLogger::addLog("Webhook got old timestamp");
            $this->response("Timestamp must be not older than 15 seconds");
        }

        if (!$this->validatePostData($postParams)) {
            PrestaShopLogger::addLog("Webhook got invalid data" . json_encode($_POST));

            $this->response("Webhook got invalid data" . json_encode($_POST));
        }

        PrestaShopLogger::addLog("Request for updating transaction status " . json_encode($postParams));

        try {
            /** @var YowTransactionService $yowTransactionService */
            $yowTransactionService = $this->container->get('yow_transactions_service');

            if (!$yowTransactionService->resolveStatus($postParams)) {
                $this->response("Webhook got invalid data" . json_encode($_POST));
            }

        } catch (Exception $e) {
            PrestaShopLogger::addLog("Failed to load transaction service from container :" . $e->getMessage());
            $this->response("Internal server error");
        }

        $this->response("ok");
    }

    /**
     * @param array $postData
     * @return bool
     */
    private function validatePostData(array $postData)
    {
        $expectedKeys = [
            'amount',
            'currency',
            'reference',
            'timestamp',
            'language',
            'orderId',
            'createDate',
            'validateDate',
            'senderIban',
            'senderSwift',
            'senderAccountHolder',
            'status',
            'amountPaid',
            'currencyPaid'
        ];

        $comparisons = array_diff($expectedKeys, array_keys($postData));

        return count($comparisons) === 0;
    }

    private function checkTimestamp($timestamp)
    {
        return time() - $timestamp <= 15;
    }

    /**
     * @param string $message
     * @return void
     */
    private function response($message)
    {
        PrestaShopLogger::addLog($message);
        echo $message;
        exit;
    }
}
