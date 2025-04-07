<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

use App\Models\Category;

use App\Models\Post;
use App\Models\PostTypes\OnlineMessage;
use App\Models\Termin;

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

        $this->importCategories(1);
        $this->importCategories(3);
        $this->importAuthors(1);
        $this->importAuthors(3);
        $this->importInvestigationThemes(1);
        $this->importInvestigationThemes(3);
        
        $this->importPosts(1);
        $this->importPosts(3);

        $this->importPostAuthors(1);
        $this->importPostAuthors(3);
        $this->importThemePosts();

        return [
            'status' => 'Import completed',
        ];
    }

    private function importContentPosts($id)
    {
        $currentPost = Post::find($id);

        $termins = [];
        $dbTermins = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ' . $id . '
            AND kind = \'term\'
        ');
        foreach ($dbTermins as $dbTermin) {
            $content = json_decode($dbTermin->content ?? '{}')->text ?? '';

            if (!isset($dbTermin->human_id) || $dbTermin->human_id === '' || $content === '') {
                continue;
            }

            $termins[$dbTermin->human_id] = $content;
        }

        $templates = [];
        $dbTemplates = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ' . $id . '
            AND kind IN (\'image\', \'big_image\', \'gallery\', \'video\', \'quote\')
            ORDER BY position ASC
        ');
        foreach ($dbTemplates as $dbTemplate) {
            if (in_array($dbTemplate->kind, ['image', 'big_image', 'gallery'])) {
                $images = $this->legacy_db->select('
                    SELECT * FROM public.content_block_images
                    WHERE content_block_id = ' . $dbTemplate->id . '
                    ORDER BY position ASC
                ');
                if (count($images) === 0) {
                    continue;
                }
                $templates[$dbTemplate->human_id]['type'] = 'images';
                foreach ($images as $image) {
                    $templates[$dbTemplate->human_id]['attributes']['images'][] = [
                        'link' => 'https://theins.ru/images/S94ufcu7GG4cr8bfPH6UU_j975FIAlL2kGy_xixJtyI/rs:auto:877:579:0:0/dpr:2/q:100/bG9jYWw6L3B1Ymxp/Yy9zdG9yYWdlL3Bv/c3QvMjgwMjA5L2Zp/bGUtYzBmOTJiZmY0/MWVmNzZiZDExZDk0/ZjJkNTMxYzg1MjUu/anBn.jpg',
                        'author' => $image->credit ?? '',
                        'description' => $image->caption ?? '',
                    ];
                }
            }
            if ($dbTemplate->kind === 'video') {
                $dbTemplateContent = json_decode($dbTemplate->content);
                if (isset($dbTemplateContent->video_embed) && $dbTemplateContent->video_embed === '') {
                    continue;
                }
                $templates[$dbTemplate->human_id]['type'] = 'video';
                $templates[$dbTemplate->human_id]['attributes'] = [
                    // 'embed_code' => json_decode($linksPost->content)->video_embed,
                    // 'embed_type' => 'video',
                    // 'embed_description' => $linksPost->caption,
                    // 'embed_author' => $linksPost->credit,
                    'video_url' => $dbTemplateContent->video_embed ?? '',
                    'video_description' => $dbTemplate->caption ?? '',
                    'video_author' => $dbTemplate->credit ?? '',
                ];
            }
            if ($dbTemplate->kind === 'quote') {
                $dbTemplateContent = json_decode($dbTemplate->content);
                if (isset($dbTemplateContent->quote) && $dbTemplateContent->quote === '') {
                    continue;
                }
                $templates[$dbTemplate->human_id]['type'] = 'quote';
                $templates[$dbTemplate->human_id]['attributes'] = [
                    'quote' => $dbTemplateContent->quote ?? '',
                    'quote_author' => '',
                ];
            }
        }

        $content = [];
        $blocks = $this->legacy_db->select('
            SELECT * FROM public.content_blocks
            WHERE blockable_type = \'Post\'
            AND blockable_id = ' . $id . '
            AND kind IN (\'number\', \'text\', \'social\', \'related_posts\', \'audio\', \'iframe\')
            ORDER BY position ASC
        ');

        foreach ($blocks as $block) {
            if ($block->kind === 'number') {
                if (!isset($block->title) || $block->title === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'outline',
                    'attributes' => [
                        'outline' => $block->title,
                    ],
                ];
            }

            if ($block->kind === 'text') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->text) || $blockContent->text === '') {
                    continue;
                }
                $text = $blockContent->text;

                // 1) Заменяем все ссылки на термины на код
                $text = preg_replace_callback('/<a\s+href="\{\{term_([^}]+)\}\}"[^>]*>(.*?)<\/a\s*>/is', function($matches) use ($termins, $currentPost) {
                    $terminId = $matches[1];
                    $terminTermin = $matches[2];
                    $terminCode = "{{term_" . $terminId . "}}";
                    $terminDescription = $termins[$terminCode] ?? '';
                    
                    $termin = Termin::where('termin', $terminTermin)->first();
                    if (!$termin) {
                        $termin = Termin::create([
                            'language_code' => $currentPost->language_code,
                            'termin' => $terminTermin,
                            'description' => $terminDescription,
                        ]);
                    }
                    $currentPost->termins()->syncWithoutDetaching($termin->id);
                    if ($termin) {
                        return '<code>' . $termin->termin . '</code>';
                    }
                }, $text);
                
                // Находим все вхождения h3 и шаблонов и обрабатываем их по порядку
                $matches = [];

                // Поиск всех тегов h3
                preg_match_all('/<h3>(.*?)<\/h3>/i', $text, $h3Matches, PREG_OFFSET_CAPTURE);
                if (!empty($h3Matches[0])) {
                    foreach ($h3Matches[0] as $index => $match) {
                        $matches[] = [
                            'type' => 'h3',
                            'content' => $h3Matches[1][$index][0],
                            'fullMatch' => $match[0],
                            'offset' => $match[1],
                            'length' => strlen($match[0])
                        ];
                    }
                }

                // Поиск всех шаблонов
                preg_match_all('/\{\{([^}]+)\}\}/i', $text, $templateMatches, PREG_OFFSET_CAPTURE);
                if (!empty($templateMatches[0])) {
                    foreach ($templateMatches[0] as $index => $match) {
                        $matches[] = [
                            'type' => 'template',
                            'fullMatch' => $match[0],
                            'offset' => $match[1],
                            'length' => strlen($match[0])
                        ];
                    }
                }

                // Сортируем все найденные совпадения по их позиции в тексте
                usort($matches, function($a, $b) {
                    return $a['offset'] - $b['offset'];
                });

                if (empty($matches)) {
                    // Если не найдено ни заголовков h3, ни шаблонов
                    $content[] = [
                        'type' => 'text',
                        'attributes' => [
                            'text' => $text,
                        ],
                    ];
                } else {
                    $currentPosition = 0;
                    
                    // Обрабатываем каждое совпадение по порядку
                    foreach ($matches as $match) {
                        // Добавляем текст до текущего совпадения
                        $textBefore = substr($text, $currentPosition, $match['offset'] - $currentPosition);
                        if (!empty(trim($textBefore))) {
                            $content[] = [
                                'type' => 'text',
                                'attributes' => [
                                    'text' => $textBefore,
                                ],
                            ];
                        }
                        
                        // Обрабатываем текущее совпадение в зависимости от его типа
                        if ($match['type'] === 'h3') {
                            $content[] = [
                                'type' => 'outline',
                                'attributes' => [
                                    'outline' => $match['content'],
                                ],
                            ];
                        } else { // template
                            $templateKey = $match['fullMatch'];
                            if (isset($templates[$templateKey])) {
                                $content[] = $templates[$templateKey];
                            }
                        }
                        
                        // Обновляем текущую позицию
                        $currentPosition = $match['offset'] + $match['length'];
                    }
                    
                    // Добавляем оставшийся текст после последнего совпадения
                    $textAfter = substr($text, $currentPosition);
                    if (!empty(trim($textAfter))) {
                        $content[] = [
                            'type' => 'text',
                            'attributes' => [
                                'text' => $textAfter,
                            ],
                        ];
                    }
                }
            }
            
            // TODO: add other social types
            $socialTypes = [
                'iframe',
                'telegram',
                'twitter',
                'facebook',
                'instagram',
                'vk',
                'audio'
            ];
            if ($block->kind === 'social') {
                $blockContent = json_decode($block->content);
                if (!isset($contentblockContentPostContent->social_embed) || $blockContent->social_embed === '') {
                    continue;
                }
                if (in_array($blockContent->social_type, $socialTypes)) {
                    $socialType = $blockContent->social_type;
                } else {
                    $socialType = 'iframe';
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $contentPostContent->social_embed,
                        'embed_type' => $socialType,
                    ],
                ];
            }
            if ($block->kind === 'audio') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->audio_embed) || $blockContent->audio_embed === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $blockContent->audio_embed,
                        'embed_type' => 'audio',
                    ],
                ];
            }
            if ($block->kind === 'iframe') {
                $blockContent = json_decode($block->content);
                if (!isset($blockContent->iframe) || $blockContent->iframe === '') {
                    continue;
                }
                $content[] = [
                    'type' => 'embed',
                    'attributes' => [
                        'embed_code' => $blockContent->iframe,
                        'embed_type' => 'iframe',
                    ],
                ];
            }
            if ($block->kind === 'related_posts') {
                $blockContent = json_decode($block->content);
                $title = $blockContent->related_posts_title ?? '';

                $relatedPosts= $this->legacy_db->select('
                    SELECT * FROM public.post_relations
                    WHERE postable_id = ' . $block->id . '
                    AND postable_type = \'ContentBlock\'
                ');

                $postIds = [];
                foreach ($relatedPosts as $post) {
                    $postIds[] = $post->post_id;
                }

                if (count($postIds) === 0) {
                    continue;
                }

                $content[] = [
                    'type' => 'related',
                    'attributes' => [
                        'related_title' => $title,
                        'related_posts' => $postIds,
                    ],
                ];
            }
        }

        Post::where('id', $id)->update([
            'content' => json_encode($content),
        ]);

        return true;







        

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
                        'link' => 'https://theins.ru/images/S94ufcu7GG4cr8bfPH6UU_j975FIAlL2kGy_xixJtyI/rs:auto:877:579:0:0/dpr:2/q:100/bG9jYWw6L3B1Ymxp/Yy9zdG9yYWdlL3Bv/c3QvMjgwMjA5L2Zp/bGUtYzBmOTJiZmY0/MWVmNzZiZDExZDk0/ZjJkNTMxYzg1MjUu/anBn.jpg',
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
                
                // Применяем замены на основе $replacements
                if (!empty($replacements)) {
                    $keys = array_keys($replacements);
                    $values = array_values($replacements);
                    $text = str_replace($keys, $values, $text);
                }
                
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

                $content[] = [
                    'type' => 'related',
                    'attributes' => [
                        'related_title' => $contentPostContent->related_posts_title,
                        'related_posts' => $postIds,
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

    private function importContentPostsOnline($id)
    {
        $onlineIds = $this->legacy_db->select('
            SELECT postable_id FROM public.post_relations
            WHERE postable_type = \'OnlineItem\'
            AND post_id = ' . $id
        );
        
        $onlineIds = array_column($onlineIds, 'postable_id');

        if (count($onlineIds) === 0) {
            return;
        }

        $onlines = $this->legacy_db->select('
            SELECT * FROM public.online_items
            WHERE id IN (' . implode(',', $onlineIds) . ')
            ORDER BY time ASC
        ');

        foreach ($onlines as $online) {
            OnlineMessage::create([
                'language_code' => 'ru',
                'post_id' => $id,
                'published_at' => $online->time,
                'is_key_event' => $online->key_point,
                'outline' => $online->title ?? '',
                'text' => $online->text ?? '',
                'images' => $online->image ? [
                    'link' => $online->image,
                    'author' => '',
                    'description' => '',
                ] : [],
                'video_url' => $online->video_embed ?? '',
                'video_description' => '',
                'video_author' => '',
                'embed_code' => $online->social_embed ?? '',
                'embed_type' => $online->social_type ?? 'iframe',
            ]);
        }
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

    private function importPostAuthors($regionId)
    {
        if ($regionId === 1) {
            $languageCode = 'ru';
        } elseif ($regionId === 3) {
            $languageCode = 'en';
        } else {
            throw new \Exception('Unknown region id: ' . $regionId);
        }

        // Получаем ID авторов для указанного региона
        $authorIds = $this->legacy_db->select('
            SELECT regionable_id FROM public.region_relations
            WHERE region_id = ' . $regionId . ' AND regionable_type = \'Person\'
            ORDER BY id ASC 
        ');
        $authorIds = array_column($authorIds, 'regionable_id');

        // Получаем ID постов для указанного региона
        $postIds = $this->legacy_db->select('
            SELECT regionable_id FROM public.region_relations
            WHERE region_id = ' . $regionId . ' AND regionable_type = \'Post\'
            ORDER BY id ASC 
        ');
        $postIds = array_column($postIds, 'regionable_id');

        // Получаем связи между авторами и постами для данного региона
        $postAuthors = $this->legacy_db->select('
            SELECT * FROM public.person_relations
            WHERE ((personable2_type = \'Post\' AND person_id IN (' . implode(',', $authorIds) . '))
            OR (personable3_type = \'Post\' AND person_id IN (' . implode(',', $authorIds) . ')))
            ORDER BY id ASC
        ');

        $relations = [];

        foreach ($postAuthors as $postAuthor) {
            if ($postAuthor->personable2_id && in_array($postAuthor->personable2_id, $postIds)) {
                $relations[$postAuthor->personable2_id][] = $postAuthor->person_id;
            } elseif ($postAuthor->personable3_id && in_array($postAuthor->personable3_id, $postIds)) {
                $relations[$postAuthor->personable3_id][] = $postAuthor->person_id;
            }
        }
        
        if (empty($relations)) {
            return;
        }
        
        $keys = array_keys($relations);

        collect($keys)->chunk(1000)->each(function ($batch) use ($relations, $languageCode) {
            $posts = Post::where('language_code', $languageCode)
                        ->whereIn('id', $batch->toArray())
                        ->get();

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
    }

    private function clearDB()
    {
        Category::truncate();
        Author::truncate();
        InvestigationTheme::truncate();
        Post::truncate();
        OnlineMessage::truncate();
        PostAuthor::truncate();
        Termin::truncate();
    }

    private function importCategories($regionId)
    {
        if ($regionId === 1) {
            $languageCode = 'ru';
        } elseif ($regionId === 3) {
            $languageCode = 'en';
        } else {
            throw new \Exception('Unknown region id: ' . $regionId);
        }

        $categoryIds = $this->legacy_db->select('
            SELECT regionable_id FROM public.region_relations
            WHERE region_id = ' . $regionId . ' AND regionable_type = \'Rubric\'
            ORDER BY id ASC 
        ');

        $categoryIds = array_column($categoryIds, 'regionable_id');

        $categories = $this->legacy_db->select('
            SELECT * FROM public.rubrics
            WHERE id IN (' . implode(',', $categoryIds) . ')
            ORDER BY id ASC
        ');

        collect($categories)->each(function ($category) use ($languageCode) {
            Category::create([
                'language_code' => $languageCode,
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
    }
    private function importPosts($regionId)
    {
        if ($regionId === 1) {
            $languageCode = 'ru';
        } elseif ($regionId === 3) {
            $languageCode = 'en';
        } else {
            throw new \Exception('Unknown region id: ' . $regionId);
        }

        $postIds = $this->legacy_db->select('
            SELECT regionable_id FROM public.region_relations
            WHERE region_id = ' . $regionId . ' AND regionable_type = \'Post\'
            ORDER BY id DESC 
        ');

        $postIds = array_column($postIds, 'regionable_id');

        collect(array_chunk($postIds, 10000))->each(function ($batch) use ($languageCode) {
            $posts = $this->legacy_db->select('
                SELECT posts.*, rubric_relations.rubric_id FROM public.posts
                LEFT JOIN (
                    SELECT rubricable_id, MIN(rubric_id) as rubric_id
                    FROM public.rubric_relations 
                    WHERE rubricable_type = \'Post\'
                    GROUP BY rubricable_id
                ) as rubric_relations ON rubric_relations.rubricable_id = posts.id
                WHERE posts.id IN (' . implode(',', $batch) . ')
            ');
            
            foreach ($posts as $post) {
                $newPost = Post::create([
                    'language_code' => $languageCode,
                    'slug' => $post->slug ?? $post->id,
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
                if ($post->type === 'Post::Online') {
                    $this->importContentPostsOnline($newPost->id);
                } else {
                    $this->importContentPosts($newPost->id);
                }
            }
        });
    }

    private function importAuthors($regionId)
    {
        if ($regionId === 1) {
            $languageCode = 'ru';
        } elseif ($regionId === 3) {
            $languageCode = 'en';
        } else {
            throw new \Exception('Unknown region id: ' . $regionId);
        }

        $authorIds = $this->legacy_db->select('
            SELECT regionable_id FROM public.region_relations
            WHERE region_id = ' . $regionId . ' AND regionable_type = \'Person\'
            ORDER BY id ASC 
        ');

        $authorIds = array_column($authorIds, 'regionable_id');

        $authors = $this->legacy_db->select('
            SELECT * FROM public.people
            WHERE id IN (' . implode(',', $authorIds) . ')
            ORDER BY id ASC
        ');

        collect($authors)->each(function ($author) use ($languageCode) {
            Author::create([
                'id' => $author->id,
                'language_code' => $languageCode,
                'slug' => $author->slug,
                'first_name' => $author->first_name,
                'last_name' => $author->last_name,
                'avatar' => $author->image, // todo
                'position' => $author->work_position,
                'description' => $author->description,
                'twitter' => $author->twitter,
                'facebook' => $author->facebook,
                'allowed_post_types' => ['article', 'opinion', 'news', 'online'],
                'post_types_with_hidden_author_name' => [],
                'is_author_page_hidden' => false,
                'is_columnist_page_hidden' => false,
            ]);
        });
    }

    private function importInvestigationThemes($regionId)
    {
        if ($regionId === 1) {
            $languageCode = 'ru';
        } elseif ($regionId === 3) {
            $languageCode = 'en';
        } else {
            throw new \Exception('Unknown region id: ' . $regionId);
        }

        $themeIds = $this->legacy_db->select('
            SELECT regionable_id FROM public.region_relations
            WHERE region_id = ' . $regionId . ' AND regionable_type = \'Theme\'
            ORDER BY id ASC 
        ');

        $themeIds = array_column($themeIds, 'regionable_id');

        $themes = $this->legacy_db->select('
            SELECT * FROM public.themes
            WHERE id IN (' . implode(',', $themeIds) . ')
            ORDER BY id ASC
        ');

        // Вычисляем начальную позицию для тем
        $position = count($themes) * 10;
        
        collect($themes)->each(function ($theme) use ($languageCode, &$position) {
            InvestigationTheme::create([
                'id' => $theme->id,
                'language_code' => $languageCode,
                'slug' => $theme->slug,
                'title' => $theme->title,
                'description' => $theme->description,
                'position' => !empty($theme->position) ? $theme->position : $position,
                'cover_image' => $theme->image,
                'is_main' => $theme->slug === 'otraviteli-iz-fsb' && $languageCode === 'ru',
                'created_at' => $theme->created_at,
                'updated_at' => $theme->updated_at,
            ]);
            $position--;
        });
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