<?php


namespace Iidev\TaxCloud\View\Model;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class ModuleSettings extends \XLite\View\Model\ModuleSettings
{
    /**
     * Get a list of CSS files required to display the widget properly
     *
     * @return array
     */
    public function getCSSFiles()
    {
        $list = parent::getCSSFiles();

        $list[] = 'modules/Iidev/TaxCloud/settings/style.less';

        return $list;
    }
}
