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
        'taxcloudUserId'        => [
            self::SCHEMA_CLASS    => '\XLite\View\FormField\Label',
            self::SCHEMA_LABEL    => 'User ID',
            self::SCHEMA_REQUIRED => false,
        ],
    ];

    /**
     * getDefaultFieldValue
     *
     * @param string $name Field name
     *
     * @return mixed
     */
    public function getDefaultFieldValue($name)
    {
        $value = parent::getDefaultFieldValue($name);

        $login = $this->getModelObject()->getLogin();

        switch ($name) {
            case 'taxcloudUserId':
                $value = \Iidev\TaxCloud\Core\TaxCore::getInstance()->getUserId($login);
                break;

            default:
        }

        return $value;
    }

    /**
     * Return list of the class-specific sections
     *
     * @return array
     */
    protected function getProfileMainSections()
    {
        return parent::getProfileMainSections()
            + [
                static::SECTION_TAXCLOUD => 'TaxCloud',
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
