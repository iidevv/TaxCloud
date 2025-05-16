<?php


namespace Iidev\TaxCloud\API\Endpoint\Profile\Transformer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use XCart\Extender\Mapping\Extender;
use XLite\API\Endpoint\Profile\DTO\ProfileInput as InputDTO;
use XLite\API\Endpoint\Profile\Transformer\InputTransformer as ExtendedInputTransformer;
use Iidev\TaxCloud\API\Endpoint\Profile\DTO\ProfileInput as ModuleInputDTO;
use Iidev\TaxCloud\API\Endpoint\Profile\DTO\ProfileOutput as ModuleOutputDTO;
use Iidev\TaxCloud\Model\Profile as Model;
use XLite\Model\Profile as BaseModel;

/**
 * @Extender\Mixin
 */
class InputTransformer extends ExtendedInputTransformer
{
    /**
     * @param ModuleInputDTO $object
     */
    public function transform($object, string $to, array $context = []): BaseModel
    {
        /** @var Model $entity */
        $entity = parent::transform($object, $to, $context);

        $entity->setTaxCloudExemptionNumber($object->tax_cloud_exemption_number);
        $entity->setTaxCloudCustomerUsageType($object->tax_cloud_customer_usage_type);

        return $entity;
    }

    public function initialize(string $inputClass, array $context = [])
    {
        /** @var Model $entity */
        $entity = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE] ?? null;
        if (!$entity) {
            return new InputDTO();
        }

        /** @var ModuleOutputDTO $input */
        $input = parent::initialize($inputClass, $context);

        $input->tax_cloud_exemption_number = $entity->getTaxCloudExemptionNumber();
        $input->tax_cloud_customer_usage_type = $entity->getTaxCloudCustomerUsageType();

        return $input;
    }
}
