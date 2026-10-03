<?php

declare(strict_types=1);

namespace app\entities\forum;

enum ForumBlockAction: string {
    case NoForumPosts = 'no_forum_posts';
}
