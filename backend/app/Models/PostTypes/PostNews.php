<?php

namespace App\Models\PostTypes;

use App\Models\Post;

use \App\Enums\PostTypes;

class PostNews extends Post {
    const TYPE = PostTypes::NEWS;
}