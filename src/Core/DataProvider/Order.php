<?php


namespace Iidev\TaxCloud\Core\DataProvider;

use Iidev\TaxCloud\Core\TaxCore;
use XLite\Core\Config;

class Order
{
    public const REFUND_POSTFIX = 'R'; // minimize size to fit in 50 symbols limit

    private \XLite\Model\Order $order;

    public function __construct(\XLite\Model\Order $order)
    {
        $this->order = $order;
    }

    public function getTransactionCode(): string
    {
        $shopURL = \XLite::getInstance()->getShopURL();
        $shopURL = str_ireplace('http://', 'https://', $shopURL);

        return $this->order->getOrderNumber()
            ? ($this->order->getOrderNumber() . '-' . md5($shopURL))
            : '';
    }

    public function getVoidTransactionModel(string $reason): array
    {
        return ['code' => $reason];
    }

    public function getAdjustTransactionModel(array $newOrderData, string $reason, string $reasonDescription = ''): array
    {
        $newOrderData['commit'] = false;
        $newOrderData['type']   = 'SalesOrder';

        $result = [
            'newTransaction'   => $newOrderData,
            'adjustmentReason' => $reason,
        ];

        if (empty($reasonDescription)) {
            if ($reason === TaxCore::OTHER) {
                $result['adjustmentReason'] = TaxCore::PRICE_ADJUSTED;
            }
        } else {
            $reasonDescription = str_replace('Array', '', $reasonDescription);
            $reasonDescription = preg_replace('/\s+/i', ' ', $reasonDescription);
            $reasonDescription = preg_replace('/\)$/', '', $reasonDescription);
            $reasonDescription = trim($reasonDescription, "( \n\r\t\v");
            if (strlen($reasonDescription) > 254) {
                $reasonDescription = substr($reasonDescription, 0, 251) . '...'; // To avoid error "message": "AdjustmentDescription length must be between 1 and 255 characters."
            }
            $result['adjustmentDescription'] = $reasonDescription;
        }

        return $result;
    }

    public function getRefundTransactionModel(\XLite\Model\Payment\BackendTransaction $transaction): array
    {
        $refundType = $transaction->isFullRefund() ? 'Full' : 'Percentage';

        $result = [
            'refundTransactionCode' => $this->getTransactionCode() . self::REFUND_POSTFIX . $transaction->getId(),
            'refundDate'            => date('Y-m-d', $transaction->getDate()),
            'refundType'            => $refundType,
        ];

        if (strlen($result['refundTransactionCode']) > 50) {
            unset($result['refundTransactionCode']);
        }

        if ($refundType === 'Percentage') {
            // 1. The percentage number for a Percentage refund must be between 0 and 100.
            // 2. getParentValue() isn't used to preserve max precision
            // 3. $transaction->getPaymentTransaction()->getValue() is incorrect for multi-vendor
            $result['refundPercentage'] = $transaction->getValue() * 100 / $this->order->getTotal();
        }

        return $result;
    }
}
