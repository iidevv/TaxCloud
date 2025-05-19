<?php

namespace Iidev\TaxCloud\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table (name="taxcloud_statuses",
 *      uniqueConstraints={
 *          @ORM\UniqueConstraint (name="pair", columns={"payment_status_id","shipping_status_id"})
 *      },
 *      indexes={
 *          @ORM\Index (name="payment_status_id", columns={"payment_status_id"}),
 *          @ORM\Index (name="shipping_status_id", columns={"shipping_status_id"})
 *      }
 * )
 * @ORM\HasLifecycleCallbacks
 */
class TaxCloudStatuses extends \XLite\Model\AEntity
{
    /**
     * Node unique ID
     *
     * @var integer
     *
     * @ORM\Id
     * @ORM\GeneratedValue (strategy="AUTO")
     * @ORM\Column         (type="integer", options={ "unsigned": true })
     */
    protected $id;

    /**
     * Payment status
     *
     * @var \XLite\Model\Order\Status\Payment
     *
     * @ORM\ManyToOne  (targetEntity="XLite\Model\Order\Status\Payment")
     * @ORM\JoinColumn (name="payment_status_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $paymentStatus;

    /**
     * Shipping status
     *
     * @var \XLite\Model\Order\Status\Shipping
     *
     * @ORM\ManyToOne  (targetEntity="XLite\Model\Order\Status\Shipping")
     * @ORM\JoinColumn (name="shipping_status_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $shippingStatus;

    /**
     * Flag if the field is an enabled one
     *
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $enabled = true;

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    public function getPaymentStatus()
    {
        return $this->paymentStatus;
    }

    /**
     * Set payment status
     *
     * @param mixed $paymentStatus Payment status
     *
     * @return void
     */
    public function setPaymentStatus($paymentStatus): void
    {
        $this->processStatus($paymentStatus, 'payment');
    }

    /**
     * Process status
     *
     * @param mixed  $status Status
     * @param string $type   Type
     *
     * @return void
     */
    public function processStatus($status, $type): void
    {
        static $cache = [];

        if (is_scalar($status)) {
            if (!isset($cache[$type][$status])) {
                $requestedStatus = $status;

                if (
                    is_int($status)
                    || (is_string($status)
                        && preg_match('/^[\d]+$/', $status)
                    )
                ) {
                    $status = \XLite\Core\Database::getRepo('XLite\Model\Order\Status\\' . ucfirst($type))
                        ->find($status);
                } elseif (is_string($status)) {
                    $status = \XLite\Core\Database::getRepo('XLite\Model\Order\Status\\' . ucfirst($type))
                        ->findOneByCode($status);
                }

                $cache[$type][$requestedStatus] = $status;
            } else {
                $status = $cache[$type][$status];
            }
        }

        $this->{$type . 'Status'} = $status;
    }

    public function getShippingStatus()
    {
        return $this->shippingStatus;
    }

    /**
     * Set shipping status
     *
     * @param mixed $shippingStatus Shipping status
     *
     * @return void
     */
    public function setShippingStatus($shippingStatus): void
    {
        $this->processStatus($shippingStatus, 'shipping');
    }

    /**
     * @return bool
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
