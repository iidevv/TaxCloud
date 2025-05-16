<?php


namespace Iidev\TaxCloud\View\FormField\Select;

/**
 * Customer usage types selector
 */
class CustomerUsageTypes extends \XLite\View\FormField\Select\Regular
{
    /**
     * Get default options
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            ''  => 'None',
            'A' => 'Federal Government',
            'B' => 'State/Local Govt.',
            'C' => 'Tribal Government',
            'D' => 'Foreign Diplomat',
            'E' => 'Charitable Organization',
            'F' => 'Religious/Education',
            'G' => 'Resale',
            'H' => 'Agricultural Production',
            'I' => 'Industrial Prod/Mfg.',
            'J' => 'Direct Pay Permit',
            'K' => 'Direct Mail',
            'L' => 'Other',
            'N' => 'Local Government',
            'P' => 'Commercial Aquaculture (Canada)',
            'Q' => 'Commercial Fishery (Canada)',
            'R' => 'Non-resident (Canada)',
            'MED1' => 'US MDET with exempt sales tax',
            'MED2' => 'US MDET with taxable sales tax',
        ];
    }
}
