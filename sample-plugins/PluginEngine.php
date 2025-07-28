<?php

namespace Codad5\SamplePlugins;

abstract class PluginEngine {

	/**
	 * Track whether this instance has already been run
	 */
	private bool $hasRun = false;

	// private constructor to prevent instantiation
	final protected function __construct() {
	}

	abstract public static function getInstance(): static;

	abstract protected function run(): static;

	/**
	 * Check if this instance has already been run
	 */
	final public function hasRun(): bool {
		return $this->hasRun;
	}

	/**
	 * Mark this instance as having been run
	 */
	final protected function markAsRun(): void {
		$this->hasRun = true;
	}

	/**
	 * Prevent cloning of the instance
	 */
	final public function __clone() {

	}

	// static method to run a particular instance of the plugin
	final public static function runInstance(PluginEngine $instance): static {
		if ($instance->hasRun()) {
			return $instance; // Return without running if already run
		}

		$result = $instance->run();
		$instance->markAsRun();
		return $result;
	}
}