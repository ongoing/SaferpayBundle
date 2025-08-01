<?php

namespace Ongoing\Payment\SaferpayBundle\Plugin;

use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Util\SecureRandom;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\ErrorBuilder;
use Ongoing\Payment\SaferpayBundle\Client\Client;
use Ongoing\Payment\SaferpayBundle\Utils\SaferpayFormatHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * SaferpayPlugin
 *
 * @package Valiton\Payment\SaferpayBundle\Plugin
 * @author Sven Cludius<sven.cludius@valiton.com>
 */
class SaferpayPlugin extends AbstractPlugin
{
    public const PAYMENT_SYSTEM_NAME = 'saferpay_checkout';
    public const PAYMENT_SYSTEM_NAME_CARDS = 'saferpay_checkout_cards';
    public const PAYMENT_SYSTEM_NAME_TWINT = 'saferpay_checkout_twint';
    public const PAYMENT_SYSTEM_NAME_POSTFINANCEPAY = 'saferpay_checkout_postfinancepay';

    public const SIGNS = '0123456789abcdefghijklmnopqrstuvwxyz';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $returnUrl;

    /**
     * @var string
     */
    protected $errorUrl;

    /**
     * @var string
     */
    protected $cancelUrl;

    /**
     * @var string
     */
    protected $cardrefid;

    /**
     * @var string|null
     */
    protected $cardrefidPrefix;

    /**
     * @var int
     */
    protected $cardrefidLength;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var bool
     */
    private $authorizeDirect = false;

    /**
     * Constructor
     *
     * @param Client $client
     * @param string $returnUrl
     * @param string $errorUrl
     * @param string $cancelUrl
     * @param $cardrefid
     * @param $cardrefidPrefix
     * @param $cardrefidLength
     * @param $authorizeDirect
     */
    public function __construct(Client $client, $returnUrl, $errorUrl, $cancelUrl, $cardrefid, $cardrefidPrefix, $cardrefidLength, $authorizeDirect)
    {
        $this->client = $client;
        $this->returnUrl = $returnUrl;
        $this->errorUrl = $errorUrl;
        $this->cancelUrl = $cancelUrl;
        $this->cardrefid = $cardrefid;
        $this->cardrefidPrefix = $cardrefidPrefix;
        $this->cardrefidLength = min(40, $cardrefidLength);
        $this->authorizeDirect = $authorizeDirect;
    }

    /**
     * Whether this plugin can process payments for the given payment system.
     *
     * A plugin may support multiple payment systems. In these cases, the requested
     * payment system for a specific transaction  can be determined by looking at
     * the PaymentInstruction which will always be accessible either directly, or
     * indirectly.
     *
     * @param string $paymentSystemName
     * @return boolean
     */
    public function processes($paymentSystemName)
    {
        return in_array($paymentSystemName, [
            self::PAYMENT_SYSTEM_NAME,
            self::PAYMENT_SYSTEM_NAME_CARDS,
            self::PAYMENT_SYSTEM_NAME_TWINT,
            self::PAYMENT_SYSTEM_NAME_POSTFINANCEPAY,
        ]);
    }

    public function checkPaymentInstruction(PaymentInstructionInterface $instruction)
    {
        $errorBuilder = new ErrorBuilder();
        $data = $instruction->getExtendedData();

        if ($errorBuilder->hasErrors()) {
            throw $errorBuilder->getException();
        }
    }

