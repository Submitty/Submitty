<?php

namespace app\models\forum;

use app\libraries\Core;


/**
 * Class Post
 *
 * @method void setPostId($post_id)
 * @method void setParentId($parent_id)
 * @method void setThreadId($thread_id)
 * @method void setContent($content)
 * @method void setIsAnonymous($is_anonymous)
 * @method void setPostType($post_type)
 * @method void setTimestamp($timestamp)
 * @method void setId(string $id) Get the id of the loaded user
 */

class Post extends AbstractModel {

	const COMMENT    = 0;
    const UNRESOLVED = 1;
    const RESOLVED   = 2;

	protected $post_id;
	protected $parent_id;
	protected $thread_id;

	protected $author;

	protected $content;

	protected $is_anonymous;

	protected $post_type;

	protected $timestamp;

	public function __construct(Core $core, $details=array()) {
		if(empty($details)) {
			return;
		}

		setPostId($details['post_id']);
		setParentId($details['parent_id']);
		setThreadId($details['thread_id']);

		
	} 

}