<?php

/**
 * Minimal stand-ins for the WordPress classes the unit tests touch.
 *
 * Unit tests run without WordPress (Brain Monkey mocks the functions); these
 * classes only carry data. Behaviour that depends on real WordPress belongs in
 * integration tests.
 */

declare(strict_types=1);

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /** @var array<string, string[]> */
        public array $errors = [];

        /** @var array<string, mixed> */
        public array $error_data = [];

        public function __construct(string $code = '', string $message = '', mixed $data = '')
        {
            if ($code !== '') {
                $this->errors[$code][] = $message;
                if ($data !== '') {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code(): string
        {
            return (string) (array_key_first($this->errors) ?? '');
        }

        public function get_error_message(): string
        {
            $code = $this->get_error_code();
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data(): mixed
        {
            return $this->error_data[$this->get_error_code()] ?? null;
        }
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;
        public string $post_type = 'post';
        public string $post_status = 'publish';
        public string $post_title = '';
        public string $post_content = '';

        /** @param array<string, mixed> $props */
        public function __construct(array $props = [])
        {
            foreach ($props as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_Post_Type')) {
    class WP_Post_Type
    {
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var WP_Post[] */
        public array $posts = [];
    }
}
