<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http\Client;

use Codad5\WPToolkit\Contracts\Http\HttpRequest;

/**
 * How ApiClient authenticates each request (Strategy).
 */
abstract class Auth
{
    abstract public function apply(HttpRequest $request): HttpRequest;

    public static function bearer(string $token): self
    {
        return new class ($token) extends Auth {
            public function __construct(private readonly string $token)
            {
            }

            public function apply(HttpRequest $request): HttpRequest
            {
                return $request->withHeader('Authorization', 'Bearer ' . $this->token);
            }
        };
    }

    public static function basic(string $username, string $password): self
    {
        return new class ($username, $password) extends Auth {
            public function __construct(private readonly string $username, private readonly string $password)
            {
            }

            public function apply(HttpRequest $request): HttpRequest
            {
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
                return $request->withHeader('Authorization', 'Basic ' . base64_encode($this->username . ':' . $this->password));
            }
        };
    }

    public static function header(string $name, string $value): self
    {
        return new class ($name, $value) extends Auth {
            public function __construct(private readonly string $name, private readonly string $value)
            {
            }

            public function apply(HttpRequest $request): HttpRequest
            {
                return $request->withHeader($this->name, $this->value);
            }
        };
    }
}
