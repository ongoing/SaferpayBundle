<?php

namespace Ongoing\Payment\SaferpayBundle\Client;


use GuzzleHttp\Psr7\Request;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use Ongoing\Payment\SaferpayBundle\Client\Authentication\AuthenticationStrategyInterface;
use Psr\Log\LoggerInterface;


/**
 * Client - inspeared by Payment\Saferpay\Saferpay class
 *
 * @package Valiton\Payment\SaferpayBundle\Client
 * @author Sven Cludius<sven.cludius@valiton.com>
 */
class Client
{
    public const PAY_INIT_PARAM_DATA = 'DATA';
    public const PAY_INIT_PARAM_SIGNATURE = 'SIGNATURE';

    public const VERIFY_PAY_PARAM_STATUS_OK = 'OK';
    public const VERIFY_PAY_PARAM_STATUS_ERROR = 'ERROR';

    public const PAY_CONFIRM_PARAM_ID = 'ID';
    public const PAY_CONFIRM_PARAM_AMOUNT = 'AMOUNT';
    public const PAY_CONFIRM_PARAM_ACTION = 'ACTION';

    public const ALIAS_DATA_KEY = 'creditcard_alias';

    /**
     * @var AuthenticationStrategyInterface
     */
    protected $authenticationStrategy;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SaferpayDataHelperInterface
     */
    protected $saferpayDataHelper;



