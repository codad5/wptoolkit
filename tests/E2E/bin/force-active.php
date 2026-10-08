<?php

/**
 * Set the active plugins directly, bypassing activation hooks — simulates a plugin that was already
 * active when its environment stopped meeting its requirements (e.g. the host downgraded PHP).
 *
 * Usage (WP-CLI): wp eval-file wp-content/e2e-bin/force-active.php plugin-a/plugin.php plugin-b/plugin.php
 *
 * @var array<int, string> $args Passed by `wp eval-file`.
 */

update_option('active_plugins', array_values($args));
echo implode(',', get_option('active_plugins')), "\n";
