<?php


namespace Iidev\TaxCloud\Core\DataProvider;

use Iidev\TaxCloud\Core\TaxCore;

class Order
{
    private \XLite\Model\Order $order;

    public function __construct(\XLite\Model\Order $order)
    {
        $this->order = $order;
    }

    public function getRefundTransactionModel(\XLite\Model\Payment\BackendTransaction $transaction): array
    {
        $currency = $this->order->getCurrency();

        $result = [
            "orderID" => "{$this->order->getOrderNumber()}-{$this->order->getTaxCloudNumber()}",
            "returnedDate" => date('Y-m-d'),
        ];

        if (!$transaction->isFullRefund()) {
            $total = (float) $this->order->getTotal();
            $percentage = $total > 0 ? $transaction->getValue() / $total : 0.0;

            foreach ($this->order->getItems() as $i => $item) {
                $total = (float) $item->getTotal();
                $amount = (int) $item->getAmount();
                $unitPrice = $amount > 0 ? $currency->roundValue($total / $amount) : 0.0;

                $result['cartItems'][] = [
                    'Index' => $i,
                    'ItemID' => $item->getSku(),
                    'Price' => $unitPrice,
                    'Qty' => $amount * $percentage,
                    'TIC' => (int) $item->getProduct()->getTaxCloudCode(),
                    'Tax' => 0.0,
                ];
            }
        }

        return $result;
    }
}
