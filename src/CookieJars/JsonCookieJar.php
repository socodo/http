<?php

namespace Socodo\Http\CookieJars;

use Socodo\Http\Interfaces\CookieJarInterface;

class JsonCookieJar implements CookieJarInterface
{
    /** @var string JSON string reference. */
    protected string $json;

    /** @var array<string, array> Cookie storage. */
    protected array $cookies;

    /**
     * Constructor.
     *
     * @param string $json
     */
    public function __construct (string &$json)
    {
        $this->json = $json;
        $this->cookies = json_decode($json, true) ?? [];
    }

    /**
     * Get all cookies.
     *
     * @return array
     */
    public function getCookies (): array
    {
        return $this->cookies;
    }

    /**
     * Get a cookie.
     *
     * @param string $key
     * @return string|null
     */
    public function getCookie (string $key): ?string
    {
        $arr = $this->cookies[$key];
        return $arr ? $arr['value'] : null;
    }

    /**
     * Set cookies.
     *
     * @param array $setCookieHeaders
     * @return void
     */
    public function setCookies (array $setCookieHeaders): void
    {
        foreach ($setCookieHeaders as $header)
        {
            $this->setCookie($header);
        }
    }

    /**
     * Set a cookie.
     *
     * @param string $setCookieHeader
     * @return void
     */
    public function setCookie (string $setCookieHeader): void
    {
        $segments = explode(';', $setCookieHeader);
        [ $key, $value ] = explode('=', array_shift($segments), 2);
        $options = [
            'expires' => null,
            'domain' => null,
            'path' => null,
            'secure' => false,
            'httpOnly' => false,
            'sameSite' => 'Lax'
        ];

        foreach ($segments as $segment)
        {
            $seg = explode('=', $segment);
            $optKey = trim(strtolower($seg[0]));

            if ($optKey === 'secure')
            {
                $options['secure'] = true;
            }
            elseif ($optKey === 'httponly')
            {
                $options['httpOnly'] = true;
            }
            elseif ($optKey === 'samesite')
            {
                $options['sameSite'] = ucfirst(strtolower($seg[1]));
            }
            elseif ($optKey === 'domain')
            {
                $options['domain'] = $seg[1];
            }
            elseif ($optKey === 'path')
            {
                $options['path'] = $seg[1];
            }
            elseif ($optKey === 'expires')
            {
                $options['expires'] = strtotime($seg[1]);
            }
            elseif ($optKey === 'max-age')
            {
                $options['expires'] = time() + ((int) $seg[1]);
            }
        }

        $this->cookies[$key] = [
            'value' => $value,
            'options' => $options
        ];
    }

    /**
     * Save to string.
     *
     * @return void
     */
    public function save (): void
    {
        $this->json = json_encode($this->cookies);
    }
}