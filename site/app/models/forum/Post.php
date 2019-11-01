<?php

namespace app\models\forum;

use app\libraries\Core;
use app\models\User;
use app\libraries\DataTime;
use app\models\AbstractModel;

/**
 * Class Post
 *
 * @method void setPostId($id)
 * @method void setThreadId($thread_id)
 * @method void setParentId($parent_id)
 * @method void setContent($content)
 * @method void setTimestamp($timestamp)
 * @method void setIsAnonymous($is_anonymous)
 * @method void setDeleted($deleted)
 * @method void setPostType($post_type)
 * @method void setHasAttachment($has_attachment)
 *
 * @method int       getId()
 * @method int       getThreadId()
 * @method int       getParentId()
 * @method User      getAuthor()
 * @method string    getContent()
 * @method \DateTime getTimestamp()
 * @method bool      getDeleted()
 * @method int       getPostType()
 * @method bool      getHasAttachment()
 */

class Post extends AbstractModel {

    const COMMENT    = 0;
    const UNRESOLVED = 1;
    const RESOLVED   = 2;

    /** @property @var int post id */
    protected $id;
    /** @property @var int thread id */
    protected $thread_id;
    /** @property @var int parent id */
    protected $parent_id;


    //protected $parent;
    
    /** @property @var \User user */
    protected $author;
    
    /** @property @var string content of post */
    protected $content;
    //protected $timestamp;
    
    /** @property @var bool post display as anon */
    protected $is_anonymous;

    //Will add soon
    //protected $deleted;
    //protected $post_type;
    //protected $has_attachment;

    public function __construct(Core $core, $details=array()){
        parent::__construct($core);

        if(empty($details)) {
            return;
        }

        //setPostId($details['post_id']);
        $this->setThreadId((int)$details['thread_id']);
        $this->setParentId((int)$details['parent_id']);
        $this->setContent($details['content']);
        //setTimestamp($details['timestamp']);
        $this->setIsAnonymous((bool)$details['anon']);
        //setDeleted($details['deleted']);
        //setType($details['type']);
        //setAttachment($details['has_attachment']);
    }
}
