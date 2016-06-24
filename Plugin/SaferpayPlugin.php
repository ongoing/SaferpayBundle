<?php

namespace Valiton\Payment\SaferpayBundle\Plugin;

use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Payment\Saferpay\Data\PayCompleteResponse;
use Payment\Saferpay\Data\PayConfirmParameter;
use Payment\Saferpay\Data\PayConfirmParameterInterface;
use Payment\Saferpay\Data\PayInitParameterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Util\SecureRandom;
use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use Payment\Saferpay\Data\PayInitParameter;
use Valiton\Payment\SaferpayBundle\Client\Client;
use Valiton\Payment\SaferpayBundle\Utils\SaferpayFormatHelper;

/**
 * SaferpayPlugin
 *
 * @package Valiton\Payment\SaferpayBundle\Plugin
 * @author Sven Cludius<sven.cludius@valiton.com>
 */
class SaferpayPlugin extends AbstractPlugin
{
    const PAYMENT_SYSTEM_NAME = 'saferpay';

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
     * @var Request
     */
    protected $request;

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
     */
    public function __construct(Client $client, $returnUrl, $errorUrl, $cancelUrl, $cardrefid, $cardrefidPrefix, $cardrefidLength)
    {
        $this->client = $client;
        $this->returnUrl = $returnUrl;
        $this->errorUrl = $errorUrl;
        $this->cancelUrl = $cancelUrl;
        $this->cardrefid = $cardrefid;
        $this->cardrefidPrefix = $cardrefidPrefix;
        $this->cardrefidLength = min(40, $cardrefidLength);
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
        return self::PAYMENT_SYSTEM_NAME === $paymentSystemName;
    }

    /**
     * Approve
     *
     * @param FinancialTransactionInterface $transaction
     * @param bool $retry
     */
    public function approve(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        $payInitParameter = $this->createPayInitParameter($transaction);

        if ($this->getRequest()->query->has(Client::PAY_INIT_PARAM_DATA) && $this->getRequest()->query->has(Client::PAY_INIT_PARAM_SIGNATURE)) {

            try {
                $payConfirmParameter = $this->client->verifyPayConfirm($this->getRequest()->query->get(Client::PAY_INIT_PARAM_DATA), $this->getRequest()->query->get(Client::PAY_INIT_PARAM_SIGNATURE));
                $this->throwUnlessValidPayConfirm($payConfirmParameter, $payInitParameter);

            } catch(\Exception $e) {
                $this->throwFinancialTransaction($transaction, $e);
            }

            $transaction->setReferenceNumber($payConfirmParameter->getId());
            $transaction->setProcessedAmount($transaction->getRequestedAmount());
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
            $data->set('token', $payConfirmParameter->getToken());
            if (null != $payConfirmParameter->getCardrefid()) {
                $data->set('CARDREFID', $payConfirmParameter->getCardrefid());
            }

        } else {
            $url = $this->client->createPayInit($payInitParameter);

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
            $payConfirmParameter = new PayConfirmParameter();
            $payConfirmParameter->setId($referenceNumber);
            $payConfirmParameter->setAmount(SaferpayFormatHelper::formatAmount($transaction->getRequestedAmount()));
            $payCompleteResponse = $this->client->payCompleteV2($payConfirmParameter);
            $this->throwUnlessSuccessPayComplete($payCompleteResponse);

        } catch(\Exception $e) {
            $this->throwFinancialTransaction($transaction, $e);
        }

        $transaction->setReferenceNumber($payCompleteResponse->getId());
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
     * @return PayInitParameter
     */
    protected function createPayInitParameter(FinancialTransactionInterface $transaction)
    {
        $data = $transaction->getExtendedData();
        $checkoutParameters = $data->has('checkout_params') ? $data->get('checkout_params') : array();

        /** @var PaymentInterface $payment */
        $payment = $transaction->getPayment();

        /** @var PaymentInstructionInterface $paymentInstruction */
        $paymentInstruction = $payment->getPaymentInstruction();

        $payInitParameter = new PayInitParameter();
        $payInitParameter->setSuccesslink($this->getReturnUrl($data));
        $payInitParameter->setBacklink($this->getCancelUrl($data));
        $payInitParameter->setFaillink($this->getErrorUrl($data));
        $payInitParameter->setAmount(SaferpayFormatHelper::formatAmount($transaction->getRequestedAmount()));
        $payInitParameter->setCurrency($paymentInstruction->getCurrency());
        if ($this->cardrefid === 'random') {
            $random = new SecureRandom();
            $cardrefid = bin2hex($random->nextBytes(20));
            if ($this->cardrefidPrefix !== null) {
                $cardrefid = sprintf('%s%s', $this->cardrefidPrefix, $cardrefid);
            }
            $cardrefid = substr($cardrefid, 0, $this->cardrefidLength);
        } else {
            $cardrefid = 'new';
        }
        $payInitParameter->setCardrefid($cardrefid);
        foreach ($checkoutParameters as $field => $value) {
            $payInitParameter->set($field, $value);
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
     * @param PayConfirmParameterInterface $payConfirmParameter
     * @param PayInitParameterInterface $payInitParameter
     * @return bool
     * @throws \Exception
     */
    protected function throwUnlessValidPayConfirm(PayConfirmParameterInterface $payConfirmParameter, PayInitParameterInterface $payInitParameter)
    {
        $valid = $payConfirmParameter->getAmount() == (string) $payInitParameter->getAmount() && $payConfirmParameter->getCurrency() == $payInitParameter->getCurrency();
        if (!$valid) {
            throw new \Exception('Invalid.');
        }
    }

    /**
     * Throw until success payment complete response
     *
     * @param PayCompleteResponse $payCompleteResponse
     * @throws \Exception
     */
    protected function throwUnlessSuccessPayComplete(PayCompleteResponse $payCompleteResponse)
    {
        if ($payCompleteResponse->getResult() != '0') {
            // Payment was not successfully
            throw new \Exception($payCompleteResponse->getMessage());
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
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        if (null == $this->request) {
            throw new \RuntimeException('Request seems to be null in this context.');
        }
        return $this->request;
    }

    /**
     * set request
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
    }

}
