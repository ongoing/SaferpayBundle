<?php

namespace Ongoing\Payment\SaferpayBundle\Client;

use Guzzle\Http\Message\Response;

interface SaferpayDataHelperInterface
{
    /**
     * @param array $data
     * @return string
     */
    public function buildPaymentPageInitObject(array $data);

    /**
     * @param string $token
     * @return string
     */
    public function buildPaymentPageAssertObject($token);

    /**
     * @param string $transactionId
     * @return string
     */
    public function buildTransactionCaptureObject($transactionId);

    /**
     * @param Response $response
     * @return array
     */
    public function getDataFromResponse(Response $response);

    /**
     * @param Response $response
     * @return string
     */
    public function tryGetErrorInfoFromResponse(Response $response);
    
    /**
     * @return string
     */
    public function getTransactionInitUrl();

    /**
     * @return string
     */
    public function getTransactionAuthorizeUrl();

    /**
     * @return string
     */
    public function getTransactionAuthorizeDirectUrl();

    /**
     * @return string
     */
    public function getTransactionCaptureUrl();

    /**
     * @return string
     */
    public function getPaymentPageInitUrl();

    /**
     * @return string
     */
    public function getPaymentPageAssertUrl();

    /**
     * @return array
     */
    public function getNecessaryRequestHeaders();
}