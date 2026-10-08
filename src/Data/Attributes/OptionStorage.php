<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Attributes;

use Attribute;

/**
 * Store every entity of this class in one option `{slug}_{key}`. For small collections (tens of
 * records), such as saved presets or integrations — not for data that grows with traffic.
 *
 *     #[OptionStorage('presets')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class OptionStorage
{
    public function __construct(public readonly string $key, public readonly bool $autoload = false)
    {
    }
}
