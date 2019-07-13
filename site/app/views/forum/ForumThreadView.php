<?php
namespace app\views\forum;

use app\libraries\DateUtils;
use app\views\AbstractView;
use app\libraries\FileUtils;


class ForumThreadView extends AbstractView {

	public function forumAccess(){
        return $this->core->getConfig()->isForumEnabled();
    }

    public function searchResult($threads){

    	$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
    	$this->core->getOutput()->addBreadcrumb("Search");

		$buttons = array(
			array(
			"required_rank" => 4,
			"display_text" => 'Create Thread',
			"style" => 'position:relative;float:right;top:3px;',
			"link" => array(true, $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))),
			"optional_class" => '',
			"title" => 'Create Thread',
			"onclick" => array(false)
			),
			array(
				"required_rank" => 4,
				"display_text" => 'Back to Threads',
				"style" => 'position:relative;float:right;top:3px;margin-right:5px;',
				"link" => array(true, $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))),
				"optional_class" => '',
				"title" => 'Back to threads',
				"onclick" => array(false)
			)
		);

		$threadArray = array();
		$fromIdtoTitle = array();
		foreach($threads as $thread){
			if(!array_key_exists($thread["thread_id"], $threadArray)) {
				$threadArray[$thread["thread_id"]] = array();
				$fromIdtoTitle[$thread["thread_id"]] = $thread["thread_title"];
			}
			$threadArray[$thread["thread_id"]][] = $thread;
		}
		$count = 1;

		$thread_list = [];

		foreach($threadArray as $thread_id => $data){
			$thread_title = $fromIdtoTitle[$thread_id];

            $thread_link = $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id));

            $thread_list[$count-1] = Array("thread_title" => $thread_title, "thread_link" => $thread_link, "posts" => Array());

			foreach($data as $post) {
				$author = $post['author'];
				$user_info = $this->core->getQueries()->getDisplayUserInfoFromUserId($post["p_author"]);
				$first_name = trim($user_info["first_name"]);
				$last_name = trim($user_info["last_name"]);
				$visible_username = $first_name . " " . substr($last_name, 0 , 1) . ".";

				if($post["anonymous"]){
					$visible_username = 'Anonymous';
				} 

				//convert legacy htmlentities being saved in db
                $post_content = html_entity_decode($post["post_content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $pre_post = preg_replace('#(<a href=[\'"])(.*?)([\'"].*>)(.*?)(</a>)#', '[url=$2]$4[/url]', $post_content);

                if(!empty($pre_post)){
                    $post_content = $pre_post;
				}

                $post_link = $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)) . "#" . $post['p_id'];

				$posted_on = date_format(DateUtils::parseDateTime($post['timestamp_post'], $this->core->getConfig()->getTimezone()), "n/j g:i A");

                $thread_list[$count-1]["posts"][] = Array(
                    "post_link" => $post_link,
                    "count" => $count,
                    "post_content" => $post_content,
                    "visible_username" => $visible_username,
                    "posted_on" => $posted_on
                );

				$count++;
			}
		}


        $return = $this->core->getOutput()->renderTwigTemplate("forum/searchResults.twig", [
            "buttons" => $buttons,
            "count_threads" => count($threads),
            "threads" => $thread_list
        ]);

    	return $return;
    }
	
	/** Shows Forums thread splash page, including all posts
		for a specific thread, in addition to head of the threads
		that have been created after applying filter and to be
		displayed in the left panel.
	*/
    public function showForumThreads($user, $posts, $unviewed_posts, $threadsHead, $show_deleted, $show_merged_thread, $display_option, $max_thread, $initialPageNumber, $ajax=false) {
        if(!$this->forumAccess()){
            $this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
            return;
        }
        $threadExists = $this->core->getQueries()->threadExists();
        $filteredThreadExists = (count($threadsHead)>0);
        $currentThread = -1;
        $currentCategoriesIds = array();
        $show_deleted_thread_title = null;
        $currentCourse = $this->core->getConfig()->getCourse();
        $threadFiltering = $threadExists && !$filteredThreadExists && !(empty($_COOKIE[$currentCourse . '_forum_categories']) && empty($_COOKIE['forum_thread_status']) && empty($_COOKIE['unread_select_value']) === 'false');

        if(!$ajax) {
            $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));

            //Body Style is necessary to make sure that the forum is still readable...
            $this->core->getOutput()->addVendorCss('codemirror/codemirror.css');
            $this->core->getOutput()->addVendorCss('codemirror/theme/eclipse.css');
            $this->core->getOutput()->addInternalCss('forum.css');
            $this->core->getOutput()->addVendorJs('codemirror/codemirror.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/clike/clike.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/python/python.js');
            $this->core->getOutput()->addVendorJs('codemirror/mode/shell/shell.js');
            $this->core->getOutput()->addInternalJs('drag-and-drop.js');
            $this->core->getOutput()->addInternalJs('forum.js');
            $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');
            $this->core->getOutput()->addVendorJs('bootstrap/js/bootstrap.bundle.min.js');
        }

        if($filteredThreadExists || $threadFiltering) {
            $currentThread = isset($_GET["thread_id"]) && is_numeric($_GET["thread_id"]) && (int)$_GET["thread_id"] < $max_thread && (int)$_GET["thread_id"] > 0 ? (int)$_GET["thread_id"] : $posts[0]["thread_id"];
            $currentCategoriesIds = $this->core->getQueries()->getCategoriesIdForThread($currentThread);
        }

        $currentThreadArr = array_filter($threadsHead, function($ar) use($currentThread) {
            return ($ar['id'] == $currentThread);
        });

        if($show_merged_thread) {
            $show_merged_thread_class = "active";
            $show_merged_thread_action = "alterShowMergeThreadStatus(0,'" . $currentCourse . "');";
            $show_merged_thread_title = "Hide Merged Threads";
        } else {
            $show_merged_thread_class = "";
            $show_merged_thread_action = "alterShowMergeThreadStatus(1,'" . $currentCourse . "');";
            $show_merged_thread_title = "Show Merged Threads";
        }

        $show_deleted_class = '';
        $show_deleted_action = '';
        $show_deleted_thread_title = '';

        if($this->core->getUser()->accessGrading()){
            if($show_deleted) {
                $show_deleted_class = "active";
                $show_deleted_action = "alterShowDeletedStatus(0);";
                $show_deleted_thread_title = "Hide Deleted Threads";
            } else {
                $show_deleted_class = "";
                $show_deleted_action = "alterShowDeletedStatus(1);";
                $show_deleted_thread_title = "Show Deleted Threads";
            }
        }

        $categories = $this->core->getQueries()->getCategories();

        $cookieSelectedCategories = array();
        $cookieSelectedThreadStatus = array();
        $cookieSelectedUnread = false;
        $category_ids_array = array_column($categories, 'category_id');

        if(!empty($_COOKIE[$currentCourse . '_forum_categories'])) {
            foreach(explode('|', $_COOKIE[$currentCourse . '_forum_categories']) as $selectedId) {
                if(in_array((int)$selectedId, $category_ids_array)) {
                    $cookieSelectedCategories[] = $selectedId;
                }
            }
        }

        if(!empty($_COOKIE['forum_thread_status'])) {
            foreach(explode('|', $_COOKIE['forum_thread_status']) as $selectedStatus) {
                if(in_array((int)$selectedStatus, array(-1,0,1))) {
                    $cookieSelectedThreadStatus[] = $selectedStatus;
                }
            }
        }

        if(!empty($_COOKIE['unread_select_value'])){
            $cookieSelectedUnread = $_COOKIE['unread_select_value'];
        }

        $default_button = array(
            array(
                "required_rank" => 4,
                "display_text" => 'Create Thread',
                "style" => 'float:right;position:relative;top:3px;',
                "link" => array(true, $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))),
                "optional_class" => '',
                "title" => 'Create Thread',
                "onclick" => array(false)
            )
        );

        $button_params = [
            "current_thread" => $currentThread,
            "forum_bar_buttons_right" => $default_button,
            "forum_bar_buttons_left" => [],
            "show_threads" => true,
            "thread_exists" => true,
            "show_more" => true
        ];

        if($this->core->getUser()->accessGrading()){
            if($show_deleted) {
                $show_deleted_class = "active";
                $show_deleted_action = "alterShowDeletedStatus(0);";
                $show_deleted_thread_title = "Hide Deleted Threads";
            } else {
                $show_deleted_class = "";
                $show_deleted_action = "alterShowDeletedStatus(1);";
                $show_deleted_thread_title = "Show Deleted Threads";
            }
        }

        $filterFormData = Array(
            "categories" => $categories,
            "current_thread" => $currentThread,
            "current_category_ids" => $currentCategoriesIds,
            "current_course" => $currentCourse,
            "cookie_selected_categories" => $cookieSelectedCategories,
            "cookie_selected_thread_status" => $cookieSelectedThreadStatus,
            "cookie_selected_unread_value" => $cookieSelectedUnread,
            "display_option" => $display_option,
            "thread_exists" => $threadExists,
            "display_option" => $display_option
        );

        $next_page = 0;
        $prev_page = 0;
        $arrowup_visibility = 0;
        $displayThreadContent = "";
        $generatePostContent = "";

        if(!$threadExists){
            $button_params["show_threads"] = false;
            $button_params["thread_exists"] = false;
        } else {
            $more_data = array(
                array(
                    "filter_option" => $display_option
                ),
                array(
                    "display_text" => $show_merged_thread_title,
                    "id" => 'merge_thread',
                    "optional_class" => array(!empty($show_merged_thread_class), $show_merged_thread_class),
                    "title" => $show_merged_thread_title,
                    "onclick" => array(true, $show_merged_thread_action),
                    "link" => '#',
                    "required_rank" => 4
                ),
                array(
                    "display_text" => $show_deleted_thread_title,
                    "optional_class" => array(!empty($show_deleted_class), $show_deleted_class),
                    "id" => 'delete',
                    "title" => $show_deleted_thread_title,
                    "link" => '#',
                    "onclick" => array(true, $show_deleted_action),
                    "required_rank" => 3
                ),
                array(
                    "display_text" => 'Stats',
                    "id" => 'forum_stats',
                    "optional_class" => array(false, ''),
                    "title" => 'Forum Statistics',
                    "onclick" => array(false, ''),
                    "link" => $this->core->buildUrl(['component' => 'forum', 'page' => 'show_stats']),
                    "required_rank" => 2
                )
            );
            $other_buttons = array(
                array(
                    "required_rank" => 4,
                    "display_text" => 'Filter',
                    "style" => 'display:inline-block;',
                    "link" => array(false),
                    "optional_class" => '',
                    "title" => 'Filter Threads based on Categories',
                    "onclick" => array(true, "$('#category_wrapper').css('display','block');")
                )
            );

            $button_params["more_data"] = $more_data;
            $button_params["forum_bar_buttons_left"] = $other_buttons;
            $next_page = $initialPageNumber + 1;
            $prev_page = ($initialPageNumber == 1)?0:($initialPageNumber - 1);
            $arrowup_visibility = ($initialPageNumber == 1)?"display:none;":"";
            $activeThreadAnnouncement = false;
            $activeThreadTitle = "";
            $activeThread = array();
            $displayThreadContent = $this->displayThreadList($threadsHead, false, $activeThreadAnnouncement, $activeThreadTitle, $activeThread, $currentThread, $currentCategoriesIds, false);

            if(count($activeThread) == 0) {
                $activeThread = $this->core->getQueries()->getThread($currentThread)[0];
            }

            $currentThreadArrValues = array_values($currentThreadArr);
            $currentThreadFavorite = !empty($currentThreadArrValues) ? $currentThreadArrValues[0]['favorite'] : false;
            $generatePostContent = $this->generatePostList($currentThread, $posts, $unviewed_posts, $currentCourse, true, $threadExists, $display_option, $categories, $cookieSelectedCategories, $cookieSelectedThreadStatus, $cookieSelectedUnread, $currentCategoriesIds, $currentThreadFavorite, false);
        }

        if ( !empty($activeThread['id']) ) {
            $this->core->getQueries()->visitThread($user, $activeThread['id']);
        }

        $return = "";

        if(!$ajax) {
            $return = $this->core->getOutput()->renderTwigTemplate("forum/ShowForumThreads.twig", [
                "categories" => $categories,
                "filterFormData" => $filterFormData,
                "button_params" => $button_params,
                "thread_exists" => $threadExists,
                "next_page" => $next_page,
                "prev_page" => $prev_page,
                "arrowup_visibility" => $arrowup_visibility,
                "display_thread_content" => $displayThreadContent,
                "currentThread" => $currentThread,
                "currentCourse" => $currentCourse,
                "generate_post_content" => $generatePostContent,
                "display_option" => $display_option,
                "csrf_token" => $this->core->getCsrfToken()
            ]);
        }
        else{
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
                "merge_thread_content" => $generatePostContent["merge_thread_content"],
                "csrf_token" => $generatePostContent["csrf_token"],
                "activeThreadTitle" => $generatePostContent["activeThreadTitle"],
                "post_box_id" => $generatePostContent["post_box_id"]
            ]);

            $return = $this->core->getOutput()->renderJsonSuccess(["html"=> json_encode($return)]);
        }

        return $return;
    }

    public function generatePostList($currentThread, $posts, $unviewed_posts, $currentCourse, $includeReply = false, $threadExists = false, $display_option = 'time', $categories = [], $cookieSelectedCategories = [], $cookieSelectedThreadStatus = [], $cookieSelectedUnread = [], $currentCategoriesIds = [], $isCurrentFavorite = false, $render=true) {

        $activeThread = $this->core->getQueries()->getThread($currentThread)[0];

        $activeThreadTitle = ($this->core->getUser()->accessFullGrading() ? "({$activeThread['id']}) " : '') . $activeThread['title'];
        $activeThreadAnnouncement = $activeThread['pinned'];

        $thread_id = $activeThread['id'];
        $function_date = 'date_format';

        $first = true;
        $first_post_id = 1;

        $post_data = [];

        $csrf_token = $this->core->getCsrfToken();

        if($display_option == "tree"){
            $order_array = array();
            $reply_level_array = array();
            foreach($posts as $post){
                if($thread_id == -1) {
                    $thread_id = $post["thread_id"];
                }
                if($first){
                    $first= false;
                    $first_post_id = $post["id"];
                }
                if($post["parent_id"] > $first_post_id){
                    $place = array_search($post["parent_id"], $order_array);
                    $tmp_array = array($post["id"]);
                    $parent_reply_level = $reply_level_array[$place];
                    while($place && $place+1 < sizeof($reply_level_array) && $reply_level_array[$place+1] > $parent_reply_level){
                        $place++;
                    }
                    array_splice($order_array, $place+1, 0, $tmp_array);
                    array_splice($reply_level_array, $place+1, 0, $parent_reply_level+1);
                } else {
                    array_push($order_array, $post["id"]);
                    array_push($reply_level_array, 1);
                }
            }
            $i = 0;
            $first = true;
            foreach($order_array as $ordered_post){
                foreach($posts as $post){
                    if($post["id"] == $ordered_post){
                        if($post["parent_id"] == $first_post_id) {
                            $reply_level = 1;
                        } else {
                            $reply_level = $reply_level_array[$i];
                        }

                        $post_data[] = $this->createPost($thread_id, $post, $unviewed_posts, $function_date, $first, $reply_level, $display_option, $includeReply);

                        break;
                    }
                }
                if($first){
                    $first= false;
                }
                $i++;
            }
        } else {
            foreach($posts as $post){
                if($thread_id == -1) {
                    $thread_id = $post["thread_id"];
                }

                $first_post_id = $this->core->getQueries()->getFirstPostForThread($thread_id)['id'];

                $post_data[] = $this->createPost($thread_id, $post, $unviewed_posts, $function_date, $first, 1, $display_option, $includeReply);

                if($first){
                    $first= false;
                }
            }
        }

        $isThreadLocked = $this->core->getQueries()->isThreadLocked($thread_id);
        $accessFullGrading = $this->core->getUser()->accessFullGrading();

        $post_box_id = 0;

        $form_action_link = $this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'));

        if(($isThreadLocked != 1 || $accessFullGrading ) && $includeReply  ) {

            $GLOBALS['post_box_id'] = $post_box_id = isset($GLOBALS['post_box_id']) ? $GLOBALS['post_box_id'] + 1 : 1;

        }

        $merge_thread_content = [];

        if($this->core->getUser()->getGroup() <= 3){
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('chosen-js', 'chosen.min.css'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('chosen-js', 'chosen.jquery.min.js'));
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'flatpickr.min.css'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'flatpickr.min.js'));
            $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
            $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));
            $this->core->getOutput()->addInternalJs('forum.js');
            $this->core->getOutput()->addInternalCss('forum.css');
            $current_thread_first_post = $this->core->getQueries()->getFirstPostForThread($currentThread);
            $current_thread_date = $current_thread_first_post["timestamp"];
            $merge_thread_list = $this->core->getQueries()->getThreadsBefore($current_thread_date, 1);

            $merge_thread_content = [
                "current_thread_date" => $current_thread_date,
                "current_thread" => $currentThread,
                "possibleMerges" => $merge_thread_list
            ];
        }

        $return = "";

        if($render){
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
                "post_box_id" => $post_box_id
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
                "post_box_id" => $post_box_id
            ];
        }

        return $return;
    }

	public function showAlteredDisplayList($threads, $filtering, $thread_id, $categories_ids){
		$tempArray = array();
		$threadAnnouncement = false;
		$activeThreadTitle = "";
		return $this->displayThreadList($threads, $filtering, $threadAnnouncement, $activeThreadTitle, $tempArray, $thread_id, $categories_ids, true);
	}

    public function displayThreadList($threads, $filtering, &$activeThreadAnnouncement, &$activeThreadTitle, &$activeThread, $thread_id_p, $current_categories_ids, $render)
    {
        $used_active = false; //used for the first one if there is not thread_id set
        $current_user = $this->core->getUser()->getId();
        $display_thread_ids = $this->core->getUser()->getGroup() <= 2;

        $activeThreadAnnouncement = false;
        $activeThreadTitle = "";
        $function_date = 'date_format';
        $activeThread = [];

        $thread_content = [];

        foreach ($threads as $thread) {
            $first_post = $this->core->getQueries()->getFirstPostForThread($thread["id"]);
            if (is_null($first_post)) {
                // Thread without any posts(eg. Merged Thread)
                $first_post = ['content' => ""];
                $date = null;
            } else {
                $date = DateUtils::parseDateTime($first_post['timestamp'], $this->core->getConfig()->getTimezone());
            }
            if ($thread['merged_thread_id'] != -1) {
                // For the merged threads
                $thread['status'] = 0;
            }

            $class = "thread_box";
            // $current_categories_ids should be subset of $thread["categories_ids"]
            $issubset = (count(array_intersect($current_categories_ids, $thread["categories_ids"])) == count($current_categories_ids));
            if (((isset($_REQUEST["thread_id"]) && $_REQUEST["thread_id"] == $thread["id"]) || $thread_id_p == $thread["id"] || $thread_id_p == -1) && !$used_active && $issubset) {
                $class .= " active";
                $used_active = true;
                $activeThreadTitle = ($display_thread_ids ? "({$thread['id']}) " : '') . $thread["title"];
                $activeThread = $thread;
                if ($thread["pinned"])
                    $activeThreadAnnouncement = true;
                if ($thread_id_p == -1)
                    $thread_id_p = $thread["id"];
            }
            if (!$this->core->getQueries()->viewedThread($current_user, $thread["id"])) {
                $class .= " new_thread";
            }
            if ($thread["deleted"]) {
                $class .= " deleted";
            }
            //fix legacy code
            $titleDisplay = $thread['title'];


            //replace tags from displaying in sidebar
            $first_post_content = str_replace("[/code]", "", str_replace("[code]", "", strip_tags($first_post["content"])));
            $temp_first_post_content = preg_replace('#\[url=(.*?)\](.*?)(\[/url\])#', '$2', $first_post_content);

            if (!empty($temp_first_post_content)) {
                $first_post_content = $temp_first_post_content;
            }

            $sizeOfContent = strlen($first_post_content);
            $contentDisplay = substr($first_post_content, 0, ($sizeOfContent < 80) ? $sizeOfContent : strrpos(substr($first_post_content, 0, 80), " "));
            $titleLength = strlen($thread['title']);

            $titleDisplay = substr($titleDisplay, 0, ($titleLength < 40) ? $titleLength : strrpos(substr($titleDisplay, 0, 40), " "));

            if (strlen($first_post["content"]) > 80) {
                $contentDisplay .= "...";
            }
            if (strlen($thread["title"]) > 40) {
                //Fix ... appearing
                if (empty($titleDisplay))
                    $titleDisplay .= substr($thread['title'], 0, 30);
                $titleDisplay .= "...";
            }
            $titleDisplay = ($display_thread_ids ? "({$thread['id']}) " : '') . $titleDisplay;

            $link = $this->core->buildUrl(['component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread['id']]);

            $favorite = isset($thread['favorite']) && $thread['favorite'];

            $fa_icon = "fa-question";
            $fa_color = "#ffcc00";
            $fa_margin_right = "5px";
            $fa_font_size = "1.8em";
            $tooltip = "Thread Unresolved";

            if (!isset($thread['status'])) {
                $thread['status'] = 0;
            }
            if ($thread['status'] != 0) {
                if ($thread['status'] == 1) {
                    $fa_icon = "fa-check";
                    $fa_color = "#5cb85c";
                    $fa_margin_right = "0px";
                    $fa_font_size = "1.5em";
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
                $date_content["formatted"] = $function_date($date, "n/j g:i A");
            }

            $thread_content[] = [
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
                "fa_color" => $fa_color,
                "fa_margin_right" => $fa_margin_right,
                "fa_font_size" => $fa_font_size,
                "tooltip" => $tooltip,
                "is_locked" => $this->core->getQueries()->isThreadLocked($thread['id']),
                "date" => $date_content,
                "current_user_posted" => $thread["current_user_posted"]
            ];
        }

        $return = "";

        if($render) {
            $return = $this->core->getOutput()->renderTwigTemplate("forum/displayThreadList.twig", [
                "thread_content" => $thread_content,
            ]);
        }
        else{
            $return = [
                "thread_content" => $thread_content,
            ];

        }
        
        return $return;
    }

	public function filter_post_content($original_post_content) {
		$post_content = html_entity_decode($original_post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$pre_post = preg_replace('#(<a href=[\'"])(.*?)([\'"].*>)(.*?)(</a>)#', '[url=$2]$4[/url]', $post_content);

		if(!empty($pre_post)){
			$post_content = $pre_post;
		}

		preg_match_all('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', $post_content, $result);
		$accepted_schemes = array("https", "http");
		$pos = 0;
		if(count($result) > 0) {
			foreach($result[1] as $url){
				$decoded_url = filter_var(trim(strip_tags(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'))), FILTER_SANITIZE_URL);
				$parsed_url = parse_url($decoded_url, PHP_URL_SCHEME);
				if(filter_var($decoded_url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED) !== false && in_array($parsed_url, $accepted_schemes, true)){
					$pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', '<a href="' . htmlspecialchars($decoded_url, ENT_QUOTES) . '" target="_blank" rel="noopener nofollow">'. $result[2][$pos] .'</a>', $post_content, 1);
				} else {
					$pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', htmlentities(htmlspecialchars($decoded_url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $post_content, 1);
				}
				if(!empty($pre_post)){
					$post_content = $pre_post;
				}

				$pos++;
			}
		}
		//This code is for legacy posts that had an extra \r per newline
		if(strpos($original_post_content, "\r") !== false){
			$post_content = str_replace("\r","", $post_content);
		}

		//end link handling

		//handle converting code segments
		$post_content = preg_replace('/&lbrack;code&rsqb;(.*?)&lbrack;&sol;code&rsqb;/', '<textarea class="code">$1</textarea>', $post_content);

		return $post_content;
	}

	public function createPost($thread_id, $post, $unviewed_posts, $function_date, $first, $reply_level, $display_option, $includeReply)
    {
        $current_user = $this->core->getUser()->getId();
        $post_id = $post["id"];

        $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

        $date = DateUtils::parseDateTime($post["timestamp"], $this->core->getConfig()->getTimezone());
        if (!is_null($post["edit_timestamp"])) {
            $edit_date = $function_date(DateUtils::parseDateTime($post["edit_timestamp"], $this->core->getConfig()->getTimezone()), "n/j g:i A");
        } else {
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
            $classes[] = " first_post";
        }
        if (in_array($post_id, $unviewed_posts)) {
            $classes[] = " new_post";
        } else {
            $classes[] = " viewed_post";
        }
        if ($this->core->getQueries()->isStaffPost($post["author_user_id"])) {
            $classes[] = " important";
        }
        if ($post["deleted"]) {
            $classes[] = " deleted";
            $deleted = true;
        } else {
            $deleted = false;
        }

        $offset = min(($reply_level - 1) * 30, 180);

        $post_content = $post['content'];

        $isThreadLocked = $this->core->getQueries()->isThreadLocked($thread_id);
        $userAccessFullGrading = $this->core->getUser()->accessFullGrading();
        $userGroup = $this->core->getUser()->getGroup();

        $post_user_info = [];


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

		if($this->core->getUser()->getGroup() <= 3 || $post['author_user_id'] === $current_user) {
			if(!($this->core->getQueries()->isThreadLocked($thread_id) != 1 || $this->core->getUser()->accessFullGrading() )){

			} else {
				if($deleted && $this->core->getUser()->getGroup() <= 3){
					$ud_toggle_status = "false";
					$ud_button_title = "Undelete post";
					$ud_button_icon = "fa-undo";
				} else {
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

				$shouldEditThread = null;

				if($first) {
					$shouldEditThread = "true";
					$edit_button_title = "Edit thread and post";
				} else {
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

            foreach ($files as $file) {
                $path = rawurlencode($file['path']);
                $name = rawurlencode($file['name']);
                $name_display = rawurldecode($file['name']);

                $post_attachment["files"][] = [
                    "path" => $path,
                    "name" => $name,
                    "name_display" => $name_display
                ];
            }
        }

        $post_box_id = 1;
        if ($this->core->getQueries()->isThreadLocked($thread_id) != 1 || $this->core->getUser()->accessFullGrading()) {
            $GLOBALS['post_box_id'] = $post_box_id = isset($GLOBALS['post_box_id']) ? $GLOBALS['post_box_id'] + 1 : 1;
        }

        $return = [
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
            "post_date" => $function_date($date,'n/j g:i A'),
            "edit_date" => $edit_date,
            "post_buttons" => $post_button,
            "visible_username" => $visible_username,
            "post_attachment" => $post_attachment,
            "form_post_url" => $this->core->buildUrl(['component' => 'forum', 'page' => 'publish_post']),
            "post_box_id" => $post_box_id,
            "thread_id" => $thread_id,
            "parent_id" => $post_id,
        ];

		return $return;
	}

    public function createThread($category_colors){
		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildNewCourseUrl());
			return;
		}

        $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
        $this->core->getOutput()->addBreadcrumb("Create Thread", $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));

        $this->core->getOutput()->addInternalJs('drag-and-drop.js');
        $this->core->getOutput()->addVendorJs('flatpickr/flatpickr.js');
        $this->core->getOutput()->addVendorJs(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'shortcut-buttons-flatpickr.min.js'));
        $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');
        $this->core->getOutput()->addVendorCss('flatpickr/flatpickr.min.css');
        $this->core->getOutput()->addVendorCss(FileUtils::joinPaths('flatpickr', 'plugins', 'shortcutButtons', 'themes', 'light.min.css'));

        $this->core->getOutput()->addInternalJs('forum.js');
        $this->core->getOutput()->addInternalCss('forum.css');

        $categories = "";

        $category_colors;

        $categories = $this->core->getQueries()->getCategories();

        $dummy_category = array('color' => '#000000', 'category_desc' => 'dummy', 'category_id' => "dummy");
        array_unshift($categories, $dummy_category);


        $buttons = array(
            array(
                "required_rank" => 4,
                "display_text" => 'Back to Threads',
                "style" => 'position:relative;top:3px;float:right;',
                "link" => array(true, $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))),
                "optional_class" => '',
                "title" => 'Back to threads',
                "onclick" => array(false)
            )
        );

        $thread_exists = $this->core->getQueries()->threadExists();
        $manage_categories_url = $this->core->buildUrl(array('component' => 'forum', 'page' => 'show_categories'));

        $return = $this->core->getOutput()->renderTwigTemplate("forum/createThread.twig", [
            "categories" => $categories,
            "category_colors" => $category_colors,
            "buttons" => $buttons,
            "thread_exists" => $thread_exists,
            "form_action" => $this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_thread')),
            "manage_categories_url" => $manage_categories_url,
            "csrf_token" => $this->core->getCsrfToken(),
            "email_enabled" => $this->core->getConfig()->isEmailEnabled()
        ]);

        return $return;
    }

    public function showCategories($category_colors){

        if(!$this->forumAccess()){
            $this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
            return;
        }

        $this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
        $this->core->getOutput()->addBreadcrumb("Manage Categories", $this->core->buildUrl(array('component' => 'forum', 'page' => 'show_categories')));

        $this->core->getOutput()->addInternalJs('drag-and-drop.js');
        $this->core->getOutput()->addVendorJs('flatpickr/flatpickr.js');
        $this->core->getOutput()->addVendorJs('jquery.are-you-sure/jquery.are-you-sure.js');

        $this->core->getOutput()->addVendorCss('flatpickr/flatpickr.min.css');

        $categories = "";
        $category_colors;

        if($this->core->getUser()->accessGrading()){
            $categories = $this->core->getQueries()->getCategories();

            $dummy_category = array('color' => '#000000', 'category_desc' => 'dummy', 'category_id' => "dummy");
            array_unshift($categories, $dummy_category);
        }

        $buttons = array(
            array(
                "required_rank" => 4,
                "display_text" => 'Back to Threads',
                "style" => 'position:relative;float:right;top:3px;',
                "link" => array(true, $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))),
                "optional_class" => '',
                "title" => 'Back to threads',
                "onclick" => array(false)
            )
        );
        $thread_exists = $this->core->getQueries()->threadExists();

        $forumBarData = [
            "forum_bar_buttons_right" => $buttons,
            "forum_bar_buttons_left" => [],
            "show_threads" => false,
            "thread_exists" => $thread_exists
        ];

        $return = $this->core->getOutput()->renderTwigTemplate("forum/ShowCategories.twig", [
            "categories" => $categories,
            "category_colors" => $category_colors,
            "forumBarData" => $forumBarData,
            "csrf_token" => $this->core->getCsrfToken()
        ]);

        return $return;
    }

	public function statPage($users) {
		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildNewCourseUrl());
			return;
		}

		if(!$this->core->getUser()->accessFullGrading()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
			return;
		}

		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$this->core->getOutput()->addBreadcrumb("Statistics", $this->core->buildUrl(array('component' => 'forum', 'page' => 'show_stats')));

        $this->core->getOutput()->addInternalJs('forum.js');
        $this->core->getOutput()->addInternalCss('forum.css');

		$buttons = array(
			array(
				"required_rank" => 4,
				"display_text" => 'Back to Threads',
				"style" => 'position:relative;float:right;top:3px;',
				"link" => array(true, $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))),
				"optional_class" => '',
				"title" => 'Back to threads',
				"onclick" => array(false)
			)
		);

		$thread_exists = $this->core->getQueries()->threadExists();

		$forumBarData = [
            "forum_bar_buttons_right" => $buttons,
            "forum_bar_buttons_left" => [],
            "show_threads" => false,
            "thread_exists" => $thread_exists
		];

		$userData = [];

		foreach($users as $user => $details){
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
    
    $return = $this->core->getOutput()->renderTwigTemplate("forum/StatPage.twig", [
        "forumBarData" => $forumBarData,
        "userData" => $userData
    ]);

		return $return;

	}

}

