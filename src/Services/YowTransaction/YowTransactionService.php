<?php

namespace YowPayment\Services\YowTransaction;

use Cart;
use CartRule;
use Context;
use Db;
use Exception;
use Order;
use OrderHistory;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShopDatabaseException;
use PrestaShopException;
use PrestaShopLogger;
use Validate;
use YowPayment\Entity\YowTransactions;

class YowTransactionService
{
    /** @var string */
    const TRANSACTION_STATUS_OVERPAID = 'OVERPAID';

    /** @var string */
    const TRANSACTION_STATUS_PARTIALLY_PAID = 'PARTIALLY PAID';

    /** @var string */
    const TRANSACTION_STATUS_APPROVED = 'APPROVED';

    /** @var string */
    const TRANSACTION_STATUS_PENDING = 'PENDING';

    /** @var string */
    const TRANSACTION_STATUS_PAYMENT_ERROR = 'PAYMENT ERROR';


    /**
     * @param array $requestDetails
     * @return bool
     */
    public function resolveStatus(array $requestDetails)
    {
        try {
            $order = new Order((int)$requestDetails['orderId']);

            $orderTotalPrice = $order->total_paid;

            $requestDetails['amountPaid'] = (float)str_replace('_', '.', $requestDetails['amountPaid']);
            $requestDetails['transactionStatus'] = $this->resolveTransactionStatus($requestDetails['amountPaid'], $orderTotalPrice);

            $orderState = $this->resolveOrderState($requestDetails['transactionStatus']);
        } catch (PrestaShopDatabaseException $e) {
            PrestaShopLogger::addLog("Order with id " . $requestDetails['orderId'] . " not found!");
            return false;
        } catch (PrestaShopException $e) {
            PrestaShopLogger::addLog("Failed to validate the order with id " . $requestDetails['orderId'] . ": " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            PrestaShopLogger::addLog("Failed to get Transaction service from container : " . $e->getMessage());
            return false;
        }

        if(!$this->transactionUpdateAttempts($requestDetails)) {
            PrestaShopLogger::addLog("Failed to get update Transaction with details " . json_encode($requestDetails));
            return false;
        }

        if (!$this->validateOrderAttempts($requestDetails['orderId'], $orderState)) {
            PrestaShopLogger::addLog("Failed to get validate order with details " . json_encode($requestDetails));
            return false;
        }

        return true;
    }

    /**
     * @param array $criteria
     * @param array $sortBy
     * @return array
     */
    public function getYowTransactions(array $criteria = [], array $sortBy = ['createdAt' => 'DESC'])
    {
        $yowTransactionsEntity = new YowTransactions();
        return $yowTransactionsEntity->getTransactions($criteria, $sortBy);
    }

    /**
     * @param int $cartId
     * @return array
     */
    public function convertOrderToCart($cartId)
    {
        $response = [];
        try {
            $recoverStatus = $this->recoverCart($cartId);
            if (isset($recoverStatus['message'])) {
                $response['errors'][] = $recoverStatus['message'];
            } else {
                $response['redirectUrl'] = $recoverStatus['redirectUrl'];
            }
        }catch (PrestaShopException|PrestaShopDatabaseException $exception) {
            PrestaShopLogger::addLog("Exception while recovering cart: ". $exception->getMessage());
            $response['errors'][] = $exception->getMessage();
        }

        return $response;
    }

    /**
     * @return bool
     */
    public function isSupportCurrency($cartCurrencyId)
    {
        return ((int) $cartCurrencyId) === ((int) $this->getEuroCurrencyId());
    }


    /**
     * @param int $transactionId
     * @param string $transactionCode
     * @param float $totalPrice
     * @param int $orderId
     * @return bool
     */
    public function saveTransaction($transactionId, $transactionCode, $totalPrice, $orderId)
    {
        $transactionData = [
            'transactionId' => $transactionId,
            'orderId' => $orderId,
            'transactionCode' => $transactionCode,
            'price' => $totalPrice,
        ];

        $attempt = 1;
        do {
            PrestaShopLogger::addLog("Trying to save transaction $transactionId  for the $attempt time");
            $isTransactionSaved = $this->saveYowTransaction($transactionData, self::TRANSACTION_STATUS_PENDING);
            $attempt++;
        } while (!$isTransactionSaved && $attempt <= 3);

        return $isTransactionSaved;
    }

    /**
     * @param float $totalPrice
     * @param int $orderId
     * @return bool
     */
    public function cancelTransaction($totalPrice, $orderId)
    {
        $transactionData = [
            'transactionId' => 0,
            'orderId' => $orderId,
            'transactionCode' => 'UNDEFINED',
            'price' => $totalPrice,
        ];

        $attempt = 1;
        do {
            PrestaShopLogger::addLog("Trying to cancel transaction for order $orderId for the $attempt time");
            $isTransactionCancelled = $this->saveYowTransaction($transactionData, self::TRANSACTION_STATUS_PAYMENT_ERROR);
            $attempt++;
        } while (!$isTransactionCancelled && $attempt <= 3);


        $isOrderCancelled = $this->cancelOrder($orderId);

        if (!$isOrderCancelled) {
            PrestaShopLogger::addLog("Failed to cancel order with id: $orderId");
        }

        return $isTransactionCancelled && $isOrderCancelled;
    }

    /**
     * @param array $requestDetails
     * @return bool
     */
    private function transactionUpdateAttempts(array $requestDetails)
    {
        $attempt = 1;

        do {
            PrestaShopLogger::addLog("Trying to update transaction with reference " . $requestDetails['reference'] . " for the $attempt time");

            $isTransactionUpdated = $this->updateTransaction($requestDetails);
            $attempt++;
        } while (!$isTransactionUpdated && $attempt <= 3);

        return $isTransactionUpdated;
    }

    /**
     * @param int $orderState
     * @param int $orderId
     * @return bool
     */
    private function validateOrderAttempts($orderId, $orderState)
    {
        $attempt = 1;

        do {
            PrestaShopLogger::addLog("Trying to validate order with status code $orderState for the $attempt time");
            $isOrderValidated = $this->validateOrder($orderId, $orderState);
            $attempt++;
        } while (!$isOrderValidated && $attempt <= 3);

        return $isOrderValidated;
    }

    /**
     * @param array $transactionDetails
     * @return bool
     */
    private function updateTransaction(array $transactionDetails)
    {
        if (!$this->updateYowTransaction($transactionDetails['reference'], $transactionDetails['timestamp'], $transactionDetails['transactionStatus'])) {
            PrestaShopLogger::addLog("Transaction with code " . $transactionDetails['reference'] . " not found");
            return false;
        }

        PrestaShopLogger::addLog("Transaction with code " . $transactionDetails['reference'] . " successfully validated");
        return true;
    }

    /**
     * @param string $orderId
     * @param int $orderState
     * @return bool
     */
    private function validateOrder($orderId, $orderState)
    {
        try {
            $order = new Order($orderId);
            $order->setCurrentState($orderState);

            PrestaShopLogger::addLog("State for order with id $orderId changed");
        } catch (PrestaShopException $psException) {
            PrestaShopLogger::addLog("Failed to validate the order with id $orderId " . $psException->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param float $amountPaid
     * @param float $orderTotalPrice
     * @return string
     */
    private function resolveTransactionStatus($amountPaid, $orderTotalPrice)
    {
        if ($amountPaid > $orderTotalPrice) {
            return self::TRANSACTION_STATUS_OVERPAID;
        }
        if ($amountPaid < $orderTotalPrice) {
            return self::TRANSACTION_STATUS_PARTIALLY_PAID;
        }
        return self::TRANSACTION_STATUS_APPROVED;
    }

    /**
     * @param string $transactionStatus
     * @return int
     */
    private function resolveOrderState($transactionStatus)
    {
        if ($transactionStatus === 'partiallyPaid') {
            return (int)Configuration::get('YOWPAY_OS_WAITING');
        }

        return (int)Configuration::get('PS_OS_PAYMENT');
    }

    /**
     * @param int $orderId
     * @return bool
     */
    private function cancelOrder($orderId)
    {
        try {
            $order = new Order($orderId);
            $order->setCurrentState((int)Configuration::get('PS_OS_ERROR'));
        } catch (PrestaShopException $prestaShopException) {
            PrestaShopLogger::addLog("Payment error for order with id " . $orderId . ". Exception: " . $prestaShopException->getMessage());
            return false;
        }

        return true;
    }

    /**
     * @param array $data
     * @param string $status
     * @return bool
     */
    private function saveYowTransaction(array $data, $status)
    {
        $createdAt = date('Y-m-d H:i:s', time());

        $yowTransaction = new YowTransactions();

        $yowTransaction->transaction_id = $data['transactionId'];
        $yowTransaction->order_id = $data['orderId'];
        $yowTransaction->transaction_code =  $data['transactionCode'];
        $yowTransaction->price = $data['price'];
        $yowTransaction->status = $status;
        $yowTransaction->created_at = $createdAt;
        $yowTransaction->updated_at = null;

        try {
            $yowTransaction->save();
        } catch (Exception $exception) {
            PrestaShopLogger::addLog("Failed to save transaction for order " . $data['orderId'] . ": " . $exception->getMessage() . ': ' . $exception->getTraceAsString());
            return false;
        }

        return true;
    }

    /**
     * @param string $transactionCode
     * @param string $timestamp
     * @param string $status
     * @return bool
     */
    private function updateYowTransaction($transactionCode, $timestamp, $status)
    {
        $updatedAt = date('Y-m-d H:i:s', (int)$timestamp);


        $dbInstance = Db::getInstance();
        $data = [
            'status' => pSQL($status),
            'updated_at' => pSQL($updatedAt)
        ];
        $where = 'transaction_code = "' . pSQL($transactionCode) . '"';

        if (!$dbInstance->update('yow_transactions', $data, $where)) {
            PrestaShopLogger::addLog("Failed to update transaction with code $transactionCode");
            return false;
        }

        return true;
    }

    /**
     * @return array|mixed|null
     */
    private function getEuroCurrencyId()
    {
        $query = new \DbQuery();
        $query->select('id_currency');
        $query->from('currency');
        $query->where("iso_code='EUR'");

        try {
            $currency = Db::getInstance()->getRow($query);
            if (!isset($currency['id_currency'])) {
                return null;
            }
        }catch (PrestaShopDatabaseException $exception) {
            PrestaShopLogger::addLog("Failed to load currency id");
            return [];
        }

        return $currency['id_currency'];
    }

    /**
     * @param int $cartId
     * @return array
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function recoverCart($cartId)
    {

        if (Order::getIdByCartId($cartId) !== false) {
            $order = new Order(Order::getIdByCartId($cartId));

            $context = Context::getContext();

            $oldCart = new Cart($cartId);
            $duplication = $oldCart->duplicate();
            if (!$duplication || !Validate::isLoadedObject($duplication['cart'])) {
                return [
                    'status' => false,
                    'message' => 'Sorry. We cannot renew your order.'
                ];
            } elseif (!$duplication['success']) {
                return [
                    'status' => false,
                    'message' => 'Some items are no longer available, and we are unable to renew your order.'
                ];
            }
            $newCart = $duplication['cart'];

            $checkoutSessionRawData = Db::getInstance()->getValue(
                'SELECT checkout_session_data FROM ' . _DB_PREFIX_ . 'cart WHERE id_cart = ' . (int) $oldCart->id
            );

            $checkoutSessionData = json_decode($checkoutSessionRawData ?? '', true);
            if (!is_array($checkoutSessionData)) {
                $checkoutSessionData = [];
            }

            Db::getInstance()->execute(
                'UPDATE ' . _DB_PREFIX_ . 'cart SET checkout_session_data = "' . pSQL(json_encode($checkoutSessionData)) . '"
                WHERE id_cart = ' . (int) $newCart->id
            ); // to restore checkout tab state

            $context->cookie->id_cart = $duplication['cart']->id;
            $context->cart = $duplication['cart'];
            CartRule::autoAddToCart($context);
            $context->cookie->wentToYp = false;
            $context->cookie->write();

            if (!$this->validateOrderAttempts($order->id, (int) Configuration::get('PS_OS_CANCELED'))) {
                return [
                    'status' => false,
                    'message' => 'Unexpected error in cart recover'
                ];
            }

            $redirectUrl = Context::getContext()->link->getPageLink('order');

            return [
                'status' => true,
                'redirectUrl' => $redirectUrl
            ];
        }

        return [
            'status' => false,
            'message' => 'The cart is not empty'
        ];
    }
}
