<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Http;

use Codad5\WPToolkit\Exceptions\HttpException;

/**
 * Sends HTTP requests (ADR-0004). Any status code is a response; only a failure to get one (DNS,
 * timeout, TLS) is an exception.
 */
interface HttpClient
{
    /**
     * @throws HttpException When no response was received.
     */
    public function send(HttpRequest $request): HttpResponse;
}
