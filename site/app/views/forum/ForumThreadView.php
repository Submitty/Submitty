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

    	$return = <<<HTML

    	<style>
	    	.hoverable:hover {
			    -webkit-filter: brightness(85%);
			    -webkit-transition: all .5s ease;
			    -moz-transition: all .5s ease;
			    -o-transition: all .5s ease;
			    -ms-transition: all .5s ease;
			    transition: all .5s ease;
			}
    	</style>

    	<div class="content forum_content">
HTML;

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

		$return .= $this->core->getOutput()->renderTwigTemplate("forum/ForumBar.twig", [
									"forum_bar_buttons_right" => $buttons,
									"forum_bar_buttons_left" => [],
									"show_threads" => false,
									"thread_exists" => true,
									"show_more" => false
		]);

		$return .= <<<HTML
		<div id="search_wrapper">

    	<table style="" class="table table-striped table-bordered persist-area table-hover">

    	<thead class="persist-thead">
            <tr>                
                <td width="45%">Post Content</td>
                <td width="25%">Author</td>
                <td width="10%">Timestamp</td>
            </tr>	

        </thead>

        <tbody>


HTML;
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
		foreach($threadArray as $thread_id => $data){
			$thread_title = htmlentities($fromIdtoTitle[$thread_id], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$return .= <<<HTML
			<tr class="info persist-header hoverable" title="Go to thread" style="cursor: pointer;" onclick="window.location = '{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id))}';">            
				<td colspan="10" style="text-align: center"><h4>{$thread_title}</h4></td>
			</tr>
HTML;
			foreach($data as $post) {
				$author = htmlentities($post['author'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$user_info = $this->core->getQueries()->getDisplayUserInfoFromUserId($post["p_author"]);
				$first_name = htmlentities(trim($user_info["first_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$last_name = htmlentities(trim($user_info["last_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
				$post_content = htmlentities($post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$posted_on = date_format(DateUtils::parseDateTime($post['timestamp_post'], $this->core->getConfig()->getTimezone()), "n/j g:i A");
				$return .= <<<HTML

				<tr title="Go to post" style="cursor: pointer;" onclick="window.location = '{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id))}#{$post['p_id']}';" id="search-row-{$count}" class="hoverable">
	                <td align="left"><pre class='pre_forum'><p class="post_content" style="white-space: pre-wrap; ">{$post_content}</p></pre></td>
	                <td>{$visible_username}</td>
	                <td>{$posted_on}</td>      

		        </tr>
	            

HTML;
				$count++;
			}
		}
		
            

        $return .= <<<HTML

        </tbody>

        </table>
HTML;

		if(count($threads) == 0) {
		$return .= <<<HTML
			<h4 style="padding-bottom:20px;text-align:center;margin-top:20px;">No threads match your search criteria.</h4>
HTML;
		}

    	$return .= <<<HTML
    	</div> </div> 
HTML;
    	return $return;
    }
	
	/** Shows Forums thread splash page, including all posts
		for a specific thread, in addition to head of the threads
		that have been created after applying filter and to be
		displayed in the left panel.
	*/
	public function showForumThreads($user, $posts, $unviewed_posts, $threadsHead, $show_deleted, $show_merged_thread, $display_option, $max_thread, $initialPageNumber) {
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


		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		
		//Body Style is necessary to make sure that the forum is still readable...
		$return = <<<HTML

		<link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/codemirror.css" />
		<link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/theme/eclipse.css" />
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/codemirror.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/mode/clike/clike.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/mode/python/python.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/codemirror/mode/shell/shell.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/jquery.are-you-sure/jquery.are-you-sure.js"></script>
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
		<style>body {min-width: 925px;}</style>



		<script>

			$( document ).ready(function() {
			    enableTabsInTextArea('.post_content_reply');
				saveScrollLocationOnRefresh('posts_list');
				addCollapsable();
				var b = $('#nav-buttons li a');
				$.each(b, function(e) {
					$(b[e]).tooltip('disable').attr('title', $(b[e]).attr('data-original-title'));
				});
				$('#{$display_option}').attr('checked', 'checked'); //Saves the radiobutton state when refreshing the page
				$(".post_reply_from").submit(publishPost);
				$("form").areYouSure();
			});

		</script>

HTML;
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

	$return .= $this->core->getOutput()->renderTwigTemplate("forum/EditPostForm.twig");
	$return .= $this->core->getOutput()->renderTwigTemplate("forum/HistoryForm.twig");

	$return .= $this->core->getOutput()->renderTwigTemplate("forum/FilterForm.twig", [
		"categories" => $categories,
		"current_thread" => $currentThread,
		"current_category_ids" => $currentCategoriesIds,
		"current_course" => $currentCourse,
		"cookie_selected_categories" => $cookieSelectedCategories,
		"cookie_selected_thread_status" => $cookieSelectedThreadStatus,
		"cookie_selected_unread_value" => $cookieSelectedUnread,
		"display_option" => $display_option,
		"thread_exists" => $threadExists
	]);
	
	$return .= <<<HTML
		<div class="full_height content forum_content forum_show_threads">
HTML;

	if(!$threadExists){
		$button_params["show_threads"] = false;
		$button_params["thread_exists"] = false;
		$return .= $this->core->getOutput()->renderTwigTemplate("forum/ForumBar.twig", $button_params);
		$return .= <<<HTML
						<h4 style="text-align:center;">A thread hasn't been created yet. Be the first to do so!</h4>
				</div>
HTML;
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
					$return .= $this->core->getOutput()->renderTwigTemplate("forum/ForumBar.twig", $button_params);
					$next_page = $initialPageNumber + 1;
					$prev_page = ($initialPageNumber == 1)?0:($initialPageNumber - 1);
					$arrowup_visibility = ($initialPageNumber == 1)?"display:none;":"";
					$return .= <<<HTML
					<div style="position:relative; height:100%; overflow-y:hidden;" class="row">

						<div id="thread_list" style="overflow-y: auto; height:81vh;" class="col-3" prev_page="{$prev_page}" next_page="{$next_page}">
						<i class="fas fa-spinner fa-spin fa-2x fa-fw fill-available" style="color:gray;display: none;" aria-hidden="true"></i>
						<i class="fas fa-caret-up fa-2x fa-fw fill-available" style="color:gray;{$arrowup_visibility}" aria-hidden="true"></i>
HTML;

						$activeThreadAnnouncement = false;
						$activeThreadTitle = "";
						$activeThread = array();
						$return .= $this->displayThreadList($threadsHead, false, $activeThreadAnnouncement, $activeThreadTitle, $activeThread, $currentThread, $currentCategoriesIds);
						if(count($activeThread) == 0) {
							$activeThread = $this->core->getQueries()->getThread($currentThread)[0];
							$activeThreadTitle = $activeThread['title'];
						}

						$return .= <<<HTML
						<i class="fas fa-caret-down fa-2x fa-fw fill-available" style="color:gray;" aria-hidden="true"></i>
						<i class="fas fa-spinner fa-spin fa-2x fa-fw fill-available" style="color:gray;display: none;" aria-hidden="true"></i>
					</div>
					<script type="text/javascript">
						$(function(){
							dynamicScrollContentOnDemand($('#thread_list'), buildUrl({'component': 'forum', 'page': 'get_threads', 'page_number':'{{#}}'}), {$currentThread}, '', '{$currentCourse}');
							var active_thread = $('#thread_list .active');
							if(active_thread.length > 0) {
								active_thread[0].scrollIntoView(true); 
							}
						});
					</script>
					<div id="posts_list" style="overflow-y: auto; height:81vh;" class="col-9">
HTML;

		$currentThreadArrValues = array_values($currentThreadArr);
		$currentThreadFavorite = !empty($currentThreadArrValues) ? $currentThreadArrValues[0]['favorite'] : false;
		$return .= $this->generatePostList($currentThread, $posts, $unviewed_posts, $currentCourse, true, $threadExists, $display_option, $categories, $cookieSelectedCategories, $cookieSelectedThreadStatus, $cookieSelectedUnread, $currentCategoriesIds, $currentThreadFavorite);

		$return .= <<<HTML
			<script>
				$(function() {
					generateCodeMirrorBlocks(document);
				});
			</script>

			</div>
			</div>
			</div>
HTML;

		}
        if ( !empty($activeThread['id']) ) {
            $this->core->getQueries()->visitThread($user, $activeThread['id']);
        }
        return $return;
	}

	public function generatePostList($currentThread, $posts, $unviewed_posts, $currentCourse, $includeReply = false, $threadExists = false, $display_option = 'time', $categories = [], $cookieSelectedCategories = [], $cookieSelectedThreadStatus = [], $cookieSelectedUnread = [], $currentCategoriesIds = [], $isCurrentFavorite = false) {

		$return = '';
		$title_html = '';

		$activeThread = $this->core->getQueries()->getThread($currentThread)[0];

		$activeThreadTitle = ($this->core->getUser()->accessFullGrading() ? "({$activeThread['id']}) " : '') . htmlentities($activeThread['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$activeThreadAnnouncement = $activeThread['pinned'];

		$thread_id = $activeThread['id'];
		$function_date = 'date_format';

		$return .= <<<HTML
			
HTML;

		  $title_html .= <<<HTML
            <h3 style="max-width: 95%; display:inline-block;word-wrap: break-word;margin-top:10px; margin-left: 5px;">
HTML;
					if($this->core->getUser()->getGroup() <= 2 && $activeThreadAnnouncement){
                        $title_html .= <<<HTML
							<a style="display:inline-block; color:orange; " onClick="alterAnnouncement({$activeThread['id']}, 'Are you sure you want to remove this thread as an announcement?', 'remove_announcement')" title="Remove Announcement"><i class="fas fa-star" onmouseleave="changeColor(this, 'gold')" onmouseover="changeColor(this, '#e0e0e0')" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
                    } else if($activeThreadAnnouncement){
                        $title_html .= <<<HTML
						 <i class="fas fa-star" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black;" title = "Announcement" aria-hidden="true"></i>
HTML;
                    } else if($this->core->getUser()->getGroup() <= 2 && !$activeThreadAnnouncement){
                        $title_html .= <<<HTML
							<a style="position:relative; display:inline-block; color:orange; " onClick="alterAnnouncement({$activeThread['id']}, 'Are you sure you want to make this thread an announcement?', 'make_announcement')" title="Make thread an announcement"><i class="fas fa-star" title = "Make Announcement" onmouseleave="changeColor(this, '#e0e0e0')" onmouseover="changeColor(this, 'gold')" style="position:relative; display:inline-block; color:#e0e0e0; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
                    }
                    if($isCurrentFavorite) {
                    	$title_html .= <<<HTML
							<a style="position:relative; display:inline-block; color:orange; " onClick="pinThread({$activeThread['id']}, 'unpin_thread');" title="Pin Thread"><i class="fas fa-thumbtack" onmouseleave="changeColor(this, 'gold')" onmouseover="changeColor(this, '#e0e0e0')" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;-webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
					} else {
                    	$title_html .= <<<HTML
							<a style="position:relative; display:inline-block; color:orange; " onClick="pinThread({$activeThread['id']}, 'pin_thread');" title="Pin Thread"><i class="fas fa-thumbtack" onmouseleave="changeColor(this, '#e0e0e0')" onmouseover="changeColor(this, 'gold')" style="position:relative; display:inline-block; color:#e0e0e0; -webkit-text-stroke-width: 1px;-webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
					}
                    $title_html .= <<< HTML
					{$activeThreadTitle}</h3>
HTML;
					$first = true;
					$first_post_id = 1;
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
									$return .= $this->createPost($thread_id, $post, $unviewed_posts, $function_date, $title_html, $first, $reply_level, $display_option, $includeReply);
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
							$return .= $this->createPost($thread_id, $post, $unviewed_posts, $function_date, $title_html, $first, 1, $display_option, $includeReply);		
							if($first){
								$first= false;
							}			
						}
					}
			if($includeReply) {
				$return .= <<<HTML

			<hr style="border-top:1px solid #999;margin-bottom: 5px;" />
			
					<form style="margin-right:17px;" class="post_reply_from" method="POST" onsubmit="post.disabled=true; post.value='Submitting post...'; return true;" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}" enctype="multipart/form-data">
						<input type="hidden" name="thread_id" value="{$thread_id}" />
						<input type="hidden" name="parent_id" value="{$first_post_id}" />
						<input type="hidden" name="display_option" value="{$display_option}" />
HTML;
						$GLOBALS['post_box_id'] = $post_box_id = isset($GLOBALS['post_box_id'])?$GLOBALS['post_box_id']+1:1;


							$return .= $this->core->getOutput()->renderTwigTemplate("forum/ThreadPostForm.twig", [
								"show_post" => true,
								"post_content_placeholder" => "Enter your reply to all here...",
								"show_merge_thread_button" => true,
								"post_box_id" => $post_box_id,
								"attachment_script" => true,
								"show_anon" => true,
								"submit_label" => "Submit Reply to All",
							]);
						}
						
						$return .= <<<HTML
	            	</form>
	            	<br/>
HTML;

        if($this->core->getUser()->getGroup() <= 3){
        	$this->core->getOutput()->addVendorCss(FileUtils::joinPaths('chosen-js', 'chosen.min.css'));
        	$this->core->getOutput()->addVendorJs(FileUtils::joinPaths('chosen-js', 'chosen.jquery.min.js'));
			$current_thread_first_post = $this->core->getQueries()->getFirstPostForThread($currentThread);
			$current_thread_date = $current_thread_first_post["timestamp"];
			$merge_thread_list = $this->core->getQueries()->getThreadsBefore($current_thread_date, 1);
			$return .= $this->core->getOutput()->renderTwigTemplate("forum/MergeThreadsForm.twig", [
				"current_thread_date" => $current_thread_date,
				"current_thread" => $currentThread,
				"possibleMerges" => $merge_thread_list
			]);
		}

		return $return;
	}

	public function showAlteredDisplayList($threads, $filtering, $thread_id, $categories_ids){
		$tempArray = array();
		$threadAnnouncement = false;
		$activeThreadTitle = "";
		return $this->displayThreadList($threads, $filtering, $threadAnnouncement, $activeThreadTitle, $tempArray, $thread_id, $categories_ids);
	}

	public function displayThreadList($threads, $filtering, &$activeThreadAnnouncement, &$activeThreadTitle, &$activeThread, $thread_id_p, $current_categories_ids){
					$return = "";
					$used_active = false; //used for the first one if there is not thread_id set
					$current_user = $this->core->getUser()->getId();
					$display_thread_ids = $this->core->getUser()->getGroup() <= 2;

					$activeThreadAnnouncement = false;
					$activeThreadTitle = "";
					$function_date = 'date_format';
					$activeThread = array();

					foreach($threads as $thread){
						$first_post = $this->core->getQueries()->getFirstPostForThread($thread["id"]);
						if(is_null($first_post)) {
							// Thread without any posts(eg. Merged Thread)
							$first_post = array('content' => "");
							$date = null;
						} else {
							$date = DateUtils::parseDateTime($first_post['timestamp'], $this->core->getConfig()->getTimezone());
						}
						if($thread['merged_thread_id'] != -1){
							// For the merged threads
							$thread['status'] = 0;
						}

						$class = "thread_box";
						// $current_categories_ids should be subset of $thread["categories_ids"]
						$issubset = (count(array_intersect($current_categories_ids, $thread["categories_ids"])) == count($current_categories_ids));
						if(((isset($_REQUEST["thread_id"]) && $_REQUEST["thread_id"] == $thread["id"]) || $thread_id_p == $thread["id"] || $thread_id_p == -1) && !$used_active && $issubset) {
							$class .= " active";
							$used_active = true;
							$activeThreadTitle = ($display_thread_ids ? "({$thread['id']}) " : '') . $thread["title"];
							$activeThread = $thread;
							if($thread["pinned"])
								$activeThreadAnnouncement = true;
							if($thread_id_p == -1)
								$thread_id_p = $thread["id"];
						}
						if(!$this->core->getQueries()->viewedThread($current_user, $thread["id"])){
							$class .= " new_thread";
						}
						if($thread["deleted"]) {
							$class .= " deleted";
						}
						//fix legacy code
						$titleDisplay = html_entity_decode($thread['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

						//replace tags from displaying in sidebar
						$first_post_content = str_replace("[/code]", "", str_replace("[code]", "", strip_tags($first_post["content"])));
						$temp_first_post_content = preg_replace('#\[url=(.*?)\](.*?)(\[/url\])#', '$2', $first_post_content);

						if(!empty($temp_first_post_content)){
							$first_post_content = $temp_first_post_content;
						}

						$sizeOfContent = strlen($first_post_content);
						$contentDisplay = substr($first_post_content, 0, ($sizeOfContent < 80) ? $sizeOfContent : strrpos(substr($first_post_content, 0, 80), " "));
						$titleLength = strlen($thread['title']);

						$titleDisplay = substr($titleDisplay, 0, ($titleLength < 40) ? $titleLength : strrpos(substr($titleDisplay, 0, 40), " "));

						if(strlen($first_post["content"]) > 80){
							$contentDisplay .= "...";
						}
						if(strlen($thread["title"]) > 40){
							//Fix ... appearing
							if(empty($titleDisplay))
								$titleDisplay .= substr($thread['title'], 0, 30);
							$titleDisplay .= "...";
						}
						$titleDisplay = ($display_thread_ids ? "({$thread['id']}) " : '') . $titleDisplay;
						$titleDisplay = htmlentities($titleDisplay, ENT_QUOTES | ENT_HTML5, 'UTF-8');
						if($thread["current_user_posted"]) {
							$icon = '<i class="fas fa-comments" title="You have contributed"></i> ';
							$titleDisplay = $icon . $titleDisplay;
						}

						$return .= <<<HTML
						<a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread['id']))}">
						<div class="{$class}">
HTML;
						if($thread["pinned"] == true){
							$return .= <<<HTML
							<i class="fas fa-star" style="padding-left:3px;position:relative; float:right; display:inline-block; color:gold; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black;" title = "Announcement" aria-hidden="true"></i>
HTML;
						}
						if(isset($thread['favorite']) && $thread['favorite']) {
							$return .= <<<HTML
							<i class="fas fa-thumbtack" style="padding-left:3px;position:relative; float:right; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" title="Pinned as my favorite" aria-hidden="true"></i>
HTML;
						}
						if($thread['merged_thread_id'] != -1) {
							$return .= <<<HTML
							<i class="fas fa-link" style="padding-left:3px;position:relative; float:right; display:inline-block; color: white; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" title="Thread Merged" aria-hidden="true"></i>
HTML;
						}
						if (!isset($thread['status'])) {
                            $thread['status'] = 0;
                        }
						if($thread['status'] !=0) {
							if($thread['status'] == 1) {
								$fa_icon = "fa-check";
								$fa_color = "#5cb85c";
								$fa_margin_right = "0px";
								$fa_font_size = "1.5em";
								$tooltip = "Thread Resolved";
							} else {
								$fa_icon = "fa-question";
								$fa_color = "#ffcc00";
								$fa_margin_right = "5px";
								$fa_font_size = "1.8em";
								$tooltip = "Thread Unresolved";
							}
							$return .= <<<HTML
							<i class="fa ${fa_icon}" style="margin-right:${fa_margin_right}; padding-left:3px; position:relative; float:right; display:inline-block; color:${fa_color}; font-size:${fa_font_size};" title = "${tooltip}" aria-hidden="true"></i>
HTML;
						}
						$categories_content = array();
						foreach ($thread["categories_desc"] as $category_desc) {
							$categories_content[] = array(htmlentities($category_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
						}
						for ($i = 0; $i < count($thread["categories_color"]); $i+=1) {
							$categories_content[$i][] = $thread["categories_color"][$i];
						}
						$return .= <<<HTML
						<h4>{$titleDisplay}</h4>
						<h5 style="font-weight: normal;">{$contentDisplay}</h5>
HTML;
						foreach ($categories_content as $category_content) {
							$return .= <<<HTML
							<span class="label_forum" style="background-color: {$category_content[1]}">{$category_content[0]}</span>
HTML;
						}
						if(!is_null($date)) {
							$return .= <<<HTML
							<h5 style="float:right; font-weight:normal;margin-top:5px">{$function_date($date,"n/j g:i A")}</h5>
HTML;
						}
						$return .= <<<HTML
						</div>
						</a>
						<hr style="margin-top: 0px;margin-bottom:0px;">
HTML;
					}
					return $return;
	}

	public function filter_post_content($original_post_content) {
		$post_content = html_entity_decode($original_post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$pre_post = preg_replace('#(<a href=[\'"])(.*?)([\'"].*>)(.*?)(</a>)#', '[url=$2]$4[/url]', $post_content);

		if(!empty($pre_post)){
			$post_content = $pre_post;
		}

		$post_content = htmlentities($post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

	public function createPost($thread_id, $post, $unviewed_posts, $function_date, $title_html, $first, $reply_level, $display_option, $includeReply){
		$current_user = $this->core->getUser()->getId();
		$post_id = $post["id"];

		$thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

		$date = DateUtils::parseDateTime($post["timestamp"], $this->core->getConfig()->getTimezone());
		if(!is_null($post["edit_timestamp"])) {
			$edit_date = $function_date(DateUtils::parseDateTime($post["edit_timestamp"], $this->core->getConfig()->getTimezone()),"n/j g:i A");
		} else {
			$edit_date = null;
		}
		$user_info = $this->core->getQueries()->getDisplayUserInfoFromUserId($post["author_user_id"]);
		$author_email = htmlentities(trim($user_info['user_email']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$first_name = htmlentities(trim($user_info["first_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$last_name = htmlentities(trim($user_info["last_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$visible_username = $first_name . " " . substr($last_name, 0 , 1) . ".";
		$thread_resolve_state = $this->core->getQueries()->getResolveState($thread_id)[0]['status'];

		if($display_option != 'tree'){
			$reply_level = 1;
		}
		
		if($post["anonymous"]){
			$visible_username = "Anonymous";
		} 
		$classes = "post_box";						
		if($first && $display_option != 'alpha'){
			$classes .= " first_post";
		}
		if(in_array($post_id, $unviewed_posts)){
			$classes .= " new_post";
		} else {
			$classes .= " viewed_post";
		}
		if($this->core->getQueries()->isStaffPost($post["author_user_id"])){
			$classes .= " important";
		}
		if($post["deleted"]) {
			$classes .= " deleted";
			$deleted = true;
		} else {
			$deleted = false;
		}
		$offset = min(($reply_level - 1) * 30, 180);
		
		$return = <<<HTML
			<div class="$classes" id="$post_id" style="margin-left:{$offset}px;" reply-level="$reply_level">
HTML;


		if($first){
            $return .= $title_html;
        } 

        //handle converting links 


        //convert legacy htmlentities being saved in db
        $post_content = $this->filter_post_content($post['content']);

		//end code segment handling
		$return .= <<<HTML
			<pre class='pre_forum'><p class="post_content" style="white-space: pre-wrap; ">{$post_content}</p></pre>		
			<hr style="margin-bottom:3px;">
HTML;
		if($display_option == 'tree'){
			if(!$first){
				$return .= <<<HTML
					<a class="btn btn-default btn-sm post_button_color" style=" text-decoration: none;" onClick="replyPost({$post['id']})"> Reply</a>
HTML;
			} else {
				$return .= <<<HTML
					<a class="btn btn-default btn-sm post_button_color" style=" text-decoration: none;" onClick="$('html, #posts_list').animate({ scrollTop: document.getElementById('posts_list').scrollHeight }, 'slow');"> Reply</a>
HTML;
			}
			if($this->core->getUser()->getGroup() <= 3) {
				$return .= <<<HTML
					<a class="btn btn-default btn-sm post_button_color" style=" text-decoration: none;" onClick="showHistory({$post['id']})">Show History</a>
HTML;
			}
		}
		if($includeReply && ($this->core->getUser()->getGroup() <= 3 || $post['author_user_id'] === $current_user) && $first && $thread_resolve_state == -1) {
			//resolve button
			$return .= <<<HTML
				<a class="btn btn-default btn-sm post_button_color" style="text-decoration: none;" onClick="changeThreadStatus({$post['thread_id']})" title="Mark thread as resolved">Mark as resolved</a>
HTML;
		}
		$return .= <<<HTML
			<span style="margin-top:8px;margin-left:10px;float:right;">							
HTML;
       if($this->core->getUser()->getGroup() <= 2 && $post["author_user_id"]!=$current_user){
            $return .= <<<HTML
                <a style=" margin-right:2px;display:inline-block; color:black; " onClick='$(this).next().toggle();' title="Show/Hide email address"><i class="fas fa-envelope" aria-hidden="true"></i></a>
                <a href="mailto:{$author_email}" style="display: none;">{$author_email}</a>
HTML;
}
		if($this->core->getUser()->getGroup() <= 2){
			$info_name = $first_name . " " . $last_name . " (" . $post['author_user_id'] . ")";
			$visible_user_json = json_encode($visible_username);
			$info_name = json_encode($info_name);
			$jscriptAnonFix = $post['anonymous'] ? 'true' : 'false' ;
			$jscriptAnonFix = json_encode($jscriptAnonFix);
			$return .= <<<HTML
				<a style=" margin-right:2px;display:inline-block; color:black; " onClick='changeName(this.parentNode, {$info_name}, {$visible_user_json}, {$jscriptAnonFix})' title="Show full user information"><i class="fas fa-eye" aria-hidden="true"></i></a>
HTML;
}
		if(!$first){
			$return .= <<<HTML
				<a class="expand btn btn-default btn-sm post_button_color" style="float:right; text-decoration:none; margin-top: -8px" onClick="hidePosts(this, {$post['id']})"></a>
HTML;
		}
		if($this->core->getUser()->getGroup() <= 3 || $post['author_user_id'] === $current_user) {
			if($deleted && $this->core->getUser()->getGroup() <= 3){
				$ud_toggle_status = "false";
				$ud_button_title = "Undelete post";
				$ud_button_icon = "fa-undo";
			} else {
				$ud_toggle_status = "true";
				$ud_button_title = "Remove post";
				$ud_button_icon = "fa-trash";
			}
			$return .= <<<HTML
			<a class="post_button" style="bottom: 1px;position:relative; display:inline-block; float:right;" onClick="deletePostToggle({$ud_toggle_status}, {$post['thread_id']}, {$post['id']}, '{$post['author_user_id']}', '{$function_date($date,'n/j g:i A')}' )" title="{$ud_button_title}"><i class="fa {$ud_button_icon}" aria-hidden="true"></i></a>
HTML;
		}
		if($this->core->getUser()->getGroup() <= 3 || $post['author_user_id'] === $current_user) {
			$shouldEditThread = null;
			if($first) {
				$shouldEditThread = "true";
				$edit_button_title = "Edit thread and post";
			} else {
				$shouldEditThread = "false";
				$edit_button_title = "Edit post";
			}
			$return .= <<<HTML
				<a class="post_button" style="position:relative; display:inline-block; color:black; float:right;" onClick="editPost({$post['id']}, {$post['thread_id']}, {$shouldEditThread})" title="{$edit_button_title}"><i class="fas fa-edit" aria-hidden="true"></i></a>
HTML;
		} 

		$return .= <<<HTML
		<h7 style="position:relative; right:5px;">
			<strong id="post_user_id">{$visible_username}</strong>
			{$function_date($date,"n/j g:i A")}
HTML;
		if(!is_null($edit_date)) {
			$return .= <<<HTML
			(<i>Last edit at {$edit_date}</i>)
HTML;
		}
		$return .= <<<HTML
		</h7>
		</span>
HTML;

		if($post["has_attachment"]){
			$post_dir = FileUtils::joinPaths($thread_dir, $post["id"]);
			$files = FileUtils::getAllFiles($post_dir);
			foreach($files as $file){
				$path = rawurlencode($file['path']);
				$name = rawurlencode($file['name']);
				$name_display = htmlentities(rawurldecode($file['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
				$return .= <<<HTML
					<a href="#" style="text-decoration:none;display:inline-block;white-space: nowrap;" class="btn-default btn-sm post_button_color" onclick="openFileForum('forum_attachments', '{$name}', '{$path}')" > {$name_display} </a>
HTML;
			}					
		}
			$offset = $offset + 30;
						$return .= <<<HTML
</div>

					<form class="reply-box post_reply_from" id="$post_id-reply" onsubmit="post.disabled=true; post.value='Submitting post...'; return true;" style="margin-left:{$offset}px" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}" enctype="multipart/form-data">
						<input type="hidden" name="thread_id" value="{$thread_id}" />
						<input type="hidden" name="parent_id" value="{$post_id}" />
	            		<br/>
HTML;
	            		$GLOBALS['post_box_id'] = $post_box_id = isset($GLOBALS['post_box_id'])?$GLOBALS['post_box_id']+1:1;
						$return .= $this->core->getOutput()->renderTwigTemplate("forum/ThreadPostForm.twig", [
							"show_post" => true,
							"post_content_placeholder" => "Enter your reply to {$visible_username} here...",
							"show_merge_thread_button" => false,
							"post_box_id" => $post_box_id,
							"show_anon" => true,
							"submit_label" => "Submit Reply to {$visible_username}",
						]);
						$return .= <<<HTML
	            	</form>
HTML;

		return $return;
	}

	public function createThread($category_colors) {

		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$this->core->getOutput()->addBreadcrumb("Create Thread", $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
		$return = <<<HTML
		<script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}vendor/jquery.are-you-sure/jquery.are-you-sure.js"></script>
		<script type="text/javascript" src="{$this->core->getConfig()->getBaseUrl()}js/drag-and-drop.js"></script>
		<script> 
			$( document ).ready(function() {
				enableTabsInTextArea("[name=thread_post_content]");
				$("#thread_form").submit(createThread);
				$("form").areYouSure();
			});
		 </script>
HTML;
        if($this->core->getUser()->getGroup() <= 3){
            $categories = $this->core->getQueries()->getCategories();

            $dummy_category = array('color' => '#000000', 'category_desc' => 'dummy', 'category_id' => "dummy");
            array_unshift($categories, $dummy_category);

            $return .= $this->core->getOutput()->renderTwigTemplate("forum/CategoriesForm.twig", [
                "categories" => $categories,
                "category_colors" => $category_colors
            ]);
        }

		$return .= <<<HTML
		<div class="content forum_content">
		
HTML;

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

	$return .= $this->core->getOutput()->renderTwigTemplate("forum/ForumBar.twig", [
								"forum_bar_buttons_right" => $buttons,
								"forum_bar_buttons_left" => [],
								"show_threads" => false,
								"thread_exists" => $thread_exists,
								"show_more" => false
	]);


		$return .= <<<HTML

			<form style="margin-right: 15px; margin-left:15px;" id="thread_form" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_thread'))}" enctype="multipart/form-data">
			<h3 style="margin-bottom:10px;"> Create Thread </h3>
HTML;

				$return .= $this->core->getOutput()->renderTwigTemplate("forum/ThreadPostForm.twig", [
					"show_title" => true,
					"show_post" => true,
					"post_textarea_large" => true,
					"post_content_placeholder" => "Enter your post here...",
					"show_categories" => true,
					"post_box_id" => 1,
					"attachment_script" => true,
					"show_anon" => true,
					"show_thread_status" => true,
					"show_announcement" => true,
					"show_editcat" => true,
					"submit_label" => "Submit Post",
				]);
			$return .= <<<HTML
			</form>
		</div>
HTML;

		return $return;
	}



	public function statPage($users) {

		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		if(!$this->core->getUser()->accessFullGrading()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
			return;
		}

		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$this->core->getOutput()->addBreadcrumb("Statistics", $this->core->buildUrl(array('component' => 'forum', 'page' => 'show_stats')));

		$return = <<<HTML

		<div class="content forum_content">

HTML;

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

		$return .= $this->core->getOutput()->renderTwigTemplate("forum/ForumBar.twig", [
									"forum_bar_buttons_right" => $buttons,
									"forum_bar_buttons_left" => [],
									"show_threads" => false,
									"thread_exists" => $thread_exists
		]);

		$return .= <<<HTML
			<div style="padding-left:20px;padding-bottom: 10px;border-radius:3px;padding-right:20px;">
				<table class="table table-striped table-bordered persist-area" id="forum_stats_table">
					<tr>			
				        <td style = "cursor:pointer;" width="15%" id="user_sort"><a href="javascript:void(0)">User</a></td>
				        <td style = "cursor:pointer;" width="15%" id="total_posts_sort"><a href="javascript:void(0)">Total Posts (not deleted)</a></td>
				        <td style = "cursor:pointer;" width="15%" id="total_threads_sort"><a href="javascript:void(0)">Total Threads</a></td>
				        <td style = "cursor:pointer;" width="15%" id="total_deleted_sort"><a href="javascript:void(0)">Total Deleted Posts</a></td>
				        <td width="40%">Show Posts</td>
					</tr>
HTML;
		foreach($users as $user => $details){
			$first_name = $details["first_name"];
			$last_name = $details["last_name"];
			$post_count = count($details["posts"]);
			$posts = htmlspecialchars(json_encode($details["posts"]), ENT_QUOTES, 'UTF-8');
			$ids = htmlspecialchars(json_encode($details["id"]), ENT_QUOTES, 'UTF-8');
			$timestamps = htmlspecialchars(json_encode($details["timestamps"]), ENT_QUOTES, 'UTF-8');
			$thread_ids = htmlspecialchars(json_encode($details["thread_id"]), ENT_QUOTES, 'UTF-8');
			$thread_titles = htmlspecialchars(json_encode($details["thread_title"]), ENT_QUOTES, 'UTF-8');
			$num_deleted = ($details["num_deleted_posts"]);
			$return .= <<<HTML
			<tbody>
				<tr>
					<td>{$last_name}, {$first_name}</td>
					<td>{$post_count}</td>
					<td>{$details["total_threads"]}</td>
					<td>{$num_deleted}</td>
					<td><button class="btn btn-default" data-action = "expand" data-posts="{$posts}" data-id="{$ids}" data-timestamps="{$timestamps}" data-thread_id="{$thread_ids}" data-thread_titles="{$thread_titles}">Expand</button></td>
				</tr>
			</tbody>
HTML;
			
		}
		
		$return .= <<<HTML
				</table>
			</div>
			</div>

			<script>
				$("td").click(function(){
				    var table_id = 0;
				    switch ($(this).attr('id')) {
				        case "user_sort":
				            table_id = 0;
				            break;
                        case "total_posts_sort":
                            table_id = 1;
                            break;
                        case "total_threads_sort":
                            table_id = 2;
                            break;
                        case "total_deleted_sort":
                            table_id = 3;
                            break;
                        default:
                            table_id = 0;
				    }
				    
                    if ($(this).html().indexOf(' ↓') > -1) {
                        sortTable(table_id, true);
                    } else {
                        sortTable(table_id, false);
                    }
				});
				
				$("button").click(function(){
					
					var action = $(this).data('action');
					var posts = $(this).data('posts');
					var ids = $(this).data('id');
					var timestamps = $(this).data('timestamps');
					var thread_ids = $(this).data('thread_id');
					var thread_titles = $(this).data('thread_titles');
					if(action=="expand"){
						
						
						for(var i=0;i<posts.length;i++){
							var post_string = posts[i];
							post_string = escapeSpecialChars(post_string);
							var thread_title = thread_titles[i]["title"];
							thread_title = escapeSpecialChars(thread_title);
							$(this).parent().parent().parent().append('<tr id="'+ids[i]+'"><td></td><td>'+timestamps[i]+'</td><td style = "cursor:pointer;" data-type = "thread" data-thread_id="'+thread_ids[i]+'"><pre class="pre_forum" style="white-space: pre-wrap;">'+thread_title+'</pre></td><td colspan = "2" style = "cursor:pointer;" align = "left" data-type = "post" data-thread_id="'+thread_ids[i]+'"><pre class="pre_forum" style="white-space: pre-wrap;">'+post_string+'</pre></td></tr> ');
							
						}
						$(this).html("Collapse");
						$(this).data('action',"collapse");
						$("td").click(function(){
						
							if($(this).data('type')=="post" || $(this).data('type')=="thread"){
			
								var id = $(this).data('thread_id');
								var url = buildUrl({'component' : 'forum', 'page' : 'view_thread', 'thread_id' : id});
								window.open(url);
							}
						
					});
					}
					else{
						for(var i=0;i<ids.length;i++){
							var item = document.getElementById(ids[i]);
							item.remove();
						}
						
						$(this).html("Expand");
						$(this).data('action',"expand");
					}
					
					
					return false;
				});


				

				function sortTable(sort_element_index, reverse=false){
					var table = document.getElementById("forum_stats_table");
					var switching = true;
					while(switching){
						switching=false;
						var rows = table.getElementsByTagName("TBODY");
						for(var i=1;i<rows.length-1;i++){

							var a = rows[i].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
							var b = rows[i+1].getElementsByTagName("TR")[0].getElementsByTagName("TD")[sort_element_index];
							if (reverse){
							    if (sort_element_index == 0 ? a.innerHTML<b.innerHTML : parseInt(a.innerHTML) > parseInt(b.innerHTML)){
                                    rows[i].parentNode.insertBefore(rows[i+1],rows[i]);
                                    switching=true;
							    } 
							} else {
                                if(sort_element_index == 0 ? a.innerHTML>b.innerHTML : parseInt(a.innerHTML) < parseInt(b.innerHTML)){
                                    rows[i].parentNode.insertBefore(rows[i+1],rows[i]);
                                    switching=true;
                                }
							}
						}

					}

					var row0 = table.getElementsByTagName("TBODY")[0].getElementsByTagName("TR")[0];
					var headers = row0.getElementsByTagName("TD");
					
					for(var i = 0;i<headers.length;i++){
						var index = headers[i].innerHTML.indexOf(' ↓');
						var reverse_index = headers[i].innerHTML.indexOf(' ↑');
						
						if(index > -1 || reverse_index > -1){
							headers[i].innerHTML = headers[i].innerHTML.slice(0, -2);
						} 
					}
                    
					if (reverse) {
                        headers[sort_element_index].innerHTML = headers[sort_element_index].innerHTML + ' ↑';
					} else {
					    headers[sort_element_index].innerHTML = headers[sort_element_index].innerHTML + ' ↓';
					}

				}


			</script>
HTML;
		return $return;

	}

}

