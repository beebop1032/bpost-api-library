<?php
namespace Bpost\BpostApiClient;

use Bpost\BpostApiClient\ApiCaller\ApiCaller;
use Psr\Log\LoggerInterface;
use Bpost\BpostApiClient\Bpost\CreateLabelInBulkForOrders;
use Bpost\BpostApiClient\Bpost\Labels;
use Bpost\BpostApiClient\Bpost\Order;
use Bpost\BpostApiClient\Bpost\Order\Box;
use Bpost\BpostApiClient\Bpost\Order\Box\Option\Insurance;
use Bpost\BpostApiClient\Bpost\ProductConfiguration;
use Bpost\BpostApiClient\Common\ValidatedValue\LabelFormat;
use Bpost\BpostApiClient\Exception\BpostApiResponseException\BpostCurlException;
use Bpost\BpostApiClient\Exception\BpostApiResponseException\BpostInvalidResponseException;
use Bpost\BpostApiClient\Exception\BpostApiResponseException\BpostInvalidSelectionException;
use Bpost\BpostApiClient\Exception\BpostApiResponseException\BpostInvalidXmlResponseException;
use Bpost\BpostApiClient\Exception\BpostLogicException\BpostInvalidValueException;
use Bpost\BpostApiClient\Exception\XmlException\BpostXmlInvalidItemException;

/**
 * Bpost class
 *
 * @author    Tijs Verkoyen <php-bpost@verkoyen.eu>
 * @version   3.0.0
 * @copyright Copyright (c), Tijs Verkoyen. All rights reserved.
 * @license   BSD License
 */
class Bpost
{
    const LABEL_FORMAT_A4 = 'A4';
    const LABEL_FORMAT_A6 = 'A6';

    // URL for the api
    const API_URL = 'https://api-parcel.bpost.be/services/shm';

    // current version
    const VERSION = '3.3.0';

    /** Min weight, in grams, for a shipping */
    const MIN_WEIGHT = 0;

    /** Max weight, in grams, for a shipping */
    const MAX_WEIGHT = 30000;

    /** @var ApiCaller */
    private $apiCaller;

    /**
     * The account id
     *
     * @var string
     */
    private $accountId;

    /**
     * A cURL instance
     *
     * @var resource
     */
    private $curl;

    /**
     * The passPhrase
     *
     * @var string
     */
    private $passPhrase;

    /**
     * The port to use.
     *
     * @var int
     */
    private $port;

    /**
     * The timeout
     *
     * @var int
     */
    private $timeOut = 30;

    /**
     * The user agent
     *
     * @var string
     */
    private $userAgent;

    private $apiUrl;

    /** @var  Logger */
    private $logger;

    // class methods
    /**
     * Create Bpost instance
     *
     * @param string $accountId
     * @param string $passPhrase
     * @param string $apiUrl
     */
    public function __construct($accountId, $passPhrase, $apiUrl = self::API_URL)
    {
        $this->accountId = (string)$accountId;
        $this->passPhrase = (string)$passPhrase;
        $this->apiUrl = (string)$apiUrl;
        $this->logger = new Logger();
    }

    /**
     * @return ApiCaller
     */
    public function getApiCaller()
    {
        if ($this->apiCaller === null) {
            $this->apiCaller = new ApiCaller($this->logger);
        }
        return $this->apiCaller;
    }

    /**
     * @param ApiCaller $apiCaller
     */
    public function setApiCaller(ApiCaller $apiCaller)
    {
        $this->apiCaller = $apiCaller;
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if ($this->curl !== null) {
            curl_close($this->curl);
            $this->curl = null;
        }
    }

