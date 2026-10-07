<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin;

enum NoticeType: string
{
    case Success = 'success';
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

    /**
     * Errors interrupt assistive technology; everything else is announced politely.
     */
    public function role(): string
    {
        return $this === self::Error ? 'alert' : 'status';
    }
}
