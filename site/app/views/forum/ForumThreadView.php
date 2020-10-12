<?php

namespace app\views\forum;

use app\libraries\DateUtils;
use app\views\AbstractView;
use app\libraries\FileUtils;

class ForumThreadView extends AbstractView {

    public function forumAccess() {
        return $this->core->getConfig()->isForumEnabled();
    }

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

        foreach ($threadArray as $thread_id => $data) {
            $thread_title = $fromIdtoTitle[$thread_id];

            $thread_link = $this->core->buildCourseUrl(['forum', 'threads', $thread_id]);

            $thread_list[$count - 1] = ["thread_title" => $thread_title, "thread_link" => $thread_link, "posts" => []];

            foreach ($data as $post) {
                $author = $post['author'];
                $user_info = $this->core->getQueries()->getDisplayUserInfoFromUserId($post["p_author"]);
                $first_name = trim($user_info["first_name"]);
                $last_name = trim($user_info["last_name"]);
                $visible_username = $first_name . " " . substr($last_name, 0, 1) . ".";

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

                $thread_list[$count - 1]["posts"][] = [
                    "post_link" => $post_link,
                    "count" => $count,
                    "post_content" => $post_content,
                    "visible_username" => $visible_username,
                    "posted_on" => $posted_on
                ];

                $count++;
            }
        }


