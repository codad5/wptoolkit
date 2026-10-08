<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\RateLimit;

/**
 * The client's IP address, for rate-limit keys.
 *
 * Uses REMOTE_ADDR. X-Forwarded-For is trusted only when REMOTE_ADDR is one of the configured
 * trusted proxies — otherwise any client could set it and pick its own rate-limit bucket. Through
 * trusted proxies, the first address from the right that isn't itself a trusted proxy is the client.
 */
final class ClientIp
{
    /**
     * @param list<string> $trustedProxies Exact IP addresses of your load balancers / CDN edges.
     */
    public function __construct(private readonly array $trustedProxies = [])
    {
    }

    /**
     * @param array<string, mixed> $server Usually $_SERVER.
     */
    public function resolve(array $server): string
    {
        $remote = $this->valid($server['REMOTE_ADDR'] ?? null) ?? '0.0.0.0';

        if (!in_array($remote, $this->trustedProxies, true)) {
            return $remote;
        }

        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? '';
        $hops = array_reverse(array_map('trim', explode(',', is_string($forwarded) ? $forwarded : '')));
        foreach ($hops as $hop) {
            $ip = $this->valid($hop);
            if ($ip !== null && !in_array($ip, $this->trustedProxies, true)) {
                return $ip;
            }
        }

        return $remote;
    }

    private function valid(mixed $ip): ?string
    {
        if (!is_string($ip)) {
            return null;
        }

        $valid = filter_var($ip, FILTER_VALIDATE_IP);

        return is_string($valid) ? $valid : null;
    }
}
