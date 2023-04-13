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
namespace YowPayment\Entity;

/**
 * class YowTransactions for our transactions entity
 *
 * all snake_case variable used, because of property name and table column name must match
 */
class YowTransactions extends \ObjectModel
{
    /** @var int Transaction id in table */
    public $id;

    /** @var int Prestashop order Id */
    public $order_id;

    /** @var int YowPay transaction id */
    public $transaction_id;

    /** @var string YowPay transaction code */
    public $transaction_code;

    /** @var float Order price */
    public $price;

    /** @var string YowPay transaction status */
    public $status;

    /** @var string YowPay transaction sender Iban */
    public $sender_iban;

    /** @var string YowPay transaction sender Swift */
    public $sender_swift;

    /** @var string YowPay Bank account holder */
    public $sender_account_holder;

    /** @var string Transaction created date */
    public $created_at;

    /** @var string Transaction updated date */
    public $updated_at;

    public static $definition = [
        'table' => 'yow_transactions',
        'primary' => 'id',
        'multilang' => false,
        'fields' => [
            'order_id' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'transaction_id' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'transaction_code' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'price' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'status' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'sender_iban' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'sender_swift' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'sender_account_holder' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'created_at' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
            'updated_at' => ['type' => self::TYPE_DATE],
        ],
        'collation' => 'utf8_general_ci',
    ];

    public function getTransactions($criteria, $sortBy)
    {
        $where = '';

        if (!empty($criteria)) {
            $where = implode(' AND ', array_map(function ($column, $value) {
                if (is_array($value)) {
                    return $this->dateRangeFilter($column, $value);
                }

                return $column . ' = "' . $value . '"';
            }, array_keys($criteria), $criteria));
        }

        $orderByClause = implode(', ', array_map(function ($column, $order) {
            return $column . ' ' . $order;
        }, array_keys($sortBy), $sortBy));

        $query = new \DbQuery();
        $query->select('*');
        $query->from('yow_transactions');
        $query->where($where);
        $query->orderBy($orderByClause);

        try {
            $yowTransactions = \Db::getInstance()->executeS($query);
        } catch (\PrestaShopDatabaseException $exception) {
            \PrestaShopLogger::addLog('Failed to load transactions from DB');

            return [];
        }

        return $yowTransactions;
    }

    private function dateRangeFilter($column, $dateRange)
    {
        $dateFilter = [];

        if (isset($dateRange['from'])) {
            $dateFilter[] = $column . " >= '" . $dateRange['from'] . " 00:00:00'";
        }
        if (isset($dateRange['to'])) {
            $dateFilter[] = $column . " <= '" . $dateRange['to'] . " 23:59:59'";
        }

        return implode(' AND ', $dateFilter);
    }
}
