<?php


namespace Iidev\TaxCloud\View\Checkout\Step;

use XCart\Extender\Mapping\Extender;

/**
 * Shipping step
 * @Extender\Mixin
 */
class Shipping extends \XLite\View\Checkout\Step\Shipping
{
    /**
     * Check - step is complete or not
     *
     * @return boolean
     */
    public function isCompleted()
    {
        return parent::isCompleted()
            && !$this->getCart()->isTaxCloudForbidCheckout();
    }

    /**
     * Register JS files
     *
     * @return array
     */
    public function getJSFiles()
    {
        $list = parent::getJSFiles();

        $list[] = 'modules/Iidev/TaxCloud/checkout.js';

        return $list;
    }

    /**
     * Register CSS files
     *
     * @return array
     */
    public function getCSSFiles()
    {
        $list = parent::getCSSFiles();

        $list[] = 'modules/Iidev/TaxCloud/checkout.less';

        return $list;
    }

    /**
     * Check - TaxCloud address verification is enabled or not
     *
     * @return boolean
     */
    protected function isTaxCloudAddressVerificationEnabled()
    {
        return \Iidev\TaxCloud\Core\TaxCore::getInstance()->isValid()
            && \XLite\Core\Config::getInstance()->Iidev->TaxCloud->addressverif;
    }
}
