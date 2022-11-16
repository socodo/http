<?php

namespace Socodo\Http\Interfaces;

interface CookieJarInterface
{
    /**
     * Get all cookies.
     *
     * @return array>string, string>
     */
    public function getCookies (): array;

    /**
     * Get a cookie.
     *
     * @param string $key
     * @return string|null
     */
    public function getCookie (string $key): ?string;

    /**
     * Set cookies with PSR-7 compatible header array.
     *
     * @param array $setCookieHeaders
     * @return void
     */
    public function setCookies (array $setCookieHeaders): void;

    /**
     * Set a cookie with PSR-7 compatible header line.
     *
     * @param string $setCookieHeader
     * @return void
     */
    public function setCookie (string $setCookieHeader): void;

    /**
     * Save a cookie data.
     *
     * @return void
     */
    public function save (): void;
}