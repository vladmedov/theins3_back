<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\Category;
use App\Http\Resources\PostResource;
use Elastic\Elasticsearch\Client as ElasticsearchClient;

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
            if ($request->has('category') && !empty($request->input('category'))) {
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
                if ($request->has('from') && !empty($request->input('from'))) {
                    // Начало дня для from (00:00:00)
                    $dateRange['gte'] = strtotime($request->input('from') . ' 00:00:00');
                }
                if ($request->has('to') && !empty($request->input('to'))) {
                    // Конец дня для to (23:59:59)
                    $dateRange['lte'] = strtotime($request->input('to') . ' 23:59:59');
                }
                if (!empty($dateRange)) {
                    $filters[] = ['range' => ['published_at' => $dateRange]];
                }
            }

            // Определяем тип сортировки
            $sort = $request->input('sort', 'relevant');

            // Pick language-specific field analyzers (ru/en) defined in Elasticsearch mapping.
            $languageCodeLower = mb_strtolower((string) $language_code);
            $langSuffix = $languageCodeLower === 'ru' ? 'ru' : 'en';

            $fields = [
                "title.$langSuffix^10",
                "lead.$langSuffix^3",
                "content.$langSuffix",
                "authors.$langSuffix^5",
                "columnist.$langSuffix^5",
                "tags.$langSuffix^5",
            ];

            // Partial-match fields are kept lower-weighted than the normal language analyzers.
            $ngramFields = [
                "title.{$langSuffix}_ngram^2",
                "lead.{$langSuffix}_ngram^0.8",
                "content.{$langSuffix}_ngram^0.4",
                "authors.{$langSuffix}_ngram^1",
                "columnist.{$langSuffix}_ngram^1",
                "tags.{$langSuffix}_ngram^1",
            ];

            // Базовый query
            $baseQuery = [
                'bool' => [
                    'must' => [
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'multi_match' => [
                                            'query' => $query,
                                            'fields' => $fields,
                                            'type' => 'most_fields',
                                            'operator' => 'and',
                                            'boost' => 5,
                                        ],
                                    ],
                                    [
                                        'multi_match' => [
                                            'query' => $query,
                                            'fields' => $ngramFields,
                                            'type' => 'most_fields',
                                            'operator' => 'or',
                                            'boost' => 1,
                                        ],
                                    ],
                                ],
                                'minimum_should_match' => 1,
                            ],
                        ],
                    ],
                    'filter' => $filters
                ]
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
                    'title' => new \stdClass(), // Подсветка найденных слов в title
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
            $postIds = array_map(function($hit) {
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
                ->sortBy(function($post) use ($postIds) {
                    return array_search($post->id, $postIds);
                });

            return [
                'data' => array_values(PostResource::collection($posts)->toArray($request)),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ];

        } catch (\Exception $e) {
            \Log::error('Search error: ' . $e->getMessage());
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

