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
namespace YowPayment\Services\Api;

class ApiService
{
    /** @var string */
    const API_BASE_URL = 'https://yowpay.com/api';

    /** @var int */
    const CONNECTION_TIMEOUT = 90;

    /**
     * @param string $endpoint
     * @param array $data
     *
     * @return array|null
     */
    public function doRequest($endpoint, array $data)
    {
        $url = self::API_BASE_URL . $endpoint;
        $hashedParams = $this->createHash($data);

        if (!$hashedParams) {
            return null;
        }

        $appToken = \Configuration::get('CHEQUE_APP_TOKEN');

        if (!$appToken) {
            \PrestaShopLogger::addLog('Missing apiToken in config');

            return null;
        }

        $headers = [
            'X-App-Access-Ts: ' . $data['timestamp'],
            'X-App-Token: ' . \Configuration::get('CHEQUE_APP_TOKEN'),
            "X-App-Access-Sig: $hashedParams",
            'Content-Type: application/json',
        ];

        $postParams = json_encode($data);

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postParams);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::CONNECTION_TIMEOUT);

        $result = curl_exec($curl);
        $curlInfo = curl_getinfo($curl);
        curl_close($curl);

        if (!json_decode($result)) {
            return $curlInfo;
        }

        return json_decode($result, true);
    }

    /**
     * @param int $orderId
     *
     * @return array
     *
     * @throws \Exception
     */
    public function createTransaction($orderId)
    {
        $transactionData = [];
        $attempt = 1;

        do {
            \PrestaShopLogger::addLog("Trying to send transaction request for $attempt time");
            try {
                $transactionData = [
                    'amount' => (float) \Context::getContext()->cart->getOrderTotal(),
                    'currency' => \Context::getContext()->currency->iso_code,
                    'timestamp' => time(),
                    'orderId' => $orderId,
                    'language' => 'en',
                ];
            } catch (\Exception $exception) {
                \PrestaShopLogger::addLog('Exception during getOrderTotal: ' . $exception->getMessage());
            }
            ++$attempt;
        } while (empty($transactionData) && $attempt <= 3);

        if (empty($transactionData)) {
            return [];
        }

        return $this->doRequest('/createTransaction', $transactionData);
    }

    /**
     * @param array $details
     *
     * @return array|null
     */
    public function getBankData(array $details)
    {
        $endpoint = '/getBankData';

        $response = $this->doRequest($endpoint, $details);

        if (!isset($response['content'])) {
            \PrestaShopLogger::addLog('API responded an error ' . json_encode($response));

            return null;
        }

        return $response['content'];
    }

    /**
     * @param array $configDetails
     *
     * @return bool
     */
    public function setPaymentConfigLinks(array $configDetails)
    {
        $endpoint = '/updateConfig';

        $response = $this->doRequest($endpoint, $configDetails);

        if (isset($response['content'])) {
            return true;
        }

        \PrestaShopLogger::addLog('API responded ' . $response['http_code'] . ' status');

        return false;
    }

    /**
     * @param array $postParams
     *
     * @return string
     */
    private function createHash(array $postParams)
    {
        $appSecret = \Configuration::get('CHEQUE_APP_SECRET');

        if (!$appSecret) {
            \PrestaShopLogger::addLog('Missing appSecret in config');

            return null;
        }

        return hash_hmac('sha256', json_encode($postParams), $appSecret);
    }
}
