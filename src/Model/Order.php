<?php


namespace Iidev\TaxCloud\Model;

use XCart\Extender\Mapping\Extender;
use Doctrine\ORM\Mapping as ORM;
use Iidev\TaxCloud\Core\TaxCore;
use Iidev\TaxCloud\Logic\Order\Modifier\StateTax;
use Iidev\TaxCloud\Model\TaxCloudStatuses;

/**
 * Order
 * @Extender\Mixin
 */
class Order extends \XLite\Model\Order
{
    /**
     * TaxCloud Number
     *
     * @var integer
     *
     * @ORM\Column (type="integer")
     */
    protected $taxCloudNumber = 0;

    /**
     * TaxCloud errors flag
     *
     * @var boolean
     *
     * @ORM\Column (type="boolean")
     */
    protected $taxCloudErrorsFlag = false;

    /**
     * TaxCloud import flag
     *
     * @var boolean
     *
     * @ORM\Column (type="boolean")
     */
    protected $taxCloudImported = false;


    /**
     * Get taxCloud Number
     */
    public function getTaxCloudNumber()
    {
        return $this->taxCloudNumber;
    }

    /**
     * Set taxCloud Number
     */
    public function setTaxCloudNumber($taxCloudNumber): self
    {
        $this->taxCloudNumber = $taxCloudNumber;

        return $this;
    }

    /**
     * Set TaxCloud import flag
     */
    public function setTaxCloudImported($taxCloudImported)
    {
        $this->taxCloudImported = $taxCloudImported;

        return $this;
    }

    /**
     * Get TaxCloud import flag
     */
    public function getTaxCloudImported()
    {
        return $this->taxCloudImported;
    }

    /**
     * Called when an order successfully placed by a client
     *
     * @return void
     */
    public function processSucceed()
    {
        parent::processSucceed();

        if ($this->getTaxCloudErrorsFlag()) {
            $cacheDriver = \XLite\Core\Database::getCacheDriver();
            $cacheId = \XLite\Core\Session::getInstance()->getID();
            $messages = $cacheDriver->fetch('taxcloud_last_errors' . $cacheId);
            \XLite\Core\OrderHistory::getInstance()->registerEvent(
                $this->getOrderId(),
                'TAXCLOUD_HAS_NOT_TAXES',
                'The order was created with tax value not calculated',
                [],
                $messages ? implode('; ', $messages) : ''
            );
        } elseif ($this->isTaxCloudTransactionsApplicable()) {
            TaxCore::getInstance()->setFinalCalculationFlag(true);
            TaxCore::getInstance()->getStateTax($this);
        }
    }

    /**
     * Prepare order before remove operation
     */
    public function prepareBeforeRemove()
    {
        parent::prepareBeforeRemove();

        if ($this->isTaxCloudTransactionsApplicable() && $this->getOrderNumber()) {
            TaxCore::getInstance()->voidTransactionRequest($this);
        }
    }

    /**
     * A "change status" handler for taxcloudVoidTransaction, is set in \Iidev\TaxCloud\Model\Order\Status\Payment
     */
    public function processTaxCloudVoidTransaction()
    {
        if ($this->isTaxCloudTransactionsApplicable()) {
            TaxCore::getInstance()->voidTransactionRequest($this);
        }
    }

    /**
     * Set taxCloudErrorsFlag
     *
     * @param boolean $taxCloudErrorsFlag
     * @return Order
     */
    public function setTaxCloudErrorsFlag($taxCloudErrorsFlag)
    {
        $this->taxCloudErrorsFlag = $taxCloudErrorsFlag;

        return $this;
    }

    /**
     * Get taxCloudErrorsFlag
     *
     * @return boolean
     */
    public function getTaxCloudErrorsFlag()
    {
        return $this->taxCloudErrorsFlag;
    }

    public function hasTaxCloudTaxes(): bool
    {
        $result = false;
        $surcharges = $this->getSurchargesByType(\XLite\Model\Base\Surcharge::TYPE_TAX);
        foreach ($surcharges as $surcharge) {
            /** @var \XLite\Model\Order\Surcharge $surcharge */
            if ($this->isTaxCloudSurcharge($surcharge)) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    protected function isTaxCloudTransactionsApplicable(): bool
    {
        return TaxCore::getInstance()->isValid()
            && $this->hasTaxCloudTaxes();
    }

    /**
     * Return true if code is TaxCloud surcharge code
     *
     * @param \XLite\Model\Order\Surcharge $surcharge Surcharge
     */
    protected function isTaxCloudSurcharge(\XLite\Model\Order\Surcharge $surcharge): bool
    {
        $modifier = $surcharge->getOwner()->getModifier(
            \XLite\Model\Base\Surcharge::TYPE_TAX,
            StateTax::MODIFIER_CODE
        );

        /** @var \XLite\Logic\Order\Modifier\AModifier $modifier */
        return $modifier->isSurchargeOwner($surcharge);
    }

    public function processStatus($status, $type)
    {
        parent::processStatus($status, $type);

        if (!$this->getPaymentStatus())
            return;

        if (!$this->getTaxCloudStatusesByOrderStatuses())
            return;

        if (!$this->isTaxCloudTransactionsApplicable())
            return;

        if ($this->getTaxCloudImported())
            return;

        $isCaptured = TaxCore::getInstance()->AuthorizeAndCapture($this);

        if ($isCaptured) {
            $this->setTaxCloudImported(true);
        }
    }


    private function getTaxCloudStatusesByOrderStatuses(): ?TaxCloudStatuses
    {
        if (!$this->getPaymentStatus() || !$this->getShippingStatus())
            return null;

        /** @var TaxCloudStatuses $result */
        $result = \Xlite\Core\Database::getRepo(TaxCloudStatuses::class)->findOneBy([
            'paymentStatus' => $this->getPaymentStatus()->getId(),
            'shippingStatus' => $this->getShippingStatus()->getId(),
        ]);

        return $result;
    }
}
