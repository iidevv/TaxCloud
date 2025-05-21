<?php


namespace Iidev\TaxCloud\Core;

use XLite\InjectLoggerTrait;
use XLite\Core\Database;

use function PHPUnit\Framework\isNull;

/**
 * AcaTax client
 */
class TaxCore extends \XLite\Base\Singleton
{
    use InjectLoggerTrait;

    public const FAILED_BACKEND_STATUSES = [\XLite\Model\Payment\BackendTransaction::STATUS_FAILED, \XLite\Model\Payment\BackendTransaction::STATUS_INITIALIZED];

    /**
     * Valid status
     *
     * @var boolean
     */
    protected $valid;

    protected string $baseURL;

    public function __construct()
    {
        parent::__construct();
        $this->baseURL = 'https://api.taxcloud.net/1.0/TaxCloud/';
    }

    /**
     * Check valid status
     *
     * @return boolean
     */
    public function isValid()
    {
        if (!isset($this->valid)) {
            $config = \XLite\Core\Config::getInstance()->Iidev->TaxCloud;
            $this->valid = $config->api_login_id && $config->api_key;
        }

        return $this->valid;
    }

    public function refundTransactionRequest(\XLite\Model\Payment\BackendTransaction $transaction, $order = null)
    {
        $dataProvider = new DataProvider\Order($order ?: $transaction->getPaymentTransaction()->getOrder());
        $data = $dataProvider->getRefundTransactionModel($transaction);
        $this->taxcloudRequest(
            "Returned",
            $data
        );
    }

    public function voidTransactionRequest(\Iidev\TaxCloud\Model\Order $order)
    {
        $data = [
            "orderID" => "{$order->getOrderNumber()}-{$order->getTaxCloudNumber()}",
            "returnedDate" => date('Y-m-d'),
        ];

        $this->taxcloudRequest(
            "Returned",
            $data
        );
    }

    public function adjustTransactionRequest(\Iidev\TaxCloud\Model\Order $order)
    {
        $this->voidTransactionRequest($order);

        $this->updateTaxCloudNumber($order);

        $this->AuthorizeAndCapture($order);
    }

    private function updateTaxCloudNumber($order)
    {
        $order->setTaxCloudNumber($order->getTaxCloudNumber() + 1);

        Database::getEM()->persist($order);
        Database::getEM()->flush();
    }

    /**
     * Test connection
     *
     * @return boolean
     */
    public function testConnection(array &$messages = [])
    {
        [$result, $data] = $this->taxcloudRequest('Ping', []);

        $result = $data && $data['ResponseType'] === 3;
        if (!$result && !empty($data['Messages'])) {
            foreach ($data['Messages'] as $message) {
                $messages[] = $message['Message'];
            }
        }

        return $result;
    }


    /**
     * Validate address
     *
     * @param mixed $address Address
     *
     * @return array
     */
    public function validateAddress($address)
    {
        $data = $this->assembleAddressValidationRequest($address);

        [$result, $decodedResponse] = $this->taxcloudRequest('VerifyAddress', $data);

        return $result
            ? $this->processAddressValidation($decodedResponse, $address)
            : [null, null];
    }

