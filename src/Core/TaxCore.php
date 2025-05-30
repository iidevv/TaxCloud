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
        if (!$transaction->isFullRefund()) {
            \XLite\Core\TopMessage::addWarning(
                'Recalculate the Order Subtotal with the updated amount for accurate tax reporting.'
            );
        }

        $this->voidTransactionRequest($order);
    }

    public function voidTransactionRequest(\Iidev\TaxCloud\Model\Order $order)
    {
        if (!$order->getTaxCloudImported()) {
            return false;
        }

        $data = [
            "orderID" => "{$order->getOrderNumber()}-{$order->getTaxCloudNumber()}",
            "returnedDate" => date('Y-m-d'),
        ];

        [$result, $response] = $this->taxcloudRequest(
            "Returned",
            $data
        );

        if ($response['ResponseType'] === 3) {
            return true;
        } else if (!empty($response['Messages'])) {
            foreach ($response['Messages'] as $message) {
                \XLite\Core\TopMessage::addError("TaxCloud. {$message['Message']}");
            }
        } else {
            $this->getLogger('TaxCloud')->error('voidTransactionRequest error:', [
                $data,
                $response
            ]);
        }

        return false;
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
                $result['Zip5'] = $postalParts[0];
                $result['Zip4'] = isset($postalParts[1]) ? $postalParts[1] : null;
            }
        };

        if (is_object($address) && $address instanceof \XLite\Model\Address) {
            $result = [
                'Address1' => $address->getStreet() ?: '',
                'Address2' => $address->getStreet2() ?: '',
                'City' => $address->getCity() ?: '',
                'State' => $address->getState() ? $address->getState()->getCode() : '',
            ];

            $parsePostalCode($address->getZipcode() ?? '');
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

        if ($data['ErrDescription']) {
            $result[] = [
                'message' => $this->processErrDescription($data['ErrDescription']),
            ];
        } else {
            $address = $this->assembleAddressSanitaizedData($address, $data);
        }

        if (!$data['rdi'] && \XLite::isAdminZone()) {
            $result[] = [
                'message' => 'Address type is not specified.',
            ];
        }

        return [$address, $result];
    }

    private function processErrDescription($message)
    {
        $result = $message;

        switch ($message) {
            default:
                $result = 'Please verify your shipping address.';

                break;
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
        if (is_object($address) && $address instanceof \XLite\Model\Address) {
            $address->setZipcode(
                isset($data['Zip5'], $data['Zip4'])
                ? $data['Zip5'] . '-' . $data['Zip4']
                : $data['Zip5'] ?? ''
            );

            if ($data['rdi'] && $data['rdi'] === 'Commercial') {
                $address->setAddressType('C');
            } elseif ($data['rdi'] && $data['rdi'] === 'Residential') {
                $address->setAddressType('R');
            }
        } elseif (is_array($address) && !empty($address['street'])) {
            foreach ($data as $n => $value) {
                if ($data['rdi'] && $data['rdi'] === 'Commercial') {
                    $address['type'] = 'C';
                } elseif ($data['rdi'] && $data['rdi'] === 'Residential') {
                    $address['type'] = 'R';
                }

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
            'cartID' => (string) $order->getOrderNumber() ?: (string) $order->getOrderId(),
            "orderID" => "{$order->getOrderNumber()}-{$order->getTaxCloudNumber()}",
            'customerID' => $this->getUserId($order->getProfile()?->getLogin() ?: ''),
            "dateAuthorized" => date('Y-m-d'),
            "dateCaptured" => date('Y-m-d', $order->getDate()),
        ];

        [$result, $response] = $this->taxcloudRequest('AuthorizedWithCapture', $data);

        if ($response['ResponseType'] === 3) {
            \XLite\Core\TopMessage::addInfo(
                'TaxCloud. The Order was successfully authorized and captured.'
            );

            return true;
        } else if (!empty($response['Messages'])) {
            foreach ($response['Messages'] as $message) {
                \XLite\Core\TopMessage::addError("TaxCloud. {$message['Message']}");
            }
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

            $this->getLogger('TaxCloud')->error('processTaxes error:', $data);
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
        $destination = $order->isShippable() && !$order->getProfile()->isSameAddress() && $order->getProfile()->getShippingAddress()
            ? $order->getProfile()->getShippingAddress()
            : $order->getProfile()->getBillingAddress();
        if (!$destination) {
            $messages[] = 'The destination address is not set.';
            return [];
        }

        $currency = $order->getCurrency();
        $company = $this->getConfigCompany($order);
        $shippingCost = $order->getSurchargeSumByType(\XLite\Model\Base\Surcharge::TYPE_SHIPPING);
        $certificateId = $order->getProfile()?->getTaxCloudCertificateId();
        $originalZip4 = substr($company->origin_zipcode, 6, 4);
        $destinationZip4 = substr($destination->getZipcode(), 6, 4);

        $post = [
            'cartID' => (string) $order->getOrderNumber() ?: (string) $order->getOrderId(),
            'customerID' => $this->getUserId($order->getProfile()?->getLogin() ?: ''),
            'deliveredBySeller' => false,
            'cartItems' => [],
            'origin' => [
                'Address1' => $company->origin_address,
                'Address2' => '',
                'City' => $company->origin_city,
                'State' => $company->originState ? $company->originState->getCode() : '',
                'Zip5' => substr($company->origin_zipcode, 0, 5),
            ],
            'destination' => [
                'Address1' => $destination->getStreet(),
                'Address2' => $destination->getStreet2(),
                'City' => $destination->getCity(),
                'State' => $destination->getState() ? $destination->getState()->getCode() : '',
                'Zip5' => substr($destination->getZipcode(), 0, 5),
            ],
        ];

        if ($certificateId) {
            $post['CertificateID'] = $certificateId;
        }

        if ($originalZip4) {
            $post['origin']['Zip4'] = $originalZip4;
        }

        if ($destinationZip4) {
            $post['destination']['Zip4'] = $destinationZip4;
        }

        $shippingTic = \XLite\Core\Config::getInstance()->Iidev->TaxCloud->shipping_tic;

        if ($shippingCost && $shippingTic) {
            $post['cartItems'][] = [
                'Index' => 0,
                'ItemID' => "Shipping",
                'Price' => $shippingCost,
                'Qty' => 1,
                'TIC' => $shippingTic,
                'Tax' => 0.0,
            ];
        }

        $post['cartItems'] = array_merge($post['cartItems'], $this->getItems($order, $currency));

        return $post;
    }

    /**
     * Get cart items with distributed discount
     *
     * @param \XLite\Model\Order $order
     * @param \XLite\Model\Currency $currency
     * @return array
     */
    protected function getItems(\XLite\Model\Order $order, \XLite\Model\Currency $currency)
    {
        $items = [];

        $totalDiscount = $this->getTotalDiscount($order);

        $subtotal = $order->getSubtotal();

        foreach ($order->getItems() as $i => $item) {
            $itemTotal = (float) $item->getTotal();
            $amount = (int) $item->getAmount();
            $discountPercent = $subtotal > 0 ? $itemTotal / $subtotal * 100 : 0.0;
            $itemDiscount = $currency->roundValue($totalDiscount / 100 * $discountPercent);
            $unitPrice = $amount > 0 ? $currency->roundValue($itemTotal / $amount) : 0.0;

            $discountedUnitPrice = max(0.0, $unitPrice - ($itemDiscount / $amount));
            
            $items[] = [
                'Index' => $i + 1,
                'ItemID' => $item->getSku(),
                'Price' => $currency->roundValue($discountedUnitPrice),
                'Qty' => $amount,
                'TIC' => $this->getItemTic($item),
            ];
        }

        return $items;
    }

    private function getTotalDiscount(\XLite\Model\Order $order)
    {
        $totalDiscount = 0.0;

        $totalDiscount += abs($order->getSurchargeSumByType(\XLite\Model\Base\Surcharge::TYPE_DISCOUNT) ?: 0.0);

        if (!\XLite::isAdminZone()) {
            $rewardPoints = Database::getRepo(\XLite\Model\Order\Surcharge::class)
                ->findOneBy([
                    'owner' => $order,
                    'code' => 'REWARDPOINTS',
                ]);

            if ($rewardPoints) {
                $totalDiscount += abs($rewardPoints->getValue());
            }
        }

        $this->getLogger('totalDiscount')->error('', [
            'orderId' => $order->getOrderId(),
            'totalDiscount' => $totalDiscount,
            'rewardPoints' => $rewardPoints ? $rewardPoints->getValue() : null,
        ]);

        return $totalDiscount;
    }

    private function getItemTic($item)
    {
        $tic = $item->getProduct()->getTaxCloudCode() ?: \XLite\Core\Config::getInstance()->Iidev->TaxCloud->default_tic;

        if (!$tic) {
            $tic = 0;
        }

        return $tic;
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
        $country = null;

        if (is_object($address)) {
            $country = $address->getCountry() ? $address->getCountry()->getCode() : null;
        } elseif (is_array($address)) {
            $country = $address['country_code'] ?? $address['location_country'];
        } else {
            return false;
        }

        if (\XLite\Core\Config::getInstance()->Iidev->TaxCloud->addressverif) {
            return in_array($country, ['US']);
        }

        return false;
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

    /**
     * Get user ID
     *
     * @param string $login Login
     *
     * @return string
     */
    public function getUserId($login)
    {
        return (string) md5(strtolower($login));
    }
}
