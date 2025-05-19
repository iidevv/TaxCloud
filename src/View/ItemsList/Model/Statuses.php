<?php

namespace Iidev\TaxCloud\View\ItemsList\Model;

use Iidev\TaxCloud\Model\TaxCloudStatuses;
use Iidev\TaxCloud\View\FormField\Inline\Select\OrderStatus\Shipping;
use Iidev\TaxCloud\View\FormField\Inline\Select\OrderStatus\Payment;

class Statuses extends \XLite\View\ItemsList\Model\Table
{
    public static function getAllowedTargets()
    {
        $list = parent::getAllowedTargets();

        $list[] = 'tax_cloud_statuses';

        return $list;
    }

    protected function wrapWithFormByDefault()
    {
        return true;
    }

    protected function getFormTarget()
    {
        return 'tax_cloud_statuses';
    }

    protected function getListNameSuffixes()
    {
        return ['tax_cloud_statuses'];
    }

    protected function getRemoveMessage($count)
    {
        return static::t('TaxCloud x items has been removed', ['count' => $count]);
    }

    protected function getCreateMessage($count)
    {
        return static::t('TaxCloud x items has been created', ['count' => $count]);
    }

    protected function checkACL()
    {
        return parent::checkACL()
            && \XLite\Core\Auth::getInstance()->isPermissionAllowed('manage catalog');
    }

    protected function getFormOptions()
    {
        return array_merge(parent::getFormOptions(), [
            \XLite\View\Form\AForm::PARAM_CONFIRM_REMOVE => true,
        ]);
    }

    protected function isInlineCreation()
    {
        return static::CREATE_INLINE_TOP;
    }

    protected function getSearchPanelClass()
    {
        return '';
    }

    protected function isCreation()
    {
        return static::CREATE_INLINE_TOP;
    }

    protected function isExportable()
    {
        return false;
    }

    protected function isRemoved()
    {
        return true;
    }

    protected function isSwitchable()
    {
        return true;
    }

    protected function isSelectable()
    {
        return false;
    }

    protected function defineRepositoryName(): string
    {
        return TaxCloudStatuses::class;
    }

    protected function getBlankItemsListDescription()
    {
        return static::t('Table is empty');
    }

    protected function getPanelClass()
    {
        return \XLite\View\StickyPanel\ItemsListForm::class;
    }

    protected function getContainerClass()
    {
        return parent::getContainerClass() . ' taxcloud-statuses';
    }

    protected function getCreateButtonLabel()
    {
        return static::t('Add condition');
    }

    /**
     * @inheritDoc
     */
    protected function defineColumns()
    {
        return [
            'paymentStatus' => [
                static::COLUMN_NAME => static::t('Payment status'),
                static::COLUMN_CLASS => Payment::class,
                static::COLUMN_ORDERBY => 100,
            ],
            'shippingStatus' => [
                static::COLUMN_NAME => static::t('Shipping status'),
                static::COLUMN_CLASS => Shipping::class,
                static::COLUMN_ORDERBY => 200,
            ],
        ];
    }
}