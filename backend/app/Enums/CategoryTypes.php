<?php

namespace App\Enums;

use App\Enums\PostTypes;

class CategoryTypes {
    public const DEFAULT = 'default';
    public const NEWS = 'news';
    public const OPINION = 'opinion';

    private const MAPPING = [
        self::DEFAULT => [PostTypes::ARTICLE, PostTypes::ONLINE],
        self::NEWS => [PostTypes::NEWS],
        self::OPINION => [PostTypes::OPINION],
    ];

    public static function all(): array {
        return [
            self::DEFAULT => __('Default'),
            self::NEWS => __('News Entry'),
            self::OPINION => __('Opinion'),
        ];
    }

    public static function getCategoryTypeByPostType($postType): ?string {
        foreach (self::MAPPING as $categoryType => $postTypes) {
            if (in_array($postType, $postTypes)) {
                return $categoryType;
            }
        }
        return null;
    }

    public static function getPostTypes(string $categoryType): array {
        return self::MAPPING[$categoryType] ?? [];
    }

    public static function isDefault(string $postType): bool {
        return in_array($postType, self::MAPPING[self::DEFAULT]);
    }
}
