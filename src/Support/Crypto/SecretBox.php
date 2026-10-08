<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Support\Crypto;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Authenticated encryption for values stored in the database (libsodium secretbox), so a database
 * dump alone doesn't reveal them (ADR-0021). Values are tagged, so plaintext stored before
 * encryption was switched on still reads.
 *
 * The key must stay stable: if it changes (e.g. WordPress salts are rotated), open() returns null
 * and the value has to be entered again.
 */
final class SecretBox
{
    private const TAG = 'wptk:enc:v1:';

    private readonly string $key;

    public function __construct(string $keyMaterial)
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new InvalidConfigException('Encrypted settings need the PHP sodium extension.');
        }
        if ($keyMaterial === '') {
            throw new InvalidConfigException('An encryption key cannot be empty.');
        }

        $this->key = sodium_crypto_generichash($keyMaterial, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function isSealed(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::TAG);
    }

    public function seal(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::TAG . base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    /**
     * @return string|null The plaintext, or null when the value was sealed with another key or is corrupt.
     */
    public function open(string $sealed): ?string
    {
        $raw = base64_decode(substr($sealed, strlen(self::TAG)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $plaintext = sodium_crypto_secretbox_open(
            substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->key
        );

        return $plaintext === false ? null : $plaintext;
    }
}
