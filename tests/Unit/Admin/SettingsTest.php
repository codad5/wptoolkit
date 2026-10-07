<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Admin\Settings\Settings;
use Codad5\WPToolkit\Admin\Settings\SettingsForm;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Http\Route;
use Codad5\WPToolkit\Support\Crypto\SecretBox;
use Codad5\WPToolkit\Tests\Support\FakeOptions;
use Codad5\WPToolkit\Tests\TestCase;

final class SettingsTest extends TestCase
{
    private const SLUG = 'pau-alumni-manager';

    private FakeOptions $wp;

    private FieldFactory $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wp = new FakeOptions();
        $this->wp->install();
        $this->f = new FieldFactory();
        Functions\stubs(['sanitize_email' => static fn ($v) => (string) $v, 'esc_url_raw' => static fn ($v) => (string) $v]);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    // --- 0.x data (ADR-0016) -------------------------------------------------------------------

    public function test_reads_what_0x_stored_for_pau(): void
    {
        // 0.x Settings: one option per setting, '{slug}_' . sanitize_key($key); checkbox stored as bool.
        $this->store('api_base_url', 'https://api.example.test');
        $this->store('items_per_page', 20);
        $this->store('auto_refresh', '1');
        $this->store('show_unapproved', '');

        $settings = $this->pau();

        self::assertSame('https://api.example.test', $settings->get('api_base_url'));
        self::assertSame(20, $settings->get('items_per_page'));
        self::assertTrue($settings->get('auto_refresh'));
        self::assertFalse($settings->get('show_unapproved'), "0.x's false is '' — a value, not missing");
        self::assertSame('pau-alumni-manager_api_key', $settings->optionKey('api_key'));
    }

    public function test_missing_settings_fall_back_to_defaults(): void
    {
        self::assertSame(20, $this->pau()->get('items_per_page'));
        self::assertTrue($this->pau()->get('auto_refresh'));
    }

    // --- Sensitive settings (ADR-0021) ---------------------------------------------------------

    /**
     * pau commit a2d31c3: a public /settings route returned $settings->getAll(), which included the
     * API key. The same route on 1.0 cannot leak it.
     */
    public function test_pau_settings_route_does_not_return_the_api_key(): void
    {
        $this->store('api_base_url', 'https://api.example.test');
        $this->store('api_key', 'sk_live_123');
        $settings = $this->pau();
        $route = (new Route(['GET'], 'settings', static fn () => $settings->all()))->public();

        $response = (new Dispatcher(new Container(), new Identity(self::SLUG), new ArrayLogger()))
            ->dispatch($route, new Request('GET', 'rest'));

        self::assertSame(200, $response->status);
        self::assertIsArray($response->data);
        self::assertArrayNotHasKey('api_key', $response->data);
        self::assertStringNotContainsString('sk_live_123', (string) json_encode($response->data));
        self::assertSame('sk_live_123', $settings->get('api_key'), 'still readable by name');
    }

    public function test_sensitive_settings_cannot_be_sent_to_the_browser(): void
    {
        $settings = $this->pau();
        self::assertSame(['items_per_page' => 20], $settings->forScript('items_per_page'));

        $this->expectException(InvalidConfigException::class);
        $settings->forScript('items_per_page', 'api_key');
    }

    public function test_the_logger_redacts_sensitive_settings_by_name(): void
    {
        $logger = new ArrayLogger();
        new Settings([$this->f->text('webhook')->sensitive()], new FieldTypes(), new Identity(self::SLUG), logger: $logger);

        $logger->info('Calling {url}', ['url' => 'https://x.test', 'webhook' => 'https://hooks.test/abc']);

        self::assertSame('[redacted]', $logger->records[0]['context']['webhook']);
        self::assertSame('https://x.test', $logger->records[0]['context']['url']);
    }

