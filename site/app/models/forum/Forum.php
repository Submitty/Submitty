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

    public function publish(Array $data, bool $isThread) : bool {

        $pushFunction = null;

        if($isThread) {
            $verify = $this->validateThreadData($data, true);
            //$pushFunction = new $this->forum_db->pushThread;
        } else {
            $verify = $this->validatePostData($data, true, false);
            //$pushFunction = new $this->forum_db->pushPost;
        }

        if(!$verify[0]) {
            return false;
        }

        $result = $verify[1];

        if($isThread && $data['email_announcement']) {
            $this->sendEmailAnnouncement($result);
        }

        //$pushFunction($result);

        return true;

    }


    public function pinThread($thread_id, $toggle) {
        $user = $this->core->getUser()->getId();

        if(!$this->core->getQueries()->threadExists($thread_id)) {
            return false;
        }

        //Will use forum queries
        $query_execute = $this->core->getQueries()->addPinnedThread($user, $thread_id, $toggle);

        return $query_execute;
    }

    public function getEditContent($post_id) {
        if($this->checkPostEditAccess($post_id) && !empty($post_id)) {
            
            //This will return a Post obj also forum queries...
            $post = $this->core->getQueries()->getPost($post_id);
            $output = array();

            //Will have to refer to submitty.org specs for json
            $output['post'] = $post->getContent();
            $output['post_time'] = $post->getTimestamp();
            $output['anon'] = $post->getIsAnonymous();
            $output['change_anon'] = $this->modifyAnonymous($post->getAuthor());
            $output['user'] = $output['anon'] ? 'Anonymous' : $post->getAuthor();
            if(isset($_POST["thread_id"])) {
                $this->getThreadContent($post->getThreadId(), $output);
            }
            return $output;
        }
        return ['error' => 'You do not have permissions to do that.'];
    }

    //Private helper functions

    private function sendEmailAnnouncement(Thread $thread) {
        $class_list = $this->core->getQueries()->getClassEmailList();
        $formatted_body = "An Instructor/TA made an announcement in the Submitty discussion forum:\n\n".$thread->getContent();

        foreach($class_list as $student_email) {
            $email_data = array(
                "subject" => $thread->getTitle(),
                "body" => $formatted_body,
                "recipient" => $student_email["user_email"]
            );

            $announcement_email = new Email($this->core, $email_data);
            $this->core->getQueries()->createEmail($announcement_email);
        }

    }

    private function getThreadContent($thread_id, &$output){
        $result = $this->core->getQueries()->getThread($thread_id)[0];
        $output['title'] = $result["title"];
        $output['categories_ids'] = $this->core->getQueries()->getCategoriesIdForThread($thread_id);
        $output['thread_status'] = $result["status"];
    }

    // Validation of form data
    private function validateThreadData(Array $data, bool $createObject) : Array {

        //Validate the post data prior to thread data
        $goodPost = $this->validatePostData($data, false, true);
        
        if( !$goodPost[0] ||
            empty($data['title']) || empty($data['status']) ||
            empty($data['announcement']) || empty($data['categories']) || 
            empty($data['email_announcement']) || $data['parent_id'] !== -1 ||
            !$this->isValidCategories($this->core->getQueries()->getCategories(), $data['categories'])){
            return [false, null];
        }

        return $createObject ? [true, new Thread($this->core, $data)] : [true, null];

    }

    private function validatePostData(Array $data, bool $createObject, bool $isThread) : Array {

        if( empty($data['content']) || empty($data['anon']) || 
            empty($data['thread_id']) || empty($data['parent_id']) ||
            (!$isThread && !$this->core->getQueries()->existsThread($data['thread_id'])) ||
            (!$isThread && !$this->core->getQueries()->existsPost($data['thread_id'], $data['parent_id'])) ) {
            return [false, null];
        }

        return $createObject ? [true, new Post($this->core, $data)] : [true, null];

    }



}
