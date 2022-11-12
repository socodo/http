<?php

namespace Socodo\Http;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;
use Socodo\Http\Exceptions\MalformedUriException;
use TypeError;

class Uri implements UriInterface
{
    /** @var string URI scheme. */
    protected string $scheme = '';

    /** @var string URI user information. */
    protected string $userInfo = '';

    /** @var string URI host. */
    protected string $host = '';

    /** @var int|null URI port. */
    protected ?int $port = null;

    /** @var string URI path. */
    protected string $path = '';

    /** @var string URI query string. */
    protected string $query = '';

    /** @var string URI fragment. */
    protected string $fragment = '';

    /** @var string|null Compiled string from URI data. */
    protected ?string $compiledString = null;

    /** @var array<string, int> Well-known default ports.  */
    private const DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
        'ftp' => 21,
        'ssh' => 22,
        'telnet' => 23,
        'imap' => 143,
        'pop' => 110,
        'ldap' => 389,
    ];

    /**
     * Constructor.
     *
     * @param string $uri
     */
    public function __construct (string $uri = '')
    {
        if ($uri !== '')
        {
            $parts = self::parse($uri);
            if ($parts === false)
            {
                throw new MalformedUriException('Socodo\\Http\\Uri::__construct() Argument #1 ($uri) failed to parse URI: ' . $uri);
            }

            $this->applyParsed($parts);
        }
    }

    /**
     * Parse URI string with UTF-8 supports.
     *
     * @param string $uri
     * @return array|false
     */
    protected static function parse (string $uri): array|false
    {
        $prefix = '';
        if (preg_match('%^(.*://\[[0-9:a-f]+])(.*?)$%', $uri, $matches))
        {
            $prefix = $matches[1];
            $uri = $matches[2];
        }

        $encodedUri = preg_replace_callback('%[^:/@?&=#]+%u', static fn (array $matches): string => urlencode($matches[0]), $uri);

        $result = parse_url($prefix . $encodedUri);
        if ($result === false)
        {
            return false;
        }

        return array_map(static fn (string $item): string => urldecode($item), $result);
    }

    /**
     * Apply parsed parts into the instance.
     *
     * @param array $parts
     * @return void
     */
    protected function applyParsed (array $parts): void
    {
        $this->scheme = isset($parts['scheme']) ? $this->toLowerLatin($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? $this->encodeUserInfo($parts['user']) : '';
        $this->host = isset($parts['host']) ? $this->toLowerLatin($parts['host']) : '';
        $this->port = isset($parts['port']) ? (int) $parts['port'] : null;
        $this->path = isset($parts['path']) ? $this->encodeAfters($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->encodeAfters($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->encodeAfters($parts['fragment']) : '';

        $this->dropDefaultPort();
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
     * Encode a user info string.
     *
     * @param string $component
     * @return string
     */
    protected function encodeUserInfo (string $component): string
    {
        return preg_replace_callback('/[^%a-zA-Z0-9_\-.~!\$&\'()*+,;=]+|%(?![A-Fa-f0-9]{2})/', static fn (array $matches): string => rawurlencode($matches[0]), $component);
    }

    /**
     * Encode a string which comes after a hostname.
     *
     * @param string $component
     * @return string
     */
    protected function encodeAfters (string $component): string
    {
        return preg_replace_callback('/[^a-zA-Z0-9_\-.~!\$&\'()*+,;=%:@\/?]++|%(?![A-Fa-f0-9]{2})/', static fn (array $matches): string => rawurlencode($matches[0]), $component);
    }

    /**
     * Drop port number if is default port.
     *
     * @return void
     */
    private function dropDefaultPort(): void
    {
        if ($this->port !== null && in_array($this->port, self::DEFAULT_PORTS))
        {
            $this->port = null;
        }
    }

    /**
     * Compile to string.
     *
     * @return string
     */
    public function __toString (): string
    {
        if ($this->compiledString === null)
        {
            $str = '';
            if ($this->scheme !== '')
            {
                $str .= $this->scheme . ':';
            }

            $authority = $this->getAuthority();
            if ($authority !== '' || $this->scheme === 'file')
            {
                $str .= '//' . $authority;
            }

            $path = $this->path;
            if ($authority !== '' && $path !== '' && !str_starts_with($path, '/'))
            {
                $path = '/' . $path;
            }

            $str .= $path;

            if ($this->query !== '')
            {
                $str .= '?' . $this->query;
            }

            if ($this->fragment !== '')
            {
                $str .= '#' . $this->fragment;
            }

            $this->compiledString = $str;
        }

        return $this->compiledString;
    }

    /**
     * Create an instance with a new path.
     *
     * @param $path
     * @return UriInterface
     */
    public function withPath ($path): UriInterface
    {
        if (!is_string($path))
        {
            throw $this->createTypeError('withPath', 1, 'path', 'string', gettype($path));
        }

        if ($this->path === $path)
        {
            return $this;
        }

        if ($this->getAuthority() === '' && str_starts_with($path, '//'))
        {
            throw $this->createInvalidArgumentException('withPath', 1, 'path', 'of a URI without an authority must not start with two slashes.');
        }

        if ($this->scheme === '' && str_contains(explode('/', $path, 2)[0], ':'))
        {
            throw $this->createInvalidArgumentException('withPath', 1, 'path', 'of a relative URI must not begins with colon-contained segment.');
        }

        $new = clone $this;
        $new->path = $path;
        $new->compiledString = null;
        return $new;
    }

    /**
     * Create an instance with a new query string.
     *
     * @param $query
     * @return UriInterface
     */
    public function withQuery ($query): UriInterface
    {
        if (!is_string($query))
        {
            throw $this->createTypeError('withQuery', 1, 'query', 'string', gettype($query));
        }

        $query = $this->encodeAfters($query);
        if ($this->query === $query)
        {
            return $this;
        }

        $new = clone $this;
        $new->query = $query;
        $new->compiledString = null;
        return $new;
    }

    /**
     * Create an instance with a new fragment string.
     *
     * @param $fragment
     * @return UriInterface
     */
    public function withFragment ($fragment): UriInterface
    {
        if (!is_string($fragment))
        {
            throw $this->createTypeError('withFragment', 1, 'fragment', 'string', gettype($fragment));
        }

        $fragment = $this->encodeAfters($fragment);
        if ($this->fragment === $fragment)
        {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $fragment;
        $new->compiledString = null;
        return $new;
    }

    /**
     * Create an instance with a new scheme.
     *
     * @param $scheme
     * @return UriInterface
     */
    public function withScheme ($scheme): UriInterface
    {
        if (!is_string($scheme))
        {
            throw $this->createTypeError('withScheme', 1, 'scheme', 'string', gettype($scheme));
        }

        $scheme = $this->toLowerLatin($scheme);
        if ($this->scheme === $scheme)
        {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $scheme;
        $new->compiledString = null;

        $new->dropDefaultPort();
        if (($new->getAuthority() === '' && str_starts_with($new->getPath(), '//')) || ($new->scheme === '' && str_contains(explode('/', $new->getPath(), 2)[0], ':')))
        {
            throw $this->createInvalidArgumentException('withScheme', 1, 'scheme', 'affects to be a non-authority URI and it conflicts with its path policy.');
        }

        return $new;
    }

    /**
     * Create an instance with a new user information.
     *
     * @param $user
     * @param $password
     * @return UriInterface
     */
    public function withUserInfo ($user, $password = null): UriInterface
    {
        if (!is_string($user))
        {
            throw $this->createTypeError('withUserInfo', 1, 'user', 'string', gettype($user));
        }
        
        if ($password !== null && !is_string($password))
        {
            throw $this->createTypeError('withUserInfo', 2, 'password', 'string|null', gettype($user));
        }
        
        $info = $this->encodeUserInfo($user);
        if ($password !== null)
        {
            $info .= ':' . $this->encodeUserInfo($password);
        }

        if ($this->userInfo === $info)
        {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $info;
        $new->compiledString = null;

        if ($new->getAuthority() === '' && str_starts_with($new->getPath(), '//'))
        {
            throw $this->createInvalidArgumentException('withUserInfo', 1, 'user', 'affects to be a non-authority URI and it conflicts with its path policy.');
        }

        return $new;
    }

    /**
     * Create an instance with a new host.
     *
     * @param $host
     * @return UriInterface
     */
    public function withHost ($host): UriInterface
    {
        if (!is_string($host))
        {
            throw $this->createTypeError('withHost', 1, 'host', 'string', gettype($host));
        }

        $host = $this->toLowerLatin($host);
        if ($this->host === $host)
        {
            return $this;
        }

        $new = clone $this;
        $new->host = $host;
        $new->compiledString = null;

        if ($new->getAuthority() === '' && str_starts_with($new->getPath(), '//'))
        {
            throw $this->createInvalidArgumentException('withHost', 1, 'host', 'affects to be a non-authority URI and it conflicts with its path policy.');
        }

        return $new;
    }

    /**
     * Create an instance with a new port.
     *
     * @param $port
     * @return UriInterface
     */
    public function withPort ($port): UriInterface
    {
        if ($port !== null && !is_numeric($port))
        {
            throw $this->createTypeError('withPort', 1, 'port', 'int|null', gettype($port));
        }

        if ($this->port === $port)
        {
            return $this;
        }

        if (0 > $port || 0xffff < $port)
        {
            throw $this->createInvalidArgumentException('withPort', 1, 'port', 'must be between 0 and 65535, ' . $port . ' given.');
        }

        $new = clone $this;
        $new->port = $port;
        $new->compiledString = null;

        $new->dropDefaultPort();
        if ($new->getAuthority() === '' && str_starts_with($new->getPath(), '//'))
        {
            throw $this->createInvalidArgumentException('withPort', 1, 'port', 'affects to be a non-authority URI and it conflicts with its path policy.');
        }

        return $new;
    }

    /**
     * Get path.
     *
     * @return string
     */
    public function getPath (): string
    {
        return $this->path;
    }

    /**
     * Get query string.
     *
     * @return string
     */
    public function getQuery (): string
    {
        return $this->query;
    }

    /**
     * Get fragment string.
     *
     * @return string
     */
    public function getFragment (): string
    {
        return $this->fragment;
    }

    /**
     * Get scheme.
     *
     * @return string
     */
    public function getScheme (): string
    {
        return $this->scheme;
    }

    /**
     * Get user information.
     *
     * @return string
     */
    public function getUserInfo (): string
    {
        return $this->userInfo;
    }

    /**
     * Get authority string.
     *
     * @return string
     */
    public function getAuthority (): string
    {
        $authority = $this->host;
        if ($this->userInfo !== '')
        {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null)
        {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * Get host.
     *
     * @return string
     */
    public function getHost (): string
    {
        return $this->host;
    }

    /**
     * Get port number.
     *
     * @return int|null
     */
    public function getPort (): ?int
    {
        return $this->port;
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
        return new TypeError('Socodo\\Http\\Uri::' . $methodName . '() Argument #' . $argumentPos . ' ($' . $argumentName . ') must be of type ' . $expectedType . ', ' . $givenType . ' given.');
    }

    /**
     * Create an invalid argument exception.
     *
     * @param string $methodName
     * @param int $argumentPos
     * @param string $argumentName
     * @param string $message
     * @return InvalidArgumentException
     * @noinspection PhpSameParameterValueInspection
     */
    private function createInvalidArgumentException (string $methodName, int $argumentPos, string $argumentName, string $message): InvalidArgumentException
    {
        return new InvalidArgumentException('Socodo\\Http\\Uri::' . $methodName . '() Argument #' . $argumentPos . '(#' . $argumentName . ') ' . $message);
    }
}