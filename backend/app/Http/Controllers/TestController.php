<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

use App\Models\InvestigationTheme;

class TestController extends Controller
{
    private $legacy_db;

    public function __construct()
    {
        $this->legacy_db = DB::connection('legacy_pgsql');
    }

    public function test()
    {
        $this->importUsers();
        $this->importInvestigationThemes();

        
        
        

        $themes = collect($result)->pluck('id', 'title');

        $result = $legacy_db->select('
            SELECT *
            FROM public.themes;
        ');
        dd($result);

        $result = $legacy_db->select('
            SELECT rubricable_id
            FROM public.rubric_relations
            WHERE rubricable_type = \'Post\'
            GROUP BY rubricable_id
            HAVING COUNT(rubric_id) > 1;
        ');
        $ids = collect($result)->pluck('rubricable_id');
        
        $posts = collect();
        $postsData = $legacy_db->select('
            SELECT posts.*, rubric_relations.rubric_id, rubrics.title AS rubric_title
            FROM public.posts
            LEFT JOIN public.rubric_relations ON rubricable_id = posts.id AND rubricable_type = \'Post\'
            LEFT JOIN public.rubrics ON rubrics.id = rubric_relations.rubric_id
            WHERE posts.id IN (' . $ids->implode(',') . ')
            ORDER BY posts.id
        ');
        
        $currentPostId = null;
        $currentPost = null;
        
        foreach ($postsData as $row) {
            if ($currentPostId !== $row->id) {
                if ($currentPost) {
                    $posts->push($currentPost);
                }
                $currentPostId = $row->id;
                $currentPost = (object) get_object_vars($row);
                $currentPost->rubrics = [];
            }
            
            $currentPost->rubrics[] = [
                'id' => $row->rubric_id,
                'title' => $row->rubric_title
            ];
            
            // Удаляем отдельные поля рубрик, так как они теперь в массиве
            unset($currentPost->rubric_id);
            unset($currentPost->rubric_title);
        }
        
        if ($currentPost) {
            $posts->push($currentPost);
        }
        
        $result = $posts->all();

        dd($result);

        return [
            'hello' => 'world',
        ];
    }

    private function importUsers()
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


        $admins = $this->legacy_db->select('
            SELECT * FROM public.admins
            ORDER BY id ASC 
        ');

        $adminsRelation = [];

        foreach ($peopleRU as $person) {
            $id = trim($person->first_name) . ' ' . trim($person->last_name);
            if (!empty($person->admin_id)) {
                $adminsRelation[$person->admin_id][] = $id;
            } else {
                foreach ($admins as $admin) {
                    if (trim($admin->name) === $id || trim($admin->name) === $this->translit($id)) {
                        $adminsRelation[$admin->id][] = $id;
                    }
                }
            }
        }

        foreach ($peopleEN as $person) {
            $id = trim($person->first_name) . ' ' . trim($person->last_name);
            if (!empty($person->admin_id)) {
                $adminsRelation[$person->admin_id][] = $id;
            } else {
                foreach ($admins as $admin) {
                    if (trim($admin->name) === $id) {
                        $adminsRelation[$admin->id][] = $id;
                    }
                }
            }
        }

        dd($adminsRelation);

        $admins = [];



        foreach ($peopleEN as $person) {
            $id = trim($person->last_name);
            $admins[$id][] = $person->first_name . ' ' . $person->last_name;
        }

        foreach ($peopleRU as $person) {
            $id = trim($person->last_name);
            $translitId = $this->translit($id);
            $admins[$translitId][] = $person->first_name . ' ' . $person->last_name;
        }

        // Сортировка массива $admins по ключу в алфавитном порядке
        ksort($admins);
        
        dd($admins);

        $users = $this->legacy_db->select('
            SELECT * FROM public.people
            ORDER BY id ASC
        ');

        dd($users);
    }

    private function translit($str)
    {
        $converter = [
            'а' => 'a',   'б' => 'b',   'в' => 'v',   'г' => 'g',   'д' => 'd',
            'е' => 'e',   'ё' => 'e',   'ж' => 'zh',  'з' => 'z',   'и' => 'i',
            'й' => 'y',   'к' => 'k',   'л' => 'l',   'м' => 'm',   'н' => 'n',
            'о' => 'o',   'п' => 'p',   'р' => 'r',   'с' => 's',   'т' => 't',
            'у' => 'u',   'ф' => 'f',   'х' => 'kh',  'ц' => 'ts',  'ч' => 'ch',
            'ш' => 'sh',  'щ' => 'sch', 'ъ' => '',    'ы' => 'y',   'ь' => '',
            'э' => 'e',   'ю' => 'yu',  'я' => 'ya',
            ' ' => '-',   '.' => '',    ',' => '',    '-' => '-',   '(' => '',
            ')' => '',    '?' => '',    '!' => '',    ':' => '',    ';' => ''
        ];
        
        $str = mb_strtolower($str, 'UTF-8');
        $str = strtr($str, $converter);
        $str = preg_replace('/[^a-z0-9\-]/', '', $str);
        $str = preg_replace('/-+/', '-', $str);
        $str = trim($str, '-');
        
        return $str;
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
            InvestigationTheme::where('slug', $theme->slug)->where('language_code', 'ru')->delete();
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
            InvestigationTheme::where('slug', $theme->slug)->where('language_code', 'en')->delete();
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

        dd($themeRU, $themeEN);
    }
}