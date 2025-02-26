<?php

namespace App\Enums;

use App\Enums\UserRoles;

class PostTypes {
    public const ARTICLE = 'article';
    public const NEWS = 'news';
    public const OPINION = 'opinion';
    public const ONLINE = 'online';

    public static function all() {
        return [
            self::ARTICLE => __('Article'),
            self::NEWS => __('News Entry'),
            self::OPINION => __('Opinion'),
            self::ONLINE => __('Online'),
        ];
    }

    public static function get($type) {
        return (!empty(static::all()[$type])) ? static::all()[$type] : null;
    }

    public static function access() {
        return [
            self::ARTICLE => [UserRoles::AUTHOR],
            self::NEWS => [UserRoles::NEWS_WRITER],
            self::OPINION => [UserRoles::COLUMNIST],
            self::ONLINE => [UserRoles::AUTHOR, UserRoles::NEWS_WRITER],
        ];
    }
}
