<?php

declare(strict_types=1);

require_once __DIR__ . '/MarkerProvider.php';

\{{NS}}\Foundation\Application::create(dirname(__DIR__) . '/plugin.php', [
    'slug' => '{{NAME}}',
    'name' => '{{TITLE}}',
])
    ->providers([\Fixture\{{ID}}\MarkerProvider::class])
    ->boot();
