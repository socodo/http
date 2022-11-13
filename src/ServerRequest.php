<?php

namespace Socodo\Http;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Socodo\Http\Enums\HttpMethods;
use Socodo\Http\Exceptions\UnreachableUploadedFileException;

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

    /**
     * Create an instance from globals.
     *
     * @return ServerRequestInterface
     */
    public static function fromGlobals (): ServerRequestInterface
    {
        $method = HttpMethods::from($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = self::getUriFromGlobals();
        $headers = getallheaders();
        $body = new Stream(fopen('php://input', 'r+'));
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL']) : '1.1';

        $serverRequest = new ServerRequest($method, $uri, $headers, $body, $protocol, $_SERVER);
        return $serverRequest->withCookieParams($_COOKIE)->withQueryParams($_GET)->withParsedBody($_POST)->withUploadedFiles(self::getUploadedFilesFromGlobals());
    }

    /**
     * Create a URI instance from globals.
     *
     * @return UriInterface
     */
    public static function getUriFromGlobals (): UriInterface
    {
        $uri = new Uri('');
        $uri = $uri->withScheme(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');

        $hasPort = false;
        if (isset($_SERVER['HTTP_HOST']))
        {
            $parts = self::extractHostAndPortFromAuthority($_SERVER['HTTP_HOST']);
            if ($parts['host'] !== null)
            {
                $uri = $uri->withHost($parts['host']);
            }

            if ($parts['port'] !== null)
            {
                $hasPort = true;
                $uri = $uri->withPort($parts['port']);
            }
        }
        elseif (isset($_SERVER['SERVER_NAME']))
        {
            $uri = $uri->withHost($_SERVER['SERVER_NAME']);
        }
        elseif (isset($_SERVER['SERVER_ADDR']))
        {
            $uri = $uri->withHost($_SERVER['SERVER_ADDR']);
        }

        if (!$hasPort && isset($_SERVER['SERVER_PORT']))
        {
            $uri = $uri->withPort($_SERVER['SERVER_PORT']);
        }

        $hasQueryString = false;
        if (isset($_SERVER['REQUEST_URI']))
        {
            $uriParts = explode('?', $_SERVER['REQUEST_URI']);
            $uri = $uri->withPath($uriParts[0]);

            if (isset($uriParts[1]))
            {
                $hasQueryString = true;
                $uri = $uri->withQuery($uriParts[1]);
            }
        }

        if (!$hasQueryString && isset($_SERVER['QUERY_STRING']))
        {
            $uri = $uri->withQuery($_SERVER['QUERY_STRING']);
        }

        return $uri;
    }

    /**
     * Get host and port from authority string.
     *
     * @param string $authority
     * @return array|null[]
     */
    protected static function extractHostAndPortFromAuthority (string $authority): array
    {
        $uri = 'https://' . $authority;
        $parts = parse_url($uri);
        if ($parts === false)
        {
            return [ 'host' => null, 'port' => null ];
        }

        return [
            'host' => $parts['host'] ?? null,
            'port' => $parts['port'] ?? null
        ];
    }

    /**
     * Create uploaded files array from globals.
     *
     * @return array
     */
    public static function getUploadedFilesFromGlobals (): array
    {
        return self::normalizeFiles($_FILES);
    }

    /**
     * Normalize files from PHP spec array.
     *
     * @param array $files
     * @return array
     */
    protected static function normalizeFiles (array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $val)
        {
            if ($val instanceof UploadedFileInterface)
            {
                $normalized[$key] = $val;
                continue;
            }

            if (is_array($val) && isset($val['tmp_name']))
            {
                $normalized[$key] = self::getUploadedFileFromSpec($val);
                continue;
            }

            if (is_array($val))
            {
                $normalized[$key] = self::normalizeFiles($val);
                continue;
            }

            throw new UnreachableUploadedFileException('Socodo\\Http\\ServerRequest::normalizeFiles() Invalid value in file spec array key "' . $key . '".');
        }

        return $normalized;
    }

    /**
     * Create uploaded file instance from file spec.
     *
     * @param array $val
     * @return UploadedFileInterface|array<UploadedFileInterface>
     */
    protected static function getUploadedFileFromSpec (array $val): UploadedFileInterface|array
    {
        if (is_array($val['tmp_name']))
        {
            return self::getUploadedFileFromNestedSpec($val);
        }

        return new UploadedFile($val['tmp_name'], (int) $val['size'], (int) $val['error'], $val['name'], $val['type']);
    }

    /**
     * Create uploaded file array from nested spec.
     *
     * @param array $val
     * @return array
     */
    protected static function getUploadedFileFromNestedSpec (array $val): array
    {
        $normalized = [];

        foreach (array_keys($val) as $key)
        {
            $spec = [
                'tmp_name' => $val['tmp_name'][$key],
                'size' => $val['size'][$key],
                'error' => $val['error'][$key],
                'name' => $val['name'][$key],
                'type' => $val['type'][$key]
            ];

            $normalized[$key] = self::getUploadedFileFromSpec($spec);
        }

        return $normalized;
    }
}