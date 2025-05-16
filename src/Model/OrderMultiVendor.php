<?php


namespace Iidev\TaxCloud\Model;

use XCart\Extender\Mapping\Extender;
use XC\MultiVendor;

/**
 * Order
 *
 * @Extender\Mixin
 * @Extender\Depend ("XC\MultiVendor")
 * @Extender\After ("Iidev\TaxCloud")
 */
class OrderMultiVendor extends \XLite\Model\Order
{
    public function isTaxOwner()
    {
        /** @var \XC\MultiVendor\Model\Order $order */
        $order = $this;
        $warehouseMode = MultiVendor\Main::isWarehouseMode();

        return (($order->isChild() && !$warehouseMode) || ($order->isParent() && $warehouseMode));
    }

    protected function isTaxCloudTransactionsApplicable(): bool
    {
        return parent::isTaxCloudTransactionsApplicable()
            && $this->isTaxOwner();
    }
}
