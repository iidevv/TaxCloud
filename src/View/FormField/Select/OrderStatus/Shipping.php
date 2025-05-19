<?php

namespace Iidev\TaxCloud\View\FormField\Select\OrderStatus;

class Shipping extends \XLite\View\FormField\Select\OrderStatus\Shipping
{
    protected function getOptions()
    {
        $list = parent::getOptions();

        unset($list[0]);

        return $list;
    }
}
