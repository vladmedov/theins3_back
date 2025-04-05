<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

use App\Models\Category;

use App\Models\Post;

use App\Models\Author;
use App\Models\PostAuthor;

use App\Models\InvestigationTheme;

use App\Http\Resources\PostResource;
class TestController extends Controller
{
    private $legacy_db;

    public function __construct()
    {
        $this->legacy_db = DB::connection('legacy_pgsql');
    }

    public function test()
    {
        $this->clearDB();
        $this->importCategories();
        $this->importAuthors();
        $this->importInvestigationThemes();

        $this->importPosts();

        $this->importPostAuthors();
        $this->importThemePosts();

        //$this->importContentPosts(279832);

        return [
            'status' => 'Import completed',
        ];
    }

    private function importContentPosts($id)
    {
        $termsPosts = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ' . $id . '
            AND kind = \'term\'
        ');

        // ...

        $contentPosts = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ' . $id . '
            AND kind IN (\'number\', \'text\', \'social\', \'related_posts\', \'audio\', \'iframe\')
            ORDER BY position ASC
        ');

        $linksPosts = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ' . $id . '
            AND kind IN (\'image\', \'big_image\', \'gallery\', \'video\', \'quote\')
            ORDER BY position ASC
        ');

        $links = [];
        $linkKeys = [];
        
        foreach ($linksPosts as $linksPost) {
            $linkKeys[] = $linksPost->human_id;

            if (in_array($linksPost->kind, ['image', 'big_image', 'gallery'])) {
                $images = $this->legacy_db->select('
                    SELECT * FROM public.content_block_images
                    WHERE content_block_id = ' . $linksPost->id . '
                    ORDER BY position ASC
                ');
                $links[$linksPost->human_id]['type'] = 'images';
                foreach ($images as $image) {
                    $links[$linksPost->human_id]['attributes']['images'][] = [
                        'link' => '',
                        'author' => $image->credit ?? '',
                        'description' => $image->caption ?? '',
                    ];
                }
            }

            if ($linksPost->kind === 'video') {
                $content = json_decode($linksPost->content);
                if (isset($content->related_posts_title) && count((array)$content) === 1) {
                    continue;
                }
                if (isset($content->related_posts_title) && isset($content->allow_full_screen) && count((array)$content) === 2) {
                    continue;
                }
                if (isset($content->video_embed) && $content->video_embed === '') {
                    continue;
                }
                $links[$linksPost->human_id]['type'] = 'video';
                $links[$linksPost->human_id]['attributes'] = [
                    // 'embed_code' => json_decode($linksPost->content)->video_embed,
                    // 'embed_type' => 'video',
                    // 'embed_description' => $linksPost->caption,
                    // 'embed_author' => $linksPost->credit,
                    'video_url' => json_decode($linksPost->content)->video_embed ?? '',
                    'video_description' => $linksPost->caption ?? '',
                    'video_author' => $linksPost->credit ?? '',
                ];
                if ($links[$linksPost->human_id]['attributes']['video_url'] === '') {
                    dd($linksPost->content);
                }
            }

            if ($linksPost->kind === 'quote') {
                $linksPostContent = json_decode($linksPost->content);
                if (isset($linksPostContent->related_posts_title) && count((array)$linksPostContent) === 1) {
                    continue;
                }
                if (isset($linksPostContent->related_posts_title) && isset($linksPostContent->allow_full_screen) && count((array)$linksPostContent) === 2) {
                    continue;
                }
                if (isset($linksPostContent->quote) && $linksPostContent->quote === '') {
                    continue;
                }
                $links[$linksPost->human_id]['type'] = 'quote';
                $links[$linksPost->human_id]['attributes'] = [
                    'quote' => $linksPostContent->quote ?? '',
                    'quote_author' => '',
                ];
                if ($links[$linksPost->human_id]['attributes']['quote'] === '') {
                    dd($linksPost->content);
                }
            }
        }

        //dd($links);

        $content = [];
        foreach ($contentPosts as $contentPost) {
            if ($contentPost->kind === 'number') {
                $content[] = [
                    'type' => 'outline',
                    'attributes' => [
                        'outline' => $contentPost->title,
                    ],
                ];
            }
            if ($contentPost->kind === 'text') {
                $contentPostContent = json_decode($contentPost->content);
                if (isset($contentPostContent->related_posts_title) && count((array)$contentPostContent) === 1) {
                    continue;
                }
                if (isset($contentPostContent->related_posts_title) && isset($contentPostContent->allow_full_screen) && count((array)$contentPostContent) === 2) {
                    continue;
                }

                $text = $contentPostContent->text;
                
                // Ищем все теги <h3> и заменяем их на маркеры
                $h3Markers = [];
                $h3Index = 0;
                
                // Находим все теги <h3> с их содержимым
                preg_match_all('/<h3(?:\s[^>]*)?>(.*?)<\/h3>/is', $text, $h3Matches, PREG_SET_ORDER);
                
                // Заменяем найденные теги <h3> на маркеры
                foreach ($h3Matches as $match) {
                    $fullTag = $match[0]; // Полный тег <h3>...</h3>
                    $h3Content = $match[1]; // Только содержимое
                    
                    // Очищаем содержимое
                    $h3Content = strip_tags($h3Content); // Удаляем вложенные HTML-теги
                    $h3Content = str_replace('&nbsp;', ' ', $h3Content); // Заменяем неразрывные пробелы
                    $h3Content = html_entity_decode($h3Content); // Декодируем HTML-сущности
                    $h3Content = trim($h3Content); // Удаляем пробелы в начале и конце
                    
                    $markerKey = "___H3_MARKER_{$h3Index}___";
                    $h3Markers[$markerKey] = $h3Content;
                    $text = str_replace($fullTag, $markerKey, $text);
                    $h3Index++;
                }
                
                // Используем тот же подход с маркерами для обычных ключей
                $tempMarkers = [];
                $tempIndex = 0;
                
                // Проверяем, что $linkKeys существует и не пустой
                if (!empty($linkKeys)) {
                    foreach ($linkKeys as $key) {
                        if (strpos($text, $key) !== false) {
                            $markerKey = "___TEMP_MARKER_{$tempIndex}___";
                            $tempMarkers[$markerKey] = $key;
                            $text = str_replace($key, $markerKey, $text);
                            $tempIndex++;
                        }
                    }
                }
                
                // Комбинируем оба типа маркеров для разбиения
                $allMarkers = array_merge($tempMarkers, $h3Markers);
                
                if (empty($allMarkers)) {
                    // Нормализуем HTML перед добавлением
                    $text = $this->normalizeHtml($text);
                    
                    $content[] = [
                        'type' => 'text',
                        'attributes' => [
                            'text' => $text,
                        ],
                    ];
                } else {
                    // Разбиваем текст сначала по H3-маркерам, затем по обычным маркерам
                    $h3Pattern = '/(___H3_MARKER_\d+___)/';
                    $tempPattern = '/(___TEMP_MARKER_\d+___)/';
                    
                    // Сначала разбиваем по всем маркерам
                    $allPattern = '/(' . implode('|', array_map('preg_quote', array_keys($allMarkers))) . ')/';
                    $textParts = preg_split($allPattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
                    
                    foreach ($textParts as $part) {
                        // Проверяем, является ли часть H3-маркером
                        if (preg_match('/___H3_MARKER_(\d+)___/', $part)) {
                            // Это H3 маркер, получаем текст заголовка
                            $h3Text = $h3Markers[$part];
                            
                            // Очищаем от HTML-сущностей и лишних пробелов
                            $h3Text = html_entity_decode($h3Text); // Декодируем HTML-сущности
                            $h3Text = str_replace('&nbsp;', ' ', $h3Text); // Заменяем неразрывные пробелы
                            $h3Text = trim($h3Text); // Удаляем пробелы в начале и конце
                            
                            // Добавляем заголовок как блок outline
                            $content[] = [
                                'type' => 'outline',
                                'attributes' => [
                                    'outline' => $h3Text,
                                ],
                            ];
                        }
                        // Проверяем, является ли часть обычным маркером
                        else if (preg_match('/___TEMP_MARKER_(\d+)___/', $part)) {
                            // Это обычный маркер, получаем оригинальный ключ
                            $originalKey = $tempMarkers[$part];
                            
                            // Добавляем соответствующий контент из $links
                            if (isset($links[$originalKey])) {
                                $content[] = $links[$originalKey];
                            }
                        } 
                        // Это обычный текст
                        else {
                            if (trim($part) !== '') {
                                // Нормализуем HTML для каждой части текста
                                $part = $this->normalizeHtml($part);
                                
                $content[] = [
                    'type' => 'text',
                    'attributes' => [
                                        'text' => $part,
                                    ],
                                ];
                            }
                        }
                    }
                }
            }
            if ($contentPost->kind === 'social') {
                $contentPostContent = json_decode($contentPost->content);
                if (isset($contentPostContent->related_posts_title) && count((array)$contentPostContent) === 1) {
                    continue;
                }
                if (isset($contentPostContent->related_posts_title) && isset($contentPostContent->allow_full_screen) && count((array)$contentPostContent) === 2) {
                    continue;
                }
                if (!isset($contentPostContent->social_embed) || $contentPostContent->social_embed === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $contentPostContent->social_embed,
                        'embed_type' => $contentPost->social_type,
                    ],
                ];
            }
            if ($contentPost->kind === 'audio') {
                $contentPostContent = json_decode($contentPost->content);
                if (isset($contentPostContent->related_posts_title) && count((array)$contentPostContent) === 1) {
                    continue;
                }
                if (isset($contentPostContent->related_posts_title) && isset($contentPostContent->allow_full_screen) && count((array)$contentPostContent) === 2) {
                    continue;
                }
                if (isset($contentPostContent->audio_embed) && $contentPostContent->audio_embed === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $contentPostContent->audio_embed,
                        'embed_type' => 'audio',
                    ],
                ];
            }
            if ($contentPost->kind === 'iframe') {
                $contentPostContent = json_decode($contentPost->content);
                if (isset($contentPostContent->related_posts_title) && count((array)$contentPostContent) === 1) {
                    continue;
                }
                if (isset($contentPostContent->related_posts_title) && isset($contentPostContent->allow_full_screen) && count((array)$contentPostContent) === 2) {
                    continue;
                }
                if (isset($contentPostContent->iframe) && $contentPostContent->iframe === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $contentPostContent->iframe,
                        'embed_type' => 'iframe',
                    ],
                ];
            }
            if ($contentPost->kind === 'related_posts') {
                $contentPostContent = json_decode($contentPost->content);
                $postRelatedToBlock = $this->legacy_db->select('
                    SELECT * FROM public.post_relations
                    WHERE postable_id = ' . $contentPost->id . '
                    AND postable_type = \'ContentBlock\'
                ');

                $postIds = [];
                foreach ($postRelatedToBlock as $post) {
                    $postIds[] = $post->post_id;
                }

                if (count($postIds) === 0) {
                    continue;
                }

                $posts = Post::whereIn('id', $postIds)->get();

                $content[] = [
                    'type' => 'related',
                    'attributes' => [
                        'related_title' => $contentPostContent->related_posts_title,
                        'related_posts' => PostResource::collection($posts),
                    ],
                ];
            }

        }

        // Финальная нормализация - проходим еще раз по всем текстовым блокам
        for ($i = 0; $i < count($content); $i++) {
            if (isset($content[$i]['type']) && $content[$i]['type'] === 'text') {
                $content[$i]['attributes']['text'] = $this->normalizeHtml($content[$i]['attributes']['text']);
            }
        }

        Post::where('id', $id)->update([
            'content' => json_encode($content),
        ]);

        //dd($content, $contentPosts);
    }
    
    /**
     * Нормализует HTML-содержимое текстового блока
     * - Удаляет пустые параграфы
     * - Балансирует открывающие и закрывающие теги
     * - Удаляет лишние закрывающие теги
     * - Оборачивает текст без тегов в параграфы
     */
    private function normalizeHtml($html) {
        // Удаляем символ ◀ в конце текста
        $html = preg_replace('/\s*◀\s*$/', '', $html);
        
        // Подсчитываем количество открывающих и закрывающих p-тегов
        $openingPCount = substr_count(strtolower($html), '<p');
        $closingPCount = substr_count(strtolower($html), '</p');
        
        // Удаляем лишние закрывающие теги p в конце, если их больше чем открывающих
        if ($closingPCount > $openingPCount) {
            $diff = $closingPCount - $openingPCount;
            // Удаляем лишние закрывающие теги с конца строки
            $pattern = '/(<\/p>){' . $diff . '}\s*$/i';
            $html = preg_replace($pattern, '', $html);
        }
        
        // Удаляем пустые параграфы
        $html = preg_replace('/<p>\s*(&nbsp;)*\s*<\/p>/i', '', $html);
        
        // Удаляем висящие закрывающие теги в начале текста
        $html = preg_replace('/^[\s\n]*<\/([a-z0-9]+)>/', '', $html);
        
        // Если текст не начинается с тега p и не пустой, оборачиваем его
        if (!preg_match('/^\s*<p(?:\s[^>]*)?>/i', $html) && trim($html) !== '') {
            $html = "<p>$html</p>";
        }
        
        // Нормализуем структуру вложенных тегов, удаляя невалидные конструкции
        $dom = new \DOMDocument();
        // Отключаем сообщения об ошибках при парсинге невалидного HTML
        libxml_use_internal_errors(true);
        // Задаем кодировку и загружаем HTML
        $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">' . $html;
        $dom->loadHTML($html);
        // Возвращаем только body содержимое (без meta и других служебных тегов)
        $html = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
        // Удаляем теги body, которые были добавлены
        $html = preg_replace('/<\/?body>/', '', $html);
        libxml_clear_errors();
        
        // Финальная очистка - удаляем пустые параграфы, которые могли появиться
        $html = preg_replace('/<p>\s*(&nbsp;)*\s*<\/p>/i', '', $html);
        
        return $html;
    }

    // Добавляем новый вспомогательный метод для анализа открытых HTML-тегов
    private function getOpenTagsCount($html, &$openTags) {
        // Очищаем массив открытых тегов
        $openTags = [];
        
        // Находим все открывающие и закрывающие теги
        preg_match_all('/<([a-z0-9]+)(?:\s.*?)?(?<!\/)\>/i', $html, $openingTags);
        preg_match_all('/<\/([a-z0-9]+)>/i', $html, $closingTags);
        
        // Преобразуем в более удобный формат
        $openingTagsArray = $openingTags[1];
        $closingTagsArray = $closingTags[1];
        
        // Отслеживаем открытые теги
        foreach ($openingTagsArray as $tag) {
            // Игнорируем самозакрывающиеся теги
            if (!in_array($tag, ['img', 'br', 'hr'])) {
                $openTags[] = $tag;
            }
        }
        
        // Удаляем закрытые теги из списка открытых
        foreach ($closingTagsArray as $tag) {
            $pos = array_search($tag, array_reverse($openTags));
            if ($pos !== false) {
                array_splice($openTags, count($openTags) - $pos - 1, 1);
            }
        }
        
        return count($openTags);
    }

    // Улучшенный метод для очистки и коррекции неправильного HTML
    private function cleanEmptyTags($html) {
        // Удаляем висящие закрывающие теги в начале строки
        $html = preg_replace('/^[\s\n]*<\/([a-z0-9]+)>/', '', $html);
        
        // Удаляем полностью пустые параграфы (даже без пробелов)
        $html = preg_replace('/<p><\/p>/i', '', $html);
        
        // Удаляем параграфы только с пробельными символами
        $html = preg_replace('/<p>\s*(&nbsp;)*\s*<\/p>/i', '', $html);
        
        // Удаляем пустые div
        $html = preg_replace('/<div>\s*(&nbsp;)*\s*<\/div>/i', '', $html);
        
        // Удаляем пустые span
        $html = preg_replace('/<span>\s*(&nbsp;)*\s*<\/span>/i', '', $html);
        
        // Удаляем теги с атрибутами, но пустым содержимым
        $html = preg_replace('/<([a-z0-9]+)(?:\s+[^>]*)?>\s*(&nbsp;)*\s*<\/\1>/i', '', $html);
        
        // Удаляем символ ◀ и любые пробельные символы в конце текста
        $html = preg_replace('/[\s\n]*◀[\s\n]*$/', '', $html);
        
        // Удаляем висящие открывающие теги в конце строки
        $html = preg_replace('/<([a-z0-9]+)(?:\s+[^>]*)?>\s*$/', '', $html);
        
        // Рекурсивно продолжаем очистку, пока есть изменения
        $previousHtml = '';
        $iterations = 0;
        while ($previousHtml !== $html && $iterations < 5) {
            $previousHtml = $html;
            // Удаляем пустые теги
            $html = preg_replace('/<([a-z0-9]+)(?:\s+[^>]*)?>\s*(&nbsp;)*\s*<\/\1>/i', '', $html);
            // Удаляем висящие закрывающие теги
            $html = preg_replace('/[\s\n]*<\/([a-z0-9]+)>[\s\n]*$/', '', $html);
            $iterations++;
        }
        
        return $html;
    }

    private function importPostAuthors()
    {
        $peopleRegions = $this->legacy_db->select('
            SELECT * FROM public.region_relations
            WHERE regionable_type = \'Person\'
            ORDER BY id ASC 
        ');

        $peopleIds = [];
        foreach ($peopleRegions as $personRegion) {
            switch ($personRegion->region_id) {
                case 1:
                    $peopleIds['ru'][] = $personRegion->regionable_id;
                    break;
                case 3:
                    $peopleIds['en'][] = $personRegion->regionable_id;
                    break;
                default:
                    throw new \Exception('Unknown region id: ' . $personRegion->region_id);
                    break;
            }
        }

        $postAuthors = $this->legacy_db->select('
            SELECT * FROM public.person_relations
            WHERE (personable2_type = \'Post\' 
            AND person_id IN (' . implode(',', $peopleIds['ru']) . '))
            OR (personable3_type = \'Post\' 
            AND person_id IN (' . implode(',', $peopleIds['ru']) . '))
            ORDER BY id ASC
        ');

        $relations = [];

        foreach ($postAuthors as $postAuthor) {
            if ($postAuthor->personable2_id) {
                $relations[$postAuthor->personable2_id][] = $postAuthor->person_id;
            } else {
                $relations[$postAuthor->personable3_id][] = $postAuthor->person_id;
            }
        }
        $keys = array_keys($relations);

        collect($keys)->chunk(1000)->each(function ($batch) use ($relations) {
            // $posts = $this->legacy_db->select('
            //     SELECT * FROM public.posts
            //     WHERE id IN (' . implode(',', $batch->toArray()) . ')
            //     ORDER BY id ASC
            // ');

            $posts = Post::whereIn('id', $batch->toArray())->get();

            foreach ($posts as $post) { 
                foreach ($relations[$post->id] as $authorId) {
                    if ($post->type === 'opinion') {
                        $post->columnist_id = $authorId;
                        $post->save();
                    } else {
                        PostAuthor::create([
                            'post_id' => $post->id,
                            'author_id' => $authorId,
                        ]);
                    }
                }
            }
        });


        //dd($relations);

        
    }

    private function clearDB()
    {
        Category::truncate();
        Author::truncate();
        InvestigationTheme::truncate();
        Post::truncate();
        PostAuthor::truncate();
    }

    private function importCategories()
    {
        $categoryRegions = $this->legacy_db->select('
            SELECT * FROM public.region_relations
            WHERE regionable_type = \'Rubric\'
            ORDER BY id ASC 
        ');

        $categoryIds = [];
        foreach ($categoryRegions as $categoryRegion) {
            switch ($categoryRegion->region_id) {
                case 1:
                    $categoryIds['ru'][] = $categoryRegion->regionable_id;
                    break;
                case 3:
                    $categoryIds['en'][] = $categoryRegion->regionable_id;
                    break;
                default:
                    throw new \Exception('Unknown region id: ' . $categoryRegion->region_id);
                    break;
            }
        }

        $categories = $this->legacy_db->select('
            SELECT * FROM public.rubrics
            WHERE id IN (' . implode(',', $categoryIds['ru']) . ')
            ORDER BY id ASC
        ');

        collect($categories)->each(function ($category) {
            Category::create([
                'language_code' => 'ru',
                'slug' => $category->slug,
                'type' => match ($category->special) {
                    'news' => 'news',
                    'opinion' => 'opinion',
                    'simple' => 'default',
                    'longread' => 'default',
                    'confession' => 'default',
                },
                'id' => $category->id,
                'title' => $category->title,

                'position' => $category->position,
                'is_show_in_menu' => $category->show_in_menu,

                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]);
        });

        $categories = $this->legacy_db->select('
            SELECT * FROM public.rubrics
            WHERE id IN (' . implode(',', $categoryIds['en']) . ')
            ORDER BY id ASC
        ');

        collect($categories)->each(function ($category) {
            Category::create([
                'language_code' => 'en',
                'slug' => $category->slug,
                'type' => match ($category->special) {
                    'news' => 'news',
                    'opinion' => 'opinion',
                    'simple' => 'default',
                    'longread' => 'default',
                    'confession' => 'default',
                },
                'id' => $category->id,
                'title' => $category->title,

                'position' => $category->position,
                'is_show_in_menu' => $category->show_in_menu,

                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ]);
        });

        // +"created_at": "2020-05-29 11:16:42.026616"
        // +"updated_at": "2020-06-02 16:27:14.340579"

        // +"old_id": 2
        // +"old": true

    }
    private function importPosts()
    {
        $postsRegions = $this->legacy_db->select('
            SELECT * FROM public.region_relations
            WHERE regionable_type = \'Post\'
            ORDER BY id ASC 
        ');

        $postsIds = [];
        foreach ($postsRegions as $postRegion) {
            switch ($postRegion->region_id) {
                case 1:
                    $postsIds['ru'][] = $postRegion->regionable_id;
                    break;  
                case 3:
                    $postsIds['en'][] = $postRegion->regionable_id;
                    break;
                default:
                    throw new \Exception('Unknown region id: ' . $postRegion->region_id);
                    break;
            }
        }

        collect($postsIds['ru'])->chunk(10000)->each(function ($batch) {
            $posts = $this->legacy_db->select('
                SELECT posts.*, rubric_relations.rubric_id FROM public.posts
                LEFT JOIN (
                    SELECT rubricable_id, MIN(rubric_id) as rubric_id
                    FROM public.rubric_relations 
                    WHERE rubricable_type = \'Post\'
                    GROUP BY rubricable_id
                ) as rubric_relations ON rubric_relations.rubricable_id = posts.id
                WHERE posts.id IN (' . implode(',', $batch->toArray()) . ')
            ');
            //$table->enum('type', ['article', 'news', 'opinion', 'online']);
            foreach ($posts as $post) {
                Post::create([
                    'language_code' => 'ru',
                    'slug' => $post->slug ?? $post->id,
                    //'type' => 'article',
                    'type' => match ($post->type) {
                        'Post::News' => 'news',
                        'Post::Opinion' => 'opinion',
                        'Post::Article' => 'article',
                        'Post::Online' => 'online',
                        'Post::Card' => 'article', // TODO: check
                    },
                    'id' => $post->id,
                    'category_id' => $post->rubric_id,
                    'title' => $post->title,
                    'status' => $post->published ? 'published' : 'draft',
                    'image' => $post->detail_image ?? $post->preview_image ?? null,
                    'image_description' => $post->image_description,
                    'published_at' => $post->published_at,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at,
                    'lead' => $post->lead,
                    'is_super_news' => $post->super_news,
                    'views_count' => $post->viewed,
                ]);
                $this->importContentPosts($post->id);
            }
        });

        //     +"published_at": "2020-05-21 13:34:40"
        //     +"created_at": "2020-05-29 11:42:04.134553"
        //     +"updated_at": "2022-06-15 14:19:56.084248"

        // 0 => {#90339 ▼
        //     +"meta": null
        //     +"lead": null
        //     +"online": false
        //     +"meta_image": "screen20220615-13082-1q2xuxm.png"
        //     +"old_id": 220748
        //     +"old": true
        //     +"old_image": "/wp-content/uploads/2020/05/forecast.jpg"
        //     +"fb_instat_articles": true
        //     +"pre_release": false
        //     +"feature_title": null
        //   }
    }

    private function importAuthors()
    {
        $peopleRegions = $this->legacy_db->select('
            SELECT * FROM public.region_relations
            WHERE regionable_type = \'Person\'
            ORDER BY id ASC 
        ');

        $peopleIds = [];
        foreach ($peopleRegions as $personRegion) {
            switch ($personRegion->region_id) {
                case 1:
                    $peopleIds['ru'][] = $personRegion->regionable_id;
                    break;
                case 3:
                    $peopleIds['en'][] = $personRegion->regionable_id;
                    break;
                default:
                    throw new \Exception('Unknown region id: ' . $personRegion->region_id);
                    break;
            }
        }

        $peopleRU = $this->legacy_db->select('
            SELECT * FROM public.people
            WHERE id IN (' . implode(',', $peopleIds['ru']) . ')
            ORDER BY id ASC
        ');

        $peopleEN = $this->legacy_db->select('
            SELECT * FROM public.people
            WHERE id IN (' . implode(',', $peopleIds['en']) . ')
            ORDER BY id ASC
        ');

        foreach ($peopleRU as $person) {
            Author::create([
                'id' => $person->id,
                'language_code' => 'ru',
                'slug' => $person->slug,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'avatar' => $person->image, // todo
                'position' => $person->work_position,
                'description' => $person->description,
                'twitter' => $person->twitter,
                'facebook' => $person->facebook,
                'allowed_post_types' => ['article', 'opinion', 'news', 'online'],
                'post_types_with_hidden_author_name' => [],
                'is_author_page_hidden' => false,
                'is_columnist_page_hidden' => false,
            ]);
        }

        foreach ($peopleEN as $person) {
            Author::create([
                'id' => $person->id,
                'language_code' => 'en',
                'slug' => $person->slug,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'avatar' => $person->image, // todo
                'position' => $person->work_position,
                'description' => $person->description,
                'twitter' => $person->twitter,
                'facebook' => $person->facebook,
                'allowed_post_types' => ['article', 'opinion', 'news', 'online'],
                'post_types_with_hidden_author_name' => [],
                'is_author_page_hidden' => false,
                'is_columnist_page_hidden' => false,
            ]);
        }

        
        
        //dd($peopleRU, $peopleEN);


      

        // $admins = [];



        // foreach ($peopleEN as $person) {
        //     $id = trim($person->last_name);
        //     $admins[$id][] = $person->first_name . ' ' . $person->last_name;
        // }

        // foreach ($peopleRU as $person) {
        //     $id = trim($person->last_name);
        //     $translitId = $this->translit($id);
        //     $admins[$translitId][] = $person->first_name . ' ' . $person->last_name;
        // }

        // // Сортировка массива $admins по ключу в алфавитном порядке
        // ksort($admins);
        
        // dd($admins);

        // $users = $this->legacy_db->select('
        //     SELECT * FROM public.people
        //     ORDER BY id ASC
        // ');

        // dd($users);
    }

    private function importInvestigationThemes()
    {
        $themeRegions = $this->legacy_db->select('
            SELECT * FROM public.region_relations
            WHERE regionable_type = \'Theme\'
            ORDER BY id ASC 
        ');

        $themeIds = [];
        foreach ($themeRegions as $themeRegion) {
            switch ($themeRegion->region_id) {
                case 1:
                    $themeIds['ru'][] = $themeRegion->regionable_id;
                    break;
                case 3:
                    $themeIds['en'][] = $themeRegion->regionable_id;
                    break;
                default:
                    throw new \Exception('Unknown region id: ' . $themeRegion->region_id);
                    break;
            }
        }

        $themeRU = $this->legacy_db->select('
            SELECT * FROM public.themes
            WHERE id IN (' . implode(',', $themeIds['ru']) . ')
            ORDER BY id ASC
        ');

        $position = count($themeRU) * 10;
        foreach ($themeRU as $theme) {
            InvestigationTheme::create([
                'id' => $theme->id,
                'language_code' => 'ru',
                'slug' => $theme->slug,
                'title' => $theme->title,
                'description' => $theme->description,
                'position' => !empty($theme->position) ? $theme->position : $position,
                'cover_image' => $theme->image,
                'is_main' => $theme->slug === 'otraviteli-iz-fsb',
                'created_at' => $theme->created_at,
                'updated_at' => $theme->updated_at,
            ]);
            $position--;
        }

        $themeEN = $this->legacy_db->select('
            SELECT * FROM public.themes
            WHERE id IN (' . implode(',', $themeIds['en']) . ')
            ORDER BY id ASC
        ');

        $position = count($themeEN);
        foreach ($themeEN as $theme) {
            InvestigationTheme::create([
                'id' => $theme->id,
                'language_code' => 'en',
                'slug' => $theme->slug,
                'title' => $theme->title,
                'description' => $theme->description,
                'position' => $position,
                'cover_image' => $theme->image,
                'created_at' => $theme->created_at,
                'updated_at' => $theme->updated_at,
            ]);
            $position--;
        }
    }

    private function importThemePosts()
    {
        $themePosts = $this->legacy_db->select('
            SELECT * FROM public.theme_post_relations
            ORDER BY id ASC
        ');

        collect($themePosts)->chunk(10000)->each(function ($batch) {
            $ids = $batch->pluck('post_id')->toArray();
            $posts = Post::whereIn('id', $ids)->get();

            $themeIds = $batch->pluck('theme_id', 'post_id')->toArray();

            foreach ($posts as $post) {
                $post->investigation_theme_id = $themeIds[$post->id];
                $post->save();
            }
        });
    }
}