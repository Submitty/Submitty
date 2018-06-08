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
            case 'make_announcement':
                $this->alterAnnouncement(1);
                break;
            case 'publish_post':
                $this->publishPost();
                break;
            case 'delete_post':
                $this->alterPost(0);
                break;
            case 'edit_post':
                $this->alterPost(1);
                break;
            case 'search_threads':
                $this->search();
                break;
            case 'get_edit_post_content':
                $this->getEditPostContent();
                break;
            case 'remove_announcement':
                $this->alterAnnouncement(0);
                break;
            case 'get_threads':
                $this->getThreads();
                break;
            case 'add_category':
                $this->addNewCategory();
                break;
            case 'edit_category':
                $this->editCategory();
                break;
            case 'reorder_categories':
                $this->reorderCategories();
                break;
            case 'show_stats':
                $this->showStats();
                break;
            case 'merge_thread':
                $this->mergeThread();
                break;
            case 'pin_thread':
                $this->pinThread(1);
                break;
            case 'unpin_thread':
                $this->pinThread(0);
                break;
            case 'view_thread':
            default:
                $this->showThreads();
                break;
        }
    }


    private function returnUserContentToPage($error, $isThread, $thread_id){
            //Notify User
            $this->core->addErrorMessage($error);
            if($isThread){
                $url = $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'));
            } else {
                $url = $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id));
            }

            $this->core->redirect($url);
    }


    private function checkGoodAttachment($isThread, $thread_id, $file_post){
        if($_FILES[$file_post]['error'][0] === UPLOAD_ERR_NO_FILE){
            return 0;
        }
        if(count($_FILES[$file_post]['tmp_name']) > 5) {
            $this->returnUserContentToPage("Max file upload size is 5. Please try again.", $isThread, $thread_id);
            return -1;
        }
        $imageCheck = Utils::checkUploadedImageFile($file_post) ? 1 : 0;
        if($imageCheck == 0 && !empty($_FILES[$file_post]['tmp_name'])){
            $this->returnUserContentToPage("Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)", $isThread, $thread_id);
            return -1;

        } return $imageCheck;
    }

    private function isValidCategories($inputCategoriesIds = -1, $inputCategoriesName = -1){
        $rows = $this->core->getQueries()->getCategories();
        if(is_array($inputCategoriesIds)) {
            if(count($inputCategoriesIds) < 1) {
                return false;
            }
            foreach ($inputCategoriesIds as $category_id) {
                $match_found = false;
                foreach($rows as $index => $values){
                    if($values["category_id"] === $category_id) {
                        $match_found = true;
                        break;
                    }
                }
                if(!$match_found) {
                    return false;
                }
            }
        }
        if(is_array($inputCategoriesName)) {
            if(count($inputCategoriesName) < 1) {
                return false;
            }
            foreach ($inputCategoriesName as $category_name) {
                $match_found = false;
                foreach($rows as $index => $values){
                    if($values["category_desc"] === $category_name) {
                        $match_found = true;
                        break;
                    }
                }
                if(!$match_found) {
                    return false;
                }
            }
        }
        return true;
    }

    public function addNewCategory(){
        $result = array();
        if($this->core->getUser()->getGroup() <= 2){
            if(!empty($_REQUEST["newCategory"])) {
                $category = $_REQUEST["newCategory"];
                if($this->isValidCategories(-1, array($category))) {
                    $result["error"] = "That category already exists.";
                } else {
                    $newCategoryId = $this->core->getQueries()->addNewCategory($category);
                    $result["new_id"] = $newCategoryId["category_id"];
                }
            } else {
                $result["error"] = "No category data submitted. Please try again.";
            }
        } else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $result;
    }

    public function editCategory(){
        $result = array();
        if($this->core->getUser()->getGroup() <= 2){
            $category_id = $_REQUEST["category_id"];
            $toupdate_category_desc = null;
            $toupdate_category_color = null;
            $should_update = true;

            if((!empty($_REQUEST["category_desc"])) && isset($_REQUEST["category_desc"])) {
                $category_desc = $_REQUEST["category_desc"];
                if($this->isValidCategories(-1, array($category_desc))) {
                    $result["error"] = "That category already exists.";
                    $should_update = false;
                }
            }
            if(isset($_REQUEST["category_color"])) {
                $category_color = $_REQUEST["category_color"];
                if(!in_array($category_color, $this->getAllowedCategoryColor())) {
                    $result["error"] = "Given category color is not allowed.";
                    $should_update = false;
                }
            }
            if($should_update) {
                $this->core->getQueries()->editCategory($category_id, null, $category_color);
                $result["success"] = "OK";
            } else if(!isset($result["error"])) {
                $result["error"] = "No category data updated. Please try again.";
            }
        } else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $result;
    }

    public function reorderCategories(){
        $result = array();
        if($this->core->getUser()->getGroup() <= 2){
            $rows = $this->core->getQueries()->getCategories();

            $current_order = array();
            foreach ($rows as $row) {
                $current_order[] = (int)$row['category_id'];
            }
            $new_order = array();
            foreach ($_POST['categorylistitem'] as $item) {
                $new_order[] = (int)$item;
            }

            if(count(array_diff(array_merge($current_order, $new_order), array_intersect($current_order, $new_order))) === 0) {
                $this->core->getQueries()->reorderCategories($new_order);
                $results["success"] = "ok";
            } else {
                $result["error"] = "Different Categories IDs given";
            }
        } else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $result;
    }

    //CODE WILL BE CONSOLIDATED IN FUTURE

    public function publishThread(){
        $title = $_POST["title"];
        $thread_content = str_replace("\r", "", $_POST["thread_content"]);
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        $announcment = (isset($_POST["Announcement"]) && $_POST["Announcement"] == "Announcement" && $this->core->getUser()->getGroup() < 3) ? 1 : 0 ;
        $categories_ids  = array();
        foreach ($_POST["cat"] as $category_id) {
            $categories_ids[] = (int)$category_id;
        }
        if(empty($title) || empty($thread_content)){
            $this->core->addErrorMessage("One of the fields was empty or bad. Please re-submit your thread.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
        }else if(!$this->isValidCategories($categories_ids)){
            $this->core->addErrorMessage("You must select valid categories. Please re-submit your thread.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
        } else {
            $hasGoodAttachment = $this->checkGoodAttachment(true, -1, 'file_input');
            if($hasGoodAttachment == -1){
                return;
            }

            $result = $this->core->getQueries()->createThread($this->core->getUser()->getId(), $title, $thread_content, $anon, $announcment, $hasGoodAttachment, $categories_ids);
            $id = $result["thread_id"];
            $post_id = $result["post_id"];

            if($hasGoodAttachment == 1) {

                $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $id);
                FileUtils::createDir($thread_dir);

                $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                FileUtils::createDir($post_dir);

                for($i = 0; $i < count($_FILES["file_input"]["name"]); $i++){
                    $target_file = $post_dir . "/" . basename($_FILES["file_input"]["name"][$i]);
                    move_uploaded_file($_FILES["file_input"]["tmp_name"][$i], $target_file);
                }

            }

        }
        $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $id)));
    }

    private function search(){
        $results = $this->core->getQueries()->searchThreads($_POST['search_content']);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'searchResult', $results);
    }

    public function publishPost(){
        $parent_id = (!empty($_POST["parent_id"])) ? htmlentities($_POST["parent_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8') : -1;
        $post_content_tag = 'post_content';
        $file_post = 'file_input';
        if(empty($_POST['post_content'])){
            $post_content_tag .= ('_' . $parent_id);
            $file_post .= ('_' . $parent_id);
        }
        $post_content = str_replace("\r", "", $_POST[$post_content_tag]);
        $thread_id = htmlentities($_POST["thread_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $display_option = (!empty($_POST["display_option"])) ? htmlentities($_POST["display_option"], ENT_QUOTES | ENT_HTML5, 'UTF-8') : "tree";
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        if(empty($post_content) || empty($thread_id)){
            $this->core->addErrorMessage("There was an error submitting your post. Please re-submit your post.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
        } else if(!$this->core->getQueries()->existsThread($thread_id)) {
            $this->core->addErrorMessage("There was an error submitting your post. Thread doesn't exists.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
        } else {
            $hasGoodAttachment = $this->checkGoodAttachment(false, $thread_id, $file_post);
            if($hasGoodAttachment == -1){
                return;
            }
            $post_id = $this->core->getQueries()->createPost($this->core->getUser()->getId(), $post_content, $thread_id, $anon, 0, false, $hasGoodAttachment, $parent_id);
            $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

            if(!is_dir($thread_dir)) {
                FileUtils::createDir($thread_dir);
            }

            if($hasGoodAttachment == 1) {
                $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                FileUtils::createDir($post_dir);
                for($i = 0; $i < count($_FILES[$file_post]["name"]); $i++){
                    $target_file = $post_dir . "/" . basename($_FILES[$file_post]["name"][$i]);
                    move_uploaded_file($_FILES[$file_post]["tmp_name"][$i], $target_file);
                }
            }
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'option' => $display_option, 'thread_id' => $thread_id)));
        }
    }

    public function alterAnnouncement($type){
        if($this->core->getUser()->getGroup() <= 2){
            $thread_id = $_POST["thread_id"];
            $this->core->getQueries()->setAnnouncement($thread_id, $type);
        } else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
    }

    public function pinThread($type){
        $thread_id = $_POST["thread_id"];
        $current_user = $this->core->getUser()->getId();
        $this->core->getQueries()->addPinnedThread($current_user, $thread_id, $type);
        $response = array('user' => $current_user, 'thread' => $thread_id, 'type' => $type);
        $this->core->getOutput()->renderJson($response);
        return $response;
    }

    public function alterPost($modifyType){
        if($this->core->getUser()->getGroup() <= 2){

            if($modifyType == 0) { //delete post
                $thread_id = $_POST["thread_id"];
                $post_id = $_POST["post_id"];
                $type = "";
                if($this->core->getQueries()->deletePost($post_id, $thread_id)){
                    $type = "thread";
                } else {
                    $type = "post";
                }
                $this->core->getOutput()->renderJson(array('type' => $type));
            } else if($modifyType == 1) { //edit post
                $thread_id = $_POST["edit_thread_id"];
                $post_id = $_POST["edit_post_id"];
                $new_post_content = $_POST["edit_post_content"];
                if(!$this->core->getQueries()->editPost($post_id, $new_post_content)){
                    $this->core->addErrorMessage("There was an error trying to modify the post. Please try again.");
                } $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
            }
            $response = array('type' => $type);
            $this->core->getOutput()->renderJson($response);
            return $response;
        } else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
    }

    private function getSortedThreads($category_id){
        $current_user = $this->core->getUser()->getId();
        if($this->isValidCategories(array($category_id))) {
            $announce_threads = $this->core->getQueries()->loadAnnouncements($category_id);
            $reg_threads = $this->core->getQueries()->loadThreads($category_id);
        } else {
            $announce_threads = $this->core->getQueries()->loadAnnouncementsWithoutCategory();
            $reg_threads = $this->core->getQueries()->loadThreadsWithoutCategory();
        }
        $favorite_threads = $this->core->getQueries()->loadPinnedThreads($current_user);

        $ordered_threads = array();
        // Order : Favourite and Announcements => Announcements only => Favourite only => Others
        foreach ($announce_threads as $thread) {
            if(in_array($thread['id'], $favorite_threads)) {
                $thread['favorite'] = true;
                $ordered_threads[] = $thread;
            }
        }
        foreach ($announce_threads as $thread) {
            if(!in_array($thread['id'], $favorite_threads)) {
                $ordered_threads[] = $thread;
            }
        }
        foreach ($reg_threads as $thread) {
            if(in_array($thread['id'], $favorite_threads)) {
                $thread['favorite'] = true;
                $ordered_threads[] = $thread;
            }
        }
        foreach ($reg_threads as $thread) {
            if(!in_array($thread['id'], $favorite_threads)) {
                $ordered_threads[] = $thread;
            }
        }

        foreach ($ordered_threads as &$thread) {
            $list = array();
            foreach(explode("|", $thread['categories_ids']) as $id ) {
                $list[] = (int)$id;
            }
            $thread['categories_ids'] = $list;
            $thread['categories_desc'] = explode("|", $thread['categories_desc']);
            $thread['categories_color'] = explode("|", $thread['categories_color']);
        }
        return $ordered_threads;
    }

    public function getThreads(){

        $category_id = array_key_exists('thread_category', $_POST) && !empty($_POST["thread_category"]) ? (int)$_POST['thread_category'] : -1;

        $max_thread = 0;
        $threads = $this->getSortedThreads($category_id, $max_thread);

        $currentCategoriesIds = array_key_exists('currentCategoriesId', $_POST) ? explode("|", $_POST["currentCategoriesId"]) : array();
        $currentThreadId = array_key_exists('currentThreadId', $_POST) && !empty($_POST["currentThreadId"]) && is_numeric($_POST["currentThreadId"]) ? (int)$_POST["currentThreadId"] : -1;
        $thread_data = array();
        $current_thread_title = "";
        $activeThread = false;
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showAlteredDisplayList', $threads, true, $currentThreadId, $currentCategoriesIds);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return $this->core->getOutput()->renderJson(array("html" => $this->core->getOutput()->getOutput()));
    }

    public function showThreads(){
        $user = $this->core->getUser()->getId();

        $category_id = in_array('thread_category', $_POST) ? $_POST['thread_category'] : -1;

        $max_thread = 0;
        $threads = $this->getSortedThreads($category_id, $max_thread);

        $current_user = $this->core->getUser()->getId();

        $posts = null;
        if(!isset($_REQUEST['option'])){
            $_REQUEST['option'] = 'tree';
        }
        $option = ($this->core->getUser()->getGroup() <= 2 || $_REQUEST['option'] != 'alpha') ? $_REQUEST['option'] : 'tree';
        if(!empty($_REQUEST["thread_id"])){
            $thread_id = (int)$_REQUEST["thread_id"];
            if($option == "alpha"){
                $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, 'alpha');
            } else {
                $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, 'tree');
            }
            
        } 

        if(empty($_REQUEST["thread_id"]) || empty($posts)) {
            $posts = $this->core->getQueries()->getPostsForThread($current_user, -1);
        }
        
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $posts, $threads, $option, $max_thread);
    }

    public function getAllowedCategoryColor() {
        $colors = array();
        $colors[] = "#FF0000";
        $colors[] = "#00FF00";
        $colors[] = "#0000FF";
        return $colors;
    }

    public function showCreateThread(){
         $this->core->getOutput()->renderOutput('forum\ForumThread', 'createThread', $this->getAllowedCategoryColor());
    }

    public function getEditPostContent(){
        $post_id = $_POST["post_id"];
        if($this->core->getUser()->getGroup() <= 2 && !empty($post_id)) {
            $result = $this->core->getQueries()->getPost($post_id);
            $output = array();
            $output['user'] = $result["author_user_id"];
            $output['post'] = $result["content"];
            $output['post_time'] = $result['timestamp'];
            $this->core->getOutput()->renderJson($output);
            return $output;
        } else {
            $this->core->getOutput()->renderJson(array('error' => "You do not have permissions to do that."));
        }
    }


    public function showStats(){
        $posts = array();
        $posts = $this->core->getQueries()->getPosts();
        $num_posts = count($posts);
        $function_date = 'date_format';
        $num_threads = 0;
        $users = array();
        for($i=0;$i<$num_posts;$i++){
            $user = $posts[$i]["author_user_id"];
            $content = $posts[$i]["content"];
            if(!isset($users[$user])){
                $users[$user] = array();
                $u = $this->core->getQueries()->getSubmittyUser($user);
                $users[$user]["first_name"] = htmlspecialchars($u -> getDisplayedFirstName());
                $users[$user]["last_name"] = htmlspecialchars($u -> getLastName());
                $users[$user]["posts"]=array();
                $users[$user]["id"]=array();
                $users[$user]["timestamps"]=array();
                $users[$user]["total_threads"]=0;
                $users[$user]["num_deleted_posts"] = count($this->core->getQueries()->getDeletedPostsByUser($user));
            }
            if($posts[$i]["parent_id"]==-1){
                $users[$user]["total_threads"]++;
            }
            $users[$user]["posts"][] = $content;
            $users[$user]["id"][] = $posts[$i]["id"];
            $date = date_create($posts[$i]["timestamp"]);
            $users[$user]["timestamps"][] = $function_date($date,"n/j g:i A");
            $users[$user]["thread_id"][] = $posts[$i]["thread_id"];
            $users[$user]["thread_title"][] = $this->core->getQueries()->getThreadTitle($posts[$i]["thread_id"]);


        }
        ksort($users);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'statPage', $users);
    }

    public function mergeThread(){
        $parent_thread_id = $_POST["merge_thread_parent"];
        $child_thread_id = $_POST["merge_thread_child"];
        $thread_id = $child_thread_id;
        if($this->core->getUser()->getGroup() <= 2){
            if(is_numeric($parent_thread_id) && is_numeric($child_thread_id)) {
                $message = "";
                if($this->core->getQueries()->mergeThread($parent_thread_id, $child_thread_id, $message)) {
                    $child_thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $child_thread_id);
                    if(is_dir($child_thread_dir)) {
                        $parent_thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $parent_thread_id);
                        if(!is_dir($parent_thread_dir)) {
                            FileUtils::createDir($parent_thread_dir);
                        }
                        $child_posts_dirs = FileUtils::getAllDirs($child_thread_dir);
                        foreach ($child_posts_dirs as $post_id) {
                            $child_post_dir = FileUtils::joinPaths($child_thread_dir, $post_id);
                            $parent_post_dir = FileUtils::joinPaths($parent_thread_dir, $post_id);
                            rename($child_post_dir, $parent_post_dir);
                        }
                    }
                    $this->core->addSuccessMessage("Threads merged!");
                    $thread_id = $parent_thread_id;
                } else {
                    $this->core->addErrorMessage("Merging Failed! ".$message);
                }
            }
        } else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
        $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
    }

}
