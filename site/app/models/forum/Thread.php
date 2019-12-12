<?php

namespace app\models\forum;

use app\libraries\Core;
use app\models\AbstractModel;

/**
 * Class Thread
 *
 * @method void setId($id)
 * @method void setAuthor($author)
 * @method void setTitle($title)
 * @method void setPinned($pinned)
 * @method void setDeleted($deleted)
 * @method void setMergeThreadId($merged_thread_id)
 * @method void setMergedPostId($merged_post_id)
 * @method void setVisible($is_visible)
 * @method void setStatus($status)
 */

class Thread extends AbstractModel {

    protected $post_id;
    protected $parent_id;
    protected $thread_id;

    protected $author;
    protected $title;
    protected $pinned;
    protected $deleted;

    protected $post_list;

    public function __construct(Core $core, $details = array()) {
        parent::__construct($core);
        if (empty($details)) {
            return;
        }

        setPostId($details['post_id']);
        setParentId($details['parent_id']);
        setThreadId($details['thread_id']);
    }

    public function getFirstPost(): Post {
        return $posts_list[0];
    }
}
