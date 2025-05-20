<?php


namespace Iidev\TaxCloud\Core\DataProvider;

class Order
{
    private \XLite\Model\Order $order;

    public function __construct(\XLite\Model\Order $order)
    {
        $this->order = $order;
    }

    public function getRefundTransactionModel(\XLite\Model\Payment\BackendTransaction $transaction): array
    {
        $result = [
            "orderID" => "{$this->order->getOrderNumber()}-{$this->order->getTaxCloudNumber()}",
            "returnedDate" => date('Y-m-d'),
        ];

        if (!$transaction->isFullRefund()) {
            \XLite\Core\TopMessage::addWarning(
                'Recalculate the Order Subtotal with the updated amount for accurate tax reporting.'
            );
        }

        return $result;
    }
}
