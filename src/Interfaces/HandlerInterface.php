<?php

namespace Socodo\Http\Interfaces;

use Psr\Http\Message\RequestInterface;
use Socodo\Http\ClientConfig;

interface HandlerInterface
{
    public function handle (RequestInterface $request, ClientConfig $config);
}