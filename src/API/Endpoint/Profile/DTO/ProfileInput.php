<?php


namespace Iidev\TaxCloud\API\Endpoint\Profile\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use XCart\Extender\Mapping\Extender;
use XLite\API\Endpoint\Profile\DTO\ProfileInput as ExtendedInput;

/**
 * @Extender\Mixin
 */
class ProfileInput extends ExtendedInput
{
    /**
     * @Assert\Length(max="25")
     * @var string|null
     */
    public ?string $tax_cloud_exemption_number = '';

    /**
     * @Assert\Length(max="4")
     * @var string|null
     */
    public ?string $tax_cloud_customer_usage_type = '';
}