    /**
     * Assemble address validation request
     *
     * @param mixed $address Address

     * @return array
     */
    protected function assembleAddressValidationRequest($address)
    {
        $result = [
            'Address1' => '',
            'Address2' => '',
            'City' => '',
            'State' => '',
            'Zip4' => null,
            'Zip5' => null,
        ];

        $parsePostalCode = function ($postalCode) use (&$result) {
            if (!empty($postalCode)) {
                $postalParts = explode('-', $postalCode);
                $result['Zip5'] = (int) $postalParts[0];
                $result['Zip4'] = isset($postalParts[1]) ? (int) $postalParts[1] : null;
            }
        };

        if (is_array($address) && is_object(current($address))) {
            foreach ($address as $field) {
                $parts = explode('_', $field->getName(), 2);
                $fieldName = $parts[1] ?? null;
                $value = $field->getValue();

                switch ($fieldName) {
                    case 'street':
                        $result['Address1'] = $value;
                        break;
                    case 'street2':
                        $result['Address2'] = $value;
                        break;
                    case 'city':
                        $result['City'] = $value;
                        break;
                    case 'state_id':
                        if ($value) {
                            $state = Database::getRepo('XLite\Model\State')->find($value);
                            if ($state) {
                                $result['State'] = $state->getCode();
                            }
                        }
                        break;
                    case 'custom_state':
                        if (empty($result['State']) && $value) {
                            $result['State'] = $value;
                        }
                        break;
                    case 'country_code':
                        break;
                    case 'zipcode':
                        $parsePostalCode($value);
                        break;
                }
            }
        }
        // Handle static state tax address
        elseif (is_array($address) && !empty($address['line1'])) {
            $result['Address1'] = $address['line1'] ?? '';
            $result['Address2'] = $address['line2'] ?? '';
            $result['City'] = $address['city'] ?? '';
            $result['State'] = $address['region'] ?? '';
            $parsePostalCode($address['postalCode'] ?? '');
        }
        // Handle settings address
        elseif (is_array($address) && !empty($address['location_country'])) {
            $country = Database::getRepo('XLite\Model\Country')
                ->find($address['location_country']);
            $hasStates = $country && $country->hasStates();
            $state = ($address['location_state'] && $hasStates)
                ? Database::getRepo('XLite\Model\State')->findById($address['location_state'])
                : null;

            $result['Address1'] = $address['location_address'] ?? '';
            $result['Address2'] = $address['location_address2'] ?? '';
            $result['City'] = $address['location_city'] ?? '';
            $result['State'] = $state ? $state->getCode() : ($address['location_state'] ?? '');
            $parsePostalCode($address['location_zipcode'] ?? '');
        }
        // Handle checkout address
        elseif (is_array($address) && !empty($address['state_id'])) {
            $country = Database::getRepo('XLite\Model\Country')
                ->find($address['country_code']);
            $hasStates = $country && $country->hasStates();
            $state = ($address['state_id'] && $hasStates)
                ? Database::getRepo('XLite\Model\State')->findById($address['state_id'])
                : null;

            $result['Address1'] = $address['street'] ?? '';
            $result['Address2'] = $address['street2'] ?? '';
            $result['City'] = $address['city'] ?? '';
            $result['State'] = $state ? $state->getCode() : ($address['state_id'] ?? '');
            $parsePostalCode($address['zipcode'] ?? '');
        }

        return $result;
    }

    /**
     * Process address validation
     *
     * @param array $data    Raw data
     * @param mixed $address Address
     *
     * @return array
     */
    protected function processAddressValidation(array $data, $address)
    {
        $result = [];

        if (!isNull($data['ErrDescription'])) {
            $result[] = [
                null,
                'message' => $data['ErrDescription'],
            ];
        } else {
            $address = $this->assembleAddressSanitaizedData($address, $data);
        }

        return [$address, $result];
    }

