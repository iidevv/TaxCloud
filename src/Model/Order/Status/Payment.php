<?php


namespace Iidev\TaxCloud\Model\Order\Status;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Payment extends \XLite\Model\Order\Status\Payment
{
    public static function getStatusHandlers()
    {
        $handlers = parent::getStatusHandlers();

        $allPossibleStatuses = array_keys($handlers);

        $taxcloudCancelStatuses = [
            static::STATUS_DECLINED,
            static::STATUS_CANCELED,
            static::STATUS_REFUNDED,
        ];

        foreach ($allPossibleStatuses as $oldStatus) {
            // 1. From any to STATUS_PAID/STATUS_AUTHORIZED/Awaiting Payments
            // see \Iidev\TaxCloud\Model\Order::processSucceed

            // 2. From any to STATUS_CANCELED/STATUS_DECLINED/STATUS_REFUNDED
            foreach ($taxcloudCancelStatuses as $newStatus) {
                if (in_array($oldStatus, $taxcloudCancelStatuses)) {
                    continue;
                }
                if (!isset($handlers[$oldStatus][$newStatus])) {
                    $handlers[$oldStatus][$newStatus] = [];
                }

                if (!in_array('taxcloudVoidTransaction', $handlers[$oldStatus][$newStatus])) {
                    $handlers[$oldStatus][$newStatus][] = 'taxcloudVoidTransaction';
                }
            }

            // 3. From any* to STATUS_PART_PAID
            // see \Iidev\TaxCloud\Model\Payment\Base\Processor::doTransaction
        }

        return $handlers;
    }
}