    public function test_encrypted_settings_are_sealed_at_rest_and_unreadable_with_another_key(): void
    {
        $this->requireSodium();
        $fields = [$this->f->password('api_key')->sensitive()->with('encrypt', true)];
        $settings = new Settings($fields, new FieldTypes(), new Identity(self::SLUG), new SecretBox('salt-one'));

        self::assertSame([], $settings->set('api_key', 'sk_live_123'));
        $stored = $this->wp->value('pau-alumni-manager_api_key');
        self::assertIsString($stored);
        self::assertStringNotContainsString('sk_live_123', $stored);
        self::assertSame('sk_live_123', $settings->get('api_key'));

        $rotated = new Settings($fields, new FieldTypes(), new Identity(self::SLUG), new SecretBox('salt-two'));
        self::assertNull($rotated->get('api_key'));
        self::assertTrue($rotated->isUnreadable('api_key'));

        $this->expectException(InvalidConfigException::class);
        new Settings($fields, new FieldTypes(), new Identity(self::SLUG));
    }

    public function test_plaintext_from_before_encryption_still_reads(): void
    {
        $this->requireSodium();
        $this->store('api_key', 'sk_plain');
        $settings = new Settings([$this->f->password('api_key')->sensitive()->with('encrypt', true)], new FieldTypes(), new Identity(self::SLUG), new SecretBox('k'));

        self::assertSame('sk_plain', $settings->get('api_key'));
    }

    // --- Writing --------------------------------------------------------------------------------

    public function test_set_validates_then_stores_sanitized_values(): void
    {
        $settings = $this->pau();

        self::assertSame(['API Base URL must be a valid http(s) URL.'], $settings->set('api_base_url', 'not a url'));
        self::assertNull($this->wp->value('pau-alumni-manager_api_base_url'));

        self::assertSame([], $settings->set('items_per_page', '50'));
        self::assertSame(50, $this->wp->value('pau-alumni-manager_items_per_page'));
    }

    public function test_upcasters_turn_old_shapes_into_the_current_one(): void
    {
        $this->store('items_per_page', 'twenty');
        $settings = $this->pau()->upcast('items_per_page', static fn (mixed $old): mixed => $old === 'twenty' ? 20 : $old);

        self::assertSame(20, $settings->get('items_per_page'));
    }

    // --- The admin form -------------------------------------------------------------------------

    public function test_a_blank_secret_keeps_the_saved_one_and_clear_removes_it(): void
    {
        $this->store('api_key', 'sk_live_123');
        $form = $this->form();
        $apiKey = $this->pau()->field('api_key');

        self::assertSame('sk_live_123', $form->sanitize($apiKey, ''));

        $_POST['pau-alumni-manager_settings_clear'] = ['api_key'];
        self::assertSame('', $form->sanitize($apiKey, ''));
    }

    public function test_an_invalid_submission_keeps_the_old_value_and_reports_why(): void
    {
        $this->store('api_base_url', 'https://old.example.test');
        $errors = [];
        Functions\when('add_settings_error')->alias(static function (string $setting, string $code, string $message) use (&$errors) {
            $errors[] = $message;
        });

        $kept = $this->form()->sanitize($this->pau()->field('api_base_url'), 'javascript:alert(1)');

        self::assertSame('https://old.example.test', $kept);
        self::assertSame(['API Base URL must be a valid http(s) URL.'], $errors);
    }

    public function test_an_already_sealed_value_passes_the_second_sanitize_untouched(): void
    {
        $this->requireSodium();
        $sealed = (new SecretBox('k'))->seal('sk');

        self::assertSame($sealed, $this->form()->sanitize($this->pau()->field('api_key'), $sealed));
    }

    private function requireSodium(): void
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            // WordPress always has it (sodium_compat); CI's unit jobs load ext-sodium explicitly.
            self::markTestSkipped('ext-sodium is not loaded in this PHP; run with -d extension=sodium.');
        }
    }

    private function pau(): Settings
    {
        $f = $this->f;

        return new Settings([
            $f->url('api_base_url')->label('API Base URL')->required()->with('group', 'api'),
            $f->password('api_key')->label('API Key')->sensitive()->with('group', 'api'),
            $f->number('items_per_page')->default(20)->rules('min:5|max:100')->with('group', 'display'),
            $f->checkbox('auto_refresh')->default(true)->with('group', 'display'),
            $f->checkbox('show_unapproved')->with('group', 'display'),
        ], new FieldTypes(), new Identity(self::SLUG));
    }

    private function form(): SettingsForm
    {
        return new SettingsForm($this->pau(), new FieldTypes(), new Identity(self::SLUG));
    }

    private function store(string $key, mixed $value): void
    {
        $this->wp->options[self::SLUG . '_' . $key] = ['value' => $value, 'autoload' => true];
    }
}
