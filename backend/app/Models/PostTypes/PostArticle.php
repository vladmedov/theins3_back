<?php

namespace App\Models\PostTypes;

use App\Models\Post;

use \App\Enums\PostTypes;

class PostArticle extends Post {
    const TYPE = PostTypes::ARTICLE;
}