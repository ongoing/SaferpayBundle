<?php
/**
 * Created by PhpStorm.
 * User: d437861
 * Date: 10.11.16
 * Time: 13:59
 */

namespace Ongoing\Payment\SaferpayBundle\Client;


use Ongoing\Payment\SaferpayBundle\Client\Authentication\JsonAuthenticationStrategy;
use Guzzle\Http\Message\Response;
use Faker\Provider\Uuid;

/**
 * Class SaferpayJsonObjHelper
 * Builds JSON encoded arrays according to http://saferpay.github.io/jsonapi/
 *
 * @package Valiton\Payment\SaferpayBundle\Client
 */
class SaferpayJsonObjHelper implements SaferpayDataHelperInterface
{
    const SPEC_VERSION = '1.4';
    const RETRY_INDICATOR = 0;

    /**
     * @var JsonAuthenticationStrategy
     */
    protected $authenticationStrategy;

    /**
     * @var string
     */
    protected $contentTypeHeader;

    /**
     * @var string
     */
    protected $acceptHeader;

    /**
     * @var array
     */
    private $paymentUrls;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * SaferpayJsonObjHelper constructor.
     * @param JsonAuthenticationStrategy $authenticationStrategy
     * @param string $baseUrl
     * @param array $paymentUrls
     * @param string $contentTypeHeader
     * @param string $acceptHeader
     */
    function __construct(JsonAuthenticationStrategy $authenticationStrategy,
                         $baseUrl,
                         $paymentUrls,
                         $contentTypeHeader,
                         $acceptHeader)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->paymentUrls = $paymentUrls;
        $this->baseUrl = $baseUrl;
        $this->contentTypeHeader = $contentTypeHeader;
        $this->acceptHeader = $acceptHeader;
    }

    /**
     * @param array $data
     * @return string
     */
    public function buildPayInitObj(array $data)
    {
        return $this->buildPaymentPageInitializeObj($data);
    }

    /**
     * @param string $token
     * @return string
     */
    public function buildPayConfirmObj($token)
    {
        return $this->buildPaymentPageAssertObj($token);
    }

    /**
     * @param string $transactionId
     * @return string
     */
    public function buildPayCompleteObj($transactionId)
    {
        return $this->buildTransactionCaptureObj($transactionId);
    }

    /**
     * @param Response $response
     * @return array
     */
    public function getDataFromResponse(Response $response)
    {
        return json_decode($response->getBody(), true);
    }

    /**
     * @param Response $response
     * @return string
     */
    public function tryGetErrorInfoFromResponse(Response $response)
    {
        $errorInfo = "";
        if (strtolower($response->getContentType()) == strtolower($this->contentTypeHeader))
        {
            $responseData = $this->getDataFromResponse($response);
            $errorInfo = 'ErrorName: ' . $responseData['ErrorName'] . ' ErrorMessage: ' . $responseData['ErrorMessage'];
            if (array_key_exists('ErrorDetail', $responseData))
            {
                $errorInfo .= ' ErrorDetail:';
                foreach ($responseData['ErrorDetail'] as $detail)
                {
                    $errorInfo .= ' ' . $detail;
                }
            }
        }
        return $errorInfo;
    }

    /**
     * @return array
     */
    public function getNecessaryRequestHeaders()
    {
        return array(
            'Content-Type' => $this->contentTypeHeader,
            'Accept' => $this->acceptHeader
        );
    }

    /**
     * @param array $data
     * @return string
     */
    protected function  buildPaymentPageInitializeObj(array $data)
    {
        $jsonData = array(
            'RequestHeader' => $this->buildRequestHeader(),

            'TerminalId' => $this->authenticationStrategy->getTerminalId(),

            'Payment' => array(
                'Amount' => array(
                    'Value' => $data['amount'],
                    'CurrencyCode' => $data['currency']
                ),

                'OrderId' => $data['orderid'], // optional
                'Description' => $data['description']
            ),

            'ReturnUrls' => array(
                'Success' => $data['successlink'],
                'Fail' => $data['faillink'],
                'Abort' => $data['backlink'] // optional
            )
        );

        if (isset($data['cardrefid'])) {
            switch ($data['cardrefid']){
                case 'new':
                    $jsonData['RegisterAlias'] = array('IdGenerator' => 'RANDOM');
                    break;
                case 'random_unique':
                    $jsonData['RegisterAlias'] = array('IdGenerator' => 'RANDOM_UNIQUE');
                    break;
                default:
                    $jsonData['RegisterAlias'] = array('IdGenerator' => 'MANUAL', 'Id' => $data['cardrefid']);
            }
        }

        return json_encode($jsonData);
    }

    /**
     * @param string $token
     * @return string
     */
    protected function buildPaymentPageAssertObj($token)
    {
        $jsonData = json_encode(array(
            'RequestHeader' => $this->buildRequestHeader(),
            'Token' => $token
        ));

        return $jsonData;
    }

    /**
     * @param string $transactionId
     * @return string
     */
    protected function buildTransactionCaptureObj($transactionId)
    {
        $jsonData = json_encode(array(
            'RequestHeader' => $this->buildRequestHeader(),
            "TransactionReference" => array(
                // user either TransactionId or OrderId to reference the transaction
                'TransactionId' => $transactionId
            )
        ));

        return $jsonData;
    }

    /**
     * @return array
     */
    protected function buildRequestHeader()
    {
        return array(
            'SpecVersion' => self::SPEC_VERSION,
            'CustomerId' => $this->authenticationStrategy->getCustomerId(),
            'RequestId' => Uuid::uuid(),
            'RetryIndicator' => self::RETRY_INDICATOR
        );
    }

    /**
     * @return string
     */
    public function getTransactionInitUrl()
    {
        if (isset($this->paymentUrls['transaction']['initialize'])){
            return $this->getApiUrl($this->paymentUrls['transaction']['initialize']);
        }
    }

    /**
     * @return string
     */
    public function getTransactionAuthorizeUrl()
    {
        if (isset($this->paymentUrls['transaction']['authorize'])){
            return $this->getApiUrl($this->paymentUrls['transaction']['authorize']);
        }
    }

    /**
     * @return string
     */
    public function getTransactionAuthorizeDirectUrl()
    {
        if (isset($this->paymentUrls['transaction']['authorize_direct'])){
            return $this->getApiUrl($this->paymentUrls['transaction']['authorize_direct']);
        }
    }

    /**
     * @return string
     */
    public function getTransactionCaptureUrl()
    {
        if (isset($this->paymentUrls['transaction']['capture'])){
            return $this->getApiUrl($this->paymentUrls['transaction']['capture']);
        }
    }

    /**
     * @return string
     */
    public function getPaymentPageInitUrl()
    {
        if (isset($this->paymentUrls['payment_page']['initialize'])){
            return $this->getApiUrl($this->paymentUrls['payment_page']['initialize']);
        }
    }

    /**
     * @return string
     */
    public function getPaymentPageAuthorizeUrl()
    {
        if (isset($this->paymentUrls['payment_page']['assert'])){
            return $this->getApiUrl($this->paymentUrls['payment_page']['assert']);
        }
    }

    /**
     * @param $path
     * @return string
     */
    protected function getApiUrl($path)
    {
        return $this->baseUrl.$path;
    }
}
