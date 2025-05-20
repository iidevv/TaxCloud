<?php


namespace Iidev\TaxCloud\Controller\Admin;

use XCart\Extender\Mapping\Extender;
use Iidev\TaxCloud\Core\TaxCore;

/**
 * @package Iidev\TaxCloud\Controller\Admin
 * @Extender\Mixin
 */
class Order extends \XLite\Controller\Admin\Order
{
    protected function doActionUpdate()
    {
        parent::doActionUpdate();

        $this->orderUpdatedCallback(
            $this->getOrderChanges(),
            $this->getOrder()
        );
    }

    /**
     * @param array                                     $diff
     * @param \XLite\Model\Order|\Iidev\TaxCloud\Model\Order $order
     */
    protected function orderUpdatedCallback(array $diff, \XLite\Model\Order $order)
    {
        if ($diff && !empty($diff['TAXCLOUD.summary']) && TaxCore::getInstance()->isValid() && $order->hasTaxCloudTaxes() && $order->getTaxCloudImported()) {
            TaxCore::getInstance()->adjustTransactionRequest($order);
        }
    }
}
