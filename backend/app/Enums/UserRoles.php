<?php

namespace App\Enums;

class UserRoles {
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const JOURNALIST = 'journalist';

    public static function all() {
        return [
            self::ADMIN => 'Администратор',
            self::EDITOR => 'Редактор',
            self::JOURNALIST => 'Журналист',
        ];
    }
}
