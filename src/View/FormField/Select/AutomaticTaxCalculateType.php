<?php


namespace Iidev\TaxCloud\View\FormField\Select;

/**
 * Automatic tax calculate type selector
 */
class AutomaticTaxCalculateType extends \XLite\View\FormField\Select\Regular
{
    public const PARAM_ALL_STATES      = 'all';
    public const PARAM_SPECIFIC_STATES = 'specific';

    /**
     * Get default options list
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        $options = [];

        $options[static::PARAM_ALL_STATES]      = static::t('All US States');
        $options[static::PARAM_SPECIFIC_STATES] = static::t('Specific states');

        return $options;
    }
}
