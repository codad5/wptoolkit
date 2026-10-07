<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Assets;

use Closure;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * One script or stylesheet, described fluently and enqueued later by the AssetManager:
 *
 *     $assets->script('admin', 'build/admin.js')->in(Asset::ADMIN)->when(fn (string $hook) => $hook === 'toplevel_page_books')
 *         ->with(['perPage' => 20]);
 *     $assets->style('front', 'build/front.css');
 */
final class Asset
{
    public const FRONT = 'front';
    public const ADMIN = 'admin';
    public const LOGIN = 'login';

    /** @var list<string> */
    public array $contexts = [self::FRONT];

    /** @var list<string> */
    public array $deps = [];

    public ?string $version = null;

    public ?Closure $condition = null;

    /** @var array<string, mixed> */
    public array $data = [];

    public bool $inFooter = true;

    public string $media = 'all';

    /**
     * @param 'script'|'style' $type
     * @param string $src A path relative to the plugin's directory, or a full URL.
     */
    public function __construct(public readonly string $type, public readonly string $name, public readonly string $src)
    {
        if (preg_match('/^[a-z0-9][a-z0-9_\-.]*$/', $name) !== 1) {
            throw new InvalidConfigException(sprintf('Asset name "%s" may contain only lowercase letters, digits, "-", "_" and ".".', $name));
        }
    }

    /**
     * Where it loads: Asset::FRONT (default), Asset::ADMIN and/or Asset::LOGIN.
     */
    public function in(string ...$contexts): self
    {
        foreach ($contexts as $context) {
            if (!in_array($context, [self::FRONT, self::ADMIN, self::LOGIN], true)) {
                throw new InvalidConfigException(sprintf('Unknown asset context "%s"; use front, admin or login.', $context));
            }
        }
        $this->contexts = array_values(array_unique($contexts));

        return $this;
    }

    /**
     * Only enqueue when this returns true. It receives the admin page's hook suffix ('' elsewhere).
     *
     * @param Closure(string): bool $condition
     */
    public function when(Closure $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Extra dependencies (handles), added to those in a `*.asset.php` manifest.
     */
    public function dependsOn(string ...$handles): self
    {
        $this->deps = array_values(array_unique([...$this->deps, ...$handles]));

        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Data for scripts, merged into `window.wptoolkit[slug]` before the script runs. Never put secrets
     * here — it is printed into the page (Settings refuses sensitive fields for this reason).
     *
     * @param array<string, mixed> $data
     */
    public function with(array $data): self
    {
        $this->data = array_replace($this->data, $data);

        return $this;
    }

    public function inHeader(): self
    {
        $this->inFooter = false;

        return $this;
    }

    public function media(string $media): self
    {
        $this->media = $media;

        return $this;
    }
}
