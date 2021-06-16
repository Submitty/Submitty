<?php

namespace app\controllers\forum;

use app\libraries\Core;
use app\libraries\ForumUtils;
use app\models\Notification;
use app\controllers\AbstractController;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\libraries\DateUtils;
use app\libraries\routers\AccessControl;
use Symfony\Component\Routing\Annotation\Route;

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

    private function showDeleted() {
        return ($this->core->getUser()->accessGrading() && isset($_COOKIE['show_deleted']) && $_COOKIE['show_deleted'] == "1");
    }

    private function showMergedThreads($currentCourse) {
        return (isset($_COOKIE["{$currentCourse}_show_merged_thread"]) && $_COOKIE["{$currentCourse}_show_merged_thread"] == "1");
    }

    private function showUnreadThreads() {
        $unread_threads = false;
        if (!empty($_COOKIE['unread_select_value'])) {
            $unread_threads = ($_COOKIE['unread_select_value'] === 'true');
        }
        return $unread_threads;
    }

    private function getSavedCategoryIds($currentCourse, $category_ids) {
        if (empty($category_ids) && !empty($_COOKIE[$currentCourse . '_forum_categories'])) {
            $category_ids = explode('|', $_COOKIE[$currentCourse . '_forum_categories']);
        }
        foreach ($category_ids as &$id) {
            $id = (int) $id;
        }
        return $category_ids;
    }

    private function getSavedThreadStatus($thread_status) {
        if (empty($thread_status) && !empty($_COOKIE['forum_thread_status'])) {
            $thread_status = explode("|", $_COOKIE['forum_thread_status']);
        }
        foreach ($thread_status as &$status) {
            $status = (int) $status;
        }
        return $thread_status;
    }

    private function returnUserContentToPage($error, $isThread, $thread_id) {
        //Notify User
        $this->core->addErrorMessage($error);

        if ($isThread) {
            $url = $this->core->buildCourseUrl(['forum', 'threads', 'new']);
        }
        else {
            $url = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]);
        }
        return [-1, $url];
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads/status", methods={"POST"})
     */
    public function changeThreadStatus($status, $thread_id = null) {
        if (is_null($thread_id)) {
            $thread_id = $_POST['thread_id'];
        }
        if ($this->core->getQueries()->getAuthorOfThread($thread_id) === $this->core->getUser()->getId() || $this->core->getUser()->accessGrading()) {
            if ($this->core->getQueries()->updateResolveState($thread_id, $status)) {
                return $this->core->getOutput()->renderJsonSuccess();
            }
            else {
                return $this->core->getOutput()->renderJsonFail('The thread resolve state could not be updated. Please try again.');
            }
        }
        else {
            return $this->core->getOutput()->renderJsonFail("You do not have permissions to do that.");
        }
    }

    private function checkGoodAttachment($isThread, $thread_id, $file_post) {
        if ((!isset($_FILES[$file_post])) || $_FILES[$file_post]['error'][0] === UPLOAD_ERR_NO_FILE) {
            return [0];
        }
        if (count($_FILES[$file_post]['tmp_name']) > 5) {
            return $this->returnUserContentToPage("Max file upload size is 5. Please try again.", $isThread, $thread_id);
        }
        $imageCheck = Utils::checkUploadedImageFile($file_post) ? 1 : 0;
        if ($imageCheck == 0 && !empty($_FILES[$file_post]['tmp_name'])) {
            return $this->returnUserContentToPage("Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)", $isThread, $thread_id);
        }
        return [$imageCheck];
    }

    private function isValidCategories($inputCategoriesIds = -1, $inputCategoriesName = -1) {
        $rows = $this->core->getQueries()->getCategories();
        if (is_array($inputCategoriesIds)) {
            if (count($inputCategoriesIds) < 1) {
                return false;
            }
            foreach ($inputCategoriesIds as $category_id) {
                $match_found = false;
                foreach ($rows as $index => $values) {
                    if ($values["category_id"] === $category_id) {
                        $match_found = true;
                        break;
                    }
                }
                if (!$match_found) {
                    return false;
                }
            }
        }
        if (is_array($inputCategoriesName)) {
            if (count($inputCategoriesName) < 1) {
                return false;
            }
            foreach ($inputCategoriesName as $category_name) {
                $match_found = false;
                foreach ($rows as $index => $values) {
                    if ($values["category_desc"] === $category_name) {
                        $match_found = true;
                        break;
                    }
                }
                if (!$match_found) {
                    return false;
                }
            }
        }
        return true;
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

    /**
     * @Route("/courses/{_semester}/{_course}/forum/categories/new", methods={"POST"})
     * @AccessControl(permission="forum.modify_category")
     */
    public function addNewCategory($category = []) {
        $result = [];
        if (!empty($_POST["newCategory"])) {
            $category = trim($_POST["newCategory"]);
            if ($this->isValidCategories(-1, [$category])) {
                return $this->core->getOutput()->renderJsonFail("That category already exists.");
            }
            else {
                if (strlen($category) > 50) {
                    return $this->core->getOutput()->renderJsonFail("Category name is more than 50 characters.");
                }
                else {
                    $newCategoryId = $this->core->getQueries()->addNewCategory($category);
                    $result["new_id"] = $newCategoryId["category_id"];
                }
            }
        }
        elseif (count($category) > 0) {
            $result["new_ids"] = [];
            foreach ($category as $categoryName) {
                if (!$this->isValidCategories(-1, [$categoryName])) {
                    $newCategoryId = $this->core->getQueries()->addNewCategory($categoryName);
                    $result["new_ids"][] = $newCategoryId;
                }
            }
        }
        else {
            return $this->core->getOutput()->renderJsonFail("No category data submitted. Please try again.");
        }
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/categories/delete", methods={"POST"})
     * @AccessControl(permission="forum.modify_category")
     */
    public function deleteCategory() {
        if (!empty($_POST["deleteCategory"])) {
            $category = (int) $_POST["deleteCategory"];
            if (!$this->isValidCategories([$category])) {
                return $this->core->getOutput()->renderJsonFail("That category doesn't exists.");
            }
            elseif (!$this->isCategoryDeletionGood($category)) {
                return $this->core->getOutput()->renderJsonFail("Last category can't be deleted.");
            }
            else {
                if ($this->core->getQueries()->deleteCategory($category)) {
                    return $this->core->getOutput()->renderJsonSuccess();
                }
                else {
                    return $this->core->getOutput()->renderJsonFail("Category is in use.");
                }
            }
        }
        else {
            return $this->core->getOutput()->renderJsonFail("No category data submitted. Please try again.");
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/categories/edit", methods={"POST"})
     * @AccessControl(permission="forum.modify_category")
     */
    public function editCategory() {
        $category_id = $_POST["category_id"];
        $category_desc = null;
        $category_color = null;

        if (!empty($_POST["category_desc"])) {
            $category_desc = trim($_POST["category_desc"]);
            if ($this->isValidCategories(-1, [$category_desc])) {
                return $this->core->getOutput()->renderJsonFail("That category already exists.");
            }
            elseif (strlen($category_desc) > 50) {
                return $this->core->getOutput()->renderJsonFail("Category name is more than 50 characters.");
            }
        }
        if (!empty($_POST["category_color"])) {
            $category_color = $_POST["category_color"];
            if (!in_array(strtoupper($category_color), $this->getAllowedCategoryColor())) {
                return $this->core->getOutput()->renderJsonFail("Given category color is not allowed.");
            }
        }

        $this->core->getQueries()->editCategory($category_id, $category_desc, $category_color);
        return $this->core->getOutput()->renderJsonSuccess();
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/categories/reorder", methods={"POST"})
     * @AccessControl(permission="forum.modify_category")
     */
    public function reorderCategories() {
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
            return $this->core->getOutput()->renderJsonSuccess();
        }
        else {
            return $this->core->getOutput()->renderJsonFail("Different Categories IDs given");
        }
    }

    //CODE WILL BE CONSOLIDATED IN FUTURE

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads/new", methods={"POST"})
     * @AccessControl(permission="forum.publish")
     */
    public function publishThread() {
        $markdown = !empty($_POST['markdown_status']);
        $current_user_id = $this->core->getUser()->getId();
        $result = [];
        $thread_title = trim($_POST["title"]);
        $thread_post_content = str_replace("\r", "", $_POST["thread_post_content"]);
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;

        if (strlen($thread_post_content) > ForumUtils::FORUM_CHAR_POST_LIMIT) {
            $result['next_page'] = $this->core->buildUrl(['forum', 'threads', 'new']);
            return $this->core->getOutput()->renderJsonFail("Posts cannot be over " . ForumUtils::FORUM_CHAR_POST_LIMIT . " characters long", $result);
        }

        if (!empty($_POST['lock_thread_date']) && $this->core->getUser()->accessAdmin()) {
            $lock_thread_date = DateUtils::parseDateTime($_POST['lock_thread_date'], $this->core->getUser()->getUsableTimeZone());
        }
        else {
            $lock_thread_date = null;
        }


        $thread_status = $_POST["thread_status"];

        $pinned = (isset($_POST["Announcement"]) && $_POST["Announcement"] == "Announcement" && $this->core->getUser()->accessFullGrading()) || (isset($_POST["pinThread"]) && $_POST["pinThread"] == "pinThread" && $this->core->getUser()->accessFullGrading()) ? 1 : 0;
        $announcement = (isset($_POST["Announcement"]) && $_POST["Announcement"] == "Announcement" && $this->core->getUser()->accessFullGrading()) ? 1 : 0;

        $categories_ids  = [];
        foreach ($_POST["cat"] as $category_id) {
            $categories_ids[] = (int) $category_id;
        }
        if (empty($thread_title) || empty($thread_post_content)) {
            $this->core->addErrorMessage("One of the fields was empty or bad. Please re-submit your thread.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', 'new']);
        }
        elseif (!$this->isValidCategories($categories_ids)) {
            $this->core->addErrorMessage("You must select valid categories. Please re-submit your thread.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', 'new']);
        }
        else {
            $hasGoodAttachment = $this->checkGoodAttachment(true, -1, 'file_input');
            if ($hasGoodAttachment[0] == -1) {
                $result['next_page'] = $hasGoodAttachment[1];
            }
            else {
                // Good Attachment
                $result = $this->core->getQueries()->createThread($markdown, $current_user_id, $thread_title, $thread_post_content, $anon, $pinned, $thread_status, $hasGoodAttachment[0], $categories_ids, $lock_thread_date);

                $thread_id = $result["thread_id"];
                $post_id = $result["post_id"];

                if ($hasGoodAttachment[0] == 1) {
                    $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);
                    FileUtils::createDir($thread_dir);

                    $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                    FileUtils::createDir($post_dir);

                    for ($i = 0; $i < count($_FILES["file_input"]["name"]); $i++) {
                        $target_file = $post_dir . "/" . basename($_FILES["file_input"]["name"][$i]);
                        move_uploaded_file($_FILES["file_input"]["tmp_name"][$i], $target_file);
                    }
                }
                $full_course_name = $this->core->getFullCourseName();
                $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]), 'thread_id' => $thread_id]);
                if ($announcement) {
                    // notify on a new announcement
                    $subject = "New Announcement: " . Notification::textShortner($thread_title);
                    $content = "An Instructor or Teaching Assistant made an announcement in:\n" . $full_course_name . "\n\n" . $thread_title . "\n\n" . $thread_post_content;
                    $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject];
                    $this->core->getNotificationFactory()->onNewAnnouncement($event);
                }
                else {
                    // notify on a new thread
                    $subject = "New Thread: " . Notification::textShortner($thread_title);
                    $content = "A new discussion thread was created in:\n" . $full_course_name . "\n\n" . $thread_title . "\n\n" . $thread_post_content;
                    $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject];
                    $this->core->getNotificationFactory()->onNewThread($event);
                }

                $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]);
            }
        }
        return $this->core->getOutput()->renderJsonSuccess($result);
    }
    
    /**
     * @Route("/courses/{_semester}/{_course}/forum/make_announcement", methods={"POST"})
     * @AccessControl(permission="forum.publish")
     */
    public function makeAnnouncement() {
        // add check here
        // check that it's the first post
        // check that it hasn't been an hour
        if (!isset($_POST['id'])) {
            return;
        }
        $thread_info = $this->core->getQueries()->findPost($_POST['id']);
        if ($thread_info["parent_id"] != -1) {
            return;
        }
        $dateTime = \DateTime::createFromFormat($this->core->getConfig()->getDateTimeFormat('forum'), $thread_info['timestamp']);
        $now = $this->core->getDateTimeNow();
        
        $full_course_name = $this->core->getFullCourseName();
        $thread_title = trim($_POST["title"]);
        $thread_post_content = str_replace("\r", "", $_POST["thread_post_content"]);
        $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]), 'thread_id' => $thread_id]);

        $subject = "New Announcement: " . Notification::textShortner($thread_title);
        $content = "An Instructor or Teaching Assistant made an announcement in:\n" . $full_course_name . "\n\n" . $thread_title . "\n\n" . $thread_post_content;
        $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject];
        $this->core->getNotificationFactory()->onNewAnnouncement($event);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/search", methods={"POST"})
     */
    public function search() {
        $results = $this->core->getQueries()->searchThreads($_POST['search_content']);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'searchResult', $results);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/posts/new", methods={"POST"})
     * @AccessControl(permission="forum.publish")
     */
    public function publishPost() {
        $current_user_id = $this->core->getUser()->getId();
        $result = [];
        $parent_id = (!empty($_POST["parent_id"])) ? htmlentities($_POST["parent_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8') : -1;
        $post_content_tag = 'thread_post_content';
        $file_post = 'file_input';
        $post_content = str_replace("\r", "", $_POST[$post_content_tag]);
        $thread_id = htmlentities($_POST["thread_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (strlen($post_content) > ForumUtils::FORUM_CHAR_POST_LIMIT) {
            $result['next_page'] = $this->core->buildUrl(['forum', 'threads']);
            return $this->core->getOutput()->renderJsonFail("Posts cannot be over " . ForumUtils::FORUM_CHAR_POST_LIMIT . " characters long", $result);
        }

        if (isset($_POST['thread_status'])) {
            $this->changeThreadStatus($_POST['thread_status'], $thread_id);
        }

        $markdown = !empty($_POST['markdown_status']);

        setcookie("markdown_enabled", ($markdown ? 1 : 0), time() + (86400 * 30), "/");

        $display_option = (!empty($_POST["display_option"])) ? htmlentities($_POST["display_option"], ENT_QUOTES | ENT_HTML5, 'UTF-8') : "tree";
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        if (empty($post_content) || empty($thread_id)) {
            $this->core->addErrorMessage("There was an error submitting your post. Please re-submit your post.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads']);
        }
        elseif (!$this->core->getQueries()->existsThread($thread_id)) {
            $this->core->addErrorMessage("There was an error submitting your post. Thread doesn't exist.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads']);
        }
        elseif (!$this->core->getQueries()->existsPost($thread_id, $parent_id)) {
            $this->core->addErrorMessage("There was an error submitting your post. Parent post doesn't exist in given thread.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads']);
        }
        elseif ($this->core->getQueries()->isThreadLocked($thread_id) && !$this->core->getUser()->accessAdmin()) {
            $this->core->addErrorMessage("Thread is locked.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]);
        }
        else {
            $hasGoodAttachment = $this->checkGoodAttachment(false, $thread_id, $file_post);
            if ($hasGoodAttachment[0] == -1) {
                $result['next_page'] = $hasGoodAttachment[1];
            }
            else {
                $post_id = $this->core->getQueries()->createPost($current_user_id, $post_content, $thread_id, $anon, 0, false, $hasGoodAttachment[0], $markdown, $parent_id);
                $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

                if (!is_dir($thread_dir)) {
                    FileUtils::createDir($thread_dir);
                }

                if ($hasGoodAttachment[0] == 1) {
                    $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                    FileUtils::createDir($post_dir);
                    for ($i = 0; $i < count($_FILES[$file_post]["name"]); $i++) {
                        $target_file = $post_dir . "/" . basename($_FILES[$file_post]["name"][$i]);
                        move_uploaded_file($_FILES[$file_post]["tmp_name"][$i], $target_file);
                    }
                }

                $full_course_name = $this->core->getFullCourseName();
                $thread_title = $this->core->getQueries()->getThread($thread_id)['title'];
                $parent_post = $this->core->getQueries()->getPost($parent_id);
                $parent_post_content = $parent_post['content'];

                $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]), 'thread_id' => $thread_id]);

                $subject = "New Reply: " . Notification::textShortner($thread_title);
                $content = "A new message was posted in:\n" . $full_course_name . "\n\nThread Title: " . $thread_title . "\nPost: " . Notification::textShortner($parent_post_content) . "\n\nNew Reply:\n\n" . $post_content;
                $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'post_id' => $post_id, 'thread_id' => $thread_id];
                $this->core->getNotificationFactory()->onNewPost($event);

                $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]) . '?' . http_build_query(['option' => $display_option]);
                $result['post_id'] = $post_id;
                $result['thread_id'] = $thread_id;
            }
        }
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/posts/single", methods={"POST"})
     */
    public function getSinglePost() {
        $post_id = $_POST['post_id'];
        $reply_level = $_POST['reply_level'];
        $post = $this->core->getQueries()->getPost($post_id);
        if (($_POST['edit']) && !empty($this->core->getQueries()->getPostHistory($post_id))) {
            $post['edit_timestamp'] = $this->core->getQueries()->getPostHistory($post_id)[0]['edit_timestamp'];
        }
        $thread_id = $post['thread_id'];
        $GLOBALS['totalAttachments'] = 0;
        $GLOBALS['post_box_id'] = $_POST['post_box_id'];
        $unviewed_posts = [$post_id];
        $first = $post['parent_id'] == -1;
        $result = $this->core->getOutput()->renderTemplate('forum\ForumThread', 'createPost', $thread_id, $post, $unviewed_posts, $first, $reply_level, 'tree', true, true);
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/announcements", methods={"POST"})
     * @AccessControl(permission="forum.modify_announcement")
     */
    public function alterAnnouncement(bool $type) {
        $thread_id = $_POST["thread_id"];
        $this->core->getQueries()->setAnnouncement($thread_id, $type);

        //TODO: notify on edited announcement
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads/bookmark", methods={"POST"})
     */
    public function bookmarkThread(bool $type) {
        $thread_id = $_POST["thread_id"];
        $current_user = $this->core->getUser()->getId();
        $this->core->getQueries()->addBookmarkedThread($current_user, $thread_id, $type);
        $response = ['user' => $current_user, 'thread' => $thread_id, 'type' => $type];
        return $this->core->getOutput()->renderJsonSuccess($response);
    }

    /**
     * Alter content/delete/undelete post of a thread
     *
     * If applied on the first post of a thread, same action will be reflected on the corresponding thread
     *
     * @param integer(0/1/2) $modifyType - 0 => delete, 1 => edit content, 2 => undelete
     *
     * @Route("/courses/{_semester}/{_course}/forum/posts/modify", methods={"POST"})
     */
    public function alterPost($modify_type) {
        $full_course_name = $this->core->getFullCourseName();
        $post_id = $_POST["post_id"] ?? $_POST["edit_post_id"];
        $post = $this->core->getQueries()->getPost($post_id);
        $current_user_id = $this->core->getUser()->getId();

        $markdown = !empty($_POST['markdown_status']);

        if (!$this->core->getAccess()->canI("forum.modify_post", ['post_author' => $post['author_user_id']])) {
                return $this->core->getOutput()->renderJsonFail('You do not have permissions to do that.');
        }
        if (!empty($_POST['edit_thread_id']) && $this->core->getQueries()->isThreadLocked($_POST['edit_thread_id']) && !$this->core->getUser()->accessAdmin()) {
            return $this->core->getOutput()->renderJsonFail('Thread is locked');
        }
        elseif (!empty($_POST['thread_id']) && $this->core->getQueries()->isThreadLocked($_POST['thread_id']) && !$this->core->getUser()->accessAdmin()) {
            return $this->core->getOutput()->renderJsonFail('Thread is locked');
        }
        elseif ($modify_type == 0) { //delete post or thread
            $thread_id = $_POST["thread_id"];
            $thread_title = $this->core->getQueries()->getThread($thread_id)['title'];
            if ($this->core->getQueries()->setDeletePostStatus($post_id, $thread_id, 1)) {
                $type = "thread";
            }
            else {
                $type = "post";
            }

            $post_author_id = $post['author_user_id'];
            $metadata = json_encode([]);
            $subject = "Deleted: " . Notification::textShortner($post["content"]);
            $content = "In " . $full_course_name . "\n\nThread: " . $thread_title . "\n\nPost:\n" . $post["content"] . " was deleted.";
            $event = [ 'component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $post_author_id, 'preference' => 'all_modifications_forum'];
            $this->core->getNotificationFactory()->onPostModified($event);

            $this->core->getQueries()->removeNotificationsPost($post_id);
            return $this->core->getOutput()->renderJsonSuccess(['type' => $type]);
        }
        elseif ($modify_type == 2) { //undelete post or thread
            $thread_id = $_POST["thread_id"];
            $result = $this->core->getQueries()->setDeletePostStatus($post_id, $thread_id, 0);
            if (is_null($result)) {
                $error = "Parent post must be undeleted first.";
                return $this->core->getOutput()->renderJsonFail($error);
            }
            else {
                // We want to reload same thread again, in both case (thread/post undelete)
                $thread_title = $this->core->getQueries()->getThread($thread_id)['title'];
                $post_author_id = $post['author_user_id'];
                $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]) . '#' . (string) $post_id, 'thread_id' => $thread_id, 'post_id' => $post_id]);
                $subject = "Undeleted: " . Notification::textShortner($post["content"]);
                $content = "In " . $full_course_name . "\n\nThe following post was undeleted.\n\nThread: " . $thread_title . "\n\n" . $post["content"];
                $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $post_author_id, 'preference' => 'all_modifications_forum'];
                $this->core->getNotificationFactory()->onPostModified($event);
                $type = "post";
                return $this->core->getOutput()->renderJsonSuccess(['type' => $type]);
            }
        }
        elseif ($modify_type == 1) { //edit post or thread
            $thread_id = $_POST["edit_thread_id"];
            $status_edit_thread = $this->editThread();
            $status_edit_post   = $this->editPost();

            $any_changes = false;
            $type = null;
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
                    $type = "Thread and Post";
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
                $thread_title = $this->core->getQueries()->getThread($thread_id)['title'];
                $post_author_id = $post['author_user_id'];
                $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]) . '#' . (string) $post_id, 'thread_id' => $thread_id, 'post_id' => $post_id]);
                if ($type == "Post") {
                    $post_content = $_POST["thread_post_content"];
                    $subject = "Post Edited: " . Notification::textShortner($post_content);
                    $content = "A message was edited in:\n" . $full_course_name . "\n\nThread Title: " . $thread_title . "\n\nEdited Post: \n\n" . $post_content;
                }
                elseif ($type == "Thread and Post") {
                    $post_content = $_POST["thread_post_content"];
                    $subject = "Thread Edited: " . Notification::textShortner($thread_title);
                    $content = "A thread was edited in:\n" . $full_course_name . "\n\nEdited Thread: " . $thread_title . "\n\nEdited Post: \n\n" . $post_content;
                }

                $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $post_author_id, 'preference' => 'all_modifications_forum'];
                $this->core->getNotificationFactory()->onPostModified($event);
            }
            if ($isError) {
                return $this->core->getOutput()->renderJsonFail($messageString);
            }
            return $this->core->getOutput()->renderJsonSuccess(['type' => $type]);
        }
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads/merge", methods={"POST"})
     * @AccessControl(permission="forum.merge_thread")
     */
    public function mergeThread() {
        $current_user_id = $this->core->getUser()->getId();
        $parent_thread_id = $_POST["merge_thread_parent"];
        $child_thread_id = $_POST["merge_thread_child"];
        $thread_id = $child_thread_id;
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

                $full_course_name = $this->core->getFullCourseName();
                $child_thread = $this->core->getQueries()->getThread($child_thread_id);
                $child_thread_author = $child_thread['created_by'];
                $child_thread_title = $child_thread['title'];
                $parent_thread_title = $this->core->getQueries()->getThreadTitle($parent_thread_id);
                $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $parent_thread_id]) . '#' . (string) $child_root_post, 'thread_id' => $parent_thread_id, 'post_id' => $child_root_post]);
                $subject = "Thread Merge: " . Notification::textShortner($child_thread_title);
                $content = "Two threads were merged in:\n" . $full_course_name . "\n\nAll messages posted in Merged Thread:\n" . $child_thread_title . "\n\nAre now contained within Parent Thread:\n" . $parent_thread_title;
                $event = [ 'component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $child_thread_author, 'preference' => 'merge_threads'];
                $this->core->getNotificationFactory()->onPostModified($event);
                $this->core->addSuccessMessage("Threads merged!");
                $thread_id = $parent_thread_id;
            }
            else {
                $this->core->addErrorMessage("Merging Failed! " . $message);
            }
        }
        $this->core->redirect($this->core->buildCourseUrl(['forum', 'threads', $thread_id]));
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/posts/split", methods={"POST"})
     * @AccessControl(permission="forum.merge_thread")
     */
    public function splitThread() {
        $title = $_POST["split_post_input"];
        $post_id = $_POST["split_post_id"];
        $thread_id = -1;
        $categories_ids = [];
        if (isset($_POST["cat"]) && is_array($_POST["cat"])) {
            foreach ($_POST["cat"] as $category_id) {
                $categories_ids[] = (int) $category_id;
            }
        }
        if (empty($title) || empty($categories_ids) || !$this->isValidCategories($categories_ids)) {
            $msg = "Failed to split thread; make sure that you have a title and that you have at least one category selected.";
            return $this->core->getOutput()->renderJsonFail($msg);
        }
        elseif (is_numeric($post_id) && $post_id > 0) {
            $thread_ids = $this->core->getQueries()->splitPost($post_id, $title, $categories_ids);
            if ($thread_ids[0] != -1) {
                $original_thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_ids[0]);
                if (is_dir($original_thread_dir)) {
                    $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_ids[1]);
                    if (!is_dir($thread_dir)) {
                        FileUtils::createDir($thread_dir);
                    }
                    $old_post_dirs = FileUtils::getAllDirs($original_thread_dir);
                    foreach ($old_post_dirs as $file_post_id) {
                        if (in_array($file_post_id, $thread_ids[2])) {
                            $old_post_dir = FileUtils::joinPaths($original_thread_dir, $file_post_id);
                            $new_post_dir = FileUtils::joinPaths($thread_dir, $file_post_id);
                            rename($old_post_dir, $new_post_dir);
                        }
                    }
                }
                $thread_id = $thread_ids[1];

                $full_course_name = $this->core->getFullCourseName();
                $thread = $this->core->getQueries()->getThread($thread_id);
                $thread_author = $thread['created_by'];
                $thread_title = $thread['title'];
                $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]), 'thread_id' => $thread_id, 'post_id' => $post_id]);
                $subject = "Post Split: " . Notification::textShortner($thread['title']);
                $content = "A post was split in:\n" . $full_course_name . "\n\nAll posts under the split post are now contained within the new thread:\n" . $thread_title;
                $event = [ 'component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $thread_author, 'preference' => 'merge_threads'];
                $this->core->getNotificationFactory()->onPostModified($event);
                $this->core->addSuccessMessage("Post split!");

                $result = [];
                $result['next'] = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]);
                $result['new_thread_id'] = $thread_id;
                $result['old_thread_id'] = $thread_ids[0];
                return $this->core->getOutput()->renderJsonSuccess($result);
            }
            else {
                return $this->core->getOutput()->renderJsonFail("Splitting Failed!");
            }
        }
    }

    private function editThread() {
        // Ensure authentication before call
        if (!empty($_POST["title"])) {
            $thread_id = $_POST["edit_thread_id"];
            if (!empty($_POST['lock_thread_date']) && $this->core->getUser()->accessAdmin()) {
                $lock_thread_date = $_POST['lock_thread_date'];
            }
            else {
                $lock_thread_date = null;
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
            return $this->core->getQueries()->editThread($thread_id, $thread_title, $categories_ids, $status, $lock_thread_date);
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
            $anon = (!empty($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
            $current_user = $this->core->getUser()->getId();
            if (!$this->modifyAnonymous($original_creator)) {
                $anon = $original_post["anonymous"] ? 1 : 0;
            }

            $markdown = !empty($_POST['markdown_status']);

            return $this->core->getQueries()->editPost($original_creator, $current_user, $post_id, $new_post_content, $anon, $markdown);
        }
        return null;
    }

    private function getSortedThreads($categories_ids, $max_thread, $show_deleted, $show_merged_thread, $thread_status, $unread_threads, &$blockNumber, $thread_id = -1) {
        $current_user = $this->core->getUser()->getId();
        if (!$this->isValidCategories($categories_ids)) {
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

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads", methods={"POST"})
     */
    public function getThreads($page_number = null) {
        $pageNumber = !empty($page_number) && is_numeric($page_number) ? (int) $page_number : 1;
        $show_deleted = $this->showDeleted();
        $currentCourse = $this->core->getConfig()->getCourse();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $categories_ids = array_key_exists('thread_categories', $_POST) && !empty($_POST["thread_categories"]) ? explode("|", $_POST['thread_categories']) : [];
        $thread_status = array_key_exists('thread_status', $_POST) && ($_POST["thread_status"] === "0" || !empty($_POST["thread_status"])) ? explode("|", $_POST['thread_status']) : [];
        $unread_threads = ($_POST["unread_select"] === 'true');

        $categories_ids = $this->getSavedCategoryIds($currentCourse, $categories_ids);
        $thread_status = $this->getSavedThreadStatus($thread_status);

        $max_thread = 0;
        $threads = $this->getSortedThreads($categories_ids, $max_thread, $show_deleted, $show_merged_thread, $thread_status, $unread_threads, $pageNumber, -1);
        $currentCategoriesIds = (!empty($_POST['currentCategoriesId'])) ? explode("|", $_POST["currentCategoriesId"]) : [];
        $currentThreadId = array_key_exists('currentThreadId', $_POST) && !empty($_POST["currentThreadId"]) && is_numeric($_POST["currentThreadId"]) ? (int) $_POST["currentThreadId"] : -1;
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showAlteredDisplayList', $threads, true, $currentThreadId, $currentCategoriesIds);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return $this->core->getOutput()->renderJsonSuccess([
                "html" => $this->core->getOutput()->getOutput(),
                "count" => count($threads),
                "page_number" => $pageNumber,
            ]);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads/single", methods={"POST"})
     */
    public function getSingleThread() {
        $thread_id = $_POST['thread_id'];
        $thread = $this->core->getQueries()->getThread($thread_id);
        $categories_ids = $this->core->getQueries()->getCategoriesIdForThread($thread_id);
        $show_deleted = $this->showDeleted();
        $currentCourse = $this->core->getConfig()->getCourse();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $pageNumber = 1;
        $threads = $this->getSortedThreads($categories_ids, 0, $show_deleted, $show_merged_thread, [$thread['status']], false, $pageNumber, $thread_id);
        $result = $this->core->getOutput()->renderTemplate('forum\ForumThread', 'showAlteredDisplayList', $threads, false, $thread_id, $categories_ids, true);
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum", methods={"GET"})
     */
    public function showFullThreads() {
        // preparing the params for threads
        $currentCourse = $this->core->getConfig()->getCourse();
        $show_deleted = $this->showDeleted();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $category_ids = $this->getSavedCategoryIds($currentCourse, []);
        $thread_status = $this->getSavedThreadStatus([]);
        $unread_threads = $this->showUnreadThreads();

        // Not used in the function
        $max_threads = 0;
        // use the default thread id
        $thread_id = -1;
        $pageNumber = 0;
        $this->core->getOutput()->addBreadcrumb("Discussion Forum");
        $threads = $this->getSortedThreads($category_ids, $max_threads, $show_deleted, $show_merged_thread, $thread_status, $unread_threads, $pageNumber, $thread_id);

        return $this->core->getOutput()->renderOutput('forum\ForumThread', 'showFullThreadsPage', $threads, $category_ids, $show_deleted, $show_merged_thread, $pageNumber);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads", methods={"GET"})
     * @Route("/courses/{_semester}/{_course}/forum/threads/{thread_id}", methods={"GET", "POST"}, requirements={"thread_id": "\d+"})
     */
    public function showThreads($thread_id = null, $option = 'tree') {

        $user = $this->core->getUser()->getId();
        $currentCourse = $this->core->getConfig()->getCourse();
        $category_id = in_array('thread_category', $_POST) ? [$_POST['thread_category']] : [];
        $category_ids = $this->getSavedCategoryIds($currentCourse, $category_id);
        $thread_status = $this->getSavedThreadStatus([]);
        $new_posts = [];
        $unread_threads = $this->showUnreadThreads();

        $max_thread = 0;
        $show_deleted = $this->showDeleted();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $current_user = $this->core->getUser()->getId();

        $thread_resolve_state = 0;

        $posts = null;
        $option = 'tree';
        if (!empty($_COOKIE['forum_display_option'])) {
            $option = $_COOKIE['forum_display_option'];
        }
        $option = ($this->core->getUser()->accessGrading() || $option != 'alpha') ? $option : 'tree';
        if (!empty($thread_id)) {
            $thread_id = (int) $thread_id;
            $thread_resolve_state = $this->core->getQueries()->getResolveState($thread_id)[0]['status'];
            $this->core->getQueries()->markNotificationAsSeen($user, -2, (string) $thread_id);
            $unread_p = $this->core->getQueries()->getUnviewedPosts($thread_id, $current_user);
            foreach ($unread_p as $up) {
                $new_posts[] = $up["id"];
            }
            $thread = $this->core->getQueries()->getThread($thread_id);
            if (!empty($thread)) {
                if ($thread['merged_thread_id'] != -1) {
                    // Redirect merged thread to parent
                    $this->core->addSuccessMessage("Requested thread is merged into current thread.");
                    if (!empty($_REQUEST["ajax"])) {
                        return $this->core->getOutput()->renderJsonSuccess(['merged' => true, 'destination' => $this->core->buildCourseUrl(['forum', 'threads', $thread['merged_thread_id']])]);
                    }
                    $this->core->redirect($this->core->buildCourseUrl(['forum', 'threads', $thread['merged_thread_id']]));
                    return;
                }
                if ($option == "alpha") {
                    $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, $show_deleted, 'alpha');
                }
                elseif ($option == "alpha_by_registration") {
                    $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, $show_deleted, 'alpha_by_registration');
                }
                elseif ($option == "alpha_by_rotating") {
                    $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, $show_deleted, 'alpha_by_rotating');
                }
                elseif ($option == "reverse-time") {
                    $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, $show_deleted, 'reverse-time');
                }
                else {
                    $posts = $this->core->getQueries()->getPostsForThread($current_user, $thread_id, $show_deleted, 'tree');
                }
                if (empty($posts)) {
                    $this->core->addErrorMessage("No posts found for selected thread.");
                }
            }
        }
        if (empty($thread_id) || empty($posts)) {
            $new_posts = $this->core->getQueries()->getUnviewedPosts(-1, $current_user);
            $posts = $this->core->getQueries()->getPostsForThread($current_user, -1, $show_deleted);
        }
        $thread_id = -1;
        if (!empty($posts)) {
            $thread_id = $posts[0]["thread_id"];
        }
        foreach ($posts as &$post) {
            do {
                $post['content'] = preg_replace('/(?:!\[(.*?)\]\((.*?)\))/', '$2', $post['content'], -1, $count);
            } while ($count > 0);
        }
        $pageNumber = 0;
        $threads = $this->getSortedThreads($category_ids, $max_thread, $show_deleted, $show_merged_thread, $thread_status, $unread_threads, $pageNumber, $thread_id);

        if (!empty($_REQUEST["ajax"])) {
            $this->core->getOutput()->renderTemplate('forum\ForumThread', 'showForumThreads', $user, $posts, $new_posts, $threads, $show_deleted, $show_merged_thread, $option, $max_thread, $pageNumber, $thread_resolve_state, ForumUtils::FORUM_CHAR_POST_LIMIT, true);
        }
        else {
            $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $posts, $new_posts, $threads, $show_deleted, $show_merged_thread, $option, $max_thread, $pageNumber, $thread_resolve_state, ForumUtils::FORUM_CHAR_POST_LIMIT, false);
        }
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

    /**
     * @Route("/courses/{_semester}/{_course}/forum/threads/new", methods={"GET"})
     */
    public function showCreateThread() {
        if (empty($this->core->getQueries()->getCategories())) {
            $this->core->redirect($this->core->buildCourseUrl(['forum', 'threads']));
            return;
        }
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'createThread', $this->getAllowedCategoryColor());
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/categories", methods={"GET"})
     * @AccessControl(permission="forum.view_modify_category")
     */
    public function showCategories() {
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showCategories', $this->getAllowedCategoryColor());
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/posts/splitinfo",methods={"POST"})
     * @AccessControl(permission="forum.merge_thread")
     */
    public function getSplitPostInfo() {
        $post_id = $_POST["post_id"];
        $result = $this->core->getQueries()->getPostOldThread($post_id);
        $result["all_categories_list"] = $this->core->getQueries()->getCategories();
        if ($result["merged_thread_id"] == -1) {
            $post = $this->core->getQueries()->getPost($post_id);
            $result["categories_list"] = $this->core->getQueries()->getCategoriesIdForThread($post["thread_id"]);
            $result["title"] = $this->core->getQueries()->getThreadTitle($post["thread_id"]);
        }
        else {
            $result["categories_list"] = $this->core->getQueries()->getCategoriesIdForThread($result["id"]);
        }
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/posts/history", methods={"POST"})
     * @AccessControl(role="LIMITED_ACCESS_GRADER")
     */
    public function getHistory() {
        $post_id = $_POST["post_id"];
        $output = [];
        $_post = [];
        $older_posts = $this->core->getQueries()->getPostHistory($post_id);
        $current_post = $this->core->getQueries()->getPost($post_id);
        $oc = $current_post["author_user_id"];
        $anon = $current_post["anonymous"];
        foreach ($older_posts as $post) {
            $_post['user'] = !$this->modifyAnonymous($oc) && $oc == $post["edit_author"] && $anon ? '' : $post["edit_author"];
            $_post['content'] = $return = $this->core->getOutput()->renderTwigTemplate("forum/RenderPost.twig", [
                "post_content" => $post["content"]
            ]);
            $_post['post_time'] = DateUtils::parseDateTime($post['edit_timestamp'], $this->core->getConfig()->getTimezone())->format("n/j g:i A");
            $output[] = $_post;
        }
        if (count($output) == 0) {
            // Current post
            $_post['user'] = !$this->modifyAnonymous($oc) && $anon ? '' : $oc;
            $_post['content'] = $return = $this->core->getOutput()->renderTwigTemplate("forum/RenderPost.twig", [
                "post_content" => $current_post["content"]
            ]);
            $_post['post_time'] = DateUtils::parseDateTime($current_post['timestamp'], $this->core->getConfig()->getTimezone())->format("n/j g:i A");
            $output[] = $_post;
        }
        // Fetch additional information
        foreach ($output as &$_post) {
            $emptyUser = empty($_post['user']);
            $_post['user_info'] = $emptyUser ? ['first_name' => 'Anonymous', 'last_name' => '', 'email' => ''] : $this->core->getQueries()->getDisplayUserInfoFromUserId($_post['user']);
            $_post['is_staff_post'] = $emptyUser ? false : $this->core->getQueries()->isStaffPost($_post['user']);
        }
        return $this->core->getOutput()->renderJsonSuccess($output);
    }

    public function modifyAnonymous($author) {
        return $this->core->getUser()->accessFullGrading() || $this->core->getUser()->getId() === $author;
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/posts/get", methods={"POST"})
     */
    public function getEditPostContent() {
        $post_id = $_POST["post_id"];
        if (!empty($post_id)) {
            $result = $this->core->getQueries()->getPost($post_id);
            if ($this->core->getAccess()->canI("forum.modify_post", ['post_author' => $result['author_user_id']])) {
                $output = [];
                $output['post'] = $result["content"];
                $output['post_time'] = $result['timestamp'];
                $output['anon'] = $result['anonymous'];
                $output['change_anon'] = $this->modifyAnonymous($result["author_user_id"]);
                $output['user'] = $output['anon'] ? 'Anonymous' : $result["author_user_id"];
                $output['markdown'] = $result['render_markdown'];
                if (isset($_POST["thread_id"])) {
                    $this->getThreadContent($_POST["thread_id"], $output);
                }
                return $this->core->getOutput()->renderJsonSuccess($output);
            }
            else {
                return $this->core->getOutput()->renderJsonFail("You do not have permissions to do that.");
            }
        }
        return $this->core->getOutput()->renderJsonFail("Empty edit post content.");
    }

    private function getThreadContent($thread_id, &$output) {
        $result = $this->core->getQueries()->getThread($thread_id);
        $output['lock_thread_date'] = $result['lock_thread_date'];
        $output['title'] = $result["title"];
        $output['categories_ids'] = $this->core->getQueries()->getCategoriesIdForThread($thread_id);
        $output['thread_status'] = $result["status"];
    }

    /**
     * @Route("/courses/{_semester}/{_course}/forum/stats")
     */
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
}
