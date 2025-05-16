<?php


namespace Iidev\TaxCloud\Model\DTO\Product;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Info extends \XLite\Model\DTO\Product\Info
{
    protected function init($object)
    {
        parent::init($object);

        $this->prices_and_inventory->tax_cloud_code = $object->getTaxCloudCode();
    }

    public function populateTo($object, $rawData = null)
    {
        parent::populateTo($object, $rawData);

        $object->setTaxCloudCode($this->prices_and_inventory->tax_cloud_code);
    }
}
