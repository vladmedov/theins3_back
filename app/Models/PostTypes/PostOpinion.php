<?php

namespace App\Models\PostTypes;

use App\Models\Post;

use \App\Enums\PostTypes;

class PostOpinion extends Post {
    const TYPE = PostTypes::OPINION;
}
