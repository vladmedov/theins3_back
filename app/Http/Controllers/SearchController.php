<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Models\Post;
use App\Services\LemmatizerService;
use Elastic\Elasticsearch\Client as ElasticsearchClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SearchController extends Controller
{
    public function search(Request $request, $language_code)
    {
        $query = $request->input('query');

        // Если нет поискового запроса, возвращаем пустой результат
        if (empty($query)) {
            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 36,
            ];
        }

        try {
            // Используем прямой запрос к Elasticsearch через клиент
            $client = app(ElasticsearchClient::class);

            $perPage = 36;
            $page = $request->input('page', 1);
            $from = ($page - 1) * $perPage;

            // Готовим фильтры
            $filters = [
                ['term' => ['language_code' => $language_code]],
                ['term' => ['status' => Post::STATUS_PUBLISHED]],
            ];

            // Фильтр по категории
            if ($request->has('category') && ! empty($request->input('category'))) {
                $category = Category::where('slug', $request->input('category'))
                    ->where('language_code', $language_code)
                    ->first();

                if ($category) {
                    $filters[] = ['term' => ['category_id' => $category->id]];
                }
            }

            // Фильтр по дате
            if ($request->has('from') || $request->has('to')) {
                $dateRange = [];
                if ($request->has('from') && ! empty($request->input('from'))) {
                    // Начало дня для from (00:00:00)
                    $dateRange['gte'] = strtotime($request->input('from').' 00:00:00');
                }
                if ($request->has('to') && ! empty($request->input('to'))) {
                    // Конец дня для to (23:59:59)
                    $dateRange['lte'] = strtotime($request->input('to').' 23:59:59');
                }
                if (! empty($dateRange)) {
                    $filters[] = ['range' => ['published_at' => $dateRange]];
                }
            }

            // Определяем тип сортировки
            $sort = $request->input('sort', 'relevant');

            // Strict token search (no stemming) to avoid false positives like "Грузия" -> "Груз".
            $strictFields = [
                'title.exact^10',
                'lead.exact^3',
                'content.exact',
                'authors.exact^5',
                'columnist.exact^5',
                'tags.exact^5',
            ];
            $lemmaFields = [
                'title_lemma^8',
                'lead_lemma^2.5',
                'content_lemma^1.2',
                'authors_lemma^4',
                'columnist_lemma^4',
                'tags_lemma^4',
            ];

            $lemmatizedQuery = app(LemmatizerService::class)->lemmatizeText((string) $query, (string) $language_code);
            $lemmaQuery = $lemmatizedQuery !== '' ? $lemmatizedQuery : (string) $query;

            $ordinarySearchQuery = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => $strictFields,
                    'type' => 'most_fields',
                    'operator' => 'and',
                    'boost' => 1,
                ],
            ];

            $lemmaSearchQuery = [
                'multi_match' => [
                    'query' => $lemmaQuery,
                    'fields' => $lemmaFields,
                    'type' => 'most_fields',
                    'operator' => 'and',
                    'boost' => 0.9,
                ],
            ];

            $normalizedQuery = trim((string) $query);
            $queryWords = preg_split('/\s+/u', $normalizedQuery) ?: [];
            $contentInclusionMust = [];

            foreach ($queryWords as $word) {
                $word = mb_strtolower(trim((string) $word));
                if ($word === '') {
                    continue;
                }
                $contentInclusionMust[] = [
                    'wildcard' => [
                        'content.exact' => [
                            'value' => '*'.$word.'*',
                        ],
                    ],
                ];
            }

            $isRelevanceSort = $sort === 'relevant';

            if ($isRelevanceSort) {
                $shouldQueries = [
                    $ordinarySearchQuery,
                    $lemmaSearchQuery,
                ];

                if (! empty($contentInclusionMust)) {
                    $shouldQueries[] = [
                        'bool' => [
                            'must' => $contentInclusionMust,
                            'boost' => 5,
                        ],
                    ];
                }

                $searchMustClause = [
                    'bool' => [
                        'should' => $shouldQueries,
                        'minimum_should_match' => 1,
                    ],
                ];
            } else {
                // For date/popularity sorts we need stable result sets across inflections,
                // so use lemma-only matching as the inclusion criterion.
                $searchMustClause = $lemmaSearchQuery;
            }

            // Базовый query
            $baseQuery = [
                'bool' => [
                    'must' => [
                        $searchMustClause,
                    ],
                    'filter' => $filters,
                ],
            ];

            // Базовое тело запроса
            $body = [
                'query' => $baseQuery,
                'from' => $from,
                'size' => $perPage,
            ];

            // Добавляем сортировку
            if ($sort === 'new') {
                $body['sort'] = [['published_at' => 'desc']];
            } elseif ($sort === 'popular') {
                $body['sort'] = [['views_count' => 'desc']];
            }
            // 'relevant' - оставляем score по умолчанию (без дополнительных бустов)

            // Добавляем подсветку
            $body['highlight'] = [
                'fields' => [
                    'title' => new \stdClass, // Подсветка найденных слов в title
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ];

            // Выполняем поиск
            $response = $client->search([
                'index' => 'posts',
                'body' => $body,
            ]);

            $hits = $response['hits']['hits'] ?? [];
            $total = $response['hits']['total']['value'] ?? 0;
            $postIds = array_map(function ($hit) {
                return $hit['_id'];
            }, $hits);

            if (empty($postIds)) {
                return [
                    'data' => [],
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                ];
            }

            // Получаем полные данные постов из БД
            $posts = Post::with(['category', 'authors', 'columnist'])
                ->whereIn('id', $postIds)
                ->get()
                ->sortBy(function ($post) use ($postIds) {
                    return array_search($post->id, $postIds);
                });

            return [
                'data' => array_values(PostResource::collection($posts)->toArray($request)),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ];

        } catch (\Exception $e) {
            \Log::error('Search error: '.$e->getMessage());

            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => 36,
                'error' => $e->getMessage(),
            ];
        }
    }
}
