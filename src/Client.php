<?php

namespace Socodo\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client implements ClientInterface
{
    protected ClientConfig $clientConfig;

    /**
     * Constructor.
     */
    public function __construct (ClientConfig $clientConfig = null)
    {
        if ($clientConfig === null)
        {
            $clientConfig = new ClientConfig();
        }

        $this->clientConfig = $clientConfig;
    }

    /**
     * Send HTTP request.
     *
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function sendRequest (RequestInterface $request): ResponseInterface
    {
        $handler = $this->clientConfig->handler;
        return $handler->handle($request, $this->clientConfig);
    }
}