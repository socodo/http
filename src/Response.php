<?php

namespace Socodo\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Socodo\Http\Enums\HttpStatuses;
use TypeError;

class Response extends Message implements ResponseInterface
{
    /** @var int HTTP status code. */
    protected int $statusCode;

    /** @var string HTTP reason phrase. */
    protected string $reasonPhrase;

    /**
     * @param HttpStatuses|int $status
     * @param array $headers
     * @param StreamInterface|resource|string|null $body
     * @param string|null $reasonPhrase
     * @param string $protocolVersion
     */
    public function __construct (HttpStatuses|int $status = HttpStatuses::OK, array $headers = [], mixed $body = null, string $reasonPhrase = null, string $protocolVersion = '1.1')
    {
        if ($status instanceof HttpStatuses)
        {
            $status = $status->value;
        }

        if (!is_int($status))
        {
            throw new TypeError('Socodo\\Http\\Response::__construct() Argument #1 ($status) must be of type HttpStatuses|int, ' . gettype($status) . ' given.');
        }

        $this->statusCode = $status;
        $this->reasonPhrase = $reasonPhrase ?? HttpStatuses::tryFrom($status)?->name;

        $this->setHeaders($headers);
        $this->protocolVersion = $protocolVersion;

        if ($body instanceof StreamInterface)
        {
            $this->stream = $body;
        }
        elseif ($body !== '' && $body !== null)
        {
            $this->stream = new Stream($body);
        }
    }

    /**
     * Get HTTP status code.
     *
     * @return int
     */
    public function getStatusCode (): int
    {
        return $this->statusCode;
    }

    /**
     * Get HTTP reason phrase.
     *
     * @return string
     */
    public function getReasonPhrase (): string
    {
        return $this->reasonPhrase;
    }

    /**
     * Get HTTP status code as enum.
     *
     * @return HttpStatuses|null
     */
    public function getHttpStatus (): ?HttpStatuses
    {
        return HttpStatuses::tryFrom($this->statusCode);
    }

    /**
     * Create an instance with a new HTTP status.
     *
     * @param HttpStatuses|int $code
     * @param string $reasonPhrase
     * @return ResponseInterface
     */
    public function withStatus ($code, $reasonPhrase = ''): ResponseInterface
    {
        if ($code instanceof HttpStatuses)
        {
            $code = $code->value;
        }

        if (!is_int($code))
        {
            throw new TypeError('Socodo\\Http\\Response::withStatus() Argument #1 ($code) must be of type HttpStatuses|int, ' . gettype($code) . ' given.');
        }

        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase ?? HttpStatuses::tryFrom($code)?->name;
        return $new;
    }
}