    /**
     * Decode the response
     *
     * @param  \SimpleXMLElement $item   The item to decode.
     * @param  array             $return Just a placeholder.
     * @param  int               $i      A internal counter.
     * @return array
     * @throws BpostXmlInvalidItemException
     */
    private static function decodeResponse($item, $return = null, $i = 0)
    {
        if (!$item instanceof \SimpleXMLElement) {
            throw new BpostXmlInvalidItemException();
        }

        $arrayKeys = array(
            'barcode',
            'orderLine',
            Insurance::INSURANCE_TYPE_ADDITIONAL_INSURANCE,
            Box\Option\Messaging::MESSAGING_TYPE_INFO_DISTRIBUTED,
            'infoPugo'
        );
        $integerKeys = array('totalPrice');

        /** @var \SimpleXMLElement $value */
        foreach ($item as $key => $value) {
            $attributes = (array)$value->attributes();

            if (!empty($attributes) && isset($attributes['@attributes'])) {
                $return[$key]['@attributes'] = $attributes['@attributes'];
            }

            // empty
            if (isset($value['nil']) && (string)$value['nil'] === 'true') {
                $return[$key] = null;
            } // empty
            elseif (isset($value[0]) && (string)$value == '') {
                if (in_array($key, $arrayKeys)) {
                    $return[$key][] = self::decodeResponse($value);
                } else {
                    $return[$key] = self::decodeResponse($value, null, 1);
                }
            } else {
                // arrays
                if (in_array($key, $arrayKeys)) {
                    $return[$key][] = (string)$value;
                } // booleans
                elseif ((string)$value == 'true') {
                    $return[$key] = true;
                } elseif ((string)$value == 'false') {
                    $return[$key] = false;
                } // integers
                elseif (in_array($key, $integerKeys)) {
                    $return[$key] = (int)$value;
                } // fallback to string
                else {
                    $return[$key] = (string)$value;
                }
            }
        }

        return $return;
    }

    /**
     * Make the call
     *
     * @param  string $url       The URL to call.
     * @param  string $body      The data to pass.
     * @param  array  $headers   The headers to pass.
     * @param  string $method    The HTTP-method to use.
     * @param  bool   $expectXML Do we expect XML?
     * @return mixed
     * @throws BpostCurlException
     * @throws BpostInvalidResponseException
     * @throws BpostInvalidSelectionException
     * @throws BpostInvalidXmlResponseException
     */
    private function doCall($url, $body = null, $headers = array(), $method = 'GET', $expectXML = true)
    {
        // build Authorization header
        $headers[] = 'Authorization: Basic ' . $this->getAuthorizationHeader();

        // set options
        $options[CURLOPT_URL] = $this->apiUrl . '/' . $this->accountId . $url;
        if ($this->getPort() != 0) {
            $options[CURLOPT_PORT] = $this->getPort();
        }
        $options[CURLOPT_USERAGENT] = $this->getUserAgent();
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_TIMEOUT] = (int)$this->getTimeOut();
        $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        $options[CURLOPT_HTTPHEADER] = $headers;

        if ($method == 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        $this->getApiCaller()->doCall($options);

        $response = $this->getApiCaller()->getResponseBody();
        $httpCode = $this->getApiCaller()->getResponseHttpCode();
        $contentType = $this->getApiCaller()->getResponseContentType();

        // valid HTTP-code
        if (!in_array($httpCode, array(0, 200, 201))) {
            // convert into XML
            $xml = @simplexml_load_string($response);

            // validate
            if ($xml !== false && (substr($xml->getName(), 0, 7) == 'invalid')
            ) {
                // message
                $message = (string)$xml->error;
                $code = isset($xml->code) ? (int)$xml->code : null;

                // throw exception
                throw new BpostInvalidSelectionException($message, $code);
            }

            $message = '';
            if (
                ($contentType !== null && substr_count($contentType, 'text/plain') > 0) ||
                (in_array($httpCode, array(400, 404)))
            ) {
                $message = $response;
            }

            throw new BpostInvalidResponseException($message, $httpCode);
        }

        // if we don't expect XML we can return the content here
        if (!$expectXML) {
            return $response;
        }

        // convert into XML
        $xml = @simplexml_load_string($response);
        if ($xml === false) {
            throw new BpostInvalidXmlResponseException();
        }

        // return the response
        return $xml;
    }