    /**
     * Assemble address validation message
     *
     * @param array $message Raw message
     * @param mixed $address Address
     *
     * @return array
     */
    protected function assembleAddressValidationMessage(array $message, $address)
    {
        $result = null;

        if (is_array($address) && is_object(current($address))) {
            // Address from XLite\View\Model\Address\Address
            switch ($message['refersTo']) {
                case 'Address':
                case 'Address.Line0':
                    $names = ['street'];
                    break;

                case 'Address.City':
                    $names = ['city'];
                    break;

                case 'Address.Region':
                    $names = ['state_id', 'custom_state'];
                    break;

                case 'Address.Country':
                    $names = ['country_code'];
                    break;

                case 'Address.PostalCode':
                    $names = ['zipcode'];
                    break;

                default:
                    $names = [];
            }

            $field = null;
            foreach ($address as $f) {
                $parts = explode('_', $f->getName(), 2);
                foreach ($names as $name) {
                    if ($name == $parts[1] && $f->getValue()) {
                        $field = $f;
                        break 2;
                    }
                }
            }

            // Assemble message
            if ($field) {
                $result = [
                    'name' => $field->getName(),
                    'field' => $field,
                    'message' => $message['details'],
                ];
            }
        } elseif (is_array($address) && !empty($address['line1'])) {
            // Address from static::getStateTax()
            $parts = explode('.', $message['refersTo']);
            if ($parts[0] == 'address') {
                $result = [
                    'name' => $parts[1],
                    'message' => $message['details'],
                ];
            }
        } elseif (is_array($address) && !empty($address['location_country'])) {
            // Address from XLite\View\Model\Settings
            switch ($message['refersTo']) {
                case 'Address':
                case 'Address.Line0':
                    $name = 'location_address';
                    break;

                case 'Address.City':
                    $name = 'location_city';
                    break;

                case 'Address.Region':
                    $name = 'location_state';
                    break;

                case 'Address.Country':
                    $name = 'location_country';
                    break;

                case 'Address.PostalCode':
                    $name = 'location_zipcode';
                    break;

                default:
                    $name = null;
            }

            if ($name) {
                $result = [
                    'name' => $name,
                    'message' => $message['details'],
                ];
            }
        } elseif (is_array($address) && !empty($address['state_id'])) {
            // Address from XLite\Controller\Customer\Checkout
            switch ($message['refersTo']) {
                case 'Address':
                case 'Address.Line0':
                    $name = 'street';
                    break;

                case 'Address.City':
                    $name = 'city';
                    break;

                case 'Address.Region':
                    $name = 'state_id';
                    break;

                case 'Address.Country':
                    $name = 'country_code';
                    break;

                case 'Address.PostalCode':
                    $name = 'zipcode';
                    break;

                default:
                    $name = null;
            }

            if ($name) {
                $result = [
                    'name' => $name,
                    'message' => $message['details'],
                ];
            }
        }

        return $result;
    }

    /**
     * Assemble address sanitized data
     *
     * @param mixed $address Address
     * @param array $data    Sanitized address
     *
     * @return array
     */
    protected function assembleAddressSanitaizedData($address, array $data)
    {
        if (is_array($address) && is_object(current($address))) {
            $state = null;
            $oldStateValue = null;
            foreach ($data as $n => $value) {
                switch ($n) {
                    case 'Address1':
                        $name = 'street';
                        break;

                    case 'City':
                        $name = 'city';
                        break;

                    case 'State':
                        $name = 'state_id';
                        break;

                    case 'Zip5':
                    case 'Zip4':
                        $name = 'zipcode';
                        break;

                    default:
                        $name = null;
                }

                if ($name) {
                    foreach ($address as $f) {
                        $parts = explode('_', $f->getName(), 2);
                        if ($name == $parts[1]) {
                            if ($name === 'state_id') {
                                $state = $f;
                                $oldStateValue = $f->getValue();
                            }

                            // Concatenate ZIP5 and ZIP4
                            if ($name === 'zipcode' && isset($data['Zip5'], $data['Zip4'])) {
                                $value = $data['Zip5'] . '-' . $data['Zip4'];
                            }

                            $f->setValue($value);
                            break;
                        }
                    }
                }
            }

            if ($state) {
                $sid = $this->processAddressState(null, $state->getValue());
                if ($sid) {
                    $state->setValue($sid);
                } else {
                    $state->setValue($oldStateValue);
                }
            }
        } elseif (is_array($address) && !empty($address['Address1'])) {
            foreach ($data as $n => $value) {
                if (in_array($n, ['Zip5', 'Zip4']) && isset($data['Zip5'], $data['Zip4'])) {
                    $address['zipcode'] = $data['Zip5'] . '-' . $data['Zip4'];
                    continue;
                }

                if (isset($address[$n])) {
                    $address[$n] = $value;
                }
            }
        } elseif (is_array($address) && !empty($address['location_country'])) {
            foreach ($data as $n => $value) {
                switch ($n) {
                    case 'Address1':
                        $name = 'location_address';
                        break;

                    case 'City':
                        $name = 'location_city';
                        break;

                    case 'State':
                        $name = 'location_state';
                        break;

                    case 'Zip5':
                    case 'Zip4':
                        $name = 'location_zipcode';
                        break;

                    default:
                        $name = null;
                }

                if ($name) {
                    if ($name === 'location_zipcode' && isset($data['Zip5'], $data['Zip4'])) {
                        $value = $data['Zip5'] . '-' . $data['Zip4'];
                    }
                    $address[$name] = $value;
                }
            }

            $sid = $this->processAddressState($address['location_country'], $address['location_state']);
            if ($sid) {
                $address['location_state'] = $sid;
            } else {
                unset($address['location_state']);
            }
        } elseif (is_array($address) && !empty($address['State'])) {
            foreach ($data as $n => $value) {
                if (in_array($n, ['Zip5', 'Zip4']) && isset($data['Zip5'], $data['Zip4'])) {
                    $address['Zip'] = $data['Zip5'] . '-' . $data['Zip4'];
                    continue;
                }

                if (isset($address[$n])) {
                    $address[$n] = $value;
                }
            }

            $sid = $this->processAddressState(null, $address['State']);
            if ($sid) {
                $address['State'] = $sid;
            } else {
                unset($address['State']);
            }
        }

        return $address;
    }


