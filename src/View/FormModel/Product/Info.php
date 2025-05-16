<?php


namespace Iidev\TaxCloud\View\FormModel\Product;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Info extends \XLite\View\FormModel\Product\Info
{
    protected function defineFields()
    {
        $schema = parent::defineFields();

        $schema['prices_and_inventory']['tax_cloud_code'] = [
            'label'       => static::t('Tax code (TaxCloud)'),
            'constraints' => [
                'XLite\Core\Validator\Constraints\MaxLength' => [
                    'length'  => 25,
                ],
            ],
            'position'    => 250,
        ];

        return $schema;
    }
}