    /**
     * Get the account id
     *
     * @return string
     */
    public function getAccountId()
    {
        return $this->accountId;
    }

    /**
     * Generate the secret string for the Authorization header
     *
     * @return string
     */
    private function getAuthorizationHeader()
    {
        return base64_encode($this->accountId . ':' . $this->passPhrase);
    }

    /**
     * Get the passPhrase
     *
     * @return string
     */
    public function getPassPhrase()
    {
        return $this->passPhrase;
    }

    /**
     * Get the port
     *
     * @return int
     */
    public function getPort()
    {
        return (int)$this->port;
    }

    /**
     * Get the timeout that will be used
     *
     * @return int
     */
    public function getTimeOut()
    {
        return (int)$this->timeOut;
    }

    /**
     * Get the useragent that will be used.
     * Our version will be prepended to yours.
     * It will look like: "PHP Bpost/<version> <your-user-agent>"
     *
     * @return string
     */
    public function getUserAgent()
    {
        return (string)'PHP Bpost/' . self::VERSION . ' ' . $this->userAgent;
    }

    /**
     * Set the timeout
     * After this time the request will stop. You should handle any errors triggered by this.
     *
     * @param int $seconds The timeout in seconds.
     */
    public function setTimeOut($seconds)
    {
        $this->timeOut = (int)$seconds;
    }

    /**
     * Set the user-agent for you application
     * It will be appended to ours, the result will look like: "PHP Bpost/<version> <your-user-agent>"
     *
     * @param string $userAgent Your user-agent, it should look like <app-name>/<app-version>.
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = (string)$userAgent;
    }

    // webservice methods
    // orders
    /**
     * Creates a new order. If an order with the same orderReference already exists
     *
     * @param  Order $order
     *
     * @return bool
     * @throws BpostCurlException
     * @throws BpostInvalidResponseException
     * @throws BpostInvalidSelectionException
     */
    public function createOrReplaceOrder(Order $order)
    {
        $url = '/orders';

        $document = new \DOMDocument('1.0', 'utf-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        $document->appendChild(
            $order->toXML(
                $document,
                $this->accountId
            )
        );

        $headers = array(
            'Content-type: application/vnd.bpost.shm-order-v3.3+XML'
        );

        return (
            $this->doCall(
                $url,
                $document->saveXML(),
                $headers,
                'POST',
                false
            ) == ''
        );
    }

    /**
     * Get List of Internationnal Pugo available near the shipping location
     * @param  string   $userLanguage The language of the client (the only two letter of it. e.g. : fr/en/de/nl/...
     * @param  string   $country      The country of the shipping Address (transform just below to take the first two letter)
     * @param  string   $streetName   The street name of the shipping Address (transform just below to replace space into +)
     * @param  int      $streetNumber The number of the house of the shipping Address
     * @param  int      $postalCode   The postal code of the shipping Address
     * @return SimpleXMLElement       Return the pugo point, if the pugo no more exist, return the first of the list
     */
    public function getPugoInformation($userLanguage, $country, $street, $streetNumber, $postalCode)
    {
        $country = substr($country, 0, 2);
        $streetName = str_replace(" ", "+", $street);

        $url = "http://pudo.bpost.be/Locator?Function=search".
                "&Partner=".$this->accountId.
                "&Language=".$userLanguage.
                "&Zone=".$postalCode.
                "&Country=".$country.
                "&Type=2";

        // build Authorization header
        $headers[] = 'Authorization: Basic ' . $this->getAuthorizationHeader();

        // set options
        $options[CURLOPT_URL] = $url;
        if ($this->getPort() != 0) {
            $options[CURLOPT_PORT] = $this->getPort();
        }
        $options[CURLOPT_USERAGENT] = $this->getUserAgent();
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_TIMEOUT] = (int)$this->getTimeOut();
        $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        $options[CURLOPT_HTTPHEADER] = $headers;

