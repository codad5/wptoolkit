<?php

/**
 * Print, then clear, wp-content/debug.log. Usage (WP-CLI): wp eval-file wp-content/e2e-bin/debug-log.php
 */

$log = WP_CONTENT_DIR . '/debug.log';
if (is_readable($log)) {
    echo file_get_contents($log);
    file_put_contents($log, '');
}