    /**
     * Process address state
     *
     * @param string $countryCode Country code
     * @param string $stateCode   Country code
     *
     * @return integer
     */
    protected function processAddressState($countryCode, $stateCode)
    {
        $state = Database::getRepo('XLite\Model\State')->findOneByCountryAndCode($countryCode, $stateCode);

        return $state ? $state->getStateId() : null;
    }

    /**
     * Final calculation flag
     *
     * @var boolean
     */
    protected $finalCalculationFlag = false;

    /**
     * Get state tax
     *
     * @param \XLite\Model\Order $order Order
     *
     * @return array
     */
    public function getStateTax(\XLite\Model\Order $order)
    {
        $result = [false, []];

        $messages = [];
        $data = $this->getInformation($order, $messages);
        if ($data) {
            return $this->createLookupRequest($data) ?: $result;
        } else {
            $result[0] = $messages;
        }

        return $result;
    }

    /**
     * Authorize and Capture
     *
     * @param \XLite\Model\Order $order Order
     *
     * @return bool
     */
    public function AuthorizeAndCapture(\XLite\Model\Order $order)
    {
        $data = [
            'cartID' => (string) $order->getOrderId(),
            "orderID" => "{$order->getOrderNumber()}-{$order->getTaxCloudNumber()}",
            'customerID' => (string) $order->getOrigProfile()?->getProfileId(),
            "dateAuthorized" => date('Y-m-d'),
            "dateCaptured" => date('Y-m-d', $order->getDate()),
        ];

        [$result, $response] = $this->taxcloudRequest('AuthorizedWithCapture', $data);

        if ($response['ResponseType'] === 3) {
            return true;
        } else {
            $this->getLogger('TaxCloud')->error('AuthorizeAndCapture error:', [
                $data,
                $response
            ]);
        }

        return false;
    }

    /**
     * Set final calculation flag
     *
     * @param boolean $flag Flag
     *
     * @return void
     */
    public function setFinalCalculationFlag($flag)
    {
        $this->finalCalculationFlag = $flag;
    }

    /**
     * Process taxes
     *
     * @param array $data Data
     *
     * @return array
     */
    protected function processTaxes(array $data)
    {
        $errors = false;
        $result = [];

        if (!empty($data['Messages'])) {
            $errors = [];

            foreach ($data['Messages'] as $message) {
                $errors[] = $message['Message'];
            }

            $this->getLogger('TaxCloud')->error('', $errors);
        } elseif (!empty($data['CartItemsResponse'])) {
            foreach ($data['CartItemsResponse'] as $row) {
                if ($row['TaxAmount'] > 0) {
                    $result[$row['CartItemIndex']] = [
                        'cost' => $row['TaxAmount'],
                    ];
                }
            }
        }

        return [$errors, $result];
    }

    protected function getConfigCompany(\XLite\Model\Order $order)
    {
        return \XLite\Core\Config::getInstance()->Company;
    }

