<?php


namespace Iidev\TaxCloud\API\Endpoint\Profile\Transformer;

use XCart\Extender\Mapping\Extender;
use XLite\API\Endpoint\Profile\DTO\ProfileOutput as OutputDTO;
use XLite\API\Endpoint\Profile\Transformer\OutputTransformer as ExtendedOutputTransformer;
use Iidev\TaxCloud\API\Endpoint\Profile\DTO\ProfileOutput as ModuleOutputDTO;
use Iidev\TaxCloud\Model\Profile as Model;

/**
 * @Extender\Mixin
 */
class OutputTransformer extends ExtendedOutputTransformer
{
    /**
     * @param Model $object
     */
    public function transform($object, string $to, array $context = []): OutputDTO
    {
        /** @var ModuleOutputDTO $dto */
        $dto = parent::transform($object, $to, $context);

        $dto->tax_cloud_exemption_number = $object->getTaxCloudExemptionNumber();
        $dto->tax_cloud_customer_usage_type = $object->getTaxCloudCustomerUsageType();

        return $dto;
    }
}
