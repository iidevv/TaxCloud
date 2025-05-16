<?php


namespace Iidev\TaxCloud\Model;

use XCart\Extender\Mapping\Extender;

/**
 * Cart
 * @Extender\Mixin
 */
class Cart extends \XLite\Model\Cart
{
    /**
     * Check - TaxCloud forbid checkout or not
     *
     * @return boolean
     */
    public function isTaxCloudForbidCheckout()
    {
        $result =  $this->getTaxCloudErrorsFlag();

        if ($result) {
            $modifier = $this->getModifier(
                \XLite\Model\Base\Surcharge::TYPE_TAX,
                \Iidev\TaxCloud\Logic\Order\Modifier\StateTax::MODIFIER_CODE
            );
            $result = $modifier->canApply();
        }

        return $result;
    }

    /**
     * Define fingerprint keys
     *
     * @return array
     */
    protected function defineFingerprintKeys()
    {
        return array_merge(
            parent::defineFingerprintKeys(),
            ['taxCloudErrorsFlag']
        );
    }

    /**
     * Get fingerprint by 'taxCloudErrorsFlag' key
     *
     * @return array
     */
    protected function getFingerprintByTaxCloudErrorsFlag()
    {
        return $this->getTaxCloudErrorsFlag();
    }
}