    /**
     * Approve
     *
     * @param FinancialTransactionInterface $transaction
     * @param bool $retry
     * @throws ActionRequiredException
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        $payInitParameter = $this->createPayInitParameter($transaction);

        //check for alias and if an authorize direct should be done (no 2nd secure like 3ds)
        if ($transaction->getExtendedData()->has(Client::ALIAS_DATA_KEY) && $this->authorizeDirect && $transaction->getResponseCode() != PluginInterface::RESPONSE_CODE_SUCCESS){

            try {
                //do direct authorization
                $payInitParameter['alias'] = $transaction->getExtendedData()->get(Client::ALIAS_DATA_KEY);
                $payConfirmParameter = $this->client->createAuthorizeDirect($payInitParameter, $transaction);
                $this->throwUnlessValidPayConfirm($payConfirmParameter, $payInitParameter);

            } catch(\Exception $e) {
                $this->throwFinancialTransaction($transaction, $e);
            }


            $transaction->setReferenceNumber($payConfirmParameter['id']);
            $transaction->setProcessedAmount($transaction->getRequestedAmount());
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
            return;
        }


        if ($transaction->getTrackingId()) {
            try {
                $payConfirmParameter = $this->client->verifyPayConfirm($transaction);
                $this->throwUnlessValidPayConfirm($payConfirmParameter, $payInitParameter);

            } catch(\Exception $e) {
                $this->throwFinancialTransaction($transaction, $e);
            }

            $transaction->setReferenceNumber($payConfirmParameter['id']);
            $transaction->setProcessedAmount($transaction->getRequestedAmount());
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
            $data->set('token', $payConfirmParameter['token']);
            if (isset($payConfirmParameter['cardrefid'])) {
                $data->set('CARDREFID', $payConfirmParameter['cardrefid']);
            }
            if (isset($payConfirmParameter['cardmask'])) {
                $data->set('CARDMASK', $payConfirmParameter['cardmask']);
            }
            if (isset($payConfirmParameter['cardbrand'])) {
                $data->set('CARDBRAND', $payConfirmParameter['cardbrand']);
            }
            if (isset($payConfirmParameter['cardvalidmonth'])) {
                $data->set('CARDVALIDMONTH', $payConfirmParameter['cardvalidmonth']);
            }
            if (isset($payConfirmParameter['cardvalidyear'])) {
                $data->set('CARDVALIDYEAR', $payConfirmParameter['cardvalidyear']);
            }


        } else {
            $url = $this->client->createPayInit($payInitParameter, $transaction);

            $actionRequest = new ActionRequiredException('User has not yet authorized the transaction.');
            $actionRequest->setFinancialTransaction($transaction);
            $actionRequest->setAction(new VisitUrl($url));

            throw $actionRequest;
        }
    }

    /**
     * Reverse Approval
     *
     * @param FinancialTransactionInterface $transaction
     * @param bool $retry
     */
    public function reverseApproval(FinancialTransactionInterface $transaction, $retry)
    {
        parent::reverseApproval($transaction, $retry); // TODO: Change the autogenerated stub
    }

