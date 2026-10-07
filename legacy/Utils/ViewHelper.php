<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * View helper class providing template functionality within views.
 *
 * This class is instantiated and passed to views as the $view variable,
 * providing access to template loading, sections, and inheritance features.
 */
class ViewHelper
{
	private ?string $default_base_path;
	private string $default_plugin_prefix;
	private bool $overridable = false;

	public function __construct(?string $base_path, string $plugin_prefix, bool $overridable = false)
	{
		$this->default_base_path = $base_path;
		$this->default_plugin_prefix = $plugin_prefix;
		$this->overridable = $overridable;
	}

	public function load(string $view, array $data = [], ?string $base_path = null, bool $overridable = null, ?string $plugin_prefix = null): string
	{
		$overridable = $overridable === null ? $this->overridable : $overridable;
		return ViewLoader::load(
			$view,
			$data,
			false,
			$base_path ?? $this->default_base_path,
			$overridable,
			$plugin_prefix ?? $this->default_plugin_prefix
		) ?: '';
	}

	public function include(string $view, array $data = [], ?string $base_path = null, bool $overridable = null, ?string $plugin_prefix = null): void
	{
		$overridable = $overridable === null ? $this->overridable : $overridable;
		ViewLoader::load(
			$view,
			$data,
			true,
			$base_path ?? $this->default_base_path,
			$overridable,
			$plugin_prefix ?? $this->default_plugin_prefix
		);
	}

	public function load_overridable(string $view, array $data = []): string
	{
		return ViewLoader::load(
			$view,
			$data,
			false,
			$this->default_base_path,
			true,
			$this->default_plugin_prefix
		) ?: '';
	}

	public function include_overridable(string $view, array $data = []): void
	{
		ViewLoader::load(
			$view,
			$data,
			true,
			$this->default_base_path,
			true,
			$this->default_plugin_prefix
		);
	}

	/**
	 * @throws \Exception
	 */
	public function section(string $name): void
	{
		ViewLoader::start_section($name);
	}

	/**
	 * @throws \Exception
	 */
	public function end_section(): void
	{
		ViewLoader::end_section();
	}

	public function yield(string $name, string $default = ''): void
	{
		echo ViewLoader::get_section($name, $default);
	}
}