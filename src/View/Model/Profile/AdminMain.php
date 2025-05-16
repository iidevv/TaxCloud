<?php


namespace Iidev\TaxCloud\View\Model\Profile;

use XCart\Extender\Mapping\Extender;

/**
 * \XLite\View\Model\Profile\AdminMain
 * @Extender\Mixin
 */
class AdminMain extends \XLite\View\Model\Profile\AdminMain
{
    public const SECTION_TAXCLOUD = 'taxcloud';

    /**
     * TaxCloud schema
     *
     * @var   array
     */
    protected $taxcloudSchema = [
        'taxCloudExemptionNumber' => [
            self::SCHEMA_CLASS    => '\XLite\View\FormField\Input\Text',
            self::SCHEMA_LABEL    => 'Exemption number',
            self::SCHEMA_MODEL_ATTRIBUTES => [
                \XLite\View\FormField\Input\Base\StringInput::PARAM_MAX_LENGTH => 'length',
            ],
        ],
        'taxCloudCustomerUsageType' => [
            self::SCHEMA_CLASS    => '\Iidev\TaxCloud\View\FormField\Select\CustomerUsageTypes',
            self::SCHEMA_LABEL    => 'Usage type',
        ],
    ];

    /**
     * Return list of the class-specific sections
     *
     * @return array
     */
    protected function getProfileMainSections()
    {
        return parent::getProfileMainSections()
            + [
                static::SECTION_TAXCLOUD => 'TaxCloud settings',
            ];
    }

    /**
     * Return fields list by the corresponding schema
     *
     * @return array
     */
    protected function getFormFieldsForSectionTaxCloud()
    {
        return $this->getFieldsBySchema($this->taxcloudSchema);
    }
}
