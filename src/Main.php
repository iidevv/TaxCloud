<?php


namespace Iidev\TaxCloud;

use XLite\Core\Config;

abstract class Main extends \XLite\Module\AModule
{
    /**
     * @return boolean
     */
    public static function hasGdprRelatedActivity()
    {
        return true;
    }

    public static function isColoradoRetailDeliveryFeeCollectionEnabled(): bool
    {
        return true;
    }
}
