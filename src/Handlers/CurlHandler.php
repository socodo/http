<?php

namespace Socodo\Http\Handlers;

use CurlHandle;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Socodo\Http\ClientConfig;
use Socodo\Http\Interfaces\HandlerInterface;
use Socodo\Http\Response;
use Socodo\Http\Stream;

class CurlHandler implements HandlerInterface
{
    /** @var CurlHandle CURL handler. */
    protected CurlHandle $handle;

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct ()
    {
        $this->handle = curl_init();
        $this->dropOptions();
    }

    /**
     * Handle request.
     *
     * @param RequestInterface $request
     * @param ClientConfig $config
     * @return ResponseInterface
     */
    public function handle (RequestInterface $request, ClientConfig $config): ResponseInterface
    {
        $uri = $request->getUri();
        curl_setopt($this->handle, CURLOPT_URL, $uri->getScheme() . '://' . $uri->getAuthority() . '/' . $request->getRequestTarget());
        curl_setopt($this->handle, CURLOPT_FOLLOWLOCATION, $config->allowRedirections);
        curl_setopt($this->handle, CURLOPT_CUSTOMREQUEST, $request->getMethod());

        $headers = [];
        foreach ($config->headers as $key => $val)
        {
            $headers[$this->toLowerLatin($key)] = $val;
        }

        foreach ($request->getHeaders() as $key => $val)
        {
            $key = $this->toLowerLatin($key);
            if (isset($headers[$key]))
            {
                if (is_array($headers[$key]) && is_array($val))
                {
                    foreach ($val as $item)
                    {
                        if (!in_array($item, $headers[$key]))
                        {
                            $headers[$key][] = $item;
                        }
                    }

                    continue;
                }
            }

            $headers[$key] = $val;
        }

        if ($config->cookieJar !== null)
        {
            $cookie = [];
            if (isset($headers['cookie']))
            {
                $cookie = explode(';', $headers['cookie']);
            }
            foreach ($config->cookieJar->getCookies() as $key => $val)
            {
                $cookie[] = $key . '=' . $val['value'];
            }

            $headers['cookie'] = implode('; ', $cookie);
        }

        $this->setHeaders($headers);

        $output = $this->execute();
        $response = (new Response())->withStatus($output['status'])->withBody(new Stream($output['body']));
        foreach ($output['headers'] as $key => $val)
        {
            $response = $response->withAddedHeader($key, $val);
        }

        $config->cookieJar?->setCookies($response->getHeader('set-cookie'));
        $config->cookieJar?->save();
        return $response;
    }

    /**
     * Drop all curl set-opt settings.
     *
     * @return void
     */
    protected function dropOptions (): void
    {
        curl_reset($this->handle);

        curl_setopt($this->handle, CURLINFO_HEADER_OUT, false);
        curl_setopt($this->handle, CURLOPT_HTTP_CONTENT_DECODING, true);
        curl_setopt($this->handle, CURLOPT_NOPROGRESS, true);
        curl_setopt($this->handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->handle, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Set headers.
     *
     * @param array $headers
     * @return void
     */
    protected function setHeaders (array $headers): void
    {
        $headerStringArr = [];

        foreach ($headers as $key => $val)
        {
            if (!is_array($val))
            {
                $val = [ $val ];
            }

            foreach ($val as $item)
            {
                $headerStringArr[] = $key . ': ' . $item;
            }
        }

        curl_setopt($this->handle, CURLOPT_HTTPHEADER, $headerStringArr);
    }

    /**
     * Execute CURL.
     *
     * @return array
     */
    protected function execute (): array
    {
        $headers = [];
        curl_setopt($this->handle, CURLOPT_HEADERFUNCTION, static function ($ch, $header) use (&$headers) {
            $len = strlen($header);

            $header = explode(':', $header, 2);
            if (count($header) < 2)
            {
                return $len;
            }

            $key = strtr(trim($header[0]), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
            if (!isset($headers[$key]))
            {
                $headers[$key] = [];
            }
            $headers[$key][] = trim($header[1]);

            return $len;
        });

        return [
            'status' => curl_getinfo($this->handle, CURLINFO_HTTP_CODE),
            'body' => curl_exec($this->handle),
            'headers' => $headers,
        ];
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
}