        $this->getApiCaller()->doCall($options);

        $response = $this->getApiCaller()->getResponseBody();

        $Pois = simplexml_load_string($response)->PoiList->Poi;

        $pugo = null;
        foreach ($Pois as $Poi) {
            if ((string) $Poi->Record->Street == $street && (string) $Poi->Record->Number == $streetNumber) {
                $pugo = $Poi->Record;
            }
        }
        if ($pugo == null) {
            $pugo = $Pois[0]->Record;
        }

        return $pugo;
    }

    /**
     * Fetch an order
     *
     * @param $reference
     *
     * @return Order
     * @throws BpostCurlException
     * @throws BpostInvalidResponseException
     * @throws BpostInvalidSelectionException
     * @throws Exception\XmlException\BpostXmlNoReferenceFoundException
     */
    public function fetchOrder($reference)
    {
        $url = '/orders/' . (string)$reference;

        $headers = array(
            'Accept: application/vnd.bpost.shm-order-v3.3+XML',
        );
        $xml = $this->doCall(
            $url,
            null,
            $headers
        );

        return Order::createFromXML($xml);
    }

    /**
     * Get the products configuration
     *
     * @return ProductConfiguration
     * @throws BpostCurlException
     * @throws BpostInvalidResponseException
     * @throws BpostInvalidSelectionException
     */
    public function fetchProductConfig()
    {
        $url = '/productconfig';

        $headers = array(
            'Accept: application/vnd.bpost.shm-productConfiguration-v3.1+XML',
        );
        /** @var \SimpleXMLElement $xml */
        $xml = $this->doCall(
            $url,
            null,
            $headers
        );

        return ProductConfiguration::createFromXML($xml);
    }

    /**
     * Modify the status for an order.
     *
     * @param  string $reference The reference for an order
     * @param  string $status    The new status, allowed values are: OPEN, PENDING, CANCELLED, COMPLETED, ON-HOLD or PRINTED
     *
     * @return bool
     * @throws BpostCurlException
     * @throws BpostInvalidResponseException
     * @throws BpostInvalidSelectionException
     * @throws BpostInvalidValueException
     */
    public function modifyOrderStatus($reference, $status)
    {
        $status = strtoupper($status);
        if (!in_array($status, Box::getPossibleStatusValues())) {
            throw new BpostInvalidValueException('status', $status, Box::getPossibleStatusValues());
        }

        $url = '/orders/' . $reference;

        $document = new \DOMDocument('1.0', 'utf-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        $orderUpdate = $document->createElement('orderUpdate');
        $orderUpdate->setAttribute('xmlns', 'http://schema.post.be/shm/deepintegration/v3/');
        $orderUpdate->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $orderUpdate->appendChild(
            $document->createElement('status', $status)
        );
        $document->appendChild($orderUpdate);

        $headers = array(
            'Content-type: application/vnd.bpost.shm-orderUpdate-v3+XML'
        );

        return (
            $this->doCall(
                $url,
                $document->saveXML(),
                $headers,
                'POST',
                false
            ) == ''
        );
    }

    // labels
    /**
     * Get the possible label formats
     *
     * @return array
     */
    public static function getPossibleLabelFormatValues()
    {
        return array(
            self::LABEL_FORMAT_A4,
            self::LABEL_FORMAT_A6,
        );
    }

    /**
     * Generic method to centralize handling of labels
     *
     * @param  string $url
     * @param  string $format
     * @param  bool   $withReturnLabels
     * @param  bool   $asPdf
     *
     * @return Bpost\Label[]
     * @throws BpostCurlException
     * @throws BpostInvalidResponseException
     * @throws BpostInvalidSelectionException
     * @throws BpostInvalidValueException
     */
    protected function getLabel($url, $format = self::LABEL_FORMAT_A6, $withReturnLabels = false, $asPdf = false)
    {
        $format = strtoupper($format);
        if (!in_array($format, self::getPossibleLabelFormatValues())) {
            throw new BpostInvalidValueException('format', $format, self::getPossibleLabelFormatValues());
        }

        $url .= '/labels/' . $format;
        if ($withReturnLabels) {
            $url .= '/withReturnLabels';
        }

        if ($asPdf) {
            $headers = array(
                'Accept: application/vnd.bpost.shm-label-pdf-v3.4+XML'
            );
        } else {
            $headers = array(
                'Accept: application/vnd.bpost.shm-label-image-v3.4+XML',
            );
        }

        $xml = $this->doCall(
            $url,
            null,
            $headers
        );

        return Labels::createFromXML($xml);
    }

    /**
     * Create the labels for all unprinted boxes in an order.
     * The service will return labels for all unprinted boxes for that order.
     * Boxes that were unprinted will get the status PRINTED, the boxes that
     * had already been printed will remain the same.
     *
     * @param  string $reference        The reference for an order
     * @param  string $format           The desired format, allowed values are: A4, A6
     * @param  bool   $withReturnLabels Should return labels be returned?
     * @param  bool   $asPdf            Should we retrieve the PDF-version instead of PNG
     *
     * @return Bpost\Label[]
     * @throws BpostInvalidValueException
     */
    public function createLabelForOrder(
        $reference,
        $format = self::LABEL_FORMAT_A6,
        $withReturnLabels = false,
        $asPdf = false
    ) {
        $url = '/orders/' . (string)$reference;

        return $this->getLabel($url, $format, $withReturnLabels, $asPdf);
    }

    /**
     * Create a label for a known barcode.
     *
     * @param  string $barcode          The barcode of the parcel
     * @param  string $format           The desired format, allowed values are: A4, A6
     * @param  bool   $withReturnLabels Should return labels be returned?
     * @param  bool   $asPdf            Should we retrieve the PDF-version instead of PNG
     *
     * @return Bpost\Label[]
     * @throws BpostInvalidValueException
     */
    public function createLabelForBox(
        $barcode,
        $format = self::LABEL_FORMAT_A6,
        $withReturnLabels = false,
        $asPdf = false
    ) {
        $url = '/boxes/' . (string)$barcode;

        return $this->getLabel($url, $format, $withReturnLabels, $asPdf);
    }

    /**
     * Create labels in bulk, according to the list of order references and the
     * list of barcodes. When there is an order reference specified in the
     * request, the service will return a label of every box of that order. If
     * a certain box was not yet printed, it will have the status PRINTED
     *
     * @param  array  $references       The references for the order
     * @param  string $format           The desired format, allowed values are: A4, A6
     * @param  bool   $withReturnLabels Should return labels be returned?
     * @param  bool   $asPdf            Should we retrieve the PDF-version instead of PNG
     * @param  bool   $forcePrinting    Reprint a already printed label
     *
     * @return Bpost\Label[]
     * @throws BpostCurlException
     * @throws BpostInvalidResponseException
     * @throws BpostInvalidSelectionException
     * @throws BpostInvalidValueException
     */
    public function createLabelInBulkForOrders(
        array $references,
        $format = LabelFormat::FORMAT_A6,
        $withReturnLabels = false,
        $asPdf = false,
        $forcePrinting = false
    ) {
        $createLabelInBulkForOrders = new CreateLabelInBulkForOrders();

        $xml = $this->doCall(
            $createLabelInBulkForOrders->getUrl(new LabelFormat($format), $withReturnLabels, $forcePrinting),
            $createLabelInBulkForOrders->getXml($references),
            $createLabelInBulkForOrders->getHeaders($asPdf),
            'POST'
        );

        return Labels::createFromXML($xml);
    }

    /**
     * Set a logger to permit to the plugin to log events
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger->setLogger($logger);
    }

    /**
     * @param int $weight in grams
     * @return bool
     */
    public function isValidWeight($weight)
    {
        return self::MIN_WEIGHT <= $weight && $weight <= self::MAX_WEIGHT;
    }
}
