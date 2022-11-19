<?php

namespace Socodo\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Socodo\Http\Exceptions\MalformedHeaderException;
use TypeError;

class Message implements MessageInterface
{
    /** @var array<string, array<string>> Map of registered headers, as original name => values. */
    protected array $headers = [];

    /** @var array<string, string> Map of registered header names, as lowercase header name => original name. */
    protected array $headerNames = [];

    /** @var string HTTP protocol version. */
    protected string $protocolVersion = '1.1';

    /** @var StreamInterface|null Body stream. */
    protected ?StreamInterface $stream = null;

    /**
     * Get all registered headers.
     *
     * @return array
     */
    public function getHeaders (): array
    {
        return $this->headers;
    }

    /**
     * Get header.
     *
     * @param string $name
     * @return array
     */
    public function getHeader ($name): array
    {
        if (!is_string($name))
        {
            throw $this->createTypeError('getHeader', 1, 'name', 'string', gettype($name));
        }

        $name = $this->toLowerLatin($name);
        if (!isset($this->headerNames[$name]))
        {
            return [];
        }

        $name = $this->headerNames[$name];
        return $this->headers[$name];
    }

    /**
     * Get header line string.
     *
     * @param string $name
     * @return string
     */
    public function getHeaderLine ($name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * Determine if the header exists.
     *
     * @param $name
     * @return bool
     */
    public function hasHeader ($name): bool
    {
        $name = $this->toLowerLatin($name);
        return isset($this->headerNames[$name]);
    }

    /**
     * Get protocol version.
     *
     * @return string
     */
    public function getProtocolVersion (): string
    {
        return $this->protocolVersion;
    }

    /**
     * Get body stream.
     *
     * @return StreamInterface
     */
    public function getBody (): StreamInterface
    {
        if (!$this->stream)
        {
            $this->stream = new Stream('');
        }

        return $this->stream;
    }

    /**
     * Create an instance with a new header.
     *
     * @param string $name
     * @param string|array<string> $value
     * @return MessageInterface
     */
    public function withHeader ($name, $value): MessageInterface
    {
        if (!is_string($name))
        {
            throw $this->createTypeError('withHeader', 1, 'name', 'string', gettype($name));
        }

        if (!$this->assertHeader($name))
        {
            throw $this->createMalformedHeaderException('withHeader', 1, 'name', $name);
        }

        if (!is_string($value) && $value !== null)
        {
            throw $this->createTypeError('withHeader', 2, 'value', 'string|null', gettype($value));
        }

        if (!is_array($value))
        {
            $value = [ $value ];
        }

        foreach ($value as $i => &$item)
        {
            $item = trim($item, " \t");
            if (!$this->assertValue($item))
            {
                throw $this->createMalformedHeaderException('withHeader', 2, 'value[' . $i . ']', $item);
            }
        }

        $normalized = $this->toLowerLatin($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized]))
        {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * Create an instance with an added header.
     *
     * @param string $name
     * @param string|array<string> $value
     * @return MessageInterface
     */
    public function withAddedHeader ($name, $value): MessageInterface
    {
        if (!is_string($name))
        {
            throw $this->createTypeError('withAddedHeader', 1, 'name', 'string', gettype($name));
        }

        if (!$this->assertHeader($name))
        {
            throw $this->createMalformedHeaderException('withAddedHeader', 1, 'header', $name);
        }

        if (!is_array($value))
        {
            $value = [ $value ];
        }

        foreach ($value as $i => &$item)
        {
            $item = trim($item, " \t");
            if (!$this->assertValue($item))
            {
                throw $this->createMalformedHeaderException('withAddedHeader', 2, 'value[' . $i . ']', $item);
            }
        }

        $normalized = $this->toLowerLatin($name);

        $new = clone $this;

        /** @noinspection DuplicatedCode */
        if (isset($new->headerNames[$normalized]))
        {
            $name = $this->headerNames[$normalized];
            $new->headers[$name] = array_merge($this->headers[$name], $value);
        }
        else
        {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    /**
     * Create an instance without a given header.
     *
     * @param $name
     * @return MessageInterface
     */
    public function withoutHeader ($name): MessageInterface
    {
        $normalized = $this->toLowerLatin($name);
        if (!isset($this->headerNames[$normalized]))
        {
            return $this;
        }

        $name = $this->headerNames[$normalized];

        $new = clone $this;
        unset($new->headers[$name]);
        unset($new->headerNames[$normalized]);
        return $new;
    }

    /**
     * Determine if the header name is valid.
     *
     * @param string $header
     * @return bool
     */
    protected function assertHeader (string $header): bool
    {
        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $header))
        {
            return false;
        }

        return true;
    }

    /**
     * Determine if the header value is valid.
     *
     * @param ?string $value
     * @return bool
     */
    protected function assertValue (?string $value): bool
    {
        if (!preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/', $value) && $value !== '' && $value !== null)
        {
            return false;
        }

        return true;
    }

    /**
     * Set headers array.
     *
     * @param array $headers
     * @return void
     */
    protected function setHeaders (array $headers): void
    {
        $this->headerNames = $this->headers = [];

        foreach ($headers as $name => $value)
        {
            $name = (string) $name;
            if (!$this->assertHeader($name))
            {
                throw new MalformedHeaderException(static::class . '::setHeaders() Argument #1 ($headers) must be of array with RFC-7230 compatible string keys, "' . $name . '" given.');
            }

            if (!is_array($value))
            {
                $value = [ $value ];
            }

            foreach ($value as $i => &$item)
            {
                $item = trim($item, " \t");
                if (!$this->assertValue($item))
                {
                    throw $this->createMalformedHeaderException('setHeaders', 1, 'headers["' . $name . '"][' . $i . ']', $item);
                }
            }

            $normalized = $this->toLowerLatin($name);

            /** @noinspection DuplicatedCode */
            if (isset($this->headerNames[$normalized]))
            {
                $name = $this->headerNames[$normalized];
                $this->headers[$name] = array_merge($this->headers[$name], $value);
            }
            else
            {
                $this->headerNames[$normalized] = $name;
                $this->headers[$name] = $value;
            }
        }
    }

    /**
     * Create an instance with a new protocol version.
     *
     * @param $version
     * @return MessageInterface
     */
    public function withProtocolVersion ($version): MessageInterface
    {
        if ($this->protocolVersion == $version)
        {
            return $this;
        }

        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    /**
     * Create an instance with a new body.
     *
     * @param StreamInterface $body
     * @return MessageInterface
     */
    public function withBody (StreamInterface $body): MessageInterface
    {
        if ($this->stream === $body)
        {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;
        return $new;
    }

    /**
     * Make target string to lower case.
     *
     * @param string $target
     * @return string
     */
    protected function toLowerLatin (string $target): string
    {
        return strtr($target, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Create a type error.
     *
     * @param string $methodName
     * @param int $argumentPos
     * @param string $argumentName
     * @param string $expectedType
     * @param string $givenType
     * @return TypeError
     */
    private function createTypeError (string $methodName, int $argumentPos, string $argumentName, string $expectedType, string $givenType): TypeError
    {
        return new TypeError(static::class . '::' . $methodName . '() Argument #' . $argumentPos . ' ($' . $argumentName . ') must be of type ' . $expectedType . ', ' . $givenType . ' given.');
    }

    /**
     * Create a malformed header exception.
     *
     * @param string $methodName
     * @param int $argumentPos
     * @param string $argumentName
     * @param string $given
     * @return MalformedHeaderException
     */
    private function createMalformedHeaderException (string $methodName, int $argumentPos, string $argumentName, string $given): MalformedHeaderException
    {
        return new MalformedHeaderException(static::class . '::' . $methodName . '() Argument #' . $argumentPos . '($' . $argumentName . ') must be RFC-7230 compatible string, "' . $given . '" given.');
    }
}