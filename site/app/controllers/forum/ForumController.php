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
            case 'remove_announcement':
                $this->removeAnnouncement();
                break;
            case 'view_thread':
            default:
                $this->showThreads();
                break;
        }
    }

    //CODE WILL BE CONSOLIDATED IN FUTURE

    public function publishThread(){
        $title = htmlentities($_POST["title"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $thread_content = htmlentities(str_replace("\r", "", $_POST["thread_content"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $thread_content = $this->displayUrl($thread_content);
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        $announcment = (isset($_POST["Announcement"]) && $_POST["Announcement"] == "Announcement" && $this->core->getUser()->getGroup() < 3) ? 1 : 0 ;
        if(empty($title) || empty($thread_content)){
            $this->core->addErrorMessage("One of the fields was empty. Please re-submit your thread.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
        } else {
            $hasGoodAttachment = Utils::checkUploadedImageFile('file_input') ? 1 : 0;
            if($hasGoodAttachment == 0 && !empty($_FILES['file_input']['tmp_name'])){
                $this->core->addErrorMessage("Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)");
                $_SESSION["thread_content"] = $_POST["thread_content"];
                $_SESSION["thread_title"] = $_POST["title"];
                $_SESSION["thread_recover_active"] = true;  
                $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
                return;
            }
            $result = $this->core->getQueries()->createThread($this->core->getUser()->getId(), $title, $thread_content, $anon, $announcment, $hasGoodAttachment);
            $id = $result["thread_id"];
            $post_id = $result["post_id"];
            $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $id);
            FileUtils::createDir($thread_dir);
            if($hasGoodAttachment == 1) {
                $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                FileUtils::createDir($post_dir);
                $target_file = $post_dir . "/" . basename($_FILES["file_input"]["name"]);
                move_uploaded_file($_FILES["file_input"]["tmp_name"], $target_file);
            }
        }
        $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $id)));
    }

    public function publishPost(){
        $post_content = htmlentities($_POST["post_content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $thread_id = htmlentities($_POST["thread_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        if(empty($post_content) || empty($thread_id)){
            $this->core->addErrorMessage("There was an error submitting your post. Please re-submit your post.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
        } else {
            $hasGoodAttachment = Utils::checkUploadedImageFile('file_input') ? 1 : 0;
            if($hasGoodAttachment == 0 && !empty($_FILES['file_input']['tmp_name'])){
                $this->core->addErrorMessage("Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)");
                $_SESSION["post_content"] = $_POST["post_content"];
                $_SESSION["post_recover_active"] = true;  
                $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
                return;
            }
            $post_id = $this->core->getQueries()->createPost($this->core->getUser()->getId(), $post_content, $thread_id, $anon, 0, false, $hasGoodAttachment);
            $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);
            if($hasGoodAttachment == 1) {
                $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                FileUtils::createDir($post_dir);
                $target_file = $post_dir . "/" . basename($_FILES["file_input"]["name"]);
                move_uploaded_file($_FILES["file_input"]["tmp_name"], $target_file);
            }
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
        }
    }

    public function removeAnnouncement(){
        if($this->core->getUser()->accessAdmin()){
            $thread_id = $_POST["thread_id"];
            $this->core->getQueries()->setAnnouncement($thread_id, 0);
        } else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
    }

    public function deletePost(){
        if($this->core->getUser()->getRank() < 3){
            $thread_id = $_POST["thread_id"];
            $post_id = $_POST["post_id"];
            $type = "";
            if($this->core->getQueries()->deletePost($post_id, $thread_id)){
                $type = "thread";
            } else {
                $type = "post";
            }
            $response = array('type' => $type);
            $this->core->getOutput()->renderJson($response);
            var_dump($response);
            throw new Exception();
            return $response;
        } else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
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

    private function displayUrl($content){
        $pos = 0;
        $positions = array();
        while(($pos = strpos($content, "&lbrack;url&equals;", $pos)) !== false){
            $end_pos = strpos($content, "&lbrack;&sol;url&rsqb;", $pos);
            $end_bracket = strpos($content, "&rsqb;", $pos);
            $line = html_entity_decode(substr($content, $pos, $end_bracket) , ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $replacement = "<a href='" . substr($line, 5, strpos($line, "]")-5) . "'>" . htmlentities(substr($content, $end_bracket+6, $end_pos-$end_bracket-6), ENT_QUOTES | ENT_HTML5, 'UTF-8'). "</a>";
            $content = str_replace(substr($content, $pos, $end_pos+22), $replacement, $content);
        } return $content;
    }

}