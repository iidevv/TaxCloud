<?php


namespace Iidev\TaxCloud\Logic\Order\Modifier;

use XLite\Controller\Customer\Cart;
use XLite\Controller\Customer\Checkout;
use Iidev\TaxCloud\Core\TaxCore;

/**
 * State tax
 */
class StateTax extends \XLite\Logic\Order\Modifier\ATax
{
    /**
     * Modifier code
     */
    public const MODIFIER_CODE = 'TAXCLOUD';

    /**
     * Tax cache TTL
     */
    public const TAXCLOUD_CACHE_TTL = 86400;

    /**
     * Modifier unique code
     *
     * @var string
     */
    protected $code = self::MODIFIER_CODE;

    /**
     * Surcharge identification pattern
     *
     * @var string
     */
    protected $identificationPattern = '/^TAXCLOUD\..+/Ss';

    /**
     * Check - can apply this modifier or not
     *
     * @return boolean
     */
    public function canApply()
    {
        $persistentItems = [];
        if ($this->order && $this->order->getItems()) {
            $persistentItems = $this->order->getItems()->filter(static function ($item) {
                return $item->isPersistent();
            });
        }

        return parent::canApply()
            && 0 < count($persistentItems)
            && TaxCore::getInstance()->isValid()
            && \XLite\Core\Config::getInstance()->Iidev->TaxCloud->taxcalculation
            && $this->order->getProfile()
            && $this->order->getProfile()->getBillingAddress()
            && $this->canApplyByStates();
    }

    /**
     * Calculate
     *
     * @return \XLite\Model\Order\Surcharge[]
     */
    public function calculate()
    {
        $orderState = $this->order->getEventFingerprint();
        unset(
            $orderState['taxCloudErrorsFlag'],
            $orderState['paymentMethodId'],
            $orderState['shippingMethodsHash'],
            $orderState['paymentMethodsHash'],
            $orderState['shippingAddressFields'],
            $orderState['billingAddressFields']
        );
        if ($this->order->getProfile()) {
            if ($this->order->getProfile()->getShippingAddress()) {
                $orderState['shippingAddress'] = $this->order->getProfile()->getShippingAddress()->getFieldsHash();
            }
            if ($this->order->getProfile()->getBillingAddress()) {
                $orderState['billingAddress'] = $this->order->getProfile()->getBillingAddress()->getFieldsHash();
            }
        }

        $hash     = md5(serialize($orderState));
        $cacheKey = 'taxcloud_' . $hash;

        $surcharges = [];

        $cacheDriver = \XLite\Core\Database::getCacheDriver();

        $error = null;
        $taxes = null;

        $cached = $cacheDriver->fetch($cacheKey);

        if ($cached) {
            $error = null;
            $taxes = $cached;
        } elseif ($this->canBeCalculatedNow()) {
            [$error, $taxes] = TaxCore::getInstance()->getStateTax($this->order);
            if (!$error) {
                $cacheDriver->save($cacheKey, $taxes, static::TAXCLOUD_CACHE_TTL);
            }
        }

        if ($taxes) {
            $taxCost = array_reduce($taxes, static function ($cost, $tax) {
                return $cost + floatval($tax['cost']);
            }, 0);

            $name      = $this->code . '.summary';
            $surcharge = $this->addOrderSurcharge($name, $taxCost);
            $surcharge->setName(static::t('Taxes'));

            $surcharges[] = $surcharge;
        }

        $this->order->setTaxCloudErrorsFlag((bool) $error);

        $sessionId = \XLite\Core\Session::getInstance()->getID();
        $cacheDriver->save('taxcloud_last_errors' . $sessionId, $error, 0);
        $cacheDriver->save('taxcloud_last_errors_ajax' . $sessionId, $error, 0);

        if (is_array($error) && !(\XLite::getController() instanceof \XLite\Controller\Customer\Checkout)) {
            foreach ($error as $e) {
                \XLite\Core\TopMessage::addError($e);
            }
        }

        return $surcharges;
    }

    /**
     * Get surcharge info
     *
     * @param \XLite\Model\Base\Surcharge $surcharge Surcharge
     *
     * @return \XLite\DataSet\Transport\Order\Surcharge
     */
    public function getSurchargeInfo(\XLite\Model\Base\Surcharge $surcharge)
    {
        $info = new \XLite\DataSet\Transport\Order\Surcharge();
        $info->name = static::t('Taxes');

        return $info;
    }

    /**
     * @return bool
     */
    protected function canBeCalculatedNow()
    {
        return (!$this->isCart() || !$this->order->isIgnoreLongCalculations())
            && !(
                \XLite::getController() instanceof Cart
                && (
                    (
                        \XLite\Core\Request::getInstance()->isGet()
                        && isset(\XLite\Core\Request::getInstance()->widget)
                    )
                    || in_array(\XLite::getController()->getAction(), ['add', 'add_order'], true)
                )
            ) && !(
                \XLite::getController() instanceof Checkout
                && \XLite\Core\Request::getInstance()->isAJAX()
                && \XLite\Core\Request::getInstance()->isGet()
                && isset(\XLite\Core\Request::getInstance()->widget)
            );
    }

    /**
     * Return state codes that is available for US
     *
     * @return array
     */
    protected function getUsAvailableStates()
    {
        return @unserialize(\XLite\Core\Config::getInstance()->Iidev->TaxCloud->calctaxforus)
            ?: [];
    }

    /**
     * Check if calculation for specific states
     *
     * @return bool
     */
    protected function isCalculationForSpecificStates()
    {
        return \XLite\Core\Config::getInstance()->Iidev->TaxCloud->calctaxforus_type
            == \Iidev\TaxCloud\View\FormField\Select\AutomaticTaxCalculateType::PARAM_SPECIFIC_STATES;
    }

    /**
     * Check - can apply this modifier - by states
     *
     * @return boolean
     */
    protected function canApplyByStates()
    {
        if ($this->order->getProfile()) {
            $address = $this->order->getProfile()->getShippingAddress()
                ?: $this->order->getProfile()->getBillingAddress();

            $states = $this->getUsAvailableStates();

            return !$this->isCalculationForSpecificStates()
                || (
                    $address->getCountry()->getCode() === 'US'
                    && in_array($address->getState()->getCode(), $states, true)
                );
        }

        return false;
    }
}
