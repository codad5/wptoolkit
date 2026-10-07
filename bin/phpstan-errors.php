<?php

/**
 * Print PHPStan's JSON report one error per line: `file:line message [identifier]`.
 *
 * Usage: vendor/bin/phpstan analyse --error-format=json | php bin/phpstan-errors.php
 */

declare(strict_types=1);

$json = json_decode((string) stream_get_contents(STDIN), true);

foreach ($json['files'] ?? [] as $file => $data) {
    foreach ($data['messages'] as $message) {
        $identifier = $message['identifier'] ?? '';
        echo basename($file), ':', $message['line'], ' ', $message['message'], " [{$identifier}]\n";
    }
}

foreach ($json['errors'] ?? [] as $error) {
    echo $error, "\n";
}
