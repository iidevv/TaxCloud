<?php


namespace Iidev\TaxCloud\Model\Payment\Base;

use Iidev\TaxCloud\Core\TaxCore;
use XCart\Extender\Mapping\Extender;
use XLite\Model\Payment\BackendTransaction;
use XLite\Model\Payment\Transaction;

/**
 * @Extender\Mixin
 */
abstract class Processor extends \XLite\Model\Payment\Base\Processor
{
    /**
     * @param Transaction $transaction     Payment transaction object
     * @param string      $transactionType Backend transaction type
     */
    public function doTransaction(Transaction $transaction, $transactionType)
    {
        $oldBackendTransactionsIds = $transaction->getBackendTransactions()
            ? array_map(static fn($txn) => $txn->getId(), $transaction->getBackendTransactions()->toArray())
            : [];

        parent::doTransaction($transaction, $transactionType);

        // refund from admin area. do taxcloud refund for each new successful refund transaction
        if (
            stripos($transactionType, 'refund') !== false
            && $transaction->getOrder()->getPaymentStatusCode() === \XLite\Model\Order\Status\Payment::STATUS_PART_PAID // status might be changed to full cancel/refund in parent call
            && $transaction->getOrder()->hasTaxCloudTaxes()
        ) {
            /** @var BackendTransaction $newBackendTransaction */
            $newBackendTransactions = $transaction->getBackendTransactions() ?: [];
            foreach ($newBackendTransactions as $newBackendTransaction) {
                if (
                    $transactionType === $newBackendTransaction->getType()
                    && !in_array($newBackendTransaction->getId(), $oldBackendTransactionsIds)
                    && !in_array($newBackendTransaction->getStatus(), TaxCore::FAILED_BACKEND_STATUSES)
                ) {
                    // don't rely on $newBackendTransaction->getPaymentTransaction()->getOrder() here
                    if (empty($orderForReference) && \XLite\Core\Request::getInstance()->order_number) {
                        $orderForReference = \XLite\Core\Database::getRepo('XLite\Model\Order')
                            ->findOneByOrderNumber(\XLite\Core\Request::getInstance()->order_number);
                    }
                    TaxCore::getInstance()->refundTransactionRequest($newBackendTransaction, $orderForReference ?? null);
                }
            }
        }
    }
}
