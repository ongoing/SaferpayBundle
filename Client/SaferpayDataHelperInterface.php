<?php

namespace Ongoing\Payment\SaferpayBundle\Client;

use GuzzleHttp\Psr7\Response;

interface SaferpayDataHelperInterface
{
    /**
     * @param array $data
     * @return string
     */
    public function buildPaymentPageInitObject(array $data);

    /**
     * @param array $data
     * @return mixed
     */
    public function buildTransactionInitObject(array $data);

    /**
     * @param array $data
     * @return mixed
     */
    public function buildTransactionAuthorizeDirectObject(array $data);

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
     * @param array $config
     * @return string
     */
    public function buildAliasInsertObject(array $config);

    /**
     * @param array $config
     * @return string
     */
    public function buildAliasAssertInsertObject(array $config);

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
     * @return string
     */
    public function getAliasAssertInsertUrl();

    /**
     * @return string
     */
    public function getAliasInsertUrl();

    /**
     * @return array
     */
    public function getNecessaryRequestHeaders();
}