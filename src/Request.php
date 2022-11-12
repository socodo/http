<?php

namespace Socodo\Http;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Socodo\Http\Enums\HttpMethods;
use TypeError;

class Request extends Message implements RequestInterface
{
    /** @var HttpMethods HTTP method. */
    protected HttpMethods $method;

    /** @var UriInterface HTTP request URI. */
    protected UriInterface $uri;

    /** @var ?string HTTP request target. */
    protected ?string $requestTarget = null;

    /**
     * Constructor.
     *
     * @param HttpMethods|string $method
     * @param UriInterface|string $uri
     * @param array $headers
     * @param mixed|null $body
     * @param string $protocolVersion
     */
    public function __construct (HttpMethods|string $method, UriInterface|string $uri, array $headers = [], mixed $body = null, string $protocolVersion = '1.1')
    {
        if (is_string($method))
        {
            $method = strtoupper($method);
            $method = HttpMethods::from($method);
        }

        if (is_string($uri))
        {
            $uri = new Uri($uri);
        }

        $this->method = $method;
        $this->protocolVersion = $protocolVersion;
        $this->uri = $uri;

        $this->setHeaders($headers);
        if (!isset($this->headerNames['host']))
        {
            $this->updateHostFromUri();
        }

        if ($body !== '' && $body !== null)
        {
            $this->stream = new Stream($body);
        }
    }

    /**
     * Get request target.
     *
     * @return string
     */
    public function getRequestTarget (): string
    {
        if ($this->requestTarget !== null)
        {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '')
        {
            $target = '/';
        }

        $query = $this->uri->getQuery();
        if ($query !== '')
        {
            $target .= '?' . $query;
        }

        return $target;
    }

    /**
     * Get URI.
     *
     * @return UriInterface
     */
    public function getUri (): UriInterface
    {
        return $this->uri;
    }

    /**
     * Get HTTP method.
     *
     * @param bool $asEnum
     * @return HttpMethods|string
     */
    public function getMethod (bool $asEnum = false): HttpMethods|string
    {
        if ($asEnum)
        {
            return $this->method;
        }

        return $this->method->value;
    }

    /**
     * Create an instance with a new request target.
     *
     * @param string $requestTarget
     * @return RequestInterface
     */
    public function withRequestTarget ($requestTarget): RequestInterface
    {
        if (preg_match('#\s#', $requestTarget))
        {
            throw new InvalidArgumentException('Socodo\\Http\\Request::withRequestTarget() Argument #1 ($requestTarget) must not contain any whitespaces, "' . $requestTarget . '" given.');
        }

        $new = clone $this;
        $new->requestTarget = $requestTarget;
        return $new;
    }

    /**
     * Create an instance with a new URI interface.
     *
     * @param UriInterface $uri
     * @param bool $preserveHost
     * @return RequestInterface
     */
    public function withUri (UriInterface $uri, $preserveHost = false): RequestInterface
    {
        if ($this->uri === $uri)
        {
            return $this;
        }

        $new = clone $this;
        $new->uri = $uri;
        if (!$preserveHost || !isset($this->headerNames['host']))
        {
            $new->updateHostFromUri();
        }

        return $new;
    }

    /**
     * Create an instance with a new method.
     *
     * @param $method
     * @return RequestInterface
     */
    public function withMethod ($method): RequestInterface
    {
        if (is_string($method))
        {
            $method = HttpMethods::from($method);
        }

        if (!$method instanceof HttpMethods)
        {
            throw new TypeError('Socodo\\Http\\Request::withMethod() Argument #1 ($method) must be of type Socodo\\Http\\Enums\\HttpMethods|string, ' . gettype($method) . ' given.');
        }

        $new = clone $this;
        $new->method = $method;
        return $new;
    }

    /**
     * Update host header value to URI host value.
     *
     * @return void
     */
    protected function updateHostFromUri (): void
    {
        $host = $this->uri->getHost();

        if ($host === '')
        {
            return;
        }

        $port = $this->uri->getPort();
        if ($port !== null)
        {
            $host .= ':' . $port;
        }

        if (isset($this->headerNames['host']))
        {
            $header = $this->headerNames['host'];
        }
        else
        {
            $header = 'Host';
            $this->headerNames['host'] = 'Host';
        }

        $this->headers = array_merge([ $header => [ $host ]], $this->headers);
    }
}