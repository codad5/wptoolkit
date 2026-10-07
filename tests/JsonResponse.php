<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests;

use RuntimeException;

/**
 * Thrown by the test doubles of wp_send_json_success / wp_send_json_error.
 */
final class JsonResponse extends RuntimeException
{
    public function __construct(
        public bool $success,
        public mixed $data,
        public int $status
    ) {
        parent::__construct('JSON response sent');
    }

    public function json(): string
    {
        return (string) json_encode(['success' => $this->success, 'data' => $this->data]);
    }
}
