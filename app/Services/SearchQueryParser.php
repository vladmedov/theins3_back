<?php

namespace App\Services;

class SearchQueryParser
{
    /**
     * Returns the inner phrase when the query is fully wrapped in double quotes,
     * or null when smart search should be used.
     */
    public function extractExactPhrase(string $query): ?string
    {
        $trimmed = trim($query);

        if (! preg_match('/^"(.*)"\s*$/us', $trimmed, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Returns exact-search phrase from strict mode or quoted query syntax.
     */
    public function resolveExactPhrase(string $query, bool $strict): ?string
    {
        if ($strict) {
            return trim($query);
        }

        return $this->extractExactPhrase($query);
    }

    public function escapeWildcard(string $value): string
    {
        return str_replace(
            ['\\', '*', '?'],
            ['\\\\', '\\*', '\\?'],
            $value
        );
    }

    /**
     * @param  array<string, float|int>  $fields  field name => boost
     * @return array<string, mixed>
     */
    public function buildExactSubstringClause(string $phrase, array $fields): array
    {
        $pattern = '*'.$this->escapeWildcard(mb_strtolower($phrase)).'*';
        $shouldQueries = [];

        foreach ($fields as $field => $boost) {
            $wildcard = [
                'wildcard' => [
                    $field => [
                        'value' => $pattern,
                    ],
                ],
            ];

            if ($boost !== 1) {
                $wildcard['wildcard'][$field]['boost'] = $boost;
            }

            $shouldQueries[] = $wildcard;
        }

        return [
            'bool' => [
                'should' => $shouldQueries,
                'minimum_should_match' => 1,
            ],
        ];
    }
}
