<?php


namespace Iidev\TaxCloud\Controller\Admin;

use XCart\Extender\Mapping\Extender;
use Iidev\TaxCloud\Core\TaxCore;

/**
 * @package Iidev\TaxCloud\Controller\Admin
 * @Extender\Mixin
 */
class Order extends \XLite\Controller\Admin\Order
{
    protected function doActionUpdate()
    {
        parent::doActionUpdate();

        $this->orderUpdatedCallback(
            $this->getOrderChanges(),
            $this->getOrder()
        );
    }

    /**
     * @param array                                     $diff
     * @param \XLite\Model\Order|\Iidev\TaxCloud\Model\Order $order
     */
    protected function orderUpdatedCallback(array $diff, \XLite\Model\Order $order)
    {
        if ($diff && !empty($diff['TAXCLOUD.summary']) && TaxCore::getInstance()->isValid() && $order->hasTaxCloudTaxes() && $order->getTaxCloudImported()) {
            TaxCore::getInstance()->adjustTransactionRequest($order);
        }
    }

    /**
     * Get shipping address type
     *
     * @return string
     */
    public function getShippingAddressType()
    {
        return $this->getOrder()?->getProfile()?->getShippingAddress()?->getAddressType();
    }

    public function isAddressVerificationEnabled()
    {
        return \XLite\Core\Config::getInstance()->Iidev->TaxCloud->addressverif;
    }

    protected function doActionCheckAddress()
    {
        if (!TaxCore::getInstance()->isValid()) {
            \XLite\Core\TopMessage::addError("TaxCloud is not configured properly. Please check your settings.");
            return;
        }

        $shippingAddress = $this->getOrder()->getProfile()->getShippingAddress();

        if (!$shippingAddress) {
            \XLite\Core\TopMessage::addError("Shipping address is not set. Please set a shipping address before checking.");
            return;
        }

        if (TaxCore::getInstance()->isAllowedAddressVerification($shippingAddress)) {
            [$address, $messages] = TaxCore::getInstance()->validateAddress($shippingAddress);

            if ($messages) {
                foreach ($messages as $message) {
                    \XLite\Core\TopMessage::addError($message['message']);
                }
            } else {
                \XLite\Core\TopMessage::addInfo("Shipping address is valid.");
            }

            if ($address->getAddressType()) {
                $shippingAddress->addAddressType($address->getAddressType());
            }
        } else {
            \XLite\Core\TopMessage::addError("Address verification is not allowed for this address.");
        }

        $url = $this->buildURL('order', '', ['order_number' => $this->getOrder()->getOrderNumber()]);
        $this->setReturnURL($url);
    }
}
