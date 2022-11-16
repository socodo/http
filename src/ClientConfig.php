<?php

namespace Socodo\Http;

use Socodo\Http\Handlers\CurlHandler;
use Socodo\Http\Interfaces\CookieJarInterface;
use Socodo\Http\Interfaces\HandlerInterface;

class ClientConfig
{
    /** @var HandlerInterface HTTP request handler. */
    public HandlerInterface $handler;

    /** @var array<string,string|array<string>> Default headers. */
    public array $headers = [
        'User-Agent' => 'Mozilla/5.0 (compatible; SocodoHttp/1.0; +https://github.com/socodo/http)'
    ];

    /** @var bool Allow redirections. */
    public bool $allowRedirections = true;

    /** @var CookieJarInterface|null Cookie jar instance. */
    public ?CookieJarInterface $cookieJar = null;

    /** @var string|null Proxy connection string. */
    public ?string $httpProxy = null;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->handler = new CurlHandler();
    }
}