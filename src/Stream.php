<?php

namespace Socodo\Http;

use Psr\Http\Message\StreamInterface;
use TypeError;

class Stream implements StreamInterface
{
    /**
     * Constructor.
     *
     * @param resource|string $stream
     */
    public function __construct(mixed $stream)
    {
        if (is_string($stream))
        {
            $resource = fopen('php://temp', 'rw+');
            fwrite($resource, $stream);
            $stream = $resource;
        }

        if (!is_resource($stream))
        {
            throw $this->createTypeError('__construct', 1, 'stream', 'resource|string', gettype($stream));
        }
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
        return new TypeError('Socodo\\Http\\Stream::' . $methodName . '() Argument #' . $argumentPos . ' ($' . $argumentName . ') must be of type ' . $expectedType . ', ' . $givenType . ' given.');
    }
}