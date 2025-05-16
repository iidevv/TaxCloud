<?php


namespace Iidev\TaxCloud\Core;

use XCart\Extender\Mapping\Extender;

/**
 * AcaTax client
 *
 * @Extender\Mixin
 * @Extender\Depend ("CDev\VAT")
 */
class TaxCoreVAT extends \Iidev\TaxCloud\Core\TaxCore
{
    /**
     * Get information
     *
     * @param \XLite\Model\Order $order     Order
     * @param array              &$messages Error messages
     *
     * @return array
     */
    protected function getInformation(\XLite\Model\Order $order, array &$messages)
    {
        $post = parent::getInformation($order, $messages);

        if (
            $post
            && $order->getProfile()
            && $order->getProfile()->getBillingAddress()
            && $order->getProfile()->getBillingAddress()->getterProperty('vat_number')
        ) {
            $post['BusinessIdentificationNo'] = $order->getProfile()->getBillingAddress()->getterProperty('vat_number');
        }

        return $post;
    }
}