    /**
     * Client constructor.
     * @param AuthenticationStrategyInterface $authenticationStrategy
     * @param SaferpayDataHelperInterface $saferpayDataHelper
     */
    public function __construct(AuthenticationStrategyInterface $authenticationStrategy,
                                SaferpayDataHelperInterface $saferpayDataHelper)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->saferpayDataHelper = $saferpayDataHelper;
    }

    /**
     * Create payment init
     *
     * @param array $payInitParameter
     * @param FinancialTransactionInterface $transaction
     * @return string
     */
    public function createPayInit(array $payInitParameter, FinancialTransactionInterface $transaction)
    {
        /** @var ExtendedDataInterface $data */
        $data = $transaction->getExtendedData();

        if ($data->has(self::ALIAS_DATA_KEY))
        {
            return $this->createTransactionInit(
                array_merge($payInitParameter, [self::ALIAS_DATA_KEY => $data->get(self::ALIAS_DATA_KEY)]),
                $transaction
            );
        } else {
            return $this->createPaymentPageInit($payInitParameter, $transaction);
        }
    }

    /**
     * @param array $config
     * @return array
     */
    public function createAliasInsert(array $config)
    {
        $data = $this->saferpayDataHelper->buildAliasInsertObject($config);
        $response = $this->sendApiRequest($this->saferpayDataHelper->getAliasInsertUrl(), $data);

        return $this->saferpayDataHelper->getDataFromResponse($response);
    }

    /**
     * @param $token
     * @return array
     */
    public function createAliasAssertInsert($token)
    {
        $data = $this->saferpayDataHelper->buildAliasAssertInsertObject(array('token' => $token));
        $response = $this->sendApiRequest($this->saferpayDataHelper->getAliasAssertInsertUrl(), $data);

        return $this->saferpayDataHelper->getDataFromResponse($response);
    }

    /**
     * @param array $payInitParameter
     * @param FinancialTransactionInterface $transaction
     * @return mixed
     */
    protected function createTransactionInit(array $payInitParameter, FinancialTransactionInterface $transaction)
    {
        $requestData = $this->saferpayDataHelper->buildTransactionInitObject($payInitParameter);

        $response = $this->sendApiRequest($this->saferpayDataHelper->getTransactionInitUrl(), $requestData);
        $responseData = $this->saferpayDataHelper->getDataFromResponse($response);

        // use field TrackingId to keep track of the returned Token
        $transaction->setTrackingId($responseData['Token']);

        return $responseData['Redirect']['RedirectUrl'];
    }

    /**
     * @param array $payInitParameter
     * @param FinancialTransactionInterface $transaction
     * @return mixed
     */
    protected function createPaymentPageInit(array $payInitParameter, FinancialTransactionInterface $transaction)
    {
        $requestData = $this->saferpayDataHelper->buildPaymentPageInitObject($payInitParameter);

        $response = $this->sendApiRequest($this->saferpayDataHelper->getPaymentPageInitUrl(), $requestData);
        $responseData = $this->saferpayDataHelper->getDataFromResponse($response);

        // use field TrackingId to keep track of the returned Token
        $transaction->setTrackingId($responseData['Token']);

        return $responseData['RedirectUrl'];
    }

    /**
     * @param array $parameter
     * @param FinancialTransactionInterface $transaction
     * @return mixed
     */
    public function createAuthorizeDirect(array $parameter, FinancialTransactionInterface $transaction)
    {
        $payConfirmParameter = [];
        $requestData = $this->saferpayDataHelper->buildTransactionAuthorizeDirectObject($parameter);

        $response = $this->sendApiRequest($this->saferpayDataHelper->getTransactionAuthorizeDirectUrl(), $requestData);
        $responseData = $this->saferpayDataHelper->getDataFromResponse($response);

        $payConfirmParameter['id'] = $responseData['Transaction']['Id'];
        $payConfirmParameter['amount'] = $responseData['Transaction']['Amount']['Value'];
        $payConfirmParameter['currency'] = $responseData['Transaction']['Amount']['CurrencyCode'];
        $payConfirmParameter['token'] = $transaction->getTrackingId();

        return $payConfirmParameter;
    }

    /**
     * Verify payment confirm
     *
     * @param FinancialTransactionInterface $transaction
     * @param array $payConfirmParameter
     * @return array
     */
    public function verifyPayConfirm(FinancialTransactionInterface $transaction, array $payConfirmParameter = null)
    {
        $requestData = $this->saferpayDataHelper->buildPaymentPageAssertObject($transaction->getTrackingId());

        $cofirmUrl = $transaction->getExtendedData()->has(self::ALIAS_DATA_KEY) ?
            $this->saferpayDataHelper->getTransactionAuthorizeUrl() : $this->saferpayDataHelper->getPaymentPageAssertUrl();

        $response = $this->sendApiRequest($cofirmUrl, $requestData);

        $responseData = $this->saferpayDataHelper->getDataFromResponse($response);

        if (null == $payConfirmParameter) {
            $payConfirmParameter = array();
        }

        $payConfirmParameter['id'] = $responseData['Transaction']['Id'];
        $payConfirmParameter['amount'] = $responseData['Transaction']['Amount']['Value'];
        $payConfirmParameter['currency'] = $responseData['Transaction']['Amount']['CurrencyCode'];
        $payConfirmParameter['token'] = $transaction->getTrackingId();

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['Brand'])
            && isset($responseData['PaymentMeans']['Brand']['PaymentMethod'])
        ) {
            $payConfirmParameter['cardbrand'] = $responseData['PaymentMeans']['Brand']['PaymentMethod'];
        }

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['DisplayText'])
        ) {
            $payConfirmParameter['cardmask'] = $responseData['PaymentMeans']['DisplayText'];
        }

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['Card'])
            && isset($responseData['PaymentMeans']['Card']['ExpYear'])
        ) {
            $payConfirmParameter['cardvalidyear'] = $responseData['PaymentMeans']['Card']['ExpYear'];
        }

        if (isset($responseData['PaymentMeans'])
            && isset($responseData['PaymentMeans']['Card'])
            && isset($responseData['PaymentMeans']['Card']['ExpMonth'])
        ) {
            $payConfirmParameter['cardvalidmonth'] = $responseData['PaymentMeans']['Card']['ExpMonth'];
        }

        if (isset($responseData['RegistrationResult'])
            && isset($responseData['RegistrationResult']['Success'])
            && $responseData['RegistrationResult']['Success']
            && isset($responseData['RegistrationResult']['Alias'])
            && isset($responseData['RegistrationResult']['Alias']['Id'])
        ) {
            $payConfirmParameter['cardrefid'] = $responseData['RegistrationResult']['Alias']['Id'];
        }

        return $payConfirmParameter;
    }

    /**
     * Pay complete v2
     *
     * @param  array            $payConfirmParameter
     * @param  array            $payCompleteParameter
     * @param  array            $payCompleteResponse
     * @return array
     * @throws \Exception
     */
    public function payCompleteV2(
        array $payConfirmParameter,
        array $payCompleteParameter = null,
        array $payCompleteResponse = null
    ) {
        if (!isset($payConfirmParameter['id'])) {
            $this->getLogger()->critical('Saferpay: call confirm before complete!');
            throw new \Exception('Saferpay: call confirm before complete!');
        }

        if (null == $payCompleteParameter) {
            $payCompleteParameter = array();
        }
        $payCompleteParameter['id'] = $payConfirmParameter['id'];
        $payCompleteParameter['amount'] = $payConfirmParameter['amount'];

        $requestData = $this->saferpayDataHelper->buildTransactionCaptureObject($payCompleteParameter['id']);

        $response = $this->sendApiRequest($this->saferpayDataHelper->getTransactionCaptureUrl(), $requestData);

        if (null == $payCompleteResponse) {
            $payCompleteResponse = array();
        }

        $payCompleteResponse['result'] = $response->getStatusCode();

        return $payCompleteResponse;
    }

    /**
     * @param $url
     * @param $data
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendApiRequest($url, $data)
    {
        $this->getLogger()->debug($url);
        $this->getLogger()->debug($data);

        $client = new \GuzzleHttp\Client();

        $request = new Request('POST', $url, $this->saferpayDataHelper->getNecessaryRequestHeaders(), $data);

        $response = $this->authenticationStrategy->sendAuthenticated($client, $request);

        $this->getLogger()->debug((string) $response->getBody());

        if ($response->getStatusCode() != 200) {
            $errorInfo = $this->saferpayDataHelper->tryGetErrorInfoFromResponse($response);
            $this->getLogger()->critical('Saferpay: request failed with statuscode: ' . $response->getStatusCode() . '! ' . $errorInfo);
            throw new \Exception('Saferpay: request failed with statuscode: ' . $response->getStatusCode() . '! ' . $errorInfo);
        }

        return $response;
    }

    /**
     * get logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * set logger
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

}
