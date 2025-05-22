<?php


namespace Iidev\TaxCloud\Controller\Customer;

use XCart\Extender\Mapping\Extender;
use Iidev\TaxCloud\Core\TaxCore;

/**
 * Checkout
 * @Extender\Mixin
 */
class Checkout extends \XLite\Controller\Customer\Checkout
{
    /**
     * Run controller
     *
     * @return void
     */
    protected function run()
    {
        parent::run();

        $request = \XLite\Core\Request::getInstance();

        if (($request->isPost() && $request->isAJAX()) || !$request->isAJAX()) {
            $session = \XLite\Core\Session::getInstance();
            $cacheDriver = \XLite\Core\Database::getCacheDriver();
            $cacheId = $session->getID();
            $errors = $request->isAJAX()
                ? $cacheDriver->fetch('taxcloud_last_errors_ajax' . $cacheId)
                : $cacheDriver->fetch('taxcloud_last_errors' . $cacheId);

            if ($this->getCart()->isTaxCloudForbidCheckout()) {
                if ($errors) {
                    $message = implode(', ', $errors);
                    \XLite\Core\TopMessage::addError($message);
                } else {
                    \XLite\Core\TopMessage::addError(
                        'Checkout cannot be completed because tax has not been calculated due to internal problems. Please contact the site administrator.'
                    );
                }

                if ($request->isAJAX()) {
                    $cacheDriver->delete('taxcloud_last_errors_ajax' . $cacheId);
                }
            } elseif ($errors) {
                $modifier = $this->getCart()->getModifier(
                    \XLite\Model\Base\Surcharge::TYPE_TAX,
                    \Iidev\TaxCloud\Logic\Order\Modifier\StateTax::MODIFIER_CODE
                );

                if ($errors && $modifier->canApply()) {
                    foreach ($errors as $e) {
                        \XLite\Core\TopMessage::addError($e);
                    }
                }

                if ($request->isAJAX()) {
                    $cacheDriver->delete('taxcloud_last_errors_ajax' . $cacheId);
                }
            }
        }
    }

    /**
     * Check TaxCloud address
     *
     * @return void
     */
    protected function doActionCheckTaxCloudAddress()
    {
        $data = \XLite\Core\Request::getInstance()->address;

        if (TaxCore::getInstance()->isValid() && $data) {
            $address = [
                'street'       => $data['street'],
                'city'         => $data['city'],
                'state_id'     => $data['state_id'],
                'country_code' => $data['country_code'],
                'zipcode'      => $data['zipcode'],
            ];

            if (TaxCore::getInstance()->isAllowedAddressVerification($address)) {
                $errors = [];

                [$address, $messages] = TaxCore::getInstance()->validateAddress($address);

                if ($messages) {
                    foreach ($messages as $message) {
                        $errors[] = static::t($message['message']);
                    }
                }
            }
        }

        $this->displayJSON(['errors' => $errors, 'address' => $address]);
        $this->setSuppressOutput(true);
        $this->set('silent', true);
    }
}
