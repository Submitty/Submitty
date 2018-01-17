<?php

namespace app\controllers\forum;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\Output;
use app\libraries\Utils;
use app\libraries\FileUtils;

/**
 * Class ForumHomeController
 *
 * Controller to deal with the submitty home page. Once the user has been authenticated, but before they have
 * selected which course they want to access, they are forwarded to the home page.
 */
class ForumController extends AbstractController {

	/**
     * ForumHomeController constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'create_thread':
                $this->showCreateThread();
                break;
            case 'publish_thread':
                $this->publishThread();
                break;
            case 'publish_post':
                $this->publishPost();
                break;
            case 'delete_post':
                $this->deletePost();
                break;
            case 'view_thread':
            default:
                $this->showThreads();
                break;
        }
    }

    public function publishThread(){
        $title = htmlentities($_POST["title"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $thread_content = htmlentities($_POST["thread_content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        $announcment = (isset($_POST["Announcement"]) && $_POST["Announcement"] == "Announcement" && $this->core->getUser()->getGroup() < 3) ? 1 : 0 ;
        if(empty($title) || empty($thread_content)){
            $this->core->addErrorMessage("One of the fields was empty. Please re-submit your thread.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
        } else if(strlen($title) > 50) {
            $this->core->addErrorMessage("Title must be under 50 characters. Please re-submit your thread.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
        } else {
            $result = $this->core->getQueries()->createThread($this->core->getUser()->getId(), $title, $thread_content, $anon, $announcment);
            $id = $result["thread_id"];
            $post_id = $result["post_id"];
            $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $id);
            FileUtils::createDir($thread_dir);
            if (isset($_FILES['file_input']) && (file_exists($_FILES['file_input']['tmp_name']))) {
                $mime_type = FileUtils::getMimeType($_FILES["file_input"]["tmp_name"]); 
                if(getimagesize($_FILES["file_input"]["tmp_name"]) !== false && substr($mime_type, 0, strrpos($mime_type, "/")) === "image") {
                    $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                    FileUtils::createDir($post_dir);
                    $target_file = $post_dir . "/" . basename($_FILES["file_input"]["name"]);
                    move_uploaded_file($_FILES["file_input"]["tmp_name"], $target_file);
                }
            }
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $id)));
        }
    }

    private function publishPost(){
        $post_content = htmlentities($_POST["post_content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $thread_id = htmlentities($_POST["thread_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        if(empty($post_content) || empty($thread_id)){
            $this->core->addErrorMessage("There was an error submitting your post. Please re-submit your post.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
        } else {
            $this->core->getQueries()->createPost($this->core->getUser()->getId(), $post_content, $thread_id, $anon, 0, false);
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
        }
    }

    public function deletePost(){
        $thread_id = $_POST["thread_id"];
        $post_id = $_POST["post_id"];
        $type = "";
        if($this->core->getQueries()->deletePost($post_id, $thread_id)){
            $type = "thread";
        } else {
            $type = "post";
        }
        $this->core->getOutput()->renderJson(array('type' => $type));
    }

    public function showThreads(){
        $user = $this->core->getUser()->getId();


        //NOTE: This section of code is neccesary until I find a better query 
        //To link the two sets together as the query function doesn't
        //support parenthesis starting a query 
        $announce_threads = $this->core->getQueries()->loadThreads(1);
        $reg_threads = $this->core->getQueries()->loadThreads(0);
        $threads = array_merge($announce_threads, $reg_threads);
        //END

        $current_user = $this->core->getUser()->getId();

        $posts = null;
        if(isset($_REQUEST["thread_id"])){
            $posts = $this->core->getQueries()->getPostsForThread($current_user, $_REQUEST["thread_id"]);
        } else {
            //We are at the "Home page"
            //Show the first post
            $posts = $this->core->getQueries()->getPostsForThread($current_user, -1);
            
        }
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $posts, $threads);
    }

    public function showCreateThread(){
         $this->core->getOutput()->renderOutput('forum\ForumThread', 'createThread');
    }

}