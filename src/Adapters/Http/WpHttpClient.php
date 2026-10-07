<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Http;

use Codad5\WPToolkit\Contracts\Http\HttpClient;
use Codad5\WPToolkit\Contracts\Http\HttpRequest;
use Codad5\WPToolkit\Contracts\Http\HttpResponse;
use Codad5\WPToolkit\Exceptions\HttpException;

/**
 * HTTP through WordPress's own transport (wp_remote_request), so site-level proxy settings,
 * `pre_http_request` mocking and SSL configuration all apply. TLS verification stays on.
 */
final class WpHttpClient implements HttpClient
{
    public function send(HttpRequest $request): HttpResponse
    {
        $args = [
            'method' => $request->method,
            'headers' => $request->sentHeaders(),
            'timeout' => $request->timeout,
            'redirection' => 3,
        ];
        $body = $request->encodedBody();
        if ($body !== null) {
            $args['body'] = $body;
        }

        $result = wp_remote_request($request->url, $args);

        if (is_wp_error($result)) {
            throw HttpException::transport($request, $result->get_error_message());
        }

        $headers = [];
        foreach ((array) wp_remote_retrieve_headers($result) as $name => $value) {
            $headers[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($result),
            $headers,
            (string) wp_remote_retrieve_body($result)
        );
    }
}
