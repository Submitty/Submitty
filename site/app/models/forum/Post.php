<?php

namespace app\models\forum;

use app\libraries\Core;
use app\models\User;
use app\models\AbstractModel;


/**
 * Class Post
 *
 * Setters
 * @method void setPostId(int $id)
 * @method void setThreadId(int $thread_id)
 * @method void setParentId(int $parent_id)
 * @method void setContent(string $content)
 * @method void setTimestamp(\DateTime $timestamp)
 * @method void setIsAnonymous(bool $is_anonymous)
 * @method void setDeleted(bool $deleted)
 * @method void setPostType(int $post_type)
 * @method void setHasAttachment(bool $has_attachment)
 *
 * Accessors
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

    protected $id;
    protected $thread_id;
    protected $parent_id;
    protected $parent;
    protected $author;
    protected $content;
    protected $timestamp;
    protected $is_anonymous;
    protected $deleted;
    protected $post_type;
    protected $has_attachment;

    public function __construct(Core $core, $details=array()){
        parent::__construct($core);

        if(empty($details)) {
            return;
        }

        //setPostId($details['post_id']);
        setThreadId($details['thread_id']);
        setParentId($details['parent_id']);
        setContent($details['content']);
        //setTimestamp($details['timestamp']);
        setAnonymous($details['anonymous']);
        //setDeleted($details['deleted']);
        //setType($details['type']);
        setAttachment($details['has_attachment']);
    } 

}