<?php

namespace app\views\forum;

use app\entities\forum\Category;
use app\libraries\DateUtils;
use app\views\AbstractView;
use app\libraries\FileUtils;
use app\libraries\ForumUtils;
use app\models\User;
use app\entities\forum\Thread;
use app\entities\forum\Post;

class ForumThreadView extends AbstractView {
    private function getSavedForumCategories($current_course, $categories) {
        $category_ids_array = array_column($categories, 'category_id');
        $cookieSelectedCategories = [];
        if (!empty($_COOKIE[$current_course . '_forum_categories'])) {
            foreach (explode('|', $_COOKIE[$current_course . '_forum_categories']) as $selectedId) {
                if (in_array((int) $selectedId, $category_ids_array)) {
                    $cookieSelectedCategories[] = $selectedId;
                }
            }
        }
        return $cookieSelectedCategories;
    }

    private function getSavedThreadStatuses() {
        $cookieSelectedThreadStatus = [];
        if (!empty($_COOKIE['forum_thread_status'])) {
            foreach (explode('|', $_COOKIE['forum_thread_status']) as $selectedStatus) {
                if (in_array((int) $selectedStatus, [-1,0,1])) {
                    $cookieSelectedThreadStatus[] = $selectedStatus;
                }
            }
        }
        return $cookieSelectedThreadStatus;
    }

    private function getUnreadThreadStatus() {
        $cookieSelectedUnread = false;
        if (!empty($_COOKIE['unread_select_value'])) {
            $cookieSelectedUnread = $_COOKIE['unread_select_value'];
        }
        return $cookieSelectedUnread;
    }

    public function searchResult($threads) {

        $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildCourseUrl(['forum']), null, $use_as_heading = true);
        $this->core->getOutput()->addBreadcrumb("Search");

        $buttons = [
            [
                "required_rank" => 4,
                "display_text" => 'Create Thread',
                "style" => 'position:absolute;top:3px;right:0px',
                "link" => [true, $this->core->buildCourseUrl(['forum', 'threads', 'new'])],
                "optional_class" => '',
                "title" => 'Create Thread',
                "onclick" => [false]
            ],
            [
                "required_rank" => 4,
                "display_text" => 'Back to Threads',
                "style" => 'position:relative;float:right;top:3px;margin-right:102px;',
                "link" => [true, $this->core->buildCourseUrl(['forum'])],
                "optional_class" => '',
                "title" => 'Back to threads',
                "onclick" => [false]
            ]
        ];

        $threadArray = [];
        $fromIdtoTitle = [];
        foreach ($threads as $thread) {
            if (!array_key_exists($thread["thread_id"], $threadArray)) {
                $threadArray[$thread["thread_id"]] = [];
                $fromIdtoTitle[$thread["thread_id"]] = $thread["thread_title"];
            }
            $threadArray[$thread["thread_id"]][] = $thread;
        }
        $count = 1;

        $thread_list = [];

        $is_instructor_full_access = [];

        $posts_in_threads = $this->core->getQueries()->getPostsInThreads(array_keys($threadArray));
        $author_user_ids = array_map(function ($post) {
            return $post["author_user_id"];
        }, $posts_in_threads);
        $author_user_groups = $this->core->getQueries()->getAuthorUserGroups($author_user_ids);

        foreach ($author_user_groups as $author) {
            $is_instructor_full_access[$author["user_id"]] = $author["user_group"] <= User::GROUP_FULL_ACCESS_GRADER;
        }

