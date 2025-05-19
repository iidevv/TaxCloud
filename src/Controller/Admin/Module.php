<?php


namespace Iidev\TaxCloud\Controller\Admin;

use XCart\Extender\Mapping\Extender;

/**
 * Module settings
 * @Extender\Mixin
 */
abstract class Module extends \XLite\Controller\Admin\Module
{
    /**
     * Preprocessor for no-action run
     *
     * @return void
     */
    protected function doNoAction()
    {
        parent::doNoAction();
        
        if (
            $this->getModule() === 'Iidev-TaxCloud'
            && \XLite\Core\Config::getInstance()->Iidev->TaxCloud->api_login_id
            && \XLite\Core\Config::getInstance()->Iidev->TaxCloud->api_key
        ) {
            // Check connection
            $messages = [];
            if (!\Iidev\TaxCloud\Core\TaxCore::getInstance()->testConnection($messages)) {
                \XLite\Core\TopMessage::addError('Connection to TaxCloud server failed');
                foreach ($messages as $message) {
                    \XLite\Core\TopMessage::addError($message);
                }
            } else {
                // Check address
                $company = \XLite\Core\Config::getInstance()->Company;
                $address = [
                    'location_address' => $company->location_address,
                    'location_city' => $company->location_city,
                    'location_state' => $company->location_state,
                    'location_country' => $company->location_country,
                    'location_zipcode' => $company->location_zipcode,
                ];
                [$address, $messages] = \Iidev\TaxCloud\Core\TaxCore::getInstance()->validateAddress($address);
                
                if ($messages) {
                    \XLite\Core\TopMessage::addError(
                        'Invalid company address. Please follow this link and correct the address.',
                        [
                            'url' => \XLite\Core\COnverter::buildURL('settings', null, ['page' => 'Company']),
                        ]
                    );
                }
            }
        }
    }
}
