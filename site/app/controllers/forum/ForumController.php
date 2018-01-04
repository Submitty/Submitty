<?php

namespace app\controllers\forum;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\Output;
use app\libraries\Utils;

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
        if(empty($title) || empty($thread_content)){
            $this->core->addErrorMessage("One of the fields was empty. Please re-submit your thread.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
        } else {
            $id = $this->core->getQueries()->createThread($this->core->getUser()->getId(), $title, $thread_content, $anon, 0);
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
            $this->core->getQueries()->createPost($this->core->getUser()->getId(), $post_content, $thread_id, $anon, 0);
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
        }
    }

    public function showThreads(){
        $user = $this->core->getUser()->getId();
        $threads = $this->core->getQueries()->loadThreads();
        $posts = null;
        if(isset($_REQUEST["thread_id"])){
            $posts = $this->core->getQueries()->getPostsForThread($_REQUEST["thread_id"]);
        } else {
            //We are at the "Home page"
            //Show the first post
            $posts = $this->core->getQueries()->getPostsForThread(-1);
            
        }
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $posts, $threads);
    }

    public function showCreateThread(){
         $this->core->getOutput()->renderOutput('forum\ForumThread', 'createThread');
    }

}