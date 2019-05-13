<?php

namespace app\models\forum;

use app\libraries\ForumUtils;
use app\models\AbstractModel;

/**
 * Class Forum
 *
 * Interface for all forum operations
 * This contains a forum specific database object
 */
class Forum extends AbstractModel {
    
    private $forum_db = null;
    
    public function __construct($core) {
        parent::__construct($core);
        //$this->forum_db = $forum_db;
    }

    public function publish(Array $data, bool $isThread) {

        $pushFunction = null;

        if($isThread) {
            $verify = $this->validateThreadData($data, true);
            //$pushFunction = new $this->forum_db->pushThread;
        } else {
            $verify = $this->validatePostData($data, true, false);
            //$pushFunction = new $this->forum_db->pushPost;
        }

        if(!$verify[0]) {
            $this->core->addErrorMessage("The post data is malformed. Please try submitting your post again.");
            return;
        }

        $result = $verify[1];

        //$pushFunction($result);

        return 'Good stuff we passed a post.';

    }



    public function validateThreadData(Array $data, bool $createObject) {

        $goodPost = $this->validatePostData($data, false, true);
        
        if( !$goodPost[0] ||
            empty($data['title']) || empty($data['status']) ||
            empty($data['announcement']) || empty($data['categories']) || 
            empty($data['email_announcement']) || $data['parent_id'] !== -1 ||
            !ForumUtils::isValidCategories($this->core->getQueries()->getCategories(), $categories_ids)){
            return [false, null];
        }

        return $createObject ? [true, new Thread($this->core, $data)] : [true, null];

    }

    public function validatePostData(Array $data, bool $createObject, bool $isThread) {

        //Still need to validate thread id...
        if( empty($data['content']) || empty($data['anon']) || 
            empty($data['thread_id']) || empty($data['parent_id']) ||
            (!$isThread && !$this->core->getQueries()->existsThread($data['thread_id'])) ||
            (!$isThread && !$this->core->getQueries()->existsPost($data['thread_id'], $data['parent_id'])) ) {
            return [false, null];
        }

        return $createObject ? [true, new Post($this->core, $data)] : [true, null];

    }



}
