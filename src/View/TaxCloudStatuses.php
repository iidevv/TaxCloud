<?php

namespace Iidev\TaxCloud\View;

use XCart\Extender\Mapping\ListChild;

/**
 * @ListChild (list="admin.center", zone="admin")
 */
class TaxCloudStatuses extends \XLite\View\AView
{
    /**
     *
     * @return array
     */
    public static function getAllowedTargets()
    {
        return array_merge(parent::getAllowedTargets(), ['tax_cloud_statuses']);
    }

    /**
     *
     * @return string
     */
    protected function getDefaultTemplate()
    {
        return 'modules/Iidev/TaxCloud/admin/statuses.twig';
    }
}
