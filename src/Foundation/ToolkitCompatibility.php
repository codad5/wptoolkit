<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Support\VersionConstraint;

/**
 * Checks the consumer's `requires_toolkit` constraint against the WPToolkit copy that is actually
 * loaded, and explains a conflict in terms an administrator can act on (ADR-0005, ADR-0020).
 *
 * With scoping this always passes — each plugin runs its own copy. It exists for unscoped installs,
 * where PHP loads one copy for everyone and the first one loaded wins. WordPress loads active
 * plugins in a fixed (alphabetical) order, so "activate this one first" is never offered as a fix.
 *
 * Messages translate: build them only at render time, after `init`.
 */
final class ToolkitCompatibility
{
    public function __construct(private readonly Config $config)
    {
    }

    public function isCompatible(): bool
    {
        $required = $this->required();

        return $required === null || VersionConstraint::satisfies(Application::VERSION, $required);
    }

    /**
     * One sentence: what is wrong, naming the plugin or theme whose copy was loaded.
     */
    public function problem(): string
    {
        $loadedFrom = Coexistence::thisCopyPath();
        $owner = CopyOwner::of($loadedFrom);

        if ($owner === null) {
            return sprintf(
                /* translators: 1: plugin name, 2: required version, 3: loaded version, 4: path of the loaded copy */
                __('%1$s needs WPToolkit %2$s, but WPToolkit %3$s was already loaded from %4$s.', 'wptoolkit'),
                $this->pluginName(),
                (string) $this->required(),
                Application::VERSION,
                $this->displayPath($loadedFrom)
            );
        }

        return sprintf(
            /* translators: 1: plugin name, 2: required version, 3: loaded version, 4: plugin type, 5: its name, 6: path */
            __('%1$s needs WPToolkit %2$s, but WPToolkit %3$s was already loaded by the %4$s "%5$s" (%6$s).', 'wptoolkit'),
            $this->pluginName(),
            (string) $this->required(),
            Application::VERSION,
            $this->ownerType($owner['type']),
            $owner['name'],
            $this->displayPath($loadedFrom)
        );
    }

    /**
     * What the administrator (or developer) can do about it, most practical first.
     *
     * @return list<string>
     */
    public function options(): array
    {
        $owner = CopyOwner::of(Coexistence::thisCopyPath());
        $other = $owner['name'] ?? __('the other plugin', 'wptoolkit');
        $loadedMajor = explode('.', Application::VERSION)[0] . '.x';

        return [
            sprintf(
                /* translators: 1: the other plugin or theme, 2: this plugin */
                __('Deactivate %1$s, then %2$s can start. The two cannot run together until one of them is updated.', 'wptoolkit'),
                $other,
                $this->pluginName()
            ),
            sprintf(
                /* translators: 1: this plugin, 2: WPToolkit major version such as 1.x, 3: the other plugin or theme */
                __('Install a version of %1$s built for WPToolkit %2$s, or update %3$s.', 'wptoolkit'),
                $this->pluginName(),
                $loadedMajor,
                $other
            ),
            __('Developers: ship a scoped copy of WPToolkit so each plugin uses its own and this conflict cannot happen.', 'wptoolkit'),
        ];
    }

    /**
     * The problem and the options as escaped HTML, for an admin notice or wp_die().
     */
    public function html(): string
    {
        $items = array_map(static fn (string $option): string => '<li>' . esc_html($option) . '</li>', $this->options());

        return sprintf(
            '<p>%1$s %2$s</p><p>%3$s</p><ul>%4$s</ul>',
            esc_html($this->problem()),
            esc_html__('It has not been started.', 'wptoolkit'),
            esc_html__('What you can do:', 'wptoolkit'),
            implode('', $items)
        );
    }

    public function pluginName(): string
    {
        $name = $this->config->get('name');

        return is_string($name) && $name !== '' ? $name : $this->config->slug;
    }

    private function required(): ?string
    {
        $required = $this->config->get('requires_toolkit');
        if ($required === null) {
            return null;
        }
        if (!is_string($required) || trim($required) === '') {
            throw new InvalidConfigException("'requires_toolkit' must be a version constraint string such as '^1.0'.");
        }

        return $required;
    }

    /**
     * @param 'plugin'|'mu-plugin'|'theme' $type
     */
    private function ownerType(string $type): string
    {
        return match ($type) {
            'plugin' => __('plugin', 'wptoolkit'),
            'mu-plugin' => __('must-use plugin', 'wptoolkit'),
            'theme' => __('theme', 'wptoolkit'),
        };
    }

    private function displayPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (defined('ABSPATH')) {
            $root = str_replace('\\', '/', (string) constant('ABSPATH'));
            if ($root !== '' && str_starts_with($path, $root)) {
                return substr($path, strlen($root));
            }
        }

        return $path;
    }
}
