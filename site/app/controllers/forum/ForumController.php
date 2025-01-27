<?php

namespace app\controllers\forum;

use app\entities\UserEntity;
use app\libraries\Core;
use app\libraries\ForumUtils;
use app\models\Notification;
use app\controllers\AbstractController;
use app\libraries\Utils;
use app\libraries\FileUtils;
use app\libraries\DateUtils;
use app\libraries\routers\AccessControl;
use app\libraries\routers\Enabled;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use app\libraries\socket\Client;
use app\entities\forum\Post;
use app\entities\forum\Thread;
use app\entities\forum\Category;
use WebSocket;

/**
 * Class ForumHomeController
 *
 * Controller to deal with the submitty home page. Once the user has been authenticated, but before they have
 * selected which course they want to access, they are forwarded to the home page.
 *
 * @Enabled("forum")
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
     * @param mixed[] $posts
     * @return mixed[]
     */
    public static function getPostsOrderAndReplies(array $posts, string $thread_id): array {
        $first = true;
        $first_post_id = 1;
        $order_array = [];
        $reply_level_array = [];
        foreach ($posts as $post_) {
            if (((int) $thread_id) === -1) {
                $thread_id = $post_["thread_id"];
            }
            if ($first) {
                $first = false;
                $first_post_id = $post_["id"];
            }
            if ($post_["parent_id"] > $first_post_id) {
                $place = array_search($post_["parent_id"], $order_array, true);
                $tmp_array = [$post_["id"]];
                $parent_reply_level = $reply_level_array[$place];
                if ($place !== false) {
                    while (array_key_exists($place + 1, $reply_level_array) && $reply_level_array[$place + 1] > $parent_reply_level) {
                        $place++;
                    }
                }
                array_splice($order_array, $place + 1, 0, $tmp_array);
                array_splice($reply_level_array, $place + 1, 0, $parent_reply_level + 1);
            }
            else {
                array_push($order_array, $post_["id"]);
                array_push($reply_level_array, 1);
            }
        }

        return [$order_array, $reply_level_array];
    }

    #[Route("/courses/{_semester}/{_course}/forum/threads/status", methods: ["POST"])]
    public function changeThreadStatus($status, $thread_id = null) {
        if (is_null($thread_id)) {
            $thread_id = $_POST['thread_id'];
        }
        if ($this->core->getQueries()->getAuthorOfThread($thread_id) === $this->core->getUser()->getId() || $this->core->getUser()->accessGrading()) {
            if ($this->core->getQueries()->updateResolveState($thread_id, $status)) {
                $this->sendSocketMessage(['type' => 'resolve_thread', 'thread_id' => $thread_id]);
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
     * @AccessControl(permission="forum.modify_category")
     */
    #[Route("/courses/{_semester}/{_course}/forum/categories/new", methods: ["POST"])]
    public function addNewCategory($category = []) {
        $result = [];
        if (!empty($_POST["newCategory"])) {
            $category = trim($_POST["newCategory"]);
            if ($this->core->getUser()->accessAdmin() && !empty($_POST["visibleDate"])) {
                $visibleDate = DateUtils::parseDateTime($_POST['visibleDate'], $this->core->getUser()->getUsableTimeZone());
            }
            else {
                $visibleDate = null;
            }
            if ($this->isValidCategories(-1, [$category])) {
                return $this->core->getOutput()->renderJsonFail("That category already exists.");
            }
            else {
                if (strlen($category) > 50) {
                    return $this->core->getOutput()->renderJsonFail("Category name is more than 50 characters.");
                }
                else {
                    $newCategoryId = $this->core->getQueries()->addNewCategory($category, $_POST["rank"], $visibleDate);
                    $result["new_id"] = $newCategoryId["category_id"];
                }
            }
        }
        elseif (count($category) > 0) {
            $result["new_ids"] = [];
            foreach ($category as $categoryName) {
                if (!$this->isValidCategories(-1, [$categoryName])) {
                    $newCategoryId = $this->core->getQueries()->addNewCategory($categoryName, $_POST["rank"]);
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
     * @AccessControl(permission="forum.modify_category")
     */
    #[Route("/courses/{_semester}/{_course}/forum/categories/delete", methods: ["POST"])]
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
     * @AccessControl(permission="forum.modify_category")
     */
    #[Route("/courses/{_semester}/{_course}/forum/categories/edit", methods: ["POST"])]
    public function editCategory() {
        $category_id = $_POST["category_id"];
        $category_desc = null;
        $category_color = null;
        $category_visible_date = null;

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
        if (!empty($_POST["visibleDate"]) && $this->core->getUser()->accessAdmin()) {
            if ($_POST["visibleDate"] === "    ") {
                $category_visible_date = "";
            }
            else {
                $category_visible_date = DateUtils::parseDateTime($_POST['visibleDate'], $this->core->getUser()->getUsableTimeZone());
                //ASSUME NO ISSUE
            }
        }
        else {
            $category_visible_date = null;
        }

        $this->core->getQueries()->editCategory($category_id, $category_desc, $category_color, $category_visible_date);
        return $this->core->getOutput()->renderJsonSuccess();
    }

    /**
     * @AccessControl(permission="forum.modify_category")
     */
    #[Route("/courses/{_semester}/{_course}/forum/categories/reorder", methods: ["POST"])]
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
     * @AccessControl(permission="forum.publish")
     */
    #[Route("/courses/{_semester}/{_course}/forum/threads/new", methods: ["POST"])]
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
        $expiration = (isset($_POST["expirationDate"]) && $this->core->getUser()->accessFullGrading()) ? $_POST["expirationDate"] : '1900-01-01 00:00:00';

        if (empty($expiration) && $pinned && $this->core->getUser()->accessAdmin()) {
            $expiration = $this->core->getDateTimeNow();
            $expiration = $expiration->add(new \DateInterval('P7D'));
        }
        elseif (!$pinned) {
            $expiration = '1900-01-01 00:00:00';
        }

        $categories_ids  = [];
        foreach ($_POST["cat"] as $category_id) {
            $categories_ids[] = (int) $category_id;
        }
        if (strlen($thread_title) === 0 || strlen($thread_post_content) === 0) {
            $this->core->addErrorMessage("One of the fields was empty or bad. Please re-submit your thread.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', 'new']);
        }
        elseif (!$this->isValidCategories($categories_ids)) {
            $this->core->addErrorMessage("You must select valid categories. Please re-submit your thread.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', 'new']);
        }
        else {
            $hasGoodAttachment = $this->checkGoodAttachment(true, -1, 'file_input');
            if ($hasGoodAttachment[0] === -1) {
                $result['next_page'] = $hasGoodAttachment[1];
            }
            else {
                // Good Attachment
                $attachment_name = [];
                if ($hasGoodAttachment[0] === 1) {
                    foreach ($_FILES['file_input']["name"] as $file_name) {
                        $attachment_name[] = basename($file_name);
                    }
                }
                $result = $this->core->getQueries()->createThread(
                    $markdown,
                    $current_user_id,
                    $thread_title,
                    $thread_post_content,
                    $anon,
                    $pinned,
                    $thread_status,
                    $hasGoodAttachment[0],
                    $attachment_name,
                    $categories_ids,
                    $lock_thread_date,
                    $expiration,
                    $announcement
                );
                $thread_id = $result["thread_id"];
                $post_id = $result["post_id"];

                if ($hasGoodAttachment[0] === 1) {
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
                 $this->sendSocketMessage(['type' => 'new_thread', 'thread_id' => $thread_id]);
            }
        }
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    /**
     * @AccessControl(permission="forum.modify_announcement")
     */
    #[Route("/courses/{_semester}/{_course}/forum/make_announcement", methods: ["POST"])]
    public function makeAnnouncement(): JsonResponse {
        if (!isset($_POST['id'])) {
            $this->core->addErrorMessage("thread_id not provided");
            return JsonResponse::getFailResponse("thread_id not provided");
        }
        // Check that the post is the first post of the thread
        $thread_info = $this->core->getQueries()->findParentPost($_POST['id']);
        if (count($thread_info) == 0) {
            $this->core->addErrorMessage("No post found");
            return JsonResponse::getFailResponse("No post found");
        }
        // Check that the post is indeed less than an hour old on the server
        $dateTime = new \DateTime($thread_info['timestamp']);
        $now = $this->core->getDateTimeNow();

        if ($dateTime->add(new \DateInterval("PT1H")) < $now) {
            $this->core->addErrorMessage("Post is too old");
            return JsonResponse::getFailResponse("Post is too old.");
        }

        $full_course_name = $this->core->getFullCourseName();
        $thread_post_content = str_replace("\r", "", $thread_info['content']);
        $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $_POST['id']]), 'thread_id' => $_POST['id']]);

        $thread_title = $this->core->getQueries()->findThread($_POST['id'])['title'];
        $subject = "New Announcement: " . Notification::textShortner($thread_title);
        $content = "An Instructor or Teaching Assistant made an announcement in:\n" . $full_course_name . "\n\n" . $thread_title . "\n\n" . $thread_post_content;
        $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject];
        $this->core->getNotificationFactory()->onNewAnnouncement($event);
        $this->core->addSuccessMessage("Announcement successfully queued for sending");
        $this->core->getQueries()->setAnnounced($_POST['id']);
        $this->core->getQueries()->updateResolveState($_POST['id'], 0);
        return JsonResponse::getSuccessResponse("Announcement successfully queued for sending");
    }

    #[Route("/courses/{_semester}/{_course}/forum/search", methods: ["POST"])]
    public function search() {
        $results = $this->core->getQueries()->searchThreads($_POST['search_content']);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'searchResult', $results);
    }

    /**
     * @AccessControl(permission="forum.publish")
     */
    #[Route("/courses/{_semester}/{_course}/forum/posts/new", methods: ["POST"])]
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

        $display_option = (!empty($_POST["display_option"])) ? htmlentities($_POST["display_option"], ENT_QUOTES | ENT_HTML5, 'UTF-8') : "tree";
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        if (!$this->core->getQueries()->existsThread($thread_id)) {
            $this->core->addErrorMessage("There was an error submitting your post. Thread doesn't exist.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads']);
        }
        elseif (!$this->core->getQueries()->existsPost($thread_id, $parent_id)) {
            $this->core->addErrorMessage("There was an error submitting your post. Parent post doesn't exist in given thread.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads']);
        }
        elseif ($this->core->getQueries()->isThreadLocked(intval($thread_id)) && !$this->core->getUser()->accessAdmin()) {
            $this->core->addErrorMessage("Thread is locked.");
            $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]);
        }
        else {
            $hasGoodAttachment = $this->checkGoodAttachment(false, $thread_id, $file_post);
            if ($hasGoodAttachment[0] === -1) {
                $result['next_page'] = $hasGoodAttachment[1];
            }
            else {
                $attachment_name = [];

                if ($hasGoodAttachment[0] !== 1 && (strlen($post_content) === 0 || strlen($thread_id) === 0)) {
                    $this->core->addErrorMessage("There was an error submitting your post. Please re-submit your post.");
                    $result['next_page'] = $this->core->buildCourseUrl(['forum', 'threads']);
                }
                elseif ($hasGoodAttachment[0] === 1) {
                    for ($i = 0; $i < count($_FILES[$file_post]["name"]); $i++) {
                        $attachment_name[] = basename($_FILES[$file_post]["name"][$i]);
                    }
                }

                $post_id = $this->core->getQueries()->createPost(
                    $current_user_id,
                    $post_content,
                    $thread_id,
                    $anon,
                    0,
                    false,
                    $hasGoodAttachment[0],
                    $markdown,
                    $attachment_name,
                    $parent_id
                );
                $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

                if (!is_dir($thread_dir)) {
                    FileUtils::createDir($thread_dir);
                }

                if ($hasGoodAttachment[0] === 1) {
                    $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                    FileUtils::createDir($post_dir);
                    for ($i = 0; $i < count($_FILES[$file_post]["name"]); $i++) {
                        $target_file = $post_dir . "/" . basename($_FILES[$file_post]["name"][$i]);
                        move_uploaded_file($_FILES[$file_post]["tmp_name"][$i], $target_file);
                    }
                }

                $full_course_name = $this->core->getFullCourseName();
                $thread_title = $this->core->getQueries()->getThread(intval($thread_id))['title'];
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

                $posts = $this->core->getQueries()->getPostsInThreads([$thread_id]);
                $order_array = [];
                $reply_level_array = [];
                if ($display_option !== 'tree') {
                    $reply_level = 1;
                }
                else {
                    $order_and_replies = self::getPostsOrderAndReplies($posts, $thread_id);
                    $order_array = $order_and_replies[0];
                    $reply_level_array = $order_and_replies[1];
                }

                $place = array_search($post_id, $order_array, true);
                $reply_level = $reply_level_array[$place];
                $max_post_box_id = count($posts);
                $this->sendSocketMessage([
                    'type' => 'new_post',
                    'thread_id' => $thread_id,
                    'post_id' => $post_id,
                    'reply_level' => $reply_level,
                    'post_box_id' => $max_post_box_id
                ]);
            }
        }
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    #[Route("/courses/{_semester}/{_course}/forum/posts/single", methods: ["POST"])]
    public function getSinglePost() {
        $post_id = $_POST['post_id'];
        $repo = $this->core->getCourseEntityManager()->getRepository(Post::class);
        $post = $repo->getPostDetail($post_id);
        if (is_null($post)) {
            return $this->core->getOutput()->renderJsonFail("Invalid post id : {$post_id}");
        }
        $post->setReplyLevel($_POST['reply_level']);
        $GLOBALS['totalAttachments'] = 0;

        $result = $this->core->getOutput()->renderTemplate(
            'forum\ForumThread',
            'createPost',
            $post->getThread()->getFirstPost(),
            $post->getThread(),
            $post,
            $post->getParent()->getId() === -1,
            'tree',
            true,
            $_POST['post_box_id'],
            true,
            $post->getThread()->isAnnounced()
        );
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    /**
     * @AccessControl(permission="forum.modify_announcement")
     */
    #[Route("/courses/{_semester}/{_course}/forum/announcements", methods: ["POST"])]
    public function alterAnnouncement(bool $type) {
        $thread_id = $_POST["thread_id"];
        $this->sendSocketMessage([
            'type' => $type ? 'announce_thread' : 'unpin_thread',
            'thread_id' => $thread_id,
        ]);
        $this->core->getQueries()->setAnnouncement($thread_id, $type);
        //TODO: notify on edited announcement
    }


    #[Route("/courses/{_semester}/{_course}/forum/threads/bookmark", methods: ["POST"])]
    public function bookmarkThread(bool $type) {
        $thread_id = $_POST["thread_id"];
        $current_user = $this->core->getUser()->getId();
        $this->core->getQueries()->addBookmarkedThread($current_user, $thread_id, $type);
        $response = ['user' => $current_user, 'thread' => $thread_id, 'type' => $type];
        return $this->core->getOutput()->renderJsonSuccess($response);
    }

    #[Route("/courses/{_semester}/{_course}/forum/threads/unread", methods: ["POST"])]
    public function markThreadUnread(): JsonResponse {
        $thread_id = $_POST["thread_id"];
        $current_user = $this->core->getUser()->getId();
        $this->core->getQueries()->unreadThread($current_user, $thread_id);
        $response = ['user' => $current_user, 'thread' => $thread_id];
        return JsonResponse::getSuccessResponse($response);
    }

    #[Route("/courses/{_semester}/{_course}/forum/posts/unread", methods: ["POST"])]
    public function markPostUnread(): JsonResponse {
        $thread_id = $_POST["thread_id"];
        $last_viewed_timestamp = $_POST["last_viewed_timestamp"];
        // format the last viewed timestamp to be in the same format as the database
        $last_viewed_timestamp = DateUtils::parseDateTime($last_viewed_timestamp, $this->core->getUser()->getUsableTimeZone())->format("Y-m-d H:i:sO");
        $current_user = $this->core->getUser()->getId();
        $this->core->getQueries()->visitThread($current_user, $thread_id, $last_viewed_timestamp);
        $response = ['user' => $current_user, 'last_viewed_timestamp' => $last_viewed_timestamp];
        return JsonResponse::getSuccessResponse($response);
    }

    /**
     * Edit a post or thread
     * @return mixed[]
     */
    #[Route("/courses/{_semester}/{_course}/forum/posts/modify", methods: ["POST"])]
    public function alterPost(): array {
        $post_id = filter_input(INPUT_POST, "edit_post_id", FILTER_VALIDATE_INT);
        $thread_id = filter_input(INPUT_POST, "edit_thread_id", FILTER_VALIDATE_INT);
        if ($thread_id === false) {
            return $this->core->getOutput()->renderJsonFail("Unable to parse thread id.");
        }
        if ($post_id === false) {
            return $this->core->getOutput()->renderJsonFail("Unable to parse post id.");
        }
        $order = $_COOKIE['forum_display_option'] ?? 'tree';
        $repo = $this->core->getCourseEntityManager()->getRepository(Thread::class);
        $thread = $repo->getThreadDetail($thread_id, $order);
        if (is_null($thread)) {
            return $this->core->getOutput()->renderJsonFail("Invalid thread id: {$thread_id}");
        }

        $post = $thread->getPosts()->filter(function ($x) use ($post_id) {
            return $x->getId() === $post_id;
        })->first();

        if ($post === false) {
            return $this->core->getOutput()->renderJsonFail("Unable to find post: {$post_id}");
        }
        if (!$this->core->getAccess()->canI("forum.modify_post", ['post_author' => $post->getAuthor()->getId()])) {
                return $this->core->getOutput()->renderJsonFail("You do not have permissions to do that.");
        }
        if ($post->getThread()->isLocked() && !$this->core->getUser()->accessAdmin()) {
            return $this->core->getOutput()->renderJsonFail("Thread is locked");
        }

        $status_edit_thread = true;
        $did_edit_thread = false;
        if ($thread->getFirstPost()->getId() === $post->getId()) {
            $status_edit_thread = $this->editThread($thread);
            $did_edit_thread = true;
        }
        $status_edit_post = $this->editPost($post);

        $message = [];
        if (!$status_edit_thread) {
            $message[] = "Thread update failed.";
            $this->core->getCourseEntityManager()->refresh($thread);
        }
        if (!$status_edit_post) {
            $this->core->getCourseEntityManager()->refresh($post);
            $message[] = "Post update failed.";
        }
        if (count($message) > 0) {
            return $this->core->getOutput()->renderJsonFail(join(" ", $message));
        }

        $type = "";
        $full_course_name = $this->core->getFullCourseName();
        $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]) . '#' . (string) $post_id, 'thread_id' => $thread_id, 'post_id' => $post_id]);
        if ($did_edit_thread) {
            $subject = "Thread Edited: " . Notification::textShortner($thread->getTitle());
            $content = "A thread was edited in:\n" . $full_course_name . "\n\nEdited Thread: " . $thread->getTitle() . "\n\nEdited Post: \n\n" . $post->getContent();
            $type = "edit_thread";
        }
        else {
            $subject = "Post Edited: " . Notification::textShortner($post->getContent());
            $content = "A message was edited in:\n" . $full_course_name . "\n\nThread Title: " . $thread->getTitle() . "\n\nEdited Post: \n\n" . $post->getContent();
            if ($order === 'tree') {
                ForumUtils::BuildReplyHeirarchy($thread->getFirstPost());
            }
            $type = "edit_post";
        }
        $this->sendSocketMessage([
            'type' => $type,
            'thread_id' => $thread_id,
            'post_id' => $post_id,
            'reply_level' => $post->getReplyLevel(),
            'post_box_id' => 1,
        ]);
        $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $post->getAuthor()->getId(), 'preference' => 'all_modifications_forum'];
        $this->core->getNotificationFactory()->onPostModified($event);
        $this->core->getCourseEntityManager()->flush();

        return $this->core->getOutput()->renderJsonSuccess(['type' => $type]);
    }

    /**
     * Toggle deletion of a post or thread
     * @return mixed[]
     */
    #[Route("courses/{_semester}/{_course}/forum/posts/delete", methods: ["POST"])]
    public function deleteOrRestorePost(): array {
        $full_course_name = $this->core->getFullCourseName();
        $post_id = filter_input(INPUT_POST, "post_id", FILTER_VALIDATE_INT);
        $thread_id = filter_input(INPUT_POST, "thread_id", FILTER_VALIDATE_INT);
        if (is_null($thread_id) || $thread_id === false) {
            return $this->core->getOutput()->renderJsonFail("Unable to parse thread id.");
        }
        if (is_null($post_id) || $post_id === false) {
            return $this->core->getOutput()->renderJsonFail("Unable to parse post id.");
        }
        $repo = $this->core->getCourseEntityManager()->getRepository(Thread::class);
        $thread = $repo->getThreadDetail($thread_id, "tree", true);
        if (is_null($thread)) {
            return $this->core->getOutput()->renderJsonFail("Invalid thread id: {$thread_id}");
        }

        $post = $thread->getPosts()->filter(function ($x) use ($post_id) {
            return $x->getId() === $post_id;
        })->first();

        if ($post === false) {
            return $this->core->getOutput()->renderJsonFail("Unable to find post: {$post_id}");
        }
        if (!$this->core->getAccess()->canI("forum.modify_post", ['post_author' => $post->getAuthor()->getId()])) {
            return $this->core->getOutput()->renderJsonFail("You do not have permissions to do that.");
        }
        if ($post->getThread()->isLocked() && !$this->core->getUser()->accessAdmin()) {
            return $this->core->getOutput()->renderJsonFail("Thread is locked");
        }
        $type = "post";
        if ($post->isDeleted()) {
            if ($thread->getFirstPost() !== $post && $post->getParent()->isDeleted()) {
                return $this->core->getOutput()->renderJsonFail("Parent post must be restored first.");
            }
            else {
                $post->setDeleted(false);
                if ($thread->getFirstPost()->getId() === $post->getId()) {
                    $thread->setDeleted(false);
                }
                // We want to reload same thread again, in both case (thread/post undelete)
                $metadata = json_encode(['url' => $this->core->buildCourseUrl(['forum', 'threads', $thread_id]) . '#' . (string) $post_id, 'thread_id' => $thread_id, 'post_id' => $post_id]);
                $subject = "Restored: " . Notification::textShortner($post->getContent());
                $content = "In " . $full_course_name . "\n\nThe following post was Restored.\n\nThread: " . $thread->getTitle() . "\n\n" . $post->getContent();
                $event = ['component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $post->getAuthor()->getId(), 'preference' => 'all_modifications_forum'];
            }
        }
        else {
            $this->recursiveDeletePost($post);
            if ($thread->getFirstPost()->getId() === $post->getId()) {
                $thread->setDeleted(true);
                $type = "thread";
            }
            $metadata = json_encode([]);
            $subject = "Deleted: " . Notification::textShortner($post->getContent());
            $content = "In " . $full_course_name . "\n\nThread: " . $thread->getTitle() . "\n\nPost:\n" . $post->getContent() . " was deleted.";
            $event = [ 'component' => 'forum', 'metadata' => $metadata, 'content' => $content, 'subject' => $subject, 'recipient' => $post->getAuthor()->getId(), 'preference' => 'all_modifications_forum'];
            $this->core->getQueries()->removeNotificationsPost($post_id);
            $this->sendSocketMessage(array_merge(
                ['type' => 'delete_post', 'thread_id' => $thread_id],
                $type === "post" ? ['post_id' => $post_id] : []
            ));
        }
        $this->core->getCourseEntityManager()->flush();
        $this->core->getNotificationFactory()->onPostModified($event);
        return $this->core->getOutput()->renderJsonSuccess(['type' => $type]);
    }

    private function recursiveDeletePost(Post $post): void {
        $post->setDeleted(true);
        foreach ($post->getChildren() as $child) {
            $this->recursiveDeletePost($child);
        }
    }

    /**
     * @AccessControl(permission="forum.merge_thread")
     */
    #[Route("/courses/{_semester}/{_course}/forum/threads/merge", methods: ["POST"])]
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
     * @AccessControl(permission="forum.merge_thread")
     */
    #[Route("/courses/{_semester}/{_course}/forum/posts/split", methods: ["POST"])]
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
                $this->sendSocketMessage(['type' => 'split_post', 'new_thread_id' =>  $result['new_thread_id'], 'thread_id' => $result['old_thread_id'], 'post_id' => $post_id]);
                return $this->core->getOutput()->renderJsonSuccess($result);
            }
            else {
                return $this->core->getOutput()->renderJsonFail("Splitting Failed!");
            }
        }
    }

    /**
     *
     * @param Thread $thread
     * @return bool true iff successful
     */
    private function editThread(Thread $thread): bool {
        // Ensure authentication before call
        $user = $this->core->getUser();
        $title = filter_input(INPUT_POST, "title", FILTER_UNSAFE_RAW);
        $status = filter_input(INPUT_POST, "thread_status", FILTER_VALIDATE_INT);
        $categories_ids  = [];
        if (isset($_POST['cat'])) {
            foreach ($_POST['cat'] as $category_id) {
                $categories_ids[] = (int) $category_id;
            }
        }
        if ($title === false || $status === false || !$this->isValidCategories($categories_ids)) {
            return false;
        }
        $thread->setTitle($title);
        $thread->setStatus($status);
        $categories = $this->core->getCourseEntityManager()->getRepository(Category::class)->findBy(['category_id' => $categories_ids], ['category_id' => 'ASC']);
        $thread->setCategories(new ArrayCollection($categories));

        if ($user->accessAdmin()) {
            $lock_thread_date_input = filter_input(INPUT_POST, "lock_thread_date", FILTER_UNSAFE_RAW);
            if ($lock_thread_date_input !== false) {
                $thread->setLockDate(DateUtils::parseDateTime($lock_thread_date_input, $user->getUsableTimeZone()));
            }
            else {
                $thread->setLockDate(null);
            }

            $expiration_input = filter_input(INPUT_POST, "expirationDate", FILTER_UNSAFE_RAW);
            if ($expiration_input !== false) {
                $thread->setPinnedExpiration(DateUtils::parseDateTime($expiration_input, $user->getUsableTimeZone()));
            }
            else {
                print($expiration_input);
                return false;
            }
        }
        return true;
    }

    /**
     * @param Post $post
     * @return bool true iff successful
     */
    private function editPost(Post $post): bool {
        $initial_post = null;
        if (count($post->getHistory()) === 0) {
            $initial_post = $post->saveNewVersion($post->getAuthor());
        }
        // Ensure authentication before call
        $content = filter_input(INPUT_POST, "thread_post_content", FILTER_UNSAFE_RAW);
        if ($content === false || $content === "") {
            return false;
        }
        if (strlen($content) > ForumUtils::FORUM_CHAR_POST_LIMIT) {
            $this->core->addErrorMessage("Posts cannot be over " . ForumUtils::FORUM_CHAR_POST_LIMIT . " characters long");
            return false;
        }
        $post->setContent($content);
        if ($this->modifyAnonymous($post->getAuthor()->getId())) {
            $post->setAnonymous(filter_input(INPUT_POST, "Anon", FILTER_UNSAFE_RAW) === "Anon");
        }
        $post->setRenderMarkdown(filter_input(INPUT_POST, "markdown_status", FILTER_VALIDATE_BOOL) !== false);

        // TODO: Handle attachments & edit table
        // As core gets legacy user model currently, which isn't a doctrine entity, we need to get the corresponding entity.
        $edit_author = $this->core->getCourseEntityManager()->find(UserEntity::class, $this->core->getUser()->getId());
        $post_edit = $post->saveNewVersion($edit_author);
        $new_attachments = $this->saveAttachments(false, $post->getThread()->getId(), $post->getId(), "file_input");
        $em = $this->core->getCourseEntityManager();
        if ($new_attachments !== false) {
            foreach ($new_attachments as $attachment_name) {
                $em->persist($post->addAttachment($attachment_name, $post_edit->getVersion()));
            }
        }
        foreach (json_decode($_POST['deleted_attachments']) as $attachment_name) {
            $post->deleteAttachment($attachment_name, $post_edit->getVersion());
        }
        if (!is_null($initial_post)) {
            $em->persist($initial_post);
        }
        $em->persist($post_edit);
        return true;
    }

    /**
     * Writes attachments to filesystem
     * @param bool $is_thread
     * @param int $thread_id
     * @param int $post_id
     * @param string $file_post
     * @return string[]|bool false if no good attachments. Otherwise array of attachment names.
     */
    private function saveAttachments(bool $is_thread, int $thread_id, int $post_id, string $file_post): array|bool {
        $hasGoodAttachment = $this->checkGoodAttachment($is_thread, $thread_id, $file_post);
        if ($hasGoodAttachment[0] !== 1) {
            return false;
        }
        $attachment_names = [];
        $thread_dir = FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments", $thread_id);
        $post_dir = FileUtils::joinPaths($thread_dir, $post_id);

        if (!is_dir($thread_dir)) {
            FileUtils::createDir($thread_dir);
        }
        if (!is_dir($post_dir)) {
            FileUtils::createDir($post_dir);
        }

        $existing_attachments = array_column(FileUtils::getAllFiles($post_dir), "name");
        //compile list of attachment names
        for ($i = 0; $i < count($_FILES[$file_post]['name']); $i++) {
            //check for files with same name
            $file_name = basename($_FILES[$file_post]['name'][$i]);
            if (in_array($file_name, $existing_attachments, true)) {
                // add unique prefix if file with this name already exists on this post
                $tmp = 1;
                while (in_array("(" . $tmp . ")" . $file_name, $existing_attachments, true)) {
                    $tmp++;
                }
                $file_name = "(" . $tmp . ")" . $file_name;
            }
            $attachment_names[] = $file_name;
        }

        for ($i = 0; $i < count($_FILES[$file_post]['name']); $i++) {
            $target_file = $post_dir . "/" . $attachment_names[$i];
            move_uploaded_file($_FILES[$file_post]['tmp_name'][$i], $target_file);
        }
        return $attachment_names;
    }
    #[Route("/courses/{_semester}/{_course}/forum/threads", methods: ["POST"])]
    public function getThreads($page_number = null) {
        $current_user = $this->core->getUser()->getId();
        $block_number = !empty($page_number) && is_numeric($page_number) ? (int) $page_number : 0;
        $show_deleted = $this->showDeleted();
        $currentCourse = $this->core->getConfig()->getCourse();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $categories_ids = array_key_exists('thread_categories', $_POST) && !empty($_POST["thread_categories"]) ? explode("|", $_POST['thread_categories']) : [];
        $thread_status = array_key_exists('thread_status', $_POST) && ($_POST["thread_status"] === "0" || !empty($_POST["thread_status"])) ? explode("|", $_POST['thread_status']) : [];
        $unread_threads = ($_POST["unread_select"] === 'true');
        $scroll_down = filter_var($_POST['scroll_down'] ?? "true", FILTER_VALIDATE_BOOLEAN);

        $categories_ids = $this->getSavedCategoryIds($currentCourse, $categories_ids);
        $thread_status = $this->getSavedThreadStatus($thread_status);

        $repo = $this->core->getCourseEntityManager()->getRepository(Thread::class);
        $threads = $repo->getAllThreads($categories_ids, $thread_status, $show_deleted, $show_merged_thread, $unread_threads, $current_user, $block_number, $scroll_down);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showAlteredDisplayList', $threads);
        $this->core->getOutput()->useHeader(false);
        $this->core->getOutput()->useFooter(false);
        return $this->core->getOutput()->renderJsonSuccess([
                "html" => $this->core->getOutput()->getOutput(),
                "count" => count($threads),
                "page_number" => $block_number,
            ]);
    }

    #[Route("/courses/{_semester}/{_course}/forum/threads/single", methods: ["POST"])]
    public function getSingleThread() {
        $thread_id = $_POST['thread_id'];
        // Checks if thread id is empty. If so, render "fail" json response case informing that thread id is empty.
        if (empty($thread_id)) {
            return $this->core->getOutput()->renderJsonFail("Invalid thread id (EMPTY ID)");
        }
        // Checks if thread id is not an integer value. If so, render "fail" json response case informing that thread id is not an integer value.
        if (!(is_int($thread_id) || ctype_digit($_POST['thread_id']))) {
            return $this->core->getOutput()->renderJsonFail("Invalid thread id (NON-INTEGER ID)");
        }
        $repo = $this->core->getCourseEntityManager()->getRepository(Thread::class);
        $thread = $repo->getThreadDetail($thread_id);
        // Checks if no threads were found. If so, render "fail" json response case informing that the no threads were found with the given ID.
        if (is_null($thread)) {
            return $this->core->getOutput()->renderJsonFail("Invalid thread id (NON-EXISTENT ID)");
        }
        $result = $this->core->getOutput()->renderTemplate('forum\ForumThread', 'showAlteredDisplayList', [$thread]);
        return $this->core->getOutput()->renderJsonSuccess($result);
    }

    #[Route("/courses/{_semester}/{_course}/forum", methods: ["GET"])]
    public function showFullThreads() {
        // preparing the params for threads
        $current_user = $this->core->getUser()->getId();
        $currentCourse = $this->core->getConfig()->getCourse();
        $show_deleted = $this->showDeleted();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $category_ids = $this->getSavedCategoryIds($currentCourse, []);
        $thread_status = $this->getSavedThreadStatus([]);
        $unread_threads = $this->showUnreadThreads();
        $this->core->getOutput()->addBreadcrumb("Discussion Forum");

        $repo = $this->core->getCourseEntityManager()->getRepository(Thread::class);
        $block_number = 0;
        $threads = $repo->getAllThreads($category_ids, $thread_status, $show_deleted, $show_merged_thread, $unread_threads, $current_user, $block_number);
        return $this->core->getOutput()->renderOutput('forum\ForumThread', 'showFullThreadsPage', $threads, $show_deleted, $show_merged_thread, $block_number);
    }

    #[Route("/courses/{_semester}/{_course}/forum/threads/{thread_id}", methods: ["GET","POST"], requirements: ["thread_id" => "\d+"])]
    public function showThreads($thread_id = null, $option = 'tree') {
        $thread_id = (int) $thread_id;
        $user = $this->core->getUser()->getId();
        $currentCourse = $this->core->getConfig()->getCourse();
        $category_id = in_array('thread_category', $_POST) ? [$_POST['thread_category']] : [];
        $category_ids = $this->getSavedCategoryIds($currentCourse, $category_id);
        $thread_status = $this->getSavedThreadStatus([]);
        $unread_threads = $this->showUnreadThreads();
        $show_deleted = $this->showDeleted();
        $show_merged_thread = $this->showMergedThreads($currentCourse);
        $option = 'tree';
        if (!empty($_COOKIE['forum_display_option'])) {
            $option = $_COOKIE['forum_display_option'];
        }
        $option = ($this->core->getUser()->accessGrading() || $option != 'alpha') ? $option : 'tree';

        $repo = $this->core->getCourseEntityManager()->getRepository(Thread::class);
        $thread = $repo->getThreadDetail($thread_id, $option, $show_deleted);
        if (is_null($thread)) {
            $this->core->addErrorMessage("Requested thread does not exist.");
            $this->core->redirect(($this->core->buildCourseUrl(['forum'])));
            return;
        }

        $this->core->getQueries()->markNotificationAsSeen($user, -2, (string) $thread_id);
        if ($thread->isMergedThread()) {
            // Redirect merged thread to parent
            $this->core->addSuccessMessage("The requested thread was merged into this thread.");
            if (!empty($_REQUEST["ajax"])) {
                return $this->core->getOutput()->renderJsonSuccess(['merged' => true, 'destination' => $this->core->buildCourseUrl(['forum', 'threads', $thread->getMergedThread()->getId()])]);
            }
            $this->core->redirect($this->core->buildCourseUrl(['forum', 'threads', $thread->getMergedThread()->getId()]));
            return;
        }

        $merge_thread_options = $repo->getMergeThreadOptions($thread);

        $block_number = 0;
        $threads = $repo->getAllThreads($category_ids, $thread_status, $show_deleted, $show_merged_thread, $unread_threads, $user, $block_number);
        if (!empty($_REQUEST["ajax"])) {
            $this->core->getOutput()->renderTemplate('forum\ForumThread', 'showForumThreads', $user, $thread, $threads, $merge_thread_options, $show_deleted, $show_merged_thread, $option, $block_number, true);
        }
        else {
            $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $thread, $threads, $merge_thread_options, $show_deleted, $show_merged_thread, $option, $block_number, false);
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

    #[Route("/courses/{_semester}/{_course}/forum/threads/new", methods: ["GET"])]
    public function showCreateThread() {
        if (empty($this->core->getQueries()->getCategories())) {
            $this->core->redirect($this->core->buildCourseUrl(['forum', 'threads']));
            return;
        }
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'createThread', $this->getAllowedCategoryColor());
    }

    /**
     * @AccessControl(permission="forum.view_modify_category")
     */
    #[Route("/courses/{_semester}/{_course}/forum/categories", methods: ["GET"])]
    public function showCategories() {
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showCategories', $this->getAllowedCategoryColor());
    }

    /**
     * @AccessControl(permission="forum.merge_thread")
     */
    #[Route("/courses/{_semester}/{_course}/forum/posts/splitinfo", methods: ["POST"])]
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
     * @AccessControl(role="LIMITED_ACCESS_GRADER")
     */
    #[Route("/courses/{_semester}/{_course}/forum/posts/history", methods: ["POST"])]
    public function getHistory() {
        $repo = $this->core->getCourseEntityManager()->getRepository(Post::class);
        $post_id = $_POST["post_id"];
        $output = [];
        $post = $repo->getPostWithHistory($post_id);

        $GLOBALS['totalAttachments'] = 0;
        $edit_id = 0;
        foreach ($post->getHistory() as $version) {
            $tmp = [];
            // If I'm not full-access nor post author, AND the post author was the editor, AND the post is anonymous, then preserve anonymity
            $tmp['user'] = (!$this->modifyAnonymous($post->getAuthor()->getId())
                            && $post->getAuthor()->getId() == $version->getEditAuthor()->getId()
                            && $post->isAnonymous()) ? '' : $version->getEditAuthor()->getId();
            $tmp['content'] = $this->core->getOutput()->renderTwigTemplate("forum/RenderPost.twig", [
                "post_content" => $version->getContent(),
                "render_markdown" => false,
                "post_attachment" => ForumUtils::getForumAttachments(
                    $post_id,
                    $post->getThread()->getId(),
                    $version->getAttachments()->map(function ($x) {
                        return $x->getFileName();
                    })->toArray(),
                    $this->core->getConfig()->getCoursePath(),
                    $this->core->buildCourseUrl(['display_file'])
                ),
                "edit_id" => $post_id . "-" . $edit_id,
            ]);
            $emptyAuthor = $tmp['user'] === '';
            $tmp['user_info'] = $emptyAuthor ? ['given_name' => 'Anonymous', 'family_name' => '', 'email' => '', 'pronouns' => '', 'display_pronouns' => false ] : $version->getEditAuthor()->getDisplayInfo();
            $tmp['is_staff_post'] = $version->getEditAuthor()->accessFullGrading();
            $tmp['post_time'] = DateUtils::parseDateTime($version->getEditTimestamp(), $this->core->getConfig()->getTimezone())->format($this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));
            $output[] = $tmp;
            $edit_id++;
        }
        if (count($output) == 0) {
            // No history, get current post
            $tmp = [];
            $tmp['user'] = (!$this->modifyAnonymous($post->getAuthor()->getId()) && $post->isAnonymous()) ? '' : $post->getAuthor()->getId();
            $tmp['content'] = $this->core->getOutput()->renderTwigTemplate("forum/RenderPost.twig", [
                "post_content" => $post->getContent(),
                "render_markdown" => false,
                "post_attachment" => ForumUtils::getForumAttachments(
                    $post_id,
                    $post->getThread()->getId(),
                    $post->getAttachments()->filter(function ($x) {
                        return $x->isCurrent();
                    })->map(function ($x) {
                        return $x->getFileName();
                    })->toArray(),
                    $this->core->getConfig()->getCoursePath(),
                    $this->core->buildCourseUrl(['display_file'])
                ),
                "edit_id" => $post_id . "-" . $edit_id,
            ]);
            $emptyAuthor = $tmp['user'] === '';
            $tmp['user_info'] = $emptyAuthor ? ['given_name' => 'Anonymous', 'family_name' => '', 'email' => '', 'pronouns' => '', 'display_pronouns' => false ] : $post->getAuthor()->getDisplayInfo();
            $tmp['is_staff_post'] = !$emptyAuthor && $post->getAuthor()->accessFullGrading();
            $tmp['post_time'] = DateUtils::parseDateTime($post->getTimestamp(), $this->core->getConfig()->getTimezone())->format($this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));
            $output[] = $tmp;
        }
        return $this->core->getOutput()->renderJsonSuccess($output);
    }

    public function modifyAnonymous($author) {
        return $this->core->getUser()->accessFullGrading() || $this->core->getUser()->getId() === $author;
    }

    #[Route("/courses/{_semester}/{_course}/forum/posts/get", methods: ["POST"])]
    public function getEditPostContent() {
        $post_id = $_POST["post_id"];
        if (!empty($post_id)) {
            $result = $this->core->getQueries()->getPost($post_id);
            $post_attachments = $this->core->getQueries()->getForumAttachments([$post_id]);
            $GLOBALS['totalAttachments'] = 0;
            $img_table = $this->core->getOutput()->renderTwigTemplate('forum/EditImgTable.twig', [
                "post_attachments" => ForumUtils::getForumAttachments(
                    $post_id,
                    $result['thread_id'],
                    $post_attachments[$post_id][0],
                    $this->core->getConfig()->getCoursePath(),
                    $this->core->buildCourseUrl(['display_file'])
                )
            ]);
            if ($this->core->getAccess()->canI("forum.modify_post", ['post_author' => $result['author_user_id']])) {
                $output = [];
                $output['post'] = $result["content"];
                $output['post_time'] = $result['timestamp'];
                $output['anon'] = $result['anonymous'];
                $output['change_anon'] = $this->modifyAnonymous($result["author_user_id"]);
                $output['user'] = $output['anon'] ? 'Anonymous' : $result["author_user_id"];
                $output['markdown'] = $result['render_markdown'];
                $output['img_table'] = $img_table;

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
        $output['expiration'] = $result["pinned_expiration"];
    }

    #[Route("/courses/{_semester}/{_course}/forum/stats")]
    public function showStats() {
        $posts = $this->core->getQueries()->getPosts();
        $num_posts = count($posts);
        $upducks = $this->core->getQueries()->getUpDucks();
        $num_users_with_upducks = count($upducks);
        $users = [];
        for ($i = 0; $i < $num_posts; $i++) {
            $user = $posts[$i]["author_user_id"];
            $content = $posts[$i]["content"];
            if (!isset($users[$user])) {
                $users[$user] = [];
                $u = $this->core->getQueries()->getSubmittyUser($user);
                $users[$user]["given_name"] = htmlspecialchars($u -> getDisplayedGivenName());
                $users[$user]["family_name"] = htmlspecialchars($u -> getDisplayedFamilyName());
                $users[$user]["posts"] = [];
                $users[$user]["id"] = [];
                $users[$user]["timestamps"] = [];
                $users[$user]["total_threads"] = 0;
                $users[$user]["num_deleted_posts"] = count($this->core->getQueries()->getDeletedPostsByUser($user));
                $users[$user]["total_upducks"] = 0;
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
        for ($i = 0; $i < $num_users_with_upducks; $i++) {
            $user = $upducks[$i]["author_user_id"];
            $users[$user]["total_upducks"] = $upducks[$i]["upducks"];
        }
        ksort($users);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'statPage', $users);
    }

    #[Route("/courses/{_semester}/{_course}/posts/likes", methods: ["POST"])]
    public function toggleLike(): JsonResponse {
        $requiredKeys = ['post_id', 'current_user'];
        foreach ($requiredKeys as $key) {
            if (!isset($_POST[$key])) {
                return JsonResponse::getErrorResponse('Missing required key in POST data: ' . $key);
            }
        }
        $output = $this->core->getQueries()->toggleLikes($_POST['post_id'], $this->core->getUser()->getId());

        if ($output['status'] === "false") {
            return JsonResponse::getErrorResponse('Catch Fail in Query');
        }

        $this->sendSocketMessage([
            'type' => 'edit_likes',
            'post_id' => $_POST['post_id'],
            'status' => $output['status'],
            'likesCount' => $output['likesCount'],
            'likesFromStaff' => $output['likesFromStaff']
        ]);

        return JsonResponse::getSuccessResponse([
            'status' => $output['status'], // 'like' or 'unlike'
            'likesCount' => $output['likesCount'], // Total likes count
            'likesFromStaff' => $output['likesFromStaff'] // Likes from staff
        ]);
    }

    /**
     * this function opens a WebSocket client and sends a message with the corresponding update
     * @param array<mixed> $msg_array
     */
    private function sendSocketMessage(array $msg_array): void {
        $msg_array['user_id'] = $this->core->getUser()->getId();
        $msg_array['page'] = $this->core->getConfig()->getTerm() . '-' . $this->core->getConfig()->getCourse() . "-discussion_forum";
        try {
            $client = new Client($this->core);
            $client->json_send($msg_array);
        }
        catch (WebSocket\ConnectionException $e) {
            $this->core->addNoticeMessage("WebSocket Server is down, page won't load dynamically.");
        }
    }
}