        foreach ($threadArray as $thread_id => $data) {
            $thread_title = $fromIdtoTitle[$thread_id];
            $thread_link = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]);

            $thread_posts = [];
            foreach ($data as $post) {
                $author = $post['author'];
                $user_info = $this->core->getQueries()->getDisplayUserInfoFromUserId($post["p_author"]);
                $given_name = trim($user_info["given_name"]);
                $family_name = trim($user_info["family_name"]);
                $visible_username = $given_name . " " . substr($family_name, 0, 1) . ".";
                $pronouns = trim($user_info["pronouns"]);
                $display_pronouns = $user_info["display_pronouns"];

                if ($is_instructor_full_access[$post["p_author"]]) {
                    $visible_username = $given_name . " " . $family_name;
                }

                if ($post["anonymous"]) {
                    $visible_username = 'Anonymous';
                }

                //convert legacy htmlentities being saved in db
                $post_content = html_entity_decode($post["post_content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $pre_post = preg_replace('#(<a href=[\'"])(.*?)([\'"].*>)(.*?)(</a>)#', '[url=$2]$4[/url]', $post_content);

                if (!empty($pre_post)) {
                    $post_content = $pre_post;
                }

                $post_link = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]) . "#" . $post['p_id'];

                $posted_on = DateUtils::convertTimeStamp($this->core->getUser(), $post['timestamp_post'], $this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));

                $thread_posts[] = [
                    "post_link" => $post_link,
                    "count" => $count,
                    "post_content" => $post_content,
                    "visible_username" => $visible_username,
                    "posted_on" => $posted_on
                ];

                $count++;
            }
            $thread_list[] = [
                "thread_title" => $thread_title,
                "thread_link" => $thread_link,
                "posts" => $thread_posts
            ];
        }

        return $this->core->getOutput()->renderTwigTemplate("forum/searchResults.twig", [
            "buttons" => $buttons,
            "count_threads" => count($threads),
            "threads" => $thread_list,
            "search_url" => $this->core->buildCourseUrl(['forum', 'search'])
        ]);
    }

    /** Shows Forums thread splash page, including all posts
     * for a specific thread, in addition to head of the threads
     * that have been created after applying filter and to be
     * displayed in the left panel.
     * @param Thread[] $threads
     * @param Thread[] $merge_thread_options
     * @return mixed[]|string
     */
    public function showForumThreads(string $user, Thread $thread, array $threads, array $merge_thread_options, bool $show_deleted, bool $show_merged_thread, string $display_option, int $initialPageNumber, bool $ajax = false): array|string {
        $currentCourse = $this->core->getConfig()->getCourse();
        $repo = $this->core->getCourseEntityManager()->getRepository(Category::class);
        $categories = $repo->getCategories();

        $cookieSelectedCategories = $this->getSavedForumCategories($currentCourse, $categories);
        $cookieSelectedThreadStatus = $this->getSavedThreadStatuses();
        $cookieSelectedUnread = $this->getUnreadThreadStatus();

        $filterFormData = [
            "categories" => $categories,
            "current_thread" => $thread->getId(),
            "current_category_ids" => $thread->getCategories()->map(function ($x) {
                return $x->getId();
            }),
            "current_course" => $currentCourse,
            "cookie_selected_categories" => $cookieSelectedCategories,
            "cookie_selected_thread_status" => $cookieSelectedThreadStatus,
            "cookie_selected_unread_value" => $cookieSelectedUnread,
            "display_option" => $display_option,
            "thread_exists" => true,
        ];

        $next_page = $initialPageNumber + 1;
        $prev_page = $initialPageNumber - 1;
        $arrowup_visibility = ($initialPageNumber === 0) ? "display:none;" : "";
        $displayThreadContent = $this->displayThreadList($threads, false);

        $generatePostContent = $this->generatePostList($thread, true, $display_option, $merge_thread_options, false);

        $this->core->getQueries()->visitThread($user, $thread->getId());

        $return = "";

        $markdown_enabled = 0;
        if (isset($_COOKIE['markdown_enabled'])) {
            $markdown_enabled = $_COOKIE['markdown_enabled'];
        }

        $button_params = $this->getAllForumButtons(true, $thread->getId(), $display_option, $show_deleted, $show_merged_thread);

        if (!$ajax) {
            $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildCourseUrl(['forum']), null, true);
             // Add breadcrumb for the current thread
             $max_length = 25;
             $fullTitle = $thread->getTitle();
             $title = strlen($fullTitle) > $max_length ? substr($fullTitle, 0, $max_length - 3) . "..." : $fullTitle;
             $this->core->getOutput()->addBreadcrumb("(" . $thread->getId() . ") " . $title, $this->core->buildCourseUrl(['forum', 'threads', $thread->getId()]), null, true);

            //Body Style is necessary to make sure that the forum is still readable...
            $this->core->getOutput()->addVendorCss('codemirror/codemirror.css');
            $this->core->getOutput()->addVendorCss('codemirror/theme/eclipse.css');
            $this->core->getOutput()->addInternalCss('forum.css');
            $this->core->getOutput()->addInternalCss('highlightjs/atom-one-light.css');
            $this->core->getOutput()->addInternalCss('highlightjs/atom-one-dark.css');
            $this->core->getOutput()->addVendorJs('codemirror/codemirror.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/clike/clike.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/python/python.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/shell/shell.js');
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('highlight.js', 'highlight.min.js'));
            $this->core->getOutput()->addInternalJs('markdown-code-highlight.js');
            $this->core->getOutput()->addInternalJs('drag-and-drop.js');
            $this->core->getOutput()->addInternalJs('autosave-utils.js');
            $this->core->getOutput()->addInternalJs('websocket.js');
            $this->core->getOutput()->addInternalJs('forum.js');
            $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');
            $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');


            $return = $this->core->getOutput()->renderTwigTemplate("forum/ShowForumThreads.twig", [
                "categories" => $categories,
                "filterFormData" => $filterFormData,
                "button_params" => $button_params,
                "thread_exists" => true,
                "next_page" => $next_page,
                "prev_page" => $prev_page,
                "arrowup_visibility" => $arrowup_visibility,
                "display_thread_content" => $displayThreadContent,
                "display_thread_count" => count($displayThreadContent["thread_content"]),
                "currentThread" => $thread,
                "currentCourse" => $currentCourse,
                "accessGrading" => $this->core->getUser()->accessGrading(),
                "manage_categories_url" => $this->core->buildCourseUrl(['forum', 'categories']),
                "generate_post_content" => $generatePostContent,
                "thread_resolve_state" => $thread->getStatus(),
                "show_unresolve" => false,
                "display_option" => $display_option,
                "render_markdown" => $markdown_enabled,
                "csrf_token" => $this->core->getCsrfToken(),
                "edit_url" => $this->core->buildCourseUrl(['forum', 'posts', 'modify']),
                "search_url" => $this->core->buildCourseUrl(['forum', 'search']),
                "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
                "split_url" => $this->core->buildCourseUrl(['forum', 'posts', 'split']),
                "post_content_limit" => ForumUtils::FORUM_CHAR_POST_LIMIT
            ]);
        }
        else {
            $return = $this->core->getOutput()->renderTwigTemplate("forum/GeneratePostList.twig", [
                "userGroup" => $generatePostContent["userGroup"],
                "activeThread" => $generatePostContent["activeThread"],
                "activeThreadAnnouncement" => $generatePostContent["activeThreadAnnouncement"],
                "expiring" => $generatePostContent["expiring"],
                "isCurrentFavorite" => $generatePostContent["isCurrentFavorite"],
                "display_option" => $generatePostContent["display_option"],
                "post_data" => $generatePostContent["post_data"],
                "isThreadLocked" => $generatePostContent["isThreadLocked"],
                "accessFullGrading" => $generatePostContent["accessFullGrading"],
                "includeReply" => $generatePostContent["includeReply"],
                "thread_id" => $generatePostContent["thread_id"],
                "first_post_id" => $generatePostContent["first_post_id"],
                "form_action_link" => $generatePostContent["form_action_link"],
                "thread_resolve_state" => $thread->getStatus(),
                "show_unresolve" => false,
                "merge_thread_content" => $generatePostContent["merge_thread_content"],
                "csrf_token" => $generatePostContent["csrf_token"],
                "activeThreadTitle" => $generatePostContent["activeThreadTitle"],
                "post_box_id" => $generatePostContent["post_box_id"],
                "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
                "split_url" => $this->core->buildCourseUrl(['forum', 'posts', 'split']),
                "post_content_limit" => ForumUtils::FORUM_CHAR_POST_LIMIT,
                "render_markdown" => $markdown_enabled
            ]);

            $return = $this->core->getOutput()->renderJsonSuccess(["html" => json_encode($return)]);
        }

        return $return;
    }

    // Returns the set of buttons with the corresponding attributes
    public function getAllForumButtons($thread_exists, $thread_id, $display_option, $show_deleted, $show_merged_thread) {
        $show_deleted_class = "";
        $show_deleted_action = "";
        $show_deleted_thread_title = "";

        $currentCourse = $this->core->getConfig()->getCourse();

        $default_button = [
            [
                "required_rank" => 4,
                "display_text" => 'Create Thread',
                "style" => 'position:absolute;top:3px;right:0px',
                "link" => [true, $this->core->buildCourseUrl(['forum', 'threads', 'new'])],
                "optional_class" => '',
                "title" => 'Create Thread',
                "onclick" => [false]
            ]
        ];
        $button_params = [
            "current_thread" => $thread_id,
            "forum_bar_buttons_right" => $default_button,
            "forum_bar_buttons_left" => [],
            "show_threads" => true,
            "thread_exists" => true,
            "show_more" => true
        ];
        if ($this->core->getUser()->accessGrading()) {
            if ($show_deleted) {
                $show_deleted_class = "active";
                $show_deleted_action = "alterShowDeletedStatus(0);";
                $show_deleted_thread_title = "Hide Deleted Threads";
            }
            else {
                $show_deleted_class = "";
                $show_deleted_action = "alterShowDeletedStatus(1);";
                $show_deleted_thread_title = "Show Deleted Threads";
            }
        }

        if ($show_merged_thread) {
            $show_merged_thread_class = "active";
            $show_merged_thread_action = "alterShowMergeThreadStatus(0,'" . $currentCourse . "');";
            $show_merged_thread_title = "Hide Merged Threads";
        }
        else {
            $show_merged_thread_class = "";
            $show_merged_thread_action = "alterShowMergeThreadStatus(1,'" . $currentCourse . "');";
            $show_merged_thread_title = "Show Merged Threads";
        }

        if (!$thread_exists) {
            $button_params["show_threads"] = false;
            $button_params["thread_exists"] = false;
            $button_params["show_more"] =  $this->core->getUser()->accessGrading();
        }
        else {
            $more_data = [
                [
                    "filter_option" => $display_option
                ],
                [
                    "display_text" => $show_merged_thread_title,
                    "id" => 'merge_thread',
                    "optional_class" => [!empty($show_merged_thread_class), $show_merged_thread_class],
                    "title" => $show_merged_thread_title . " on Forum",
                    "onclick" => [true, $show_merged_thread_action],
                    "link" => '#',
                    "required_rank" => 4
                ],
                [
                    "display_text" => $show_deleted_thread_title,
                    "optional_class" => [!empty($show_deleted_class), $show_deleted_class],
                    "id" => 'delete',
                    "title" => $show_deleted_thread_title . " on Forum",
                    "link" => '#',
                    "onclick" => [true, $show_deleted_action],
                    "required_rank" => 3
                ],
                [
                    "display_text" => 'Stats',
                    "id" => 'forum_stats',
                    "optional_class" => [false, ''],
                    "title" => 'Forum Statistics',
                    "onclick" => [false, ''],
                    "link" => $this->core->buildCourseUrl(['forum', 'stats']),
                    "required_rank" => 2
                ]
            ];
            $other_buttons = [
                [
                    "required_rank" => 4,
                    "display_text" => 'Filter (<span id="num_filtered">0</span>)',
                    "style" => 'display:inline-block;',
                    "link" => [false],
                    "optional_class" => '',
                    "title" => 'Filter Threads based on Categories',
                    "onclick" => [true, "forumFilterBar()"]
                ]
            ];

            $button_params["more_data"] = $more_data;
            $button_params["forum_bar_buttons_left"] = $other_buttons;
        }
        return $button_params;
    }


    /**
     * Renders or formats a list of posts.
     * @param Thread $thread
     * @param bool $includeReply
     * @param string $display_option
     * @param Thread[] $merge_thread_options
     * @param bool $render
     * @return mixed[]|string
     */
    public function generatePostList(Thread $thread, bool $includeReply, string $display_option, array $merge_thread_options, bool $render = true): array|string {
        $first = true;
        $post_data = [];
        $csrf_token = $this->core->getCsrfToken();
        $GLOBALS['totalAttachments'] = 0;
        $user = $this->core->getUser();
        $first_post = $thread->getFirstPost();
        if ($first_post === false) {
            $first_post = new Post($thread);
        }

        $posts = [];
        if ($display_option == "tree") {
            $posts = ForumUtils::BuildReplyHeirarchy($first_post);
        }
        else {
            // posts were ordered at query-time by repository
            $posts = $thread->getPosts()->toArray();
        }
        $post_box_id = 2;
        foreach ($posts as $post) {
            $post_data[] = $this->createPost(
                $first_post,
                $thread,
                $post,
                $first,
                $display_option,
                $includeReply,
                $post_box_id,
                false,
            );
            if ($first) {
                $first = false;
            }
            $post_box_id++;
        }

        $form_action_link = $this->core->buildCourseUrl(['forum', 'posts', 'new']);

        $merge_thread_content = [];

        if ($this->core->getUser()->accessGrading()) {
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('chosen-js', 'chosen.min.css'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('chosen-js', 'chosen.jquery.min.js'));
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
            $this->core->getOutput()->addInternalJs('autosave-utils.js');
            $this->core->getOutput()->addInternalJs('forum.js');
            $this->core->getOutput()->addInternalCss('forum.css');
            $merge_thread_content = [
                "current_thread_date" => $first_post->getTimestamp(),
                "current_thread" => $thread->getId(),
                "possibleMerges" => $merge_thread_options
            ];
        }

        $generated_post_list = [
            "userGroup" => $user->getGroup(),
            "activeThread" => $thread,
            "activeThreadAnnouncement" => $thread->isPinned(),
            "expiring" => $thread->isPinnedExpiring(),
            "isCurrentFavorite" => $thread->isFavorite($user->getId()),
            "display_option" => $display_option,
            "post_data" => $post_data,
            "isThreadLocked" => $thread->isLocked(),
            "accessFullGrading" => $user->accessFullGrading(),
            "includeReply" => $includeReply,
            "thread_id" => $thread->getId(),
            "first_post_id" => $first_post->getId(),
            "form_action_link" => $form_action_link,
            "merge_thread_content" => $merge_thread_content,
            "csrf_token" => $csrf_token,
            "activeThreadTitle" => "({$thread->getId()}) " . $thread->getTitle(),
            "post_box_id" => $post_box_id,
            "total_attachments" => $GLOBALS['totalAttachments'],
            "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
            "split_url" => $this->core->buildCourseUrl(['forum', 'posts', 'split'])
        ];
        if ($render) {
            $generated_post_list = $this->core->getOutput()->renderTwigTemplate("forum/GeneratePostList.twig", $generated_post_list);
        }
        return $generated_post_list;
    }

    /**
     * Renders scroll content or new posts from websocket.
     * @param Thread[] $threads
     * @return string
     */
    public function showAlteredDisplayList(array $threads): string {
        return $this->displayThreadList($threads, true, true);
    }

    public function contentMarkdownToPlain($str) {
        $str = preg_replace("/\[[^)]+\]/", "", $str);
        $str = preg_replace('/\(([^)]+)\)/s', '$1', $str);
        $str = str_replace("```", "", $str);
        return $str;
    }

    /**
     * Renders the main forum page
     * @param Thread[] $threads
     * @param bool $show_deleted
     * @param bool $show_merged_threads
     * @return string
     */
    public function showFullThreadsPage(array $threads, bool $show_deleted, bool $show_merged_threads, int $block_number): string {
        $GLOBALS['totalAttachments'] = 0;
        $thread_content =  $this->displayThreadList($threads, false, true);
        $repo = $this->core->getCourseEntityManager()->getRepository(Category::class);
        $categories = $repo->getCategories();
        $current_course = $this->core->getConfig()->getCourse();
        // getting the forum page buttons
        $button_params = $this->getAllForumButtons(true, -1, null, $show_deleted, $show_merged_threads);

        // add css and js files
        $this->core->getOutput()->addInternalCss("forum.css");
        $this->core->getOutput()->addInternalJs("forum.js");
        $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        $this->core->getOutput()->addInternalJs('autosave-utils.js');
        $this->core->getOutput()->addVendorJs('flatpickr/flatpickr.js');
        $this->core->getOutput()->addInternalJs('websocket.js');
        $this->core->getOutput()->addInternalJs('drag-and-drop.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');
        $this->core->getOutput()->addVendorCss('flatpickr/flatpickr.min.css');
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));

        $filterFormData = [
            "categories" => $categories,
            "current_thread" => -1,
            "current_category_ids" => [],
            "current_course" => $current_course,
            "cookie_selected_categories" => $this->getSavedForumCategories($current_course, $categories),
            "cookie_selected_thread_status" => $this->getSavedThreadStatuses(),
            "cookie_selected_unread_value" => $this->getUnreadThreadStatus(),
            "thread_exists" => true
        ];


        return $this->core->getOutput()->renderTwigTemplate("forum/showFullThreadsPage.twig", [
            "thread_content" => $thread_content["thread_content"],
            "button_params" => $button_params,
            "filterFormData" => $filterFormData,
            "next_page" => count($threads) > 0 ? $block_number + 1 : 0,
            "prev_page" => count($threads) > 0 ? $block_number - 1 : 0,
            "display_thread_count" => empty($thread_content) ? 0 : count($thread_content["thread_content"]),
            "csrf_token" => $this->core->getCsrfToken(),
            "search_url" => $this->core->buildCourseUrl(['forum', 'search']),
            "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
            "edit_url" => $this->core->buildCourseUrl(['forum']),
            "current_user" => $this->core->getUser()->getId(),
            "user_group" => $this->core->getUser()->getGroup(),
            "thread_exists" => true,
            "manage_categories_url" => $this->core->buildCourseUrl(['forum', 'categories'])
        ]);
    }

    public function sizeTitle(string $title, int $titleLength, $length = 40) {
        $titleDisplay = substr($title, 0, ($titleLength < $length) ? $titleLength : strrpos(substr($title, 0, $length), " "));

        if ($titleLength > $length) {
            //Fix ... appearing
            if (empty($titleDisplay)) {
                $titleDisplay .= substr($title, 0, $length - 10);
            }
            $titleDisplay .= "...";
        }
        return $titleDisplay;
    }

    public function sizeContent($sizeOfContent, $first_post_content, $length = 80) {
        $contentDisplay = substr($first_post_content, 0, ($sizeOfContent < $length) ? $sizeOfContent : strrpos(substr($first_post_content, 0, $length), " "));
        if ($sizeOfContent > $length) {
            $contentDisplay .= "...";
        }
        return $contentDisplay;
    }

    /**
     * Renders or formats a list of threads.
     * @param Thread[] $threads
     * @param bool $render
     * @param bool $is_full_page
     * @return mixed[]|string
     */
    public function displayThreadList(array $threads, bool $render, bool $is_full_page = false): array|string {
        $current_user = $this->core->getUser()->getId();
        $thread_content = [];

        foreach ($threads as $thread) {
            $first_post = $thread->getFirstPost();
            if ($first_post === false) {
                // Thread without any posts(eg. Merged Thread)
                $first_post = new Post($thread);
                $date = null;
            }
            else {
                $date = DateUtils::convertTimeStamp($this->core->getUser(), DateUtils::dateTimeToString($first_post->getTimestamp()), $this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));
            }
            if ($thread->isMergedThread()) {
                // For the merged threads
                $thread->setStatus(0);
            }

            $class = $is_full_page ? "thread_box thread-box-full" : "thread_box";
            if (($_REQUEST["thread_id"] ?? -1) === $thread->getId()) {
                $class .= " active";
            }
            $isNewThread = $thread->isUnread($current_user);
            if ($isNewThread) {
                $class .= " new_thread";
            }
            if ($thread->isDeleted()) {
                if ($isNewThread) {
                    $class .= " deleted-unviewed";
                }
                $class .= " deleted";
            }

            if ($thread->getAuthor()->accessGrading()) {
                $class .= " important";
            }

            //replace tags from displaying in sidebar
            $first_post_content = str_replace("`", "", strip_tags($first_post->getContent()));
            $first_post_content = str_replace("#", "", $first_post_content);
            $temp_first_post_content = preg_replace('#\[(.*?)\]\((.*?)\)#', '$2', $first_post_content);

            if (!empty($temp_first_post_content)) {
                $first_post_content = $temp_first_post_content;
            }

            if ($first_post->isRenderMarkdown()) {
                $first_post_content = $this->contentMarkdownToPlain($first_post_content);
            }

            $titleDisplay = $this->sizeTitle($thread->getTitle(), strlen($thread->getTitle()), $is_full_page ? 140 : 40);
            $contentDisplay = $this->sizeContent(strlen($first_post_content), $first_post_content, $is_full_page ? 500 : 80);

            $titleDisplay = "({$thread->getId()}) " . $titleDisplay;

            $link = $this->core->buildCourseUrl(['forum', 'threads', $thread->getId()]);

            $favorite = $thread->isFavorite($current_user);

            $fa_icon = "fa-question";
            $fa_class = "thread-unresolved";
            $tooltip = "Thread Unresolved";

            if ($thread->getStatus() === 1) {
                $fa_icon = "fa-check";
                $fa_class = "thread-resolved";
                $tooltip = "Thread Resolved";
            }

            $categories_content = [];
            foreach ($thread->getCategories() as $category) {
                $categories_content[] = [$category->getDescription(), $category->getColor()];
            }

            $date_content = ["not_null" => !is_null($date)];

            if (!is_null($date)) {
                $date_content["formatted"] = $date;
            }
            $thread_info = [
                'thread_id' => $thread->getId(),
                "title" => $titleDisplay,
                "content" => $contentDisplay,
                "categories" => $categories_content,
                "link" => $link,
                "class" => $class,
                "pinned" => $thread->isPinned(),
                "expiring" => $thread->isPinnedExpiring(),
                "favorite" => $favorite,
                "merged_thread_id" => $thread->getMergedThread()?->getId() ?? -1,
                "status" => $thread->getStatus(),
                "fa_icon" => $fa_icon,
                "fa_class" => $fa_class,
                "tooltip" => $tooltip,
                "is_locked" => $thread->isLocked(),
                "date" => $date_content,
                "current_user_posted" => $thread->getAuthor()->getId() === $current_user,
            ];

            if ($is_full_page) {
                $user_info = $first_post->getAuthor()->getDisplayInfo();
                $email = trim($user_info["user_email"]);
                $given_name = trim($user_info["given_name"]);
                $family_name = trim($user_info["family_name"]);
                $visible_username = $given_name . " " . substr($family_name, 0, 1) . ".";
                $pronouns = trim($user_info["pronouns"]);
                $display_pronouns = $user_info["display_pronouns"];

                if ($first_post->getAuthor()->accessFullGrading()) {
                    $visible_username = $given_name . " " . $family_name;
                }

                $author_info = [
                    "user_id" => $first_post->getAuthor()->getId(),
                    "name" => $first_post->isAnonymous() ? "Anonymous" : $visible_username,
                    "email" => $email,
                    "full_name" => $given_name . " " . $family_name . " (" . $first_post->getAuthor()->getId() . ")",
                    "pronouns" => $pronouns,
                    "display_pronouns" => $display_pronouns
                ];
                $thread_info = array_merge($thread_info, [
                    "post_id" => $first_post->getId(),
                    "is_thread_locked" => $thread->isLocked(),
                    "thread_resolve_state" => $thread->getStatus(),
                    "show_unresolve" => false,
                    "is_anon" => $first_post->isAnonymous(),
                    "render_markdown" => $first_post->isRenderMarkdown(),
                    "author_info" => $author_info,
                    "deleted" => $first_post->isDeleted(),
                    "sum_ducks" => $thread->getSumUpducks()
                ]);
            }
            $thread_content[] = $thread_info;
        }

        $return = "";

        if ($render) {
            $return = $this->core->getOutput()->renderTwigTemplate("forum/displayThreadList.twig", [
                "thread_content" => $thread_content,
                "is_full_page" => $is_full_page,
            ]);
        }
        else {
            $return = [
                "thread_content" => $thread_content,
            ];
        }

        return $return;
    }

    public function filter_post_content($original_post_content) {
        $post_content = html_entity_decode($original_post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $pre_post = preg_replace('#(<a href=[\'"])(.*?)([\'"].*>)(.*?)(</a>)#', '[url=$2]$4[/url]', $post_content);

        if (!empty($pre_post)) {
            $post_content = $pre_post;
        }

        preg_match_all('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', $post_content, $result);
        $accepted_schemes = ["https", "http"];
        $pos = 0;
        if (count($result) > 0) {
            foreach ($result[1] as $url) {
                $decoded_url = filter_var(trim(strip_tags(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), FILTER_SANITIZE_URL);
                $parsed_url = parse_url($decoded_url, PHP_URL_SCHEME);
                if (filter_var($decoded_url, FILTER_VALIDATE_URL) !== false && in_array($parsed_url, $accepted_schemes, true)) {
                    $pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', '<a href="' . htmlspecialchars($decoded_url, ENT_QUOTES) . '" target="_blank" rel="noopener nofollow">' . $result[2][$pos] . '</a>', $post_content, 1);
                }
                else {
                    $pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', htmlentities(htmlspecialchars($decoded_url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $post_content, 1);
                }
                if (!empty($pre_post)) {
                    $post_content = $pre_post;
                }

                $pos++;
            }
        }
        //This code is for legacy posts that had an extra \r per newline
        if (strpos($original_post_content, "\r") !== false) {
            $post_content = str_replace("\r", "", $post_content);
        }

        //end link handling

        //handle converting code segments
        $post_content = preg_replace('/&lbrack;code&rsqb;(.*?)&lbrack;&sol;code&rsqb;/', '<textarea class="code">$1</textarea>', $post_content);

        return $post_content;
    }

    /**
     * Renders or formats a single post.
     * @param Post $first_post
     * @param Thread $thread
     * @param Post $post
     * @param bool $first
     * @param string $display_option
     * @param bool $includeReply
     * @param int $post_box_id
     * @param bool $render
     * @return mixed[]|string
     */
    public function createPost(Post $first_post, Thread $thread, Post $post, bool $first, string $display_option, bool $includeReply, int $post_box_id, bool $render = false): array|string {
        $user = $this->core->getUser();
        // Get formatted time stamps
        $date = DateUtils::convertTimeStamp($this->core->getUser(), DateUtils::dateTimeToString($post->getTimestamp()), $this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));

        if (!$post->getHistory()->isEmpty()) {
            $edit_timestamp = max($post->getHistory()->map(function ($x) {
                return $x->getEditTimestamp();
            })->toArray());
            $edit_date = DateUtils::convertTimeStamp($this->core->getUser(), DateUtils::dateTimeToString($edit_timestamp), $this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));
        }
        else {
            $edit_date = null;
        }

        $classes = ["post_box"];
        if ($first && $display_option != 'alpha') {
            $classes[] = "first_post";
        }
        $isNewPost = false;
        if ($thread->getNewPosts($user->getId())->contains($post)) {
            $classes[] = "new_post";
            $isNewPost = true;
            if ($post->getAuthor()->accessGrading()) {
                $classes[] = "important important-new";
            }
        }
        else {
            $classes[] = "viewed_post";
            if ($post->getAuthor()->accessGrading()) {
                $classes[] = "important";
            }
        }

        if ($post->isDeleted()) {
            $classes[] = "deleted";
            if ($isNewPost) {
                $classes[] = "deleted-unviewed";
            }
            $deleted = true;
        }
        else {
            $deleted = false;
        }

        $offset = min(($post->getReplyLevel() - 1) * 30, 180);

        $post_content = $post->getContent();
        do {
            $post_content = preg_replace('/(?:!\[(.*?)\]\((.*?)\))/', '$2', $post_content, -1, $count);
        } while ($count > 0);

        $post_user_info = [];

        $merged_thread = $thread->isMergedThread() && $user->accessFullGrading();

        $post_button = [];

        if (($user->accessGrading() || $post->getAuthor()->getId() === $user->getId()) && (!$thread->isLocked() || $user->accessFullGrading())) {
            if ($deleted && $user->accessGrading()) {
                $ud_toggle_status = "false";
                $ud_button_title = "Restore post";
                $ud_button_icon = "fa-undo";
            }
            else {
                $ud_toggle_status = "true";
                $ud_button_title = "Remove post";
                $ud_button_icon = "fa-trash";
            }

            $post_button["delete"] = [
                "ud_toggle_status" => $ud_toggle_status,
                "csrf_token" => $this->core->getCsrfToken(),
                "ud_button_title" => $ud_button_title,
                "ud_button_icon" => $ud_button_icon
            ];

            if ($first) {
                $shouldEditThread = "true";
                $edit_button_title = "Edit thread and post";
            }
            else {
                $shouldEditThread = "false";
                $edit_button_title = "Edit post";
            }

            $post_button["edit"] = [
                "shouldEditThread" => $shouldEditThread,
                "edit_button_title" => $edit_button_title,
                "csrf_token" => $this->core->getCsrfToken()
            ];
        }

        $post_up_duck = [
            "upduck_count" => count($post->getUpduckers()),
            "upduck_user_liked" => $post->getUpduckers()->map(function ($x) {
                return $x->getId();
            })->contains($user->getId()),
            "taTrue" => !$post->getUpduckers()->filter(function ($x) {
                return $x->accessGrading();
            })->isEmpty(),
        ];

        $author_display_info = $post->getAuthor()->getDisplayInfo();
        $visible_username = $author_display_info["given_name"] . " " . substr($author_display_info["family_name"], 0, 1) . ".";

        if ($post->getAuthor()->accessFullGrading()) {
            $visible_username = $author_display_info["given_name"] . " " . $author_display_info["family_name"];
        }

        if ($post->isAnonymous()) {
            $visible_username = "Anonymous";
        }

        $post_user_info = [
            "info_name" => json_encode($author_display_info["given_name"] . " " . $author_display_info["family_name"] . " (" . $post->getAuthor()->getId() . ")"),
            "visible_user_json" => json_encode($visible_username),
            "jscriptAnonFix" => json_encode($post->isAnonymous() ? 'true' : 'false'),
            "pronouns" => trim($author_display_info["pronouns"]),
            "display_pronouns" => $author_display_info["display_pronouns"],
            "is_OP" => ($post->getAuthor()->getId() === $first_post->getAuthor()->getId()) && ($post->isAnonymous() === $first_post->isAnonymous()),
        ];

        $post_attachment = ForumUtils::getForumAttachments(
            $post->getId(),
            $thread->getId(),
            $post->getAttachments()->filter(function ($x) {
                return $x->isCurrent();
            })->map(function ($x) {
                return $x->getFileName();
            })->toArray(),
            $this->core->getConfig()->getCoursePath(),
            $this->core->buildCourseUrl(['display_file'])
        );

        $created_post = [
            "classes" => $classes,
            "post_id" => $post->getId(),
            "reply_level" => $post->getReplyLevel(),
            "offset" => $offset,
            "first" => $first,
            "post_content" => $post_content,
            "post" => $post,
            "display_option" => $display_option,
            "isThreadLocked" => $thread->isLocked(),
            "userAccessFullGrading" => $user->accessFullGrading(),
            "userGroup" => $user->getGroup(),
            "includeReply" => $includeReply,
            "thread_resolve_state" => $thread->getStatus(),
            "show_unresolve" => false,
            "current_user" => $user->getId(),
            "author_email" => $author_display_info["user_email"],
            "post_user_info" => $post_user_info,
            "post_up_duck" => $post_up_duck,
            "post_date" => $date,
            "edit_date" => $edit_date,
            "post_buttons" => $post_button,
            "visible_username" => $visible_username,
            "post_attachment" => $post_attachment,
            "form_post_url" => $this->core->buildCourseUrl(['forum', 'posts', 'new']),
            "post_box_id" => $post_box_id,
            "thread_id" => $thread->getId(),
            "parent_id" => $post->getParent()->getId(),
            "render_markdown" => $post->isRenderMarkdown(),
            "has_history" => !$post->getHistory()->isEmpty(),
            "thread_previously_merged" => $merged_thread,
            "thread_announced" => $thread->isAnnounced(),
        ];

        if ($render) {
            if ($first) {
                $created_post['activeThreadTitle'] = "({$thread->getId()}) " . $thread->getTitle();
                $created_post['activeThreadAnnouncement'] = $thread->isPinned();
            }
            $created_post['activeThread'] = $thread;
            $created_post['isCurrentFavorite'] = $thread->isFavorite($user->getId());
            $created_post['csrf_token'] = $this->core->getCsrfToken();
            return $this->core->getOutput()->renderTwigTemplate("forum/CreatePost.twig", $created_post);
        }
        else {
            return $created_post;
        }
    }

    public function createThread($category_colors) {
        $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildCourseUrl(['forum']), null, $use_as_heading = true);
        $this->core->getOutput()->addBreadcrumb("Create Thread", $this->core->buildCourseUrl(['forum', 'threads', 'new']));

        $this->core->getOutput()->addInternalJs('drag-and-drop.js');
        $this->core->getOutput()->addVendorJs('flatpickr/flatpickr.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');
        $this->core->getOutput()->addVendorCss('flatpickr/flatpickr.min.css');
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));

        $this->core->getOutput()->addInternalJs('autosave-utils.js');
        $this->core->getOutput()->addInternalJs('websocket.js');
        $this->core->getOutput()->addInternalJs('forum.js');
        $this->core->getOutput()->addInternalCss('forum.css');

        $repo = $this->core->getCourseEntityManager()->getRepository(Category::class);
        $categories = $repo->getCategories();
        $create_thread_message = $this->core->getConfig()->getForumCreateThreadMessage();

        $buttons = [
            [
                "required_rank" => 4,
                "display_text" => 'Back to Threads',
                "style" => 'position:relative;top:3px;float:right;',
                "link" => [true, $this->core->buildCourseUrl(['forum'])],
                "optional_class" => '',
                "title" => 'Back to threads',
                "onclick" => [false]
            ]
        ];

        $thread_exists = $this->core->getQueries()->threadExists();
        $manage_categories_url = $this->core->buildCourseUrl(['forum', 'categories']);
        $expiration = $this->core->getDateTimeNow();

        return $this->core->getOutput()->renderTwigTemplate("forum/createThread.twig", [
            "categories" => $categories,
            "category_colors" => $category_colors,
            "buttons" => $buttons,
            "thread_exists" => $thread_exists,
            "create_thread_message" => $create_thread_message,
            "form_action" => $this->core->buildCourseUrl(['forum', 'threads', 'new']),
            "manage_categories_url" => $manage_categories_url,
            "csrf_token" => $this->core->getCsrfToken(),
            "email_enabled" => $this->core->getConfig()->isEmailEnabled(),
            "search_url" => $this->core->buildCourseUrl(['forum', 'search']),
            "expiration_placeholder" => $expiration->add(new \DateInterval('P7D'))->format('Y-m-d'),
            "render_markdown" => isset($_COOKIE['markdown_enabled']) ? $_COOKIE['markdown_enabled'] : 0
        ]);
    }

    public function showCategories($category_colors) {
        $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildCourseUrl(['forum']), null, $use_as_heading = true);
        $this->core->getOutput()->addBreadcrumb("Manage Categories", $this->core->buildCourseUrl(['forum', 'categories']));

        $this->core->getOutput()->addInternalJs('drag-and-drop.js');
        $this->core->getOutput()->addInternalJs('autosave-utils.js');
        $this->core->getOutput()->addInternalJs('forum.js');
        $this->core->getOutput()->addVendorJs('flatpickr/flatpickr.js');
        $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');

        $this->core->getOutput()->addVendorCss('flatpickr/flatpickr.min.css');
        $this->core->getOutput()->addInternalCss('forum.css');

        $categories = "";

        if ($this->core->getUser()->accessGrading()) {
            $repo = $this->core->getCourseEntityManager()->getRepository(Category::class);
            $categories = $repo->getCategories();
        }

        $buttons = [
            [
                "required_rank" => 4,
                "display_text" => 'Back to Threads',
                "style" => 'position:relative;float:right;top:3px;',
                "link" => [true, $this->core->buildCourseUrl(['forum'])],
                "optional_class" => '',
                "title" => 'Back to threads',
                "onclick" => [false]
            ]
        ];
        $thread_exists = $this->core->getQueries()->threadExists();

        $forumBarData = [
            "forum_bar_buttons_right" => $buttons,
            "forum_bar_buttons_left" => [],
            "show_threads" => false,
            "thread_exists" => $thread_exists
        ];

        return $this->core->getOutput()->renderTwigTemplate("forum/ShowCategories.twig", [
            "categories" => $categories,
            "category_colors" => $category_colors,
            "forumBarData" => $forumBarData,
            "csrf_token" => $this->core->getCsrfToken(),
            "search_url" => $this->core->buildCourseUrl(['forum', 'search'])
        ]);
    }

    public function statPage($users) {
        if (!$this->core->getUser()->accessFullGrading()) {
            $this->core->redirect($this->core->buildCourseUrl(['forum', 'threads']));
            return;
        }
        $this->core->getOutput()->addInternalJs('stat-page.js');
        $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildCourseUrl(['forum']), null, $use_as_heading = true);
        $this->core->getOutput()->addBreadcrumb("Statistics", $this->core->buildCourseUrl(['forum', 'stats']));

        $this->core->getOutput()->addInternalJs('autosave-utils.js');
        $this->core->getOutput()->addInternalJs('forum.js');
        $this->core->getOutput()->addInternalCss('forum.css');

        $buttons = [
            [
                "required_rank" => 4,
                "display_text" => 'Back to Threads',
                "style" => 'position:relative;float:right;top:3px;',
                "link" => [true, $this->core->buildCourseUrl(['forum'])],
                "optional_class" => '',
                "title" => 'Back to threads',
                "onclick" => [false]
            ]
        ];

        $thread_exists = $this->core->getQueries()->threadExists();

        $forumBarData = [
            "forum_bar_buttons_right" => $buttons,
            "forum_bar_buttons_left" => [],
            "show_threads" => false,
            "thread_exists" => $thread_exists
        ];

        $userData = [];

        foreach ($users as $user => $details) {
            $given_name = $details["given_name"];
            $family_name = $details["family_name"];
            $post_count = count($details["posts"]);
            $posts = json_encode($details["posts"]);
            $ids = json_encode($details["id"]);
            $timestamps = json_encode($details["timestamps"]);
            $thread_ids = json_encode($details["thread_id"]);
            $thread_titles = json_encode($details["thread_title"]);
            $num_deleted = ($details["num_deleted_posts"]);
            $total_upducks = ($details["total_upducks"]);

            $userData[] = [
                "family_name" => $family_name,
                "given_name" => $given_name,
                "post_count" => $post_count,
                "details_total_threads" => $details["total_threads"],
                "num_deleted" => $num_deleted,
                "posts" => $posts,
                "ids" => $ids,
                "timestamps" => $timestamps,
                "thread_ids" => $thread_ids,
                "thread_titles" => $thread_titles,
                "total_upducks" => $total_upducks
            ];
        }

        return $this->core->getOutput()->renderTwigTemplate("forum/StatPage.twig", [
            "forumBarData" => $forumBarData,
            "userData" => $userData,
            "search_url" => $this->core->buildCourseUrl(['forum', 'search']),
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
