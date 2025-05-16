<?php


namespace Iidev\TaxCloud\View\FormField\Select\Select2;

use XLite\Core\Database;
use XLite\Model\State;
use XLite\View\FormField\Select\Select2Trait;

/**
 * StateCodes
 */
class StateCodes extends \XLite\View\FormField\Select\Multiple
{
    use Select2Trait;

    public const PARAM_COUNTRY_CODE = 'countryCode';

    public function getJSFiles()
    {
        $list = parent::getJSFiles();
        $list[] = 'modules/Iidev/TaxCloud/form_field/select/input_select2.js';

        return $list;
    }

    protected function getOptions()
    {
        $options = $this->getParam(static::PARAM_COUNTRY_CODE)
            ? $this->getCodesByCountryCode($this->getParam(static::PARAM_COUNTRY_CODE))
            : parent::getOptions();

        return $options + $this->getAdditionalOptions();
    }

    /**
     * @return array
     */
    protected function getAdditionalOptions()
    {
        return $this->getValue()
            ? array_combine($this->getValue(), $this->getValue())
            : [];
    }

    protected function defineWidgetParams()
    {
        parent::defineWidgetParams();

        $this->widgetParams += [
            static::PARAM_COUNTRY_CODE => new \XLite\Model\WidgetParam\TypeString(
                'All',
                null
            ),
        ];
    }

    /**
     * @return string
     */
    protected function getDefaultCountryCode()
    {
        return 'US';
    }

    /**
     * @param $code
     *
     * @return array
     */
    protected function getCodesByCountryCode($code)
    {
        $list = Database::getRepo('XLite\Model\State')->findByCountryCode($code) ?: [];

        $list = array_filter($list, static function (State $state) {
            return mb_strlen($state->getCode()) > 0;
        });

        $codes = array_map(
            static function (State $state) {
                return $state->getCode();
            },
            $list
        );

        return array_combine($codes, $codes);
    }

    protected function getDefaultOptions()
    {
        return $this->getCodesByCountryCode($this->getDefaultCountryCode());
    }

    protected function getValueContainerClass()
    {
        return parent::getValueContainerClass() . ' input-select2 input-state-codes-select2';
    }

    public function setValue($value)
    {
        if (is_string($value)) {
            $value = @unserialize($value);
        }

        parent::setValue($value);
    }

    public function prepareRequestData($value)
    {
        if (is_array($value)) {
            $value = serialize(array_filter(array_map('mb_strtoupper', $value), function ($e) {
                return isset($this->getOptions()[$e]);
            }));
        }

        return parent::prepareRequestData($value);
    }
}
