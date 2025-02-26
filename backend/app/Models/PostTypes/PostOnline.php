<?php

namespace App\Models\PostTypes;

use App\Models\Post;

use \App\Enums\PostTypes;

class PostOnline extends Post {
    const TYPE = PostTypes::ONLINE;
}
