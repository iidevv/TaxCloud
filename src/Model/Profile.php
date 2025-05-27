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
     * TaxCloud Certificate Id
     *
     * @var string
     *
     * @ORM\Column (type="string", nullable=true)
     */
    protected $taxCloudCertificateId;

    /**
     * Set TaxCloud Certificate Id
     *
     * @param string $taxCloudCertificateId
     * @return Profile
     */
    public function setTaxCloudCertificateId($taxCloudCertificateId)
    {
        $this->taxCloudCertificateId = $taxCloudCertificateId;
        return $this;
    }

    /**
     * Get TaxCloud Certificate Id
     *
     * @return string
     */
    public function getTaxCloudCertificateId()
    {
        return $this->taxCloudCertificateId;
    }
}
