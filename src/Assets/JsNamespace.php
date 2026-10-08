<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Assets;

use Codad5\WPToolkit\Foundation\Identity;

/**
 * Inline JavaScript that writes a plugin's data to `window.wptoolkit[slug]` (ADR-0006).
 *
 * The namespace object is created once and locked — no script can replace `window.wptoolkit`, the
 * same lock `resources/js/client.js` applies — and each plugin's entry is merged, never replaced, so
 * two plugins (or two scripts of one plugin) can't wipe each other's data. Every entry carries the
 * `toolkitVersion` of the copy that wrote it; there is no single site-wide version.
 */
final class JsNamespace
{
    /** JSON that is safe inside a <script> element. */
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES;

    public function __construct(private readonly Identity $identity, private readonly string $toolkitVersion)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function merge(array $data): string
    {
        return sprintf(
            '(function(w,n,k,d){if(!Object.prototype.hasOwnProperty.call(w,n)){'
            . 'Object.defineProperty(w,n,{value:{},writable:false,configurable:false,enumerable:true});}'
            . 'w[n][k]=Object.assign(w[n][k]||{},d);})(window,%s,%s,%s);',
            $this->json(Identity::JS_NAMESPACE),
            $this->json($this->identity->jsKey()),
            $this->json(['toolkitVersion' => $this->toolkitVersion] + $data)
        );
    }

    /**
     * Create this plugin's API client from the client factory registered for this toolkit version.
     */
    public function client(): string
    {
        return sprintf(
            '(function(w,n,k,v){var e=w[n][k];if(e&&e.__api&&w[n].__clients&&w[n].__clients[v]){e.api=w[n].__clients[v](e.__api);}})(window,%s,%s,%s);',
            $this->json(Identity::JS_NAMESPACE),
            $this->json($this->identity->jsKey()),
            $this->json($this->toolkitVersion)
        );
    }

    private function json(mixed $value): string
    {
        return (string) wp_json_encode($value, self::JSON_FLAGS);
    }
}
