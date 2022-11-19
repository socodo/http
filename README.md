# socodo/http
![GitHub](https://img.shields.io/github/license/socodo/http)

HTTP component for socodo. Compatible with PSR-7, semi-compatible with PSR-18.

## Getting Started

```shell
$ composer require socodo/http
```

```php
use Socodo\Http\ServerRequest;
use Socodo\Http\Request;
use Socodo\Http\Enums\HttpMethods;
use Socodo\Http\Uri;
use Socodo\Http\Client;

$serverRequest = ServerRequest::fromGlobals();
$target = $serverRequest->getRequestTarget();

$uri = new Uri('https://example.com/');
$request = new Request(HttpMethods::GET, $uri->withPath($target));

$client = new Client();
$response = $client->sendRequest($request);
```

### ClientConfig
```php
use Socodo\Http\ClientConfig;
use Socodo\Http\Client;
use Socodo\Http\Request;
use Socodo\Http\Enums\HttpMethods;
use Socodo\Http\Uri;

$clientConfig = new ClientConfig();
$clientConfig->headers = [
    'X-Foobar' => 'Foobar-Header'
];

$client = new Client($clientConfig);
$client->sendRequest($request = new Request(HttpMethods::GET, new Uri('https://example.com/')));
// X-Foobar: Foobar-Header

$request = $request->withAddedHeader('X-Foobar', 'Foobar-First');
$client->sendRequest($request);
// X-Foobar: Foobar-First
// > Request has higher priority.


$clientConfig->headers = [
    'X-Foobar' => [ 'Foobar-Second', 'Foobar-Third' ]
];
$client->sendRequest($request);
// X-Foobar: Foobar-First
// X-Foobar: Foobar-Second
// X-Foobar: Foobar-Third
// > Merge if the config value is array. 
```

### CookieJar
```php
use Socodo\Http\ClientConfig;
use Socodo\Http\CookieJars\StreamCookieJar;
use Socodo\Http\Stream;
use Socodo\Http\Client;
use Socodo\Http\Request;
use Socodo\Http\Enums\HttpMethods;
use Socodo\Http\Uri;

$stream = new Stream(fopen('filepath', 'w+'));
$cookieJar = new StreamCookieJar($stream);

$clientConfig = new ClientConfig();
$clientConfig->cookieJar = $cookieJar;
$client = new Client($clientConfig);
$client->sendRequest(new Request(HttpMethods::GET, new Uri('https://example.com/')));

$stream->close();
```