        return $this->core->getOutput()->renderTwigTemplate("forum/searchResults.twig", [
            "buttons" => $buttons,
            "count_threads" => count($threads),
            "threads" => $thread_list,
            "search_url" => $this->core->buildCourseUrl(['forum', 'search'])
        ]);
    }

    /** Shows Forums thread splash page, including all posts
        for a specific thread, in addition to head of the threads
        that have been created after applying filter and to be
        displayed in the left panel.
     */

    public function showForumThreads($user, $posts, $unviewed_posts, $threadsHead, $show_deleted, $show_merged_thread, $display_option, $max_thread, $initialPageNumber, $thread_resolve_state, $post_content_limit, $ajax = false) {

        if (!$this->forumAccess()) {
            $this->core->redirect($this->core->buildCourseUrl([]));
            return;
        }
        $threadExists = $this->core->getQueries()->threadExists();
        $filteredThreadExists = (count($threadsHead) > 0);
        $currentThread = -1;
        $currentCategoriesIds = [];
        $show_deleted_thread_title = null;
        $currentCourse = $this->core->getConfig()->getCourse();
        $threadFiltering = $threadExists && !$filteredThreadExists && !(empty($_COOKIE[$currentCourse . '_forum_categories']) && empty($_COOKIE['forum_thread_status']) && empty($_COOKIE['unread_select_value']) === 'false');

        if (!$ajax) {
            $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildCourseUrl(['forum']), null, $use_as_heading = true);

            //Body Style is necessary to make sure that the forum is still readable...
            $this->core->getOutput()->addVendorCss('codemirror/codemirror.css');
            $this->core->getOutput()->addVendorCss('codemirror/theme/eclipse.css');
            $this->core->getOutput()->addInternalCss('forum.css');
            $this->core->getOutput()->addVendorJs('codemirror/codemirror.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/clike/clike.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/python/python.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/shell/shell.js');
            $this->core->getOutput()->addInternalJs('drag-and-drop.js');
            $this->core->getOutput()->addInternalJs('autosave-utils.js');
            $this->core->getOutput()->addInternalJs('websocket.js');
            $this->core->getOutput()->addInternalJs('forum.js');
            $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');
            $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        }

        if ($filteredThreadExists || $threadFiltering) {
            $currentThread = isset($_GET["thread_id"]) && is_numeric($_GET["thread_id"]) && (int) $_GET["thread_id"] < $max_thread && (int) $_GET["thread_id"] > 0 ? (int) $_GET["thread_id"] : $posts[0]["thread_id"];
            $currentCategoriesIds = $this->core->getQueries()->getCategoriesIdForThread($currentThread);
        }

        $currentThreadArr = array_filter($threadsHead, function ($ar) use ($currentThread) {
            return ($ar['id'] == $currentThread);
        });

        $categories = $this->core->getQueries()->getCategories();

        $cookieSelectedCategories = $this->getSavedForumCategories($currentCourse, $categories);
        $cookieSelectedThreadStatus = $this->getSavedThreadStatuses();
        $cookieSelectedUnread = $this->getUnreadThreadStatus();

        $filterFormData = [
            "categories" => $categories,
            "current_thread" => $currentThread,
            "current_category_ids" => $currentCategoriesIds,
            "current_course" => $currentCourse,
            "cookie_selected_categories" => $cookieSelectedCategories,
            "cookie_selected_thread_status" => $cookieSelectedThreadStatus,
            "cookie_selected_unread_value" => $cookieSelectedUnread,
            "display_option" => $display_option,
            "thread_exists" => $threadExists
        ];

        $next_page = 0;
        $prev_page = 0;
        $arrowup_visibility = 0;
        $displayThreadContent = "";
        $generatePostContent = "";

        if ($threadExists) {
            $next_page = $initialPageNumber + 1;
            $prev_page = ($initialPageNumber == 1) ? 0 : ($initialPageNumber - 1);
            $arrowup_visibility = ($initialPageNumber == 1) ? "display:none;" : "";
            $activeThreadAnnouncement = false;
            $activeThreadTitle = "";
            $activeThread = [];
            $displayThreadContent = $this->displayThreadList($threadsHead, false, $activeThreadAnnouncement, $activeThreadTitle, $activeThread, $currentThread, $currentCategoriesIds, false);

            if (count($activeThread) == 0) {
                $activeThread = $this->core->getQueries()->getThread($currentThread);
            }

            $currentThreadArrValues = array_values($currentThreadArr);
            $currentThreadFavorite = !empty($currentThreadArrValues) ? $currentThreadArrValues[0]['favorite'] : false;
            $generatePostContent = $this->generatePostList($currentThread, $posts, $unviewed_posts, $currentCourse, true, $threadExists, $display_option, $categories, $cookieSelectedCategories, $cookieSelectedThreadStatus, $cookieSelectedUnread, $currentCategoriesIds, $currentThreadFavorite, false);
        }

        if (!empty($activeThread['id'])) {
            $this->core->getQueries()->visitThread($user, $activeThread['id']);
        }

        $return = "";

        $markdown_enabled = 0;
        if (isset($_COOKIE['markdown_enabled'])) {
            $markdown_enabled = $_COOKIE['markdown_enabled'];
        }

        $button_params = $this->getAllForumButtons($threadExists, $currentThread, $display_option, $show_deleted, $show_merged_thread);

        if (!$ajax) {
            // Add breadcrumb for the current thread
            $currentThreadArrValues = array_values($currentThreadArr);
            if ($currentThreadArrValues) {
                $max_length = 25;
                $fullTitle = $currentThreadArrValues[0]["title"];
                $title = strlen($fullTitle) > $max_length ? substr($fullTitle, 0, $max_length - 3) . "..." : $fullTitle;
                $this->core->getOutput()->addBreadcrumb("(" . $currentThreadArrValues[0]["id"] . ") " . $title, $this->core->buildCourseUrl(['forum', 'threads', 9]), null, $use_as_heading = true);
            }

            $return = $this->core->getOutput()->renderTwigTemplate("forum/ShowForumThreads.twig", [
                "categories" => $categories,
                "filterFormData" => $filterFormData,
                "button_params" => $button_params,
                "thread_exists" => $threadExists,
                "next_page" => $next_page,
                "prev_page" => $prev_page,
                "arrowup_visibility" => $arrowup_visibility,
                "display_thread_content" => $displayThreadContent,
                "display_thread_count" => empty($displayThreadContent) ? 0 : count($displayThreadContent["thread_content"]),
                "currentThread" => $currentThread,
                "currentCourse" => $currentCourse,
                "accessGrading" => $this->core->getUser()->accessGrading(),
                "manage_categories_url" => $this->core->buildCourseUrl(['forum', 'categories']),
                "generate_post_content" => $generatePostContent,
                "thread_resolve_state" => $thread_resolve_state,
                "display_option" => $display_option,
                "render_markdown" => $markdown_enabled,
                "csrf_token" => $this->core->getCsrfToken(),
                "edit_url" => $this->core->buildCourseUrl(['forum', 'posts', 'modify']) . '?' . http_build_query(['modify_type' => '1']),
                "search_url" => $this->core->buildCourseUrl(['forum', 'search']),
                "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
                "split_url" => $this->core->buildCourseUrl(['forum', 'posts', 'split']),
                "post_content_limit" => $post_content_limit
            ]);
        }
        else {
            $return = $this->core->getOutput()->renderTwigTemplate("forum/GeneratePostList.twig", [
                "userGroup" => $generatePostContent["userGroup"],
                "activeThread" => $generatePostContent["activeThread"],
                "activeThreadAnnouncement" => $generatePostContent["activeThreadAnnouncement"],
                "isCurrentFavorite" => $generatePostContent["isCurrentFavorite"],
                "display_option" => $generatePostContent["display_option"],
                "post_data" => $generatePostContent["post_data"],
                "isThreadLocked" => $generatePostContent["isThreadLocked"],
                "accessFullGrading" => $generatePostContent["accessFullGrading"],
                "includeReply" => $generatePostContent["includeReply"],
                "thread_id" => $generatePostContent["thread_id"],
                "first_post_id" => $generatePostContent["first_post_id"],
                "form_action_link" => $generatePostContent["form_action_link"],
                "thread_resolve_state" => $thread_resolve_state,
                "merge_thread_content" => $generatePostContent["merge_thread_content"],
                "csrf_token" => $generatePostContent["csrf_token"],
                "activeThreadTitle" => $generatePostContent["activeThreadTitle"],
                "post_box_id" => $generatePostContent["post_box_id"],
                "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
                "split_url" => $this->core->buildCourseUrl(['forum', 'posts', 'split']),
                "post_content_limit" => $post_content_limit
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


    public function generatePostList($currentThread, $posts, $unviewed_posts, $currentCourse, $includeReply = false, $threadExists = false, $display_option = 'time', $categories = [], $cookieSelectedCategories = [], $cookieSelectedThreadStatus = [], $cookieSelectedUnread = [], $currentCategoriesIds = [], $isCurrentFavorite = false, $render = true) {

        $activeThread = $this->core->getQueries()->getThread($currentThread);

        $activeThreadTitle = ($this->core->getUser()->accessFullGrading() ? "({$activeThread['id']}) " : '') . $activeThread['title'];
        $activeThreadAnnouncement = $activeThread['pinned'];

        $thread_id = $activeThread['id'];

        $first = true;
        $first_post_id = 1;

        $post_data = [];

        $csrf_token = $this->core->getCsrfToken();

        $totalAttachments = 0;
        $GLOBALS['totalAttachments'] = 0;

        if ($display_option == "tree") {
            $order_array = [];
            $reply_level_array = [];
            foreach ($posts as $post) {
                if ($thread_id == -1) {
                    $thread_id = $post["thread_id"];
                }
                if ($first) {
                    $first = false;
                    $first_post_id = $post["id"];
                }
                if ($post["parent_id"] > $first_post_id) {
                    $place = array_search($post["parent_id"], $order_array);
                    $tmp_array = [$post["id"]];
                    $parent_reply_level = $reply_level_array[$place];
                    while ($place && $place + 1 < count($reply_level_array) && $reply_level_array[$place + 1] > $parent_reply_level) {
                        $place++;
                    }
                    array_splice($order_array, $place + 1, 0, $tmp_array);
                    array_splice($reply_level_array, $place + 1, 0, $parent_reply_level + 1);
                }
                else {
                    array_push($order_array, $post["id"]);
                    array_push($reply_level_array, 1);
                }
            }
            $i = 0;
            $first = true;

            foreach ($order_array as $ordered_post) {
                foreach ($posts as $post) {
                    if ($post["id"] == $ordered_post) {
                        if ($post["parent_id"] == $first_post_id) {
                            $reply_level = 1;
                        }
                        else {
                            $reply_level = $reply_level_array[$i];
                        }

                        $post_data[] = $this->createPost($thread_id, $post, $unviewed_posts, $first, $reply_level, $display_option, $includeReply);

                        break;
                    }
                }
                if ($first) {
                    $first = false;
                }
                $i++;
            }
        }
        else {
            foreach ($posts as $post) {
                if ($thread_id == -1) {
                    $thread_id = $post["thread_id"];
                }

                $first_post_id = $this->core->getQueries()->getFirstPostForThread($thread_id)['id'];

                $post_data[] = $this->createPost($thread_id, $post, $unviewed_posts, $first, 1, $display_option, $includeReply);

                if ($first) {
                    $first = false;
                }
            }
        }

        $isThreadLocked = $this->core->getQueries()->isThreadLocked($thread_id);
        $accessFullGrading = $this->core->getUser()->accessFullGrading();

        $post_box_id = 0;

        $form_action_link = $this->core->buildCourseUrl(['forum', 'posts', 'new']);

        if (($isThreadLocked != 1 || $accessFullGrading ) && $includeReply) {
            $GLOBALS['post_box_id'] = $post_box_id = isset($GLOBALS['post_box_id']) ? $GLOBALS['post_box_id'] + 1 : 1;
        }

        $merge_thread_content = [];

        if ($this->core->getUser()->getGroup() <= 3) {
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('chosen-js', 'chosen.min.css'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('chosen-js', 'chosen.jquery.min.js'));
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
            $this->core->getOutput()->addInternalJs('autosave-utils.js');
            $this->core->getOutput()->addInternalJs('forum.js');
            $this->core->getOutput()->addInternalCss('forum.css');
            $current_thread_first_post = $this->core->getQueries()->getFirstPostForThread($currentThread);
            $current_thread_date = $current_thread_first_post["timestamp"];
            $merge_thread_list = $this->core->getQueries()->getThreadsBefore($current_thread_date, 1);

            // Get first post of each thread. To be used later
            // to obtain the content of the post to be displayed
            // in the modal.
            foreach ($merge_thread_list as $key => $temp_thread) {
                $temp_first_post = $this->core->getQueries()->getFirstPostForThread($temp_thread['id']);
                $merge_thread_list[$key]['first_post_id'] = $temp_first_post['id'];
            }

            $merge_thread_content = [
                "current_thread_date" => $current_thread_date,
                "current_thread" => $currentThread,
                "possibleMerges" => $merge_thread_list
            ];
        }

        $return = "";

        if ($render) {
            $return = $this->core->getOutput()->renderTwigTemplate("forum/GeneratePostList.twig", [
                "userGroup" => $this->core->getUser()->getGroup(),
                "activeThread" => $activeThread,
                "activeThreadAnnouncement" => $activeThreadAnnouncement,
                "isCurrentFavorite" => $isCurrentFavorite,
                "display_option" => $display_option,
                "post_data" => $post_data,
                "isThreadLocked" => $isThreadLocked,
                "accessFullGrading" => $accessFullGrading,
                "includeReply" => $includeReply,
                "thread_id" => $thread_id,
                "first_post_id" => $first_post_id,
                "form_action_link" => $form_action_link,
                "merge_thread_content" => $merge_thread_content,
                "csrf_token" => $csrf_token,
                "activeThreadTitle" => $activeThreadTitle,
                "post_box_id" => $post_box_id,
                "total_attachments" => $GLOBALS['totalAttachments'],
                "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
                "split_url" => $this->core->buildCourseUrl(['forum', 'posts', 'split'])
            ]);
        }
        else {
            $return = [
                "userGroup" => $this->core->getUser()->getGroup(),
                "activeThread" => $activeThread,
                "activeThreadAnnouncement" => $activeThreadAnnouncement,
                "isCurrentFavorite" => $isCurrentFavorite,
                "display_option" => $display_option,
                "post_data" => $post_data,
                "isThreadLocked" => $isThreadLocked,
                "accessFullGrading" => $accessFullGrading,
                "includeReply" => $includeReply,
                "thread_id" => $thread_id,
                "first_post_id" => $first_post_id,
                "form_action_link" => $form_action_link,
                "merge_thread_content" => $merge_thread_content,
                "csrf_token" => $csrf_token,
                "activeThreadTitle" => $activeThreadTitle,
                "post_box_id" => $post_box_id,
                "total_attachments" => $GLOBALS['totalAttachments']
            ];
        }

        return $return;
    }

    public function showAlteredDisplayList($threads, $filtering, $thread_id, $categories_ids, $ajax = false) {
        $tempArray = [];
        $threadAnnouncement = false;
        $activeThreadTitle = "";
        $thread = "";
        if ($ajax) {
            for ($i = 0; $i < count($threads); $i++) {
                if ($threads[$i]["id"] == $thread_id) {
                    $thread = $threads[$i];
                    break;
                }
            }
            $threads = [$thread];
        }
        return $this->displayThreadList($threads, $filtering, $threadAnnouncement, $activeThreadTitle, $tempArray, $thread_id, $categories_ids, true);
    }

    public function contentMarkdownToPlain($str) {
        $str = preg_replace("/\[[^)]+\]/", "", $str);
        $str = preg_replace('/\(([^)]+)\)/s', '$1', $str);
        $str = str_replace("```", "", $str);
        return $str;
    }

    public function showFullThreadsPage($threads, $category_ids, $show_deleted, $show_merged_threads, $page_number) {
        $activeThreadAnnouncements = [];
        $activeThreadTitle = "";
        $activeThread = [];
        $thread_content =  $this->displayThreadList($threads, false, $activeThreadAnnouncements, $activeThreadTitle, $activeThread, null, $category_ids, false, true);
        $categories = $this->core->getQueries()->getCategories();
        $current_course = $this->core->getConfig()->getCourse();
        $cookieSelectedCategories = $this->getSavedForumCategories($current_course, $categories);
        $cookieSelectedThreadStatus = $this->getSavedThreadStatuses();
        $cookieSelectedUnread = $this->getUnreadThreadStatus();
        $next_page = 0;
        $prev_page = 0;
        // getting the forum page buttons
        $thread_id = -1;
        $thread_exists = $this->core->getQueries()->threadExists();
        $button_params = $this->getAllForumButtons($thread_exists, $thread_id, null, $show_deleted, $show_merged_threads);

        // add css and js files
        $this->core->getOutput()->addInternalCss("forum.css");
        $this->core->getOutput()->addInternalJs("forum.js");
        $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        $this->core->getOutput()->addInternalJs('autosave-utils.js');
        $this->core->getOutput()->addVendorJs('flatpickr/flatpickr.js');
        $this->core->getOutput()->addInternalJs('websocket.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');
        $this->core->getOutput()->addVendorCss('flatpickr/flatpickr.min.css');
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));

        if ($thread_exists) {
            $next_page = $page_number + 1;
            $prev_page = $page_number - 1;
        }

        $filterFormData = [
            "categories" => $categories,
            "current_thread" => $thread_id,
            "current_category_ids" => [],
            "current_course" => $current_course,
            "cookie_selected_categories" => $cookieSelectedCategories,
            "cookie_selected_thread_status" => $cookieSelectedThreadStatus,
            "cookie_selected_unread_value" => $cookieSelectedUnread,
            "thread_exists" => $thread_exists
        ];


        return $this->core->getOutput()->renderTwigTemplate("forum/showFullThreadsPage.twig", [
            "thread_content" => $thread_content["thread_content"],
            "button_params" => $button_params,
            "filterFormData" => $filterFormData,
            "next_page" => $next_page,
            "prev_page" => $prev_page,
            "display_thread_count" => empty($thread_content) ? 0 : count($thread_content["thread_content"]),
            "csrf_token" => $this->core->getCsrfToken(),
            "search_url" => $this->core->buildCourseUrl(['forum', 'search']),
            "merge_url" => $this->core->buildCourseUrl(['forum', 'threads', 'merge']),
            "current_user" => $this->core->getUser()->getId(),
            "user_group" => $this->core->getUser()->getGroup(),
            "thread_exists" => $thread_exists
        ]);
    }

    public function sizeTitle($titleDisplay, $title, $titleLength, $length = 40) {
        $titleDisplay = substr($titleDisplay, 0, ($titleLength < $length) ? $titleLength : strrpos(substr($titleDisplay, 0, $length), " "));

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

    public function displayThreadList($threads, $filtering, &$activeThreadAnnouncement, &$activeThreadTitle, &$activeThread, $thread_id_p, $current_categories_ids, $render, $is_full_page = false) {
        $used_active = false; //used for the first one if there is not thread_id set
        $current_user = $this->core->getUser()->getId();
        $display_thread_ids = $this->core->getUser()->getGroup() <= 2;

        $activeThreadAnnouncement = false;
        $activeThreadTitle = "";
        $activeThread = [];

        $thread_content = [];

        foreach ($threads as $thread) {
            $first_post = $this->core->getQueries()->getFirstPostForThread($thread["id"]);
            if (is_null($first_post)) {
                // Thread without any posts(eg. Merged Thread)
                $first_post = ['content' => "", 'render_markdown' => 0];
                $date = null;
            }
            else {
                $date = DateUtils::convertTimeStamp($this->core->getUser(), $first_post['timestamp'], $this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));
            }
            if ($thread['merged_thread_id'] != -1) {
                // For the merged threads
                $thread['status'] = 0;
            }

            $class = $is_full_page ? "thread_box thread-box-full" : "thread_box";
            // $current_categories_ids should be subset of $thread["categories_ids"]
            $issubset = (count(array_intersect($current_categories_ids, $thread["categories_ids"])) == count($current_categories_ids));
            if (((isset($_REQUEST["thread_id"]) && $_REQUEST["thread_id"] == $thread["id"]) || $thread_id_p == $thread["id"] || $thread_id_p == -1) && !$used_active && $issubset) {
                $class .= " active";
                $used_active = true;
                $activeThreadTitle = ($display_thread_ids ? "({$thread['id']}) " : '') . $thread["title"];
                $activeThread = $thread;
                if ($thread["pinned"]) {
                    $activeThreadAnnouncement = true;
                }
                if ($thread_id_p == -1) {
                    $thread_id_p = $thread["id"];
                }
            }
            if (!$this->core->getQueries()->viewedThread($current_user, $thread["id"]) && $current_user != $thread['created_by']) {
                $class .= " new_thread";
            }
            if ($thread["deleted"]) {
                $class .= " deleted";
            }

            if ($this->core->getQueries()->getUserById($thread['created_by'])->accessGrading()) {
                $class .= " important";
            }

            //fix legacy code
            $titleDisplay = $thread['title'];

            //replace tags from displaying in sidebar
            $first_post_content = str_replace("[/code]", "", str_replace("[code]", "", strip_tags($first_post["content"])));
            $temp_first_post_content = preg_replace('#\[url=(.*?)\](.*?)(\[/url\])#', '$2', $first_post_content);

            if (!empty($temp_first_post_content)) {
                $first_post_content = $temp_first_post_content;
            }

            if ($first_post['render_markdown'] == 1) {
                $first_post_content = $this->contentMarkdownToPlain($first_post_content);
            }

            $sizeOfContent = strlen($first_post_content);
            $titleLength = strlen($thread['title']);

            if ($is_full_page) {
                $titleDisplay = $this->sizeTitle($titleDisplay, $thread['title'], $titleLength, 140);
                $contentDisplay = $this->sizeContent($sizeOfContent, $first_post_content, 500);
            }
            else {
                $titleDisplay = $this->sizeTitle($titleDisplay, $thread['title'], $titleLength);
                $contentDisplay = $this->sizeContent($sizeOfContent, $first_post_content);
            }

            $titleDisplay = ($display_thread_ids ? "({$thread['id']}) " : '') . $titleDisplay;

            $link = $this->core->buildCourseUrl(['forum', 'threads', $thread['id']]);

            $favorite = isset($thread['favorite']) && $thread['favorite'];

            $fa_icon = "fa-question";
            $fa_class = "thread-unresolved";
            $tooltip = "Thread Unresolved";

            if (!isset($thread['status'])) {
                $thread['status'] = 0;
            }
            if ($thread['status'] != 0) {
                if ($thread['status'] == 1) {
                    $fa_icon = "fa-check";
                    $fa_class = "thread-resolved";
                    $tooltip = "Thread Resolved";
                }
            }

            $categories_content = [];
            foreach ($thread["categories_desc"] as $category_desc) {
                $categories_content[] = [$category_desc];
            }
            for ($i = 0; $i < count($thread["categories_color"]); $i += 1) {
                $categories_content[$i][] = $thread["categories_color"][$i];
            }

            $date_content = ["not_null" => !is_null($date)];

            if (!is_null($date)) {
                $date_content["formatted"] = $date;
            }

            $thread_info = [
                'thread_id' => $thread['id'],
                "title" => $titleDisplay,
                "content" => $contentDisplay,
                "categories" => $categories_content,
                "link" => $link,
                "class" => $class,
                "pinned" => $thread["pinned"],
                "favorite" => $favorite,
                "merged_thread_id" => $thread['merged_thread_id'],
                "status" => $thread["status"],
                "fa_icon" => $fa_icon,
                "fa_class" => $fa_class,
                "tooltip" => $tooltip,
                "is_locked" => $this->core->getQueries()->isThreadLocked($thread['id']),
                "date" => $date_content,
                "current_user_posted" => $thread["current_user_posted"]
            ];

            if ($is_full_page) {
                $user_info = $this->core->getQueries()->getDisplayUserInfoFromUserId($first_post["author_user_id"]);
                $email = trim($user_info['user_email']);
                $first_name = trim($user_info["first_name"]);
                $last_name = trim($user_info["last_name"]);

                $author_info = [
                    "user_id" => $first_post['author_user_id'],
                    "name" => $first_post['anonymous'] ? "Anonymous" : $first_name . " " . substr($last_name, 0, 1) . ".",
                    "email" => $email,
                    "full_name" => $first_name . " " . $last_name . " (" . $first_post['author_user_id'] . ")",
                ];
                $thread_info = array_merge($thread_info, [
                    "post_id" => $first_post["id"],
                    "is_thread_locked" => $this->core->getQueries()->isThreadLocked($thread['id']),
                    "thread_resolve_state" => $this->core->getQueries()->getResolveState($thread['id'])[0]['status'],
                    "is_anon" => $first_post["anonymous"],
                    "render_markdown" => $first_post["render_markdown"],
                    "author_info" => $author_info,
                    "deleted" => $first_post['deleted']
                ]);
            }
//            var_dump($first_post);

            $thread_content[] = $thread_info;
        }

        $return = "";

        if ($render) {
            $return = $this->core->getOutput()->renderTwigTemplate("forum/displayThreadList.twig", [
                "thread_content" => $thread_content,
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
                if (filter_var($decoded_url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED) !== false && in_array($parsed_url, $accepted_schemes, true)) {
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

    public function createPost($thread_id, $post, $unviewed_posts, $first, $reply_level, $display_option, $includeReply, $render = false) {
        $current_user = $this->core->getUser()->getId();
        $post_id = $post["id"];
        $parent_id = $post["parent_id"];

        $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

        // Get formatted time stamps
        $date = DateUtils::convertTimeStamp($this->core->getUser(), $post['timestamp'], $this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));

        if (isset($post["edit_timestamp"])) {
            $edit_date = DateUtils::convertTimeStamp($this->core->getUser(), $post["edit_timestamp"], $this->core->getConfig()->getDateTimeFormat()->getFormat('forum'));
        }
        else {
            $edit_date = null;
        }

        $user_info = $this->core->getQueries()->getDisplayUserInfoFromUserId($post["author_user_id"]);
        $author_email = trim($user_info['user_email']);
        $first_name = trim($user_info["first_name"]);
        $last_name = trim($user_info["last_name"]);
        $visible_username = $first_name . " " . substr($last_name, 0, 1) . ".";
        $thread_resolve_state = $this->core->getQueries()->getResolveState($thread_id)[0]['status'];

        if ($display_option != 'tree') {
            $reply_level = 1;
        }

        if ($post["anonymous"]) {
            $visible_username = "Anonymous";
        }
        $classes = ["post_box"];
        if ($first && $display_option != 'alpha') {
            $classes[] = "first_post";
        }
        if (in_array($post_id, $unviewed_posts)) {
            if ($current_user != $post["author_user_id"]) {
                $classes[] = "new_post";
            }
        }
        else {
            $classes[] = "viewed_post";
        }
        if ($this->core->getQueries()->isStaffPost($post["author_user_id"])) {
            $classes[] = "important";
        }
        if ($post["deleted"]) {
            $classes[] = "deleted";
            $deleted = true;
        }
        else {
            $deleted = false;
        }

        $offset = min(($reply_level - 1) * 30, 180);

        $post_content = $post['content'];
        $markdown = $post["render_markdown"];

        $isThreadLocked = $this->core->getQueries()->isThreadLocked($thread_id);
        $userAccessFullGrading = $this->core->getUser()->accessFullGrading();
        $userGroup = $this->core->getUser()->getGroup();

        $post_user_info = [];

        $merged_thread = false;
        if ($this->core->getUser()->getGroup() <= 2) {
            $info_name = $first_name . " " . $last_name . " (" . $post['author_user_id'] . ")";
            $visible_user_json = json_encode($visible_username);
            $info_name = json_encode($info_name);
            $jscriptAnonFix = $post['anonymous'] ? 'true' : 'false';
            $jscriptAnonFix = json_encode($jscriptAnonFix);

            $post_user_info = [
                "info_name" => $info_name,
                "visible_user_json" => $visible_user_json,
                "jscriptAnonFix" => $jscriptAnonFix
            ];
        }

        $post_button = [];

        if ($this->core->getUser()->getGroup() <= 3 || $post['author_user_id'] === $current_user) {
            if (!($this->core->getQueries()->isThreadLocked($thread_id) != 1 || $this->core->getUser()->accessFullGrading())) {
            }
            else {
                if ($deleted && $this->core->getUser()->getGroup() <= 3) {
                    $ud_toggle_status = "false";
                    $ud_button_title = "Undelete post";
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

                if ($this->core->getUser()->accessGrading()) {
                    $merged_thread_query = $this->core->getQueries()->getPostOldThread($post_id);
                    if ($merged_thread_query["merged_thread_id"] != -1) {
                        $merged_thread = true;
                    }
                }

                $shouldEditThread = null;

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
        }

        $post_attachment = ["exist" => false];

        if ($post["has_attachment"]) {
            $post_attachment["exist"] = true;

            $post_dir = FileUtils::joinPaths($thread_dir, $post["id"]);
            $files = FileUtils::getAllFiles($post_dir);

            $post_attachment["files"] = [];

            $attachment_num_files = count($files);
            $attachment_id = "attachments_{$post['id']}";
            $attachment_button_id = "button_attachments_{$post['id']}";
            $attachment_file_count = 0;
            $attachment_encoded_data = [];

            foreach ($files as $file) {
                $path = rawurlencode($file['path']);
                $name = rawurlencode($file['name']);
                $url = $this->core->buildCourseUrl(['display_file']) . '?dir=forum_attachments&path=' . $path;

                $post_attachment["files"][] = [
                    "file_viewer_id" => "file_viewer_" . $post_id . "_" . $attachment_file_count
                ];

                $attachment_encoded_data[] = [$url, $post_id . '_' . $attachment_file_count, $name];

                $attachment_file_count++;
                $GLOBALS['totalAttachments']++;
            }

            $attachment_encoded_data[] = $attachment_id;

            $post_attachment["params"] = [
                "well_id"   => $attachment_id,
                "button_id" => $attachment_button_id,
                "num_files" => $attachment_num_files,
                "encoded_data" => json_encode($attachment_encoded_data)
            ];
        }

        $post_box_id = 1;
        if ($this->core->getQueries()->isThreadLocked($thread_id) != 1 || $this->core->getUser()->accessFullGrading()) {
            $GLOBALS['post_box_id'] = $post_box_id = isset($GLOBALS['post_box_id']) ? $GLOBALS['post_box_id'] + 1 : 1;
        }

        $has_history = $this->core->getQueries()->postHasHistory($post_id);

        $created_post = [
            "classes" => $classes,
            "post_id" => $post_id,
            "reply_level" => $reply_level,
            "offset" => $offset,
            "first" => $first,
            "post_content" => $post_content,
            "post" => $post,
            "display_option" => $display_option,
            "isThreadLocked" => $isThreadLocked,
            "userAccessFullGrading" => $userAccessFullGrading,
            "userGroup" => $userGroup,
            "includeReply" => $includeReply,
            "thread_resolve_state" => $thread_resolve_state,
            "current_user" => $current_user,
            "author_email" => $author_email,
            "post_user_info" => $post_user_info,
            "post_date" => $date,
            "edit_date" => $edit_date,
            "post_buttons" => $post_button,
            "visible_username" => $visible_username,
            "post_attachment" => $post_attachment,
            "form_post_url" => $this->core->buildCourseUrl(['forum', 'posts', 'new']),
            "post_box_id" => $post_box_id,
            "thread_id" => $thread_id,
            "parent_id" => $parent_id,
            "render_markdown" => $markdown,
            "has_history" => $has_history,
            "thread_previously_merged" => $merged_thread
        ];

        if ($render) {
            if ($first) {
                $thread_title = $this->core->getQueries()->getThreadTitle($thread_id);
                $activeThreadTitle = ($this->core->getUser()->accessFullGrading() ? "({$thread_id}) " : '') . $thread_title;
                $created_post['activeThreadTitle'] = $activeThreadTitle;
            }
            $created_post['csrf_token'] = $this->core->getCsrfToken();
            return $this->core->getOutput()->renderTwigTemplate("forum/CreatePost.twig", $created_post);
        }
        else {
            return $created_post;
        }
    }

    public function createThread($category_colors) {
        if (!$this->forumAccess()) {
            $this->core->redirect($this->core->buildCourseUrl());
            return;
        }

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

        $categories = "";

        $category_colors;

        $categories = $this->core->getQueries()->getCategories();
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
            "search_url" => $this->core->buildCourseUrl(['forum', 'search'])
        ]);
    }

    public function showCategories($category_colors) {

        if (!$this->forumAccess()) {
            $this->core->redirect($this->core->buildCourseUrl([]));
            return;
        }

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
        $category_colors;

        if ($this->core->getUser()->accessGrading()) {
            $categories = $this->core->getQueries()->getCategories();
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
        if (!$this->forumAccess()) {
            $this->core->redirect($this->core->buildCourseUrl());
            return;
        }

        if (!$this->core->getUser()->accessFullGrading()) {
            $this->core->redirect($this->core->buildCourseUrl(['forum', 'threads']));
            return;
        }

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
            $first_name = $details["first_name"];
            $last_name = $details["last_name"];
            $post_count = count($details["posts"]);
            $posts = json_encode($details["posts"]);
            $ids = json_encode($details["id"]);
            $timestamps = json_encode($details["timestamps"]);
            $thread_ids = json_encode($details["thread_id"]);
            $thread_titles = json_encode($details["thread_title"]);
            $num_deleted = ($details["num_deleted_posts"]);

            $userData[] = [
                "last_name" => $last_name,
                "first_name" => $first_name,
                "post_count" => $post_count,
                "details_total_threads" => $details["total_threads"],
                "num_deleted" => $num_deleted,
                "posts" => $posts,
                "ids" => $ids,
                "timestamps" => $timestamps,
                "thread_ids" => $thread_ids,
                "thread_titles" => $thread_titles
            ];
        }

        return $this->core->getOutput()->renderTwigTemplate("forum/StatPage.twig", [
            "forumBarData" => $forumBarData,
            "userData" => $userData,
            "search_url" => $this->core->buildCourseUrl(['forum', 'search'])
        ]);
    }
}
