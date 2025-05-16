<?php


namespace Iidev\TaxCloud\Model;

use XCart\Extender\Mapping\Extender;
use Doctrine\ORM\Mapping as ORM;

/**
 * Profile
 * @Extender\Mixin
 */
class Profile extends \XLite\Model\Profile
{
    /**
     * TaxCloud exemption number
     *
     * @var string
     *
     * @ORM\Column (type="string", length=25, nullable=true)
     */
    protected $taxCloudExemptionNumber;

    /**
     * TaxCloud exemption number
     *
     * @var string
     *
     * @ORM\Column (type="string", length=4, nullable=true)
     */
    protected $taxCloudCustomerUsageType;


    /**
     * Set taxCloudExemptionNumber
     *
     * @param string $taxCloudExemptionNumber
     * @return Profile
     */
    public function setTaxCloudExemptionNumber($taxCloudExemptionNumber)
    {
        $this->taxCloudExemptionNumber = $taxCloudExemptionNumber;
        return $this;
    }

    /**
     * Get taxCloudExemptionNumber
     *
     * @return string
     */
    public function getTaxCloudExemptionNumber()
    {
        return $this->taxCloudExemptionNumber;
    }

    /**
     * Set taxCloudCustomerUsageType
     *
     * @param string $taxCloudCustomerUsageType
     * @return Profile
     */
    public function setTaxCloudCustomerUsageType($taxCloudCustomerUsageType)
    {
        $this->taxCloudCustomerUsageType = $taxCloudCustomerUsageType;
        return $this;
    }

    /**
     * Get taxCloudCustomerUsageType
     *
     * @return string
     */
    public function getTaxCloudCustomerUsageType()
    {
        return $this->taxCloudCustomerUsageType;
    }
}
