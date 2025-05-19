<?php

namespace Iidev\TaxCloud\View\Menu\Admin;

use XCart\Extender\Mapping\Extender;

/**
 * Left menu widget
 *
 * @Extender\Mixin
 */
class LeftMenu extends \XLite\View\Menu\Admin\LeftMenu
{
    /**
     * Define items
     *
     * @return array
     */
    protected function defineItems()
    {
        $list = parent::defineItems();

        if (isset($list['store_setup'])) {
            $list['store_setup'][static::ITEM_CHILDREN]['tax_cloud_statuses'] = [
                static::ITEM_TITLE  => static::t('TaxCloud Statuses'),
                static::ITEM_TARGET => 'tax_cloud_statuses',
                static::ITEM_WEIGHT => 960,
            ];
        }

        return $list;
    }
}
