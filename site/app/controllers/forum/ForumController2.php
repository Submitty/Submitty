<?php

namespace app\controllers\forum;

use app\libraries\Core;
use app\models\Notification;
use app\models\Email;
use app\controllers\AbstractController;
use app\libraries\Utils;
use app\libraries\ForumUtils;
use app\libraries\FileUtils;
use app\libraries\DateUtils;

/**
 * Class ForumHomeController
 *
 * Controller to deal with the submitty home page. Once the user has been authenticated, but before they have
 * selected which course they want to access, they are forwarded to the home page.
 */
class ForumController2 extends AbstractController {

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
            case 'undelete_post':
                $this->alterPost(2);
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
            case 'get_history':
                $this->getHistory();
                break;
            case 'add_category':
                $this->addNewCategory();
                break;
            case 'delete_category':
                $this->deleteCategory();
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
            case 'change_thread_status_resolve':
                $this->changeThreadStatus(1);
                break;
            case 'view_thread':
            default:
                $this->showThreads();
                break;
        }
    }

    private function showDeleted() {
        return ($this->core->getUser()->accessGrading() && isset($_COOKIE['show_deleted']) && $_COOKIE['show_deleted'] == "1");
    }

    private function showMergedThreads($currentCourse) {
        return (isset($_COOKIE["{$currentCourse}_show_merged_thread"]) && $_COOKIE["{$currentCourse}_show_merged_thread"] == "1");
    }

    private function returnUserContentToPage($error, $isThread, $thread_id) {
        //Notify User
        $this->core->addErrorMessage($error);
        if ($isThread) {
            $url = $this->core->buildUrl(['component' => 'forum', 'page' => 'create_thread']);
        }
        else {
            $url = $this->core->buildUrl(['component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id]);
        }
        return [-1, $url];
    }

    private function changeThreadStatus($status) {
        $thread_id = $_POST['thread_id'];
        $result = [];
        if ($this->core->getQueries()->getAuthorOfThread($thread_id) === $this->core->getUser()->getId() || $this->core->getUser()->accessGrading()) {
            if ($this->core->getQueries()->updateResolveState($thread_id, $status)) {
                $result['success'] = 'Thread resolve state has been changed.';
            }
            else {
                $result['error'] = 'The thread resolve state could not be updated. Please try again.';
            }
        }
        else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $this->core->getOutput()->getOutput();
    }

    private function isCategoryDeletionGood($category_id) {
        // Check if not the last category which exists
        $rows = $this->core->getQueries()->getCategories();
        foreach ($rows as $index => $values) {
            if (((int) $values["category_id"]) !== $category_id) {
                return true;
            }
        }
        return false;
    }

    public function addNewCategory() {
        $result = [];
        if ($this->core->getUser()->accessGrading()) {
            if (!empty($_REQUEST["newCategory"])) {
                $category = $_REQUEST["newCategory"];
                if ($this->isValidCategories(-1, [$category])) {
                    $result["error"] = "That category already exists.";
                }
                else {
                    $newCategoryId = $this->core->getQueries()->addNewCategory($category);
                    $result["new_id"] = $newCategoryId["category_id"];
                }
            }
            else {
                $result["error"] = "No category data submitted. Please try again.";
            }
        }
        else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $this->core->getOutput()->getOutput();
    }

    public function deleteCategory() {
        $result = [];
        if ($this->core->getUser()->accessGrading()) {
            if (!empty($_REQUEST["deleteCategory"])) {
                $category = (int) $_REQUEST["deleteCategory"];
                if (!$this->isValidCategories([$category])) {
                    $result["error"] = "That category doesn't exists.";
                }
                elseif (!$this->isCategoryDeletionGood($category)) {
                    $result["error"] = "Last category can't be deleted.";
                }
                else {
                    if ($this->core->getQueries()->deleteCategory($category)) {
                        $result["success"] = "OK";
                    }
                    else {
                        $result["error"] = "Category is in use.";
                    }
                }
            }
            else {
                $result["error"] = "No category data submitted. Please try again.";
            }
        }
        else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $this->core->getOutput()->getOutput();
    }

    public function editCategory() {
        $result = [];
        if ($this->core->getUser()->accessGrading()) {
            $category_id = $_REQUEST["category_id"];
            $category_desc = null;
            $category_color = null;
            $should_update = true;

            if (!empty($_REQUEST["category_desc"])) {
                $category_desc = $_REQUEST["category_desc"];
                if ($this->isValidCategories(-1, [$category_desc])) {
                    $result["error"] = "That category already exists.";
                    $should_update = false;
                }
            }
            if (!empty($_REQUEST["category_color"])) {
                $category_color = $_REQUEST["category_color"];
                if (!in_array(strtoupper($category_color), $this->getAllowedCategoryColor())) {
                    $result["error"] = "Given category color is not allowed.";
                    $should_update = false;
                }
            }
            if ($should_update) {
                $this->core->getQueries()->editCategory($category_id, $category_desc, $category_color);
                $result["success"] = "OK";
            }
            elseif (!isset($result["error"])) {
                $result["error"] = "No category data updated. Please try again.";
            }
        }
        else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $this->core->getOutput()->getOutput();
    }

    public function reorderCategories() {
        $result = [];
        if ($this->core->getUser()->accessGrading()) {
            $rows = $this->core->getQueries()->getCategories();

            $current_order = [];
            foreach ($rows as $row) {
                $current_order[] = (int) $row['category_id'];
            }
            $new_order = [];
            foreach ($_POST['categorylistitem'] as $item) {
                $new_order[] = (int) $item;
            }

            if (count(array_diff(array_merge($current_order, $new_order), array_intersect($current_order, $new_order))) === 0) {
                $this->core->getQueries()->reorderCategories($new_order);
                $results["success"] = "ok";
            }
            else {
                $result["error"] = "Different Categories IDs given";
            }
        }
        else {
            $result["error"] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($result);
        return $this->core->getOutput()->getOutput();
    }

    private function search() {
        $results = $this->core->getQueries()->searchThreads($_POST['search_content']);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'searchResult', $results);
    }


    public function alterAnnouncement($type) {
        if ($this->core->getUser()->getGroup() <= 2) {
            $thread_id = $_POST["thread_id"];
            $this->core->getQueries()->setAnnouncement($thread_id, $type);
            if ($type) {
                $notification = new Notification($this->core, ['component' => 'forum', 'type' => 'updated_announcement', 'thread_id' => $thread_id, 'thread_title' => $this->core->getQueries()->getThreadTitle($thread_id)['title']]);
                $this->core->getQueries()->pushNotification($notification);
            }
        }
        else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
    }

    private function checkPostEditAccess($post_id) {
        if ($this->core->getUser()->accessGrading()) {
                // Instructor/full access ta/mentor
                return true;
        }
        else {
            $post = $this->core->getQueries()->getPost($post_id);
            if ($post['author_user_id'] === $this->core->getUser()->getId()) {
                // Original Author
                return true;
            }
        }
        return false;
    }

    private function checkThreadEditAccess($thread_id) {
        if ($this->core->getUser()->accessGrading()) {
                // Instructor/full access ta/mentor
                return true;
        }
        else {
            $post = $this->core->getQueries()->getThread($thread_id)[0];
            if ($post['created_by'] === $this->core->getUser()->getId()) {
                // Original Author
                return true;
            }
        }
        return false;
    }

    /**
     * Alter content/delete/undelete post of a thread
     *
     * If applied on the first post of a thread, same action will be reflected on the corresponding thread
     *
     * @param integer(0/1/2) $modifyType - 0 => delete, 1 => edit content, 2 => undelete
     */
    public function alterPost($modifyType) {
        $post_id = $_POST["post_id"] ?? $_POST["edit_post_id"];
        if (!($this->checkPostEditAccess($post_id))) {
                $this->core->addErrorMessage("You do not have permissions to do that.");
                return;
        }
        if ($modifyType == 0) { //delete post or thread
            $thread_id = $_POST["thread_id"];
            if ($this->core->getQueries()->setDeletePostStatus($post_id, $thread_id, 1)) {
                $type = "thread";
            }
            else {
                $type = "post";
            }
            $post = $this->core->getQueries()->getPost($post_id);
            $post_author = $post['author_user_id'];
            $notification = new Notification($this->core, ['component' => 'forum', 'type' => 'deleted', 'thread_id' => $thread_id, 'post_content' => $post['content'], 'reply_to' => $post_author]);
            $this->core->getQueries()->pushNotification($notification);
            $this->core->getQueries()->removeNotificationsPost($post_id);
            $this->core->getOutput()->renderJson($response = ['type' => $type]);
            return $this->core->getOutput()->getOutput();
        }
        elseif ($modifyType == 2) { //undelete post or thread
            $thread_id = $_POST["thread_id"];
            $result = $this->core->getQueries()->setDeletePostStatus($post_id, $thread_id, 0);
            if (is_null($result)) {
                $error = "Parent post must be undeleted first.";
                $this->core->getOutput()->renderJson($response = ['error' => $error]);
            }
            else {
                /// We want to reload same thread again, in both case (thread/post undelete)
                $type = "post";
                $post = $this->core->getQueries()->getPost($post_id);
                $post_author = $post['author_user_id'];
                $notification = new Notification($this->core, ['component' => 'forum', 'type' => 'undeleted', 'thread_id' => $thread_id, 'post_id' => $post_id, 'post_content' => $post['content'], 'reply_to' => $post_author]);
                $this->core->getQueries()->pushNotification($notification);
                $this->core->getOutput()->renderJson($response = ['type' => $type]);
            }
            return $this->core->getOutput()->getOutput();
        }
        elseif ($modifyType == 1) { //edit post or thread
            $thread_id = $_POST["edit_thread_id"];
            $status_edit_thread = $this->editThread();
            $status_edit_post   = $this->editPost();
            $any_changes = false;
            $isError = false;
            $messageString = '';
             // Author of first post and thread must be same
            if (is_null($status_edit_thread) && is_null($status_edit_post)) {
                $this->core->addErrorMessage("No data submitted. Please try again.");
            }
            elseif (is_null($status_edit_thread) || is_null($status_edit_post)) {
                $type = is_null($status_edit_thread) ? "Post" : "Thread";
                if ($status_edit_thread || $status_edit_post) {
                    //$type is true
                    $messageString = "{$type} updated successfully.";
                    $any_changes = true;
                }
                else {
                    $isError = true;
                    $messageString = "{$type} update failed. Please try again.";
                }
            }
            else {
                if ($status_edit_thread && $status_edit_post) {
                    $messageString = "Thread and post updated successfully.";
                    $any_changes = true;
                }
                else {
                    $type = ($status_edit_thread) ? "Thread" : "Post";
                    $type_opposite = (!$status_edit_thread) ? "Thread" : "Post";
                    $isError = true;
                    if ($status_edit_thread || $status_edit_post) {
                        //$type is true
                        $messageString = "{$type} updated successfully. {$type_opposite} update failed. Please try again.";
                        $any_changes = true;
                    }
                    else {
                        $messageString = "Thread and Post update failed. Please try again.";
                    }
                }
            }
            if ($any_changes) {
                $post = $this->core->getQueries()->getPost($post_id);
                $post_author = $post['author_user_id'];
                $notification = new Notification($this->core, ['component' => 'forum', 'type' => 'edited', 'thread_id' => $thread_id, 'post_id' => $post_id, 'post_content' => $post['content'], 'reply_to' => $post_author]);
                $this->core->getQueries()->pushNotification($notification);
            }
            if ($isError) {
                $this->core->addErrorMessage($messageString);
            }
            else {
                        $this->core->addSuccessMessage($messageString);
            }
            $this->core->redirect($this->core->buildUrl(['component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id]));
        }
    }

    private function editThread() {
        // Ensure authentication before call
        if (!empty($_POST["title"])) {
            $thread_id = $_POST["edit_thread_id"];
            if (!$this->checkThreadEditAccess($thread_id)) {
                return false;
            }
            $thread_title = $_POST["title"];
            $status = $_POST["thread_status"];
            $categories_ids  = [];
            if (!empty($_POST["cat"])) {
                foreach ($_POST["cat"] as $category_id) {
                    $categories_ids[] = (int) $category_id;
                }
            }
            if (!$this->isValidCategories($categories_ids)) {
                return false;
            }
            return $this->core->getQueries()->editThread($thread_id, $thread_title, $categories_ids, $status);
        }
        return null;
    }

    private function editPost() {
        // Ensure authentication before call
        $new_post_content = $_POST["thread_post_content"];
        if (!empty($new_post_content)) {
            if (strlen($new_post_content) > ForumUtils::FORUM_CHAR_POST_LIMIT) {
                $this->core->addErrorMessage("Posts cannot be over " . ForumUtils::FORUM_CHAR_POST_LIMIT . " characters long");
                return null;
            }

            $post_id = $_POST["edit_post_id"];
            $original_post = $this->core->getQueries()->getPost($post_id);
            if (!empty($original_post)) {
                $original_creator = $original_post['author_user_id'];
            }
            $anon = ($_POST["Anon"] == "Anon") ? 1 : 0;
            $current_user = $this->core->getUser()->getId();
            if (!$this->modifyAnonymous($original_creator)) {
                $anon = $original_post["anonymous"] ? 1 : 0;
            }
            return $this->core->getQueries()->editPost($original_creator, $current_user, $post_id, $new_post_content, $anon);
        }
        return null;
    }

    private function getSortedThreads($categories_ids, $max_thread, $show_deleted, $show_merged_thread, $thread_status, $unread_threads, &$blockNumber, $thread_id = -1) {
        $current_user = $this->core->getUser()->getId();
        if (!ForumUtils::isValidCategories($this->core->getQueries()->getCategories(), $categories_ids)) {
            // No filter for category
            $categories_ids = [];
        }

        $thread_block = $this->core->getQueries()->loadThreadBlock($categories_ids, $thread_status, $unread_threads, $show_deleted, $show_merged_thread, $current_user, $blockNumber, $thread_id);

        $ordered_threads = $thread_block['threads'];
        $blockNumber = $thread_block['block_number'];

        foreach ($ordered_threads as &$thread) {
            $list = [];
            foreach (explode("|", $thread['categories_ids']) as $id) {
                $list[] = (int) $id;
            }
            $thread['categories_ids'] = $list;
            $thread['categories_desc'] = explode("|", $thread['categories_desc']);
            $thread['categories_color'] = explode("|", $thread['categories_color']);
        }
        return $ordered_threads;
    }

    public function getThreads() {
        $pageNumber = !empty($_GET["page_number"]) && is_numeric($_GET["page_number"]) ? (int) $_GET["page_number"] : 1;
        $show_deleted = $this->showDeleted();
        $currentCourse = $this->core->getConfig()->getCourse();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $categories_ids = array_key_exists('thread_categories', $_POST) && !empty($_POST["thread_categories"]) ? explode("|", $_POST['thread_categories']) : [];
        $thread_status = array_key_exists('thread_status', $_POST) && ($_POST["thread_status"] === "0" || !empty($_POST["thread_status"])) ? explode("|", $_POST['thread_status']) : [];
        $unread_threads = ($_POST["unread_select"] === 'true');
        if (empty($categories_ids) && !empty($_COOKIE[$currentCourse . '_forum_categories'])) {
            $categories_ids = explode("|", $_COOKIE[$currentCourse . '_forum_categories']);
        }
        if (empty($thread_status) && !empty($_COOKIE['forum_thread_status'])) {
            $thread_status = explode("|", $_COOKIE['forum_thread_status']);
        }
        foreach ($categories_ids as &$id) {
            $id = (int) $id;
        }
        foreach ($thread_status as &$status) {
            $status = (int) $status;
        }
        $max_thread = 0;
        $threads = $this->getSortedThreads($categories_ids, $max_thread, $show_deleted, $show_merged_thread, $thread_status, $unread_threads, $pageNumber, -1);
        $currentCategoriesIds = (!empty($_POST['currentCategoriesId'])) ? explode("|", $_POST["currentCategoriesId"]) : [];
        $currentThreadId = array_key_exists('currentThreadId', $_POST) && !empty($_POST["currentThreadId"]) && is_numeric($_POST["currentThreadId"]) ? (int) $_POST["currentThreadId"] : -1;
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showAlteredDisplayList', $threads, true, $currentThreadId, $currentCategoriesIds);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return $this->core->getOutput()->renderJson([
                "html" => $this->core->getOutput()->getOutput(),
                "count" => count($threads),
                "page_number" => $pageNumber,
            ]);
    }

    public function showThreads() {
        $user = $this->core->getUser()->getId();
        $currentCourse = $this->core->getConfig()->getCourse();
        $category_id = in_array('thread_category', $_POST) ? $_POST['thread_category'] : -1;
        $category_id = [$category_id];
        $thread_status = [];
        $new_posts = [];
        $unread_threads = false;
        if (!empty($_COOKIE[$currentCourse . '_forum_categories']) && $category_id[0] == -1) {
            $category_id = explode('|', $_COOKIE[$currentCourse . '_forum_categories']);
        }
        if (!empty($_COOKIE['forum_thread_status'])) {
            $thread_status = explode("|", $_COOKIE['forum_thread_status']);
        }
        if (!empty($_COOKIE['unread_select_value'])) {
            $unread_threads = ($_COOKIE['unread_select_value'] === 'true');
        }
        foreach ($category_id as &$id) {
            $id = (int) $id;
        }
        foreach ($thread_status as &$status) {
            $status = (int) $status;
        }

        $max_thread = 0;
        $show_deleted = $this->showDeleted();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $current_user = $this->core->getUser()->getId();

        $posts = null;
        $option = 'tree';
        if (!empty($_REQUEST['option'])) {
            $option = $_REQUEST['option'];
        }
        elseif (!empty($_COOKIE['forum_display_option'])) {
            $option = $_COOKIE['forum_display_option'];
        }
        $option = ($this->core->getUser()->accessGrading() || $option != 'alpha') ? $option : 'tree';
        if (!empty($_REQUEST["thread_id"])) {
            $thread_id = (int) $_REQUEST["thread_id"];
            $this->core->getQueries()->markNotificationAsSeen($user, -2, (string) $thread_id);
            $unread_p = $this->core->getQueries()->getUnviewedPosts($thread_id, $current_user);
            foreach ($unread_p as $up) {
                $new_posts[] = $up["id"];
            }
            $thread = $this->core->getQueries()->getThread($thread_id);
            if (!empty($thread)) {
                $thread = $thread[0];
                if ($thread['merged_thread_id'] != -1) {
                    // Redirect merged thread to parent
                    $this->core->addSuccessMessage("Requested thread is merged into current thread.");
                    $this->core->redirect($this->core->buildUrl(['component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread['merged_thread_id']]));
                    return;
                }
                if ($option == "alpha") {
                    $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, $show_deleted, 'alpha');
                }
                else {
                    $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, $show_deleted, 'tree');
                }
                if (empty($posts)) {
                    $this->core->addErrorMessage("No posts found for selected thread.");
                }
            }
        }
        if (empty($_REQUEST["thread_id"]) || empty($posts)) {
            $new_posts = $this->core->getQueries()->getUnviewedPosts(-1, $current_user);
            $posts = $this->core->getQueries()->getPostsForThread($current_user, -1, $show_deleted);
        }
        $thread_id = -1;
        if (!empty($posts)) {
            $thread_id = $posts[0]["thread_id"];
        }
        $pageNumber = 0;
        $threads = $this->getSortedThreads($category_id, $max_thread, $show_deleted, $show_merged_thread, $thread_status, $unread_threads, $pageNumber, $thread_id);

        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $posts, $new_posts, $threads, $show_deleted, $show_merged_thread, $option, $max_thread, $pageNumber);
    }

    private function getAllowedCategoryColor() {
        $colors = [];
        $colors["MAROON"]   = "#800000";
        $colors["OLIVE"]    = "#808000";
        $colors["GREEN"]    = "#008000";
        $colors["TEAL"]     = "#008080";
        $colors["NAVY"]     = "#000080";
        $colors["PURPLE"]   = "#800080";
        $colors["GRAY"]     = "#808080";
        $colors["BLACK"]    = "#000000";
        return $colors;
    }

    public function showCreateThread() {
         $this->core->getOutput()->renderOutput('forum\ForumThread', 'createThread', $this->getAllowedCategoryColor());
    }

    public function getHistory() {
        $post_id = $_POST["post_id"];
        $output = [];
        if ($this->core->getUser()->accessGrading()) {
            $_post = [];
            $older_posts = $this->core->getQueries()->getPostHistory($post_id);
            $current_post = $this->core->getQueries()->getPost($post_id);
            $oc = $current_post["author_user_id"];
            $anon = $current_post["anonymous"];
            foreach ($older_posts as $post) {
                $_post['user'] = !$this->modifyAnonymous($oc) && $oc == $post["edit_author"] && $anon ? '' : $post["edit_author"];
                $_post['content'] = $this->core->getOutput()->renderTemplate('forum\ForumThread', 'filter_post_content', $post["content"]);
                $_post['post_time'] = DateUtils::parseDateTime($post['edit_timestamp'], $this->core->getConfig()->getTimezone())->format("n/j g:i A");
                $output[] = $_post;
            }
            if (count($output) == 0) {
                // Current post
                $_post['user'] = !$this->modifyAnonymous($oc) && $anon ? '' : $oc;
                $_post['content'] = $this->core->getOutput()->renderTemplate('forum\ForumThread', 'filter_post_content', $current_post["content"]);
                $_post['post_time'] = DateUtils::parseDateTime($current_post['timestamp'], $this->core->getConfig()->getTimezone())->format("n/j g:i A");
                $output[] = $_post;
            }
            // Fetch additional information
            foreach ($output as &$_post) {
                $emptyUser = empty($_post['user']);
                $_post['user_info'] = $emptyUser ? ['first_name' => 'Anonymous', 'last_name' => '', 'email' => ''] : $this->core->getQueries()->getDisplayUserInfoFromUserId($_post['user']);
                $_post['is_staff_post'] = $emptyUser ? false : $this->core->getQueries()->isStaffPost($_post['user']);
            }
        }
        else {
            $output['error'] = "You do not have permissions to do that.";
        }
        $this->core->getOutput()->renderJson($output);
        return $this->core->getOutput()->getOutput();
    }

    public function modifyAnonymous($author) {
        return $this->core->getUser()->accessFullGrading() || $this->core->getUser()->getId() === $author;
    }

    public function showStats() {
        $posts = $this->core->getQueries()->getPosts();
        $num_posts = count($posts);
        $users = [];
        for ($i = 0; $i < $num_posts; $i++) {
            $user = $posts[$i]["author_user_id"];
            $content = $posts[$i]["content"];
            if (!isset($users[$user])) {
                $users[$user] = [];
                $u = $this->core->getQueries()->getSubmittyUser($user);
                $users[$user]["first_name"] = htmlspecialchars($u -> getDisplayedFirstName());
                $users[$user]["last_name"] = htmlspecialchars($u -> getDisplayedLastName());
                $users[$user]["posts"] = [];
                $users[$user]["id"] = [];
                $users[$user]["timestamps"] = [];
                $users[$user]["total_threads"] = 0;
                $users[$user]["num_deleted_posts"] = count($this->core->getQueries()->getDeletedPostsByUser($user));
            }
            if ($posts[$i]["parent_id"] == -1) {
                $users[$user]["total_threads"]++;
            }
            $users[$user]["posts"][] = $content;
            $users[$user]["id"][] = $posts[$i]["id"];
            $users[$user]["timestamps"][] = DateUtils::parseDateTime($posts[$i]["timestamp"], $this->core->getConfig()->getTimezone())->format("n/j g:i A");
            $users[$user]["thread_id"][] = $posts[$i]["thread_id"];
            $users[$user]["thread_title"][] = $this->core->getQueries()->getThreadTitle($posts[$i]["thread_id"]);
        }
        ksort($users);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'statPage', $users);
    }

    public function mergeThread() {
        $parent_thread_id = $_POST["merge_thread_parent"];
        $child_thread_id = $_POST["merge_thread_child"];
        preg_match('/\((.*?)\)/', $parent_thread_id, $result);
        $parent_thread_id = $result[1];
        $thread_id = $child_thread_id;
        if ($this->core->getUser()->accessGrading()) {
            if (is_numeric($parent_thread_id) && is_numeric($child_thread_id)) {
                $message = "";
                $child_root_post = -1;
                if ($this->core->getQueries()->mergeThread($parent_thread_id, $child_thread_id, $message, $child_root_post)) {
                    $child_thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $child_thread_id);
                    if (is_dir($child_thread_dir)) {
                        $parent_thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $parent_thread_id);
                        if (!is_dir($parent_thread_dir)) {
                            FileUtils::createDir($parent_thread_dir);
                        }
                        $child_posts_dirs = FileUtils::getAllDirs($child_thread_dir);
                        foreach ($child_posts_dirs as $post_id) {
                            $child_post_dir = FileUtils::joinPaths($child_thread_dir, $post_id);
                            $parent_post_dir = FileUtils::joinPaths($parent_thread_dir, $post_id);
                            rename($child_post_dir, $parent_post_dir);
                        }
                    }
                    // Notify thread author
                    $child_thread = $this->core->getQueries()->getThread($child_thread_id)[0];
                    $child_thread_author = $child_thread['created_by'];
                    $child_thread_title = $child_thread['title'];
                    $parent_thread_title = $this->core->getQueries()->getThreadTitle($parent_thread_id)['title'];
                    $notification = new Notification($this->core, ['component' => 'forum', 'type' => 'merge_thread', 'child_thread_id' => $child_thread_id, 'parent_thread_id' => $parent_thread_id, 'child_thread_title' => $child_thread_title, 'parent_thread_title' => $parent_thread_title, 'child_thread_author' => $child_thread_author, 'child_root_post' => $child_root_post]);
                    $this->core->getQueries()->pushNotification($notification);
                    $this->core->addSuccessMessage("Threads merged!");
                    $thread_id = $parent_thread_id;
                }
                else {
                    $this->core->addErrorMessage("Merging Failed! " . $message);
                }
            }
        }
        else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
        $this->core->redirect($this->core->buildUrl(['component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id]));
    }


    //Modified functions below...

    public function publishThread() {

        //Get post data
        $title = trim($_POST['title']);
        $thread_post_content = $_POST['thread_post_content'];
        $anon = !empty($_POST['Anon']) ? true : false;
        $thread_status = $_POST['thread_status'];


        //Default to false
        $announcement = !empty($_POST['Announcement']) && $this->core->getUser()->accessGrading() && $_POST['Announcement'] == 'true' ? true : false;
        $email_announcement = !empty($_POST['EmailAnnouncement']) && $this->core->getUser()->accessFullGrading() && $_POST['EmailAnnouncement'] == 'true' ? true : false;

        $categories_ids  = [];
        foreach ($_POST["cat"] as $category_id) {
            $categories_ids[] = (int) $category_id;
        }

        $result = $this->core->getForum()->publish([
            'title'              => $title,
            'content'            => $thread_post_content,
            'anon'               => $anon,
            'status'             => $thread_status,
            'announcement'       => $announcement,
            'email_announcement' => $email_announcement,
            'categories'         => $categories_ids,
            'parent_id'          => -1,
            'thread_id'          => -1
        ], true);

        if ($result) {
            //We published with success!
            $this->core->addSuccessMessage('Thread created successfully.');
            $this->core->redirect($this->core->buildUrl(['component' => 'forum', 'page' => 'view_thread']));
        }
        else {
            return $this->core->getOutput()->renderJson(['error' => 'The post data is malformed. Please try submitting your post again.']);
        }
    }

    public function publishPost() {
        $parent_id = $_POST['parent_id'];
        $post_content = $_POST['thread_post_content'];
        $thread_id = $_POST['thread_id'];
        $anon = !empty($_POST['Anon']) ? true : false;


        return $this->core->getForum()->publish([
            'content'   => $post_content,
            'anon'      => $anon,
            'thread_id' => $thread_id,
            'parent_id' => $parent_id
        ], false);
    }


    //Ajax endpoint
    public function pinThread($type) {
        $thread_id = $_POST["thread_id"];

        $result = $this->core->getForum()->pinThread($thread_id, $type);

        if ($result) {
            //Should review specs on submitty.org
            $response = ['success' => 'Thread pinned successfully.'];
        }
        else {
            $response = ['failure' => 'Thread id does not exist.'];
        }

        $this->core->getOutput()->renderJson($response);
        return $this->core->getOutput()->getOutput();
    }

    public function getEditPostContent() {
        $post_id = $_POST["post_id"];

        $result = $this->core->getForum()->getEditContent($post_id);

        $this->core->getOutput()->renderJson($result);
        return $this->core->getOutput()->getOutput();
    }

    private function getThreadContent($thread_id, &$output) {
        $result = $this->core->getQueries()->getThread($thread_id)[0];
        $output['title'] = $result["title"];
        $output['categories_ids'] = $this->core->getQueries()->getCategoriesIdForThread($thread_id);
        $output['thread_status'] = $result["status"];
    }
}
