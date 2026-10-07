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
        public string $post_excerpt = '';

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

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @var array<string, array<string, mixed>> */
        private array $params = ['URL' => [], 'GET' => [], 'POST' => [], 'JSON' => [], 'FILES' => []];

        /** @var array<string, list<string>> */
        private array $headers = [];

        public function __construct(private string $method = 'GET', private string $route = '')
        {
        }

        public function get_method(): string
        {
            return $this->method;
        }

        public function get_route(): string
        {
            return $this->route;
        }

        /** @param array<string, mixed> $params */
        public function set_url_params(array $params): void
        {
            $this->params['URL'] = $params;
        }

        /** @param array<string, mixed> $params */
        public function set_query_params(array $params): void
        {
            $this->params['GET'] = $params;
        }

        /** @param array<string, mixed> $params */
        public function set_body_params(array $params): void
        {
            $this->params['POST'] = $params;
        }

        /** @param array<string, mixed> $params */
        public function set_json_params(array $params): void
        {
            $this->params['JSON'] = $params;
        }

        public function set_header(string $name, string $value): void
        {
            $this->headers[strtolower(str_replace('-', '_', $name))] = [$value];
        }

        /** @return array<string, mixed> */
        public function get_url_params(): array
        {
            return $this->params['URL'];
        }

        /** @return array<string, mixed> */
        public function get_query_params(): array
        {
            return $this->params['GET'];
        }

        /** @return array<string, mixed> */
        public function get_body_params(): array
        {
            return $this->params['POST'];
        }

        /** @return array<string, mixed>|null */
        public function get_json_params(): ?array
        {
            return $this->params['JSON'] ?: null;
        }

        /** @return array<string, mixed> */
        public function get_file_params(): array
        {
            return $this->params['FILES'];
        }

        /** @return array<string, list<string>> */
        public function get_headers(): array
        {
            return $this->headers;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var array<string, string> */
        public array $headers = [];

        public function __construct(public mixed $data = null, public int $status = 200)
        {
        }

        public function header(string $name, string $value): void
        {
            $this->headers[$name] = $value;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function get_data(): mixed
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        /** @var WP_Post[] */
        public array $posts = [];
    }
}
