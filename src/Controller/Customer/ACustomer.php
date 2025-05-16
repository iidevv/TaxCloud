<?php


namespace Iidev\TaxCloud\Controller\Customer;

use XCart\Extender\Mapping\Extender;

/**
 * Abstract customer
 * @Extender\Mixin
 */
class ACustomer extends \XLite\Controller\Customer\ACustomer
{
    /**
     * Get fingerprint difference
     *
     * @param array $old Old fingerprint
     * @param array $new New fingerprint
     *
     * @return array
     */
    protected function getCartFingerprintDifference(array $old, array $new)
    {
        $diff = parent::getCartFingerprintDifference($old, $new);

        if (
            isset($old['taxCloudErrorsFlag'])
            && isset($new['taxCloudErrorsFlag'])
            && $old['taxCloudErrorsFlag'] != $new['taxCloudErrorsFlag']
        ) {
            $diff['taxCloudErrorsFlag'] = $new['taxCloudErrorsFlag'];
        }

        return $diff;
    }
}