    /**
     * Deposit
     *
     * @param FinancialTransactionInterface $transaction
     * @param bool $retry
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {
        $referenceNumber = $transaction->getPayment()->getApproveTransaction()->getReferenceNumber();
        try {
            $payConfirmParameter = array();
            $payConfirmParameter['id'] = $referenceNumber;
            $payConfirmParameter['amount'] = SaferpayFormatHelper::formatAmount($transaction->getRequestedAmount());
            $payCompleteResponse = $this->client->payCompleteV2($payConfirmParameter);
            $this->throwUnlessSuccessPayComplete($payCompleteResponse);

        } catch(\Exception $e) {
            $this->throwFinancialTransaction($transaction, $e);
        }

        $transaction->setReferenceNumber($referenceNumber);
        $transaction->setProcessedAmount($transaction->getRequestedAmount());
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

    }

    /**
     * Reverse deposit
     *
     * @param FinancialTransactionInterface $transaction
     * @param bool $retry
     */
    public function reverseDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        parent::reverseDeposit($transaction, $retry); // TODO: Change the autogenerated stub
    }

    /**
     * Approve and deposit
     *
     * @param FinancialTransactionInterface $transaction
     * @param bool $retry
     */
    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $this->approve($transaction, $retry);
        $this->deposit($transaction, $retry);
    }

    /**
     * Create payment init parameter
     *
     * @param FinancialTransactionInterface $transaction
     * @return array
     */
    protected function createPayInitParameter(FinancialTransactionInterface $transaction)
    {
        $data = $transaction->getExtendedData();
        $checkoutParameters = $data->has('checkout_params') ? $data->get('checkout_params') : array();

        /** @var PaymentInterface $payment */
        $payment = $transaction->getPayment();

        /** @var PaymentInstructionInterface $paymentInstruction */
        $paymentInstruction = $payment->getPaymentInstruction();

        $payInitParameter = array();
        $payInitParameter['payment_methods'] = $paymentInstruction->getExtendedData()->has('payment_processor')
            ? $paymentInstruction->getExtendedData()->get('payment_processor')
            : [];
        $payInitParameter['successlink'] = $this->getReturnUrl($data);
        $payInitParameter['backlink'] = $this->getCancelUrl($data);
        $payInitParameter['faillink'] = $this->getErrorUrl($data);
        $payInitParameter['amount'] = SaferpayFormatHelper::formatAmount($transaction->getRequestedAmount());
        $payInitParameter['currency'] = $paymentInstruction->getCurrency();
        if ($this->cardrefid === 'random') {
            $random = new SecureRandom();
            $cardrefid = '';
            if ($this->cardrefidPrefix !== null) {
                $cardrefid = $this->cardrefidPrefix;
            }
            while (strlen($cardrefid) < $this->cardrefidLength) {
                $bytes = unpack('C', (string) $random->nextBytes(1));
                if ($bytes[1] < strlen(self::SIGNS)) {
                    $cardrefid .= substr(self::SIGNS, $bytes[1], 1);
                }
            }
        } else {
            $cardrefid = $this->cardrefid;
        }
        $payInitParameter['cardrefid'] = $cardrefid;

        if($notifyUrl = $this->getNotifyUrl($data)){
            $payInitParameter['notifylink'] = $notifyUrl;
        }

        foreach ($checkoutParameters as $field => $value) {
            $payInitParameter[$field] = $value;
        }
        return $payInitParameter;
    }

    /**
     * Throw financial transaction
     *
     * @param FinancialTransactionInterface $transaction
     * @param $e
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\FinancialException
     */
    protected function throwFinancialTransaction(FinancialTransactionInterface $transaction, $e)
    {
        $ex = new FinancialException('PaymentStatus is not completed: ' . $e->getMessage());
        $ex->setFinancialTransaction($transaction);
        $transaction->setResponseCode('Failed');
        $transaction->setReasonCode($e->getMessage());

        throw $ex;
    }

    /**
     * Throw until valid payment confirmation
     *
     * @param array $payConfirmParameter
     * @param array $payInitParameter
     * @throws \Exception
     */
    protected function throwUnlessValidPayConfirm(array $payConfirmParameter, array $payInitParameter)
    {
        $valid = $payConfirmParameter['amount'] == (string) $payInitParameter['amount'] && $payConfirmParameter['currency'] == $payInitParameter['currency'];
        if (!$valid) {
            throw new \Exception('Invalid.');
        }
    }

    /**
     * Throw until success payment complete response
     *
     * @param array $payCompleteResponse
     * @throws \Exception
     */
    protected function throwUnlessSuccessPayComplete(array $payCompleteResponse)
    {
        if ($payCompleteResponse['result'] != '200') {
            // Payment was not successful
            throw new \Exception('PayComplete error');
        }
    }

    /**
     * Get return url
     *
     * @param ExtendedDataInterface $data
     * @return string
     * @throws \RuntimeException
     */
    protected function getReturnUrl(ExtendedDataInterface $data)
    {
        if ($data->has('return_url')) {
            return $data->get('return_url');
        }
        else if (0 !== strlen($this->returnUrl)) {
            return $this->returnUrl;
        }

        throw new \RuntimeException('You must configure a return url.');
    }

    /**
     * Get cancel url
     *
     * @param ExtendedDataInterface $data
     * @return string
     * @throws \RuntimeException
     */
    protected function getCancelUrl(ExtendedDataInterface $data)
    {
        if ($data->has('cancel_url')) {
            return $data->get('cancel_url');
        }
        else if (0 !== strlen($this->cancelUrl)) {
            return $this->cancelUrl;
        }

        throw new \RuntimeException('You must configure a cancel url.');
    }

    /**
     * Get notify url
     *
     * @param ExtendedDataInterface $data
     * @return string|null
     * @throws \RuntimeException
     */
    protected function getNotifyUrl(ExtendedDataInterface $data)
    {
        if ($data->has('notify_url')) {
            return $data->get('notify_url');
        }

        return null;
    }

    /**
     * Get error url
     *
     * @param ExtendedDataInterface $data
     * @return string
     * @throws \RuntimeException
     */
    protected function getErrorUrl(ExtendedDataInterface $data)
    {
        if ($data->has('error_url')) {
            return $data->get('error_url');
        }
        else if (0 !== strlen($this->errorUrl)) {
            return $this->errorUrl;
        }

        throw new \RuntimeException('You must configure a error url.');
    }

    /**
     * get request
     *
     * @return Request
     */
    public function getRequest()
    {
        if (null == $this->requestStack) {
            throw new \RuntimeException('Request seems to be null in this context.');
        }
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * set request
     *
     * @param RequestStack $requestStack
     */
    public function setRequestStack($requestStack)
    {
        $this->requestStack = $requestStack;
    }

}
