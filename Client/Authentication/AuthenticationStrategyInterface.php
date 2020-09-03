<?php

namespace Ongoing\Payment\SaferpayBundle\Client\Authentication;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;

/**
 * AuthenticationStrategyInterface
 *
 * @package Valiton\Payment\SaferpayBundle\Client\Authentication
 * @author Sven Cludius<sven.cludius@valiton.com>
 */
interface AuthenticationStrategyInterface
{
    /**
     * Send request with custom authentication
     *
     * @param Client $client
     * @param RequestInterface|null $request
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function sendAuthenticated(Client $client, RequestInterface $request = null);
}
