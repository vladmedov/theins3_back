<?php

namespace App\Enums;

class UserRoles {
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const AUTHOR = 'author';
    public const NEWS_WRITER = 'news_writer';
    public const COLUMNIST = 'columnist';

    public static function all() {
        return [
            self::ADMIN => 'Администратор',
            self::EDITOR => 'Редактор',
            self::AUTHOR => 'Автор',
            self::NEWS_WRITER => 'Новостник',
            self::COLUMNIST => 'Колумнист',
        ];
    }
}
