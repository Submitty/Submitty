<?php

namespace app\models\forum;

use app\libraries\Core;
use app\models\Post;
use app\models\AbstractModel;

/**
 * Class PostList
 *
 * This class contains a collection of posts to be used when
 * displaying a thread. It handles the logic of post sorting.
 * This class can be used to contain Alphabetical, Chronological
 * and Hiererarchical. (Butchered that spelling...)
 *
 */

class PostList extends AbstractModel {

    const static HIER  = 1;
    const static ALPHA = 2;
    const static CHRON = 3;

    protected $post_list;

    public function __construct(Core $core, $posts=array(), int $type) {
        parent::__construct($core);
        if(empty($details)) {
            return;
        }

        $this->setList($posts, $type);
    }


    public function getFirst() : Post {
        return $post_list[0];
    } 


    /** This function sets the current list
      * 
    */
    public function setList(Array $post_list, int $transform) {
        
        if($transform == PostList::HIER) {

            $idArray = [];

            $post_list = [];

            foreach($post_list as $post) {
                $idArray[$post->getId()] = $post;
            }



        } else {
            $this->post_list = $post_list;
        }


    }

}