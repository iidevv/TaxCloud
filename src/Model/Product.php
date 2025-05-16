<?php


namespace Iidev\TaxCloud\Model;

use XCart\Extender\Mapping\Extender;
use Doctrine\ORM\Mapping as ORM;

/**
 * Product
 * @Extender\Mixin
 */
class Product extends \XLite\Model\Product
{
    /**
     * Product SKU
     *
     * @var string
     *
     * @ORM\Column (type="string", length=25, nullable=true)
     */
    protected $taxCloudCode;

    /**
     * Set taxCloudCode
     *
     * @param string $taxCloudCode
     * @return Product
     */
    public function setTaxCloudCode($taxCloudCode)
    {
        $this->taxCloudCode = $taxCloudCode;
        return $this;
    }

    /**
     * Get taxCloudCode
     *
     * @return string
     */
    public function getTaxCloudCode()
    {
        return $this->taxCloudCode;
    }
}
