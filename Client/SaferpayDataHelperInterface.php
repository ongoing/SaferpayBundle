<?php

namespace Ongoing\Payment\SaferpayBundle\Client;

use Guzzle\Http\Message\Response;

interface SaferpayDataHelperInterface
{
    /**
     * @param array $data
     * @return string
     */
    public function buildPayInitObj(array $data);

    /**
     * @param string $token
     * @return string
     */
    public function buildPayConfirmObj($token);

    /**
     * @param string $transactionId
     * @return string
     */
    public function buildPayCompleteObj($transactionId);

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
    public function getPaymentPageAuthorizeUrl();

    /**
     * @return array
     */
    public function getNecessaryRequestHeaders();
}