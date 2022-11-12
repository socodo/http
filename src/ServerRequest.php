<?php

namespace Socodo\Http;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Socodo\Http\Enums\HttpMethods;

class ServerRequest extends Request implements ServerRequestInterface
{
    /** @var array Attributes. */
    protected array $attributes = [];

    /** @var array Server parameters. */
    protected array $serverParams = [];

    /** @var array Cookie parameters. */
    protected array $cookieParams = [];

    /** @var array Query string parameters. */
    protected array $queryParams = [];

    /** @var mixed Parsed body. */
    protected mixed $parsedBody = null;

    /** @var array<UploadedFileInterface> Uploaded files. */
    protected array $uploadedFiles = [];

    /**
     * Constructor.
     *
     * @param HttpMethods|string $method
     * @param UriInterface|string $uri
     * @param array $headers
     * @param mixed|null $body
     * @param string $protocolVersion
     * @param array $serverParams
     */
    public function __construct (HttpMethods|string $method, UriInterface|string $uri, array $headers = [], mixed $body = null, string $protocolVersion = '1.1', array $serverParams = [])
    {
        $this->serverParams = $serverParams;
        parent::__construct($method, $uri, $headers, $body, $protocolVersion);
    }

    /**
     * Get all attributes.
     *
     * @return array
     */
    public function getAttributes (): array
    {
        return $this->attributes;
    }

    /**
     * Get an attribute.
     *
     * @param $name
     * @param $default
     * @return mixed
     */
    public function getAttribute ($name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Get cookie parameters.
     *
     * @return array
     */
    public function getCookieParams (): array
    {
        return $this->cookieParams;
    }

    /**
     * Get server parameters.
     *
     * @return array
     */
    public function getServerParams (): array
    {
        return $this->serverParams;
    }

    /**
     * Get query string parameters.
     *
     * @return array
     */
    public function getQueryParams (): array
    {
        return $this->queryParams;
    }

    /**
     * Get parsed body.
     *
     * @return mixed
     */
    public function getParsedBody (): mixed
    {
        return $this->parsedBody;
    }

    /**
     * Get uploaded files.
     *
     * @return array<UploadedFileInterface>
     */
    public function getUploadedFiles (): array
    {
        return $this->uploadedFiles;
    }

    /**
     * Create an instance with a new cookie parameters.
     *
     * @param array $cookies
     * @return ServerRequestInterface
     */
    public function withCookieParams (array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;
        return $new;
    }

    /**
     * Create an instance with a new query string parameters.
     *
     * @param array $query
     * @return ServerRequestInterface
     */
    public function withQueryParams (array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;
        return $new;
    }

    /**
     * Create an instance with a new parsed body.
     *
     * @param $data
     * @return ServerRequestInterface
     */
    public function withParsedBody ($data): ServerRequestInterface
    {
        $new = clone $this;
        $new->parsedBody = $data;
        return $new;
    }

    /**
     * Create an instance with uploaded files.
     *
     * @param array<UploadedFileInterface> $uploadedFiles
     * @return ServerRequestInterface
     */
    public function withUploadedFiles (array $uploadedFiles): ServerRequestInterface
    {
        if (!$this->isUploadedFilesArrayValid($uploadedFiles))
        {
            throw new InvalidArgumentException('Socodo\\Http\\ServerRequest::withUploadedFiles() Argument #1 ($uploadedFiles) can have child of type UploadedFileInterface only.');
        }

        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;
        return $new;
    }

    /**
     * Determine if the uploaded files are all valid.
     *
     * @param array $uploadedFiles
     * @return bool
     */
    protected function isUploadedFilesArrayValid (array $uploadedFiles): bool
    {
        foreach ($uploadedFiles as $uploadedFile)
        {
            if (is_array($uploadedFile))
            {
                $result = $this->isUploadedFilesArrayValid($uploadedFile);
            }
            else
            {
                $result = ($uploadedFile instanceof UploadedFileInterface);
            }

            if (!$result)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Create an instance with a new attribute.
     *
     * @param $name
     * @param $value
     * @return ServerRequestInterface
     */
    public function withAttribute ($name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;
        return $new;
    }

    /**
     * Create an instance without a given attribute.
     *
     * @param $name
     * @return ServerRequestInterface
     */
    public function withoutAttribute ($name): ServerRequestInterface
    {
        if (!isset($this->attributes[$name]))
        {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);
        return $new;
    }
}