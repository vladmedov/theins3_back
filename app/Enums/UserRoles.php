<?php

namespace App\Enums;

class UserRoles {
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const JOURNALIST = 'journalist';

    public static function all() {
        return [
            self::ADMIN => __('User role: Administrator'),
            self::EDITOR => __('User role: Editor'),
            self::JOURNALIST => __('User role: Journalist'),
        ];
    }
}
