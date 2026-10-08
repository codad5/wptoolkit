<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin;

use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Admin notices (ports 0.x `Notification`), stored per user — never by scanning the options table:
 *
 *     $notices->flash(__('Settings saved.', 'my-plugin'));                       // once, next admin page
 *     $notices->persistent('api-missing', __('Add your API key.', 'my-plugin'), NoticeType::Warning);  // until dismissed
 *
 * Messages may contain links and basic emphasis; everything else is stripped.
 */
final class Notices
{
    /** @var array<string, array{message: string, type: NoticeType, capability: string, dismissible: bool}> */
    private array $persistent = [];

    private bool $printedDismissible = false;

    private bool $registered = false;

    public function __construct(private readonly Identity $identity, private readonly HookRegistrar $hooks)
    {
    }

    /**
     * Show once, on that user's next admin page (default: the current user).
     */
    public function flash(string $message, NoticeType $type = NoticeType::Success, ?int $userId = null): void
    {
        $userId ??= get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $queue = $this->flashed($userId);
        $queue[] = ['message' => $message, 'type' => $type->value];
        update_user_meta($userId, $this->flashKey(), $queue);
    }

    /**
     * Show on every admin page to users with `$capability` until each dismisses it. Declare it on
     * every request, like a hook; only dismissals are stored.
     */
    public function persistent(
        string $id,
        string $message,
        NoticeType $type = NoticeType::Info,
        string $capability = 'manage_options',
        bool $dismissible = true
    ): void {
        $this->persistent[sanitize_key($id)] = ['message' => $message, 'type' => $type, 'capability' => $capability, 'dismissible' => $dismissible];
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $this->hooks->addAction('admin_notices', [$this, 'print']);
        $this->hooks->addAction('wp_ajax_' . $this->dismissAction(), [$this, 'handleDismiss']);
        $this->hooks->addAction('admin_footer', [$this, 'printDismissScript']);
    }

    /**
     * @internal Hooked to admin_notices.
     */
    public function print(): void
    {
        $userId = get_current_user_id();
        if ($userId > 0) {
            $queue = $this->flashed($userId);
            if ($queue !== []) {
                delete_user_meta($userId, $this->flashKey());
            }
            foreach ($queue as $notice) {
                $this->printOne($notice['message'], NoticeType::tryFrom($notice['type']) ?? NoticeType::Info, null);
            }
        }

        $dismissed = $this->dismissed($userId);
        foreach ($this->persistent as $id => $notice) {
            if (in_array($id, $dismissed, true) || !current_user_can($notice['capability'])) {
                continue;
            }
            $this->printOne($notice['message'], $notice['type'], $notice['dismissible'] ? $id : null);
        }
    }

    /**
     * @internal wp_ajax_{slug}_dismiss_notice — logged-in users only, nonce checked.
     */
    public function handleDismiss(): void
    {
        check_ajax_referer($this->identity->nonceAction('notices.dismiss'), 'nonce');

        $id = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
        $userId = get_current_user_id();
        if ($userId <= 0 || !isset($this->persistent[$id]) || !$this->persistent[$id]['dismissible']) {
            wp_send_json_error(null, 400); // exits
        }

        $dismissed = $this->dismissed($userId);
        $dismissed[] = $id;
        update_user_meta($userId, $this->dismissedKey(), array_values(array_unique($dismissed)));
        wp_send_json_success();
    }

    /**
     * @internal Hooked to admin_footer: sends WordPress's dismiss clicks for this plugin's notices.
     */
    public function printDismissScript(): void
    {
        if (!$this->printedDismissible) {
            return;
        }

        printf(
            '<script>document.addEventListener("click",function(e){var b=e.target.closest(".notice-dismiss");if(!b){return;}'
            . 'var n=b.closest("[data-wptoolkit-notice]");if(!n||n.getAttribute("data-owner")!==%1$s){return;}'
            . 'var f=new FormData();f.append("action",%2$s);f.append("id",n.getAttribute("data-wptoolkit-notice"));f.append("nonce",%3$s);'
            . 'fetch(window.ajaxurl,{method:"POST",body:f,credentials:"same-origin"});});</script>',
            wp_json_encode($this->identity->jsKey(), JSON_HEX_TAG),
            wp_json_encode($this->dismissAction(), JSON_HEX_TAG),
            wp_json_encode(wp_create_nonce($this->identity->nonceAction('notices.dismiss')), JSON_HEX_TAG)
        );
    }

    private function printOne(string $message, NoticeType $type, ?string $dismissibleId): void
    {
        $allowed = ['a' => ['href' => true], 'strong' => [], 'em' => [], 'code' => []];

        if ($dismissibleId !== null) {
            $this->printedDismissible = true;
        }

        printf(
            '<div class="notice notice-%s%s" role="%s"%s><p>%s</p></div>',
            esc_attr($type->value),
            $dismissibleId !== null ? ' is-dismissible' : '',
            esc_attr($type->role()),
            $dismissibleId !== null
                ? sprintf(' data-wptoolkit-notice="%s" data-owner="%s"', esc_attr($dismissibleId), esc_attr($this->identity->jsKey()))
                : '',
            wp_kses($message, $allowed)
        );
    }

    /**
     * @return list<array{message: string, type: string}>
     */
    private function flashed(int $userId): array
    {
        $queue = get_user_meta($userId, $this->flashKey(), true);
        if (!is_array($queue)) {
            return [];
        }

        $clean = [];
        foreach ($queue as $notice) {
            if (is_array($notice) && is_string($notice['message'] ?? null) && is_string($notice['type'] ?? null)) {
                $clean[] = ['message' => $notice['message'], 'type' => $notice['type']];
            }
        }

        return $clean;
    }

    /**
     * @return list<string>
     */
    private function dismissed(int $userId): array
    {
        $dismissed = $userId > 0 ? get_user_meta($userId, $this->dismissedKey(), true) : [];

        return is_array($dismissed) ? array_values(array_filter($dismissed, 'is_string')) : [];
    }

    private function flashKey(): string
    {
        return $this->identity->optionKey('notices');
    }

    private function dismissedKey(): string
    {
        return $this->identity->optionKey('dismissed_notices');
    }

    private function dismissAction(): string
    {
        return $this->identity->ajaxAction('dismiss_notice');
    }
}
