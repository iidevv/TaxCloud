<?php


namespace Iidev\TaxCloud\Core;

use XCart\Extender\Mapping\Extender;

/**
 * AcaTax client
 *
 * @Extender\Mixin
 * @Extender\Depend ("XC\SystemFields")
 */
class TaxCoreUPC extends \Iidev\TaxCloud\Core\TaxCore
{
    /**
     * Assemble item code
     *
     * @param \XLite\Model\OrderItem $item Order item
     *
     * @return string
     */
    protected function assembleItemCode(\XLite\Model\OrderItem $item)
    {
        $upc = $item->getProduct()->getUpcIsbn();

        return $upc ? substr('UPC:' . $upc, 0, 50) : parent::assembleItemCode($item);
    }
}
