<?php

namespace Iidev\TaxCloud\Model;

use \XLite\Core\Database;
use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Address extends \XLite\Model\Address
{
    public function addAddressType($addressType = '')
    {
        $this->setAddressType($addressType);

        Database::getEM()->persist($this);
        Database::getEM()->flush();
    }
}