    /**
     * Get information
     *
     * @param \XLite\Model\Order $order     Order
     * @param array              &$messages Error messages
     *
     * @return array
     */
    protected function getInformation(\XLite\Model\Order $order, array &$messages)
    {
        $destination = $order->getProfile()->getShippingAddress();
        $currency = $order->getCurrency();
        $company = $this->getConfigCompany($order);
        $shippingCost = $order->getSurchargeSumByType(\XLite\Model\Base\Surcharge::TYPE_SHIPPING);

        $originalZip4 = (int) substr($company->origin_zipcode, 6, 4);
        $destinationZip4 = (int) substr($destination->getZipcode(), 6, 4);

        $post = [
            'cartID' => (string) $order->getOrderId(),
            'customerID' => (string) $order->getOrigProfile()?->getProfileId(),
            'deliveredBySeller' => false,
            'cartItems' => [],
            'origin' => [
                'Address1' => $company->origin_address,
                'Address2' => '',
                'City' => $company->origin_city,
                'State' => $company->originState ? $company->originState->getCode() : '',
                'Zip5' => (int) substr($company->origin_zipcode, 0, 5),
            ],
            'destination' => [
                'Address1' => $destination->getStreet(),
                'Address2' => $destination->getStreet2(),
                'City' => $destination->getCity(),
                'State' => $destination->getState() ? $destination->getState()->getCode() : '',
                'Zip5' => (int) substr($destination->getZipcode(), 0, 5),
            ],
        ];

        if ($originalZip4) {
            $post['origin']['Zip4'] = $originalZip4;
        }

        if ($destinationZip4) {
            $post['destination']['Zip4'] = $destinationZip4;
        }

        if($shippingCost) {
            $post['cartItems'][] = [
                'Index' => 0,
                'ItemID' => "Shipping",
                'Price' => $shippingCost,
                'Qty' => 1,
                'TIC' => "11000",
                'Tax' => 0.0,
            ];
        }

        foreach ($order->getItems() as $i => $item) {
            $total = (float) $item->getTotal();
            $amount = (int) $item->getAmount();
            $unitPrice = $amount > 0 ? $currency->roundValue($total / $amount) : 0.0;

            $post['cartItems'][] = [
                'Index' => $i + 1,
                'ItemID' => $item->getSku(),
                'Price' => $unitPrice,
                'Qty' => $amount,
                'TIC' => (int) $item->getProduct()->getTaxCloudCode(),
                'Tax' => 0.0,
            ];
        }

        return $post;
    }


    /**
     * Check - address verification is allowed or not
     *
     * @param mixed $address Address
     *
     * @return boolean
     */
    public function isAllowedAddressVerification($address)
    {
        $result = false;
        if (\XLite\Core\Config::getInstance()->Iidev->TaxCloud->addressverif) {
            $assembledAddress = $this->assembleAddressValidationRequest($address);

            $result = in_array($assembledAddress['country'], ['US', 'CA']);
        }

        return $result;
    }

    /**
     * Check - is it last tax calculation or not
     *
     * @return boolean
     */
    public function isLastTaxCalculation()
    {
        return $this->finalCalculationFlag;
    }

    protected function createLookupRequest(array $data): array
    {
        $result = [false, []];
        if ($data) {
            [$result, $taxes] = $this->taxcloudRequest('Lookup', $data);

            $result = $taxes ? $this->processTaxes($taxes) : [false, []];
        }

        return $result;
    }

    /**
     * Low level POST request
     */
    protected function taxcloudRequest(string $url, array $data): array
    {
        $config = \XLite\Core\Config::getInstance()->Iidev->TaxCloud;

        $data["apiLoginID"] = $config->api_login_id;
        $data["apiKey"] = $config->api_key;

        $fullURL = $this->baseURL . $url;
        $request = new \XLite\Core\HTTP\Request($fullURL);
        $request->body = json_encode($data);
        $request->verb = 'POST';
        $request->setHeader('Content-Type', 'application/json');

        $result = $request->sendRequest();

        $decodedResponse = $result && $result->body
            ? json_decode($result->body, true)
            : null;

        if ($config->debugmode) {
            $this->getLogger('TaxCloud')->error('API request', [
                'url' => $fullURL,
                'request' => $data,
                'response' => $decodedResponse,
            ]);
        }

        return [$result, $decodedResponse];
    }
}
