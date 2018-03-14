<?php
namespace app\views\forum;

use app\authentication\DatabaseAuthentication;
use app\views\AbstractView;
use app\models\Course;
use app\libraries\FileUtils;


class ForumThreadView extends AbstractView {


	public function forumAccess(){
        return $this->core->getConfig()->isForumEnabled();
    }
	
	/** Shows Forums thread splash page, including all posts
		for a specific thread, in addition to all of the threads
		that have been created to be displayed in the left panel.
	*/
	public function showForumThreads($user, $posts, $threads) {
		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		
		//Body Style is necessary to make sure that the forum is still readable...
		$return = <<<HTML

		<link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}css/iframe/codemirror.css" />
    <link rel="stylesheet" href="{$this->core->getConfig()->getBaseUrl()}css/iframe/eclipse.css" />
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/jquery-2.0.3.min.map.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/codemirror.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/clike.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/python.js"></script>
    <script type="text/javascript" language="javascript" src="{$this->core->getConfig()->getBaseUrl()}js/iframe/shell.js"></script>
		<style>body {min-width: 925px;} pre { font-family: inherit; }</style>



		<script>
		function openFile(directory, file, path ){
			window.open("{$this->core->getConfig()->getSiteUrl()}&component=misc&page=display_file&dir=" + directory + "&file=" + file + "&path=" + path,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
		}

			$( document ).ready(function() {
			    enableTabsInTextArea('post_content');
			    saveScrollLocationOnRefresh('thread_list');
			});

		</script>

HTML;
	if($this->core->getUser()->getGroup() <= 2){
		$return .= <<<HTML
		<script>
								function changeName(element, user, visible_username, anon){
									var new_element = element.getElementsByTagName("strong")[0];
									anon = (anon == 'true');
									icon = element.getElementsByClassName("fa fa-eye")[0];
									if(icon == undefined){
										icon = element.getElementsByClassName("fa fa-eye-slash")[0];
										if(anon) {
											new_element.style.color = "black";
											new_element.style.fontStyle = "normal";
										}
										new_element.innerHTML = visible_username;
										icon.className = "fa fa-eye";
										icon.title = "Show full user information";
									} else {
										if(anon) {
											new_element.style.color = "grey";
											new_element.style.fontStyle = "italic";
										}
										new_element.innerHTML = user;
										icon.className = "fa fa-eye-slash";
										icon.title = "Hide full user information";
									} 									
								}
		</script>
HTML;
	}
	$return .= <<<HTML
		<div style="margin-top:5px;background-color:transparent; margin: !important auto;padding:0px;box-shadow: none;" class="content">

		<div style="margin-top:10px; height:50px;  " id="forum_bar">
			<div style="margin-left:20px; height: 20px; width:136px;" class="create_thread_button">

			<a class="btn btn-primary" style="position:absolute;top:3px;left:2px;vertical-align: middle;" title="Create thread" onclick="resetScrollPosition();" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))}"><i class="fa fa-plus-circle"></i> Create Thread</a>
			</div>
		</div>

HTML;
		if(count($threads) == 0){
		$return .= <<<HTML
					<div style="margin-left:20px;margin-top:10px;margin-right:20px;padding:25px; text-align:center;" class="content">
						<h4>A thread hasn't been created yet. Be the first to do so!</h4>
					</div>
				</div>
HTML;
		} else {

			if($this->core->getUser()->getGroup() <= 2){
				$return .= <<<HTML
				<div class="popup-form" id="edit-user-post">

				<h3 id="edit_user_prompt"></h3>

				<form method="post" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'edit_post'))}">
    					<input type="hidden" id="edit_post_id" name="edit_post_id" value="" />
						<input type="hidden" id="edit_thread_id" name="edit_thread_id" value="" />

	            		<textarea name="edit_post_content" id="edit_post_content" style="margin-right:10px;resize:none;min-height:200px;width:98%;" placeholder="Enter your reply here..." required></textarea>
	            	
					<div style="float: right; width: auto; margin-top: 10px">
	        			<a onclick="$('#edit-user-post').css('display', 'none');" class="btn btn-danger">Cancel</a>
	       			 	<input class="btn btn-primary" type="submit" value="Submit" />
	    			</div>	
	    			</form>
				</div>
HTML;
			}

			$return .= <<<HTML
				<div id="forum_wrapper">
					<div class="thread_list">
HTML;
					$used_active = false; //used for the first one if there is not thread_id set
					$function_date = 'date_format';
					$activeThreadTitle = "";
					$activeThread = array();
					$current_user = $this->core->getUser()->getId();
					$activeThreadAnnouncement = false;
					$start = 0;
					$end = 10;
					foreach($threads as $thread){
						$first_post = $this->core->getQueries()->getFirstPostForThread($thread["id"]);
						$date = date_create($first_post['timestamp']);
						$class = "thread_box";
						//Needs to be refactored to rid duplicated code
						if(!isset($_REQUEST["thread_id"]) && !$used_active){
							$class .= " active";
							$used_active = true;
							$activeThread = $thread;
							$activeThreadTitle = $thread["title"];
							if($thread["pinned"])
								$activeThreadAnnouncement = true;
						} else if(isset($_REQUEST["thread_id"]) && $_REQUEST["thread_id"] == $thread["id"]) {
							$class .= " active";
							$activeThreadTitle = $thread["title"];
							$activeThread = $thread;
							if($thread["pinned"])
								$activeThreadAnnouncement = true;
						}

						if($this->core->getQueries()->viewedThread($current_user, $thread["id"])){
							$class .= " viewed";
						}

						//fix legacy code
						$titleDisplay = html_entity_decode($thread['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$first_post_content = html_entity_decode($first_post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

						//replace tags from displaying in sidebar
						$first_post_content = str_replace("[/code]", "", str_replace("[code]", "", strip_tags($first_post["content"])));
						$temp_first_post_content = preg_replace('#\[url=(.*?)\](.*?)(\[/url\])#', '$2', $first_post_content);

						if(!empty($temp_first_post_content)){
							$first_post_content = $temp_first_post_content;
						}

						$sizeOfContent = strlen($first_post_content);
						$contentDisplay = substr($first_post_content, 0, ($sizeOfContent < 100) ? $sizeOfContent : strrpos(substr($first_post_content, 0, 100), " "));
						$titleLength = strlen($thread['title']);

						$titleDisplay = substr($titleDisplay, 0, ($titleLength < 40) ? $titleLength : strrpos(substr($titleDisplay, 0, 40), " "));

						if(strlen($first_post["content"]) > 100){
							$contentDisplay .= "...";
						}
						if(strlen($thread["title"]) > 40){
							//Fix ... appearing
							if(empty($titleDisplay))
								$titleDisplay .= substr($thread['title'], 0, 30);
							$titleDisplay .= "...";
						}
						$titleDisplay = htmlentities($titleDisplay, ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$first_post_content = htmlentities($first_post_content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$return .= <<<HTML
						<a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread['id']))}">
						<div class="{$class}">
HTML;
						if($thread["pinned"] == true){
							$return .= <<<HTML
							<i class="fa fa-star" style="position:relative; float:right; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i>
HTML;
						}
						$return .= <<<HTML
						<h4>{$titleDisplay}</h4>
						<h5 style="font-weight: normal;">{$contentDisplay}</h5>
						<h5 style="float:right; font-weight:normal;margin-top:5px">{$function_date($date,"m/d/Y g:i A")}</h5>
						</div>
						</a>
						<hr style="margin-top: 0px;margin-bottom:0px;">
HTML;
					}

					$activeThreadTitle = htmlentities(strip_tags(html_entity_decode($activeThreadTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

			$thread_id = -1;
			$userAccessToAnon = ($this->core->getUser()->getGroup() < 4) ? true : false;
			$title_html = '';
			$return .= <<< HTML
					</div>
					<div style="display:inline-block;width:70%; float: right;" class="posts_list">
HTML;

            $title_html .= <<< HTML
            <h3 style="max-width: 95%; display:inline-block;word-wrap: break-word;margin-top:10px; margin-left: 5px;">
HTML;
					if($this->core->getUser()->getGroup() <= 2 && $activeThreadAnnouncement){
                        $title_html .= <<<HTML
							<a style="display:inline-block; color:orange; " onClick="alterAnnouncement({$activeThread['id']}, 'Are you sure you want to remove this thread as an announcement?', 'remove_announcement')" title="Remove thread from announcements"><i class="fa fa-star" onmouseleave="changeColor(this, 'gold')" onmouseover="changeColor(this, '#e0e0e0')" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
                    } else if($activeThreadAnnouncement){
                        $title_html .= <<<HTML
						 <i class="fa fa-star" style="position:relative; display:inline-block; color:gold; -webkit-text-stroke-width: 1px; -webkit-text-stroke-color: black;" aria-hidden="true"></i>
HTML;
                    } else if($this->core->getUser()->getGroup() <= 2 && !$activeThreadAnnouncement){
                        $title_html .= <<<HTML
							<a style="position:relative; display:inline-block; color:orange; " onClick="alterAnnouncement({$activeThread['id']}, 'Are you sure you want to make this thread an announcement?', 'make_announcement')" title="Make thread an announcement"><i class="fa fa-star" onmouseleave="changeColor(this, '#e0e0e0')" onmouseover="changeColor(this, 'gold')" style="position:relative; display:inline-block; color:#e0e0e0; -webkit-text-stroke-width: 1px;
    -webkit-text-stroke-color: black;" aria-hidden="true"></i></a>
HTML;
                    }
                    $title_html .= <<< HTML
					{$activeThreadTitle}</h3>
HTML;
					$first = true;
					$first_post_id = 1;
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
								
								$return .= $this->createPost($thread_id, $post, $function_date, $title_html, $first, $reply_level);
								break;
							}
							
						}
						if($first){
							$first= false;
						}
						$i++;
					}

			$return .= <<<HTML
			
					<form style="margin-right:17px;" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}" enctype="multipart/form-data">
						<input type="hidden" name="thread_id" value="{$thread_id}" />
	            		<br/>
	            		<div class="form-group row">
	            			<textarea name="post_content" onclick="hideReplies();" id="post_content" style="white-space: pre-wrap;resize:none;height:100px;width:100%;" rows="10" cols="30" placeholder="Enter your reply here..." required></textarea>
	            		</div>

	            		<br/>

	           			<span style="float:left;display:inline-block;">
            				<label id="file_input_label" class="btn btn-primary" for="file_input">
    						<input id="file_input" name="file_input[]" accept="image/*" type="file" style="display:none" onchange="checkNumFilesForumUpload(this)" multiple>
    						Upload Attachment
							</label>
							<span class='label label-info' id="file_name"></span>
						</span>

	            		<div style="margin-bottom:20px;float:right;" class="form-group row">
	            			<label style="display:inline-block;" for="Anon">Anonymous?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" value="Anon" /><input type="submit" style="display:inline-block;" name="post" value="Reply" class="btn btn-primary" />
	            		</div>
	            	</form>
	            	<br/>

					</div>

				</div>
				</div>
HTML;
		}

		$return .= <<<HTML
	<script>
		var codeSegments = document.querySelectorAll("[id=code]");
		for (let element of codeSegments){
			var editor0 = CodeMirror.fromTextArea(element, {
            lineNumbers: true,
            readOnly: true,
            cursorHeight: 0.0,
            lineWrapping: true
	    });

	    var lineCount = editor0.lineCount();
	    if (lineCount == 1) {
	        editor0.setSize("100%", (editor0.defaultTextHeight() * 2) + "px");
	    }
	    else {
	        editor0.setSize("100%", "auto");
	    }
	    editor0.setOption("theme", "eclipse");
	    editor0.refresh(); 
		}
			
	    </script>
HTML;

if(isset($_SESSION["post_content"]) && isset($_SESSION["post_recover_active"])){
			
	$post_content = html_entity_decode($_SESSION["post_content"]);

	$return .= <<<HTML
			<script>
				var contentBox = document.getElementById('post_content');
				contentBox.innerHTML = `{$post_content}`;
				document.getElementById('file_input').value = null;
				var box = $('.posts_list');
				box.scrollTop(box.prop('scrollHeight'));
			</script>
HTML;
		$_SESSION["post_recover_active"] = null;
}

		return $return;
	}

	public function createPost($thread_id, $post, $function_date, $title_html, $first, $reply_level){
		$post_html = "";
		$post_id = $post["id"];
		$thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);

		$date = date_create($post["timestamp"]);
		$full_name = $this->core->getQueries()->getDisplayUserNameFromUserId($post["author_user_id"]);
		$first_name = htmlentities(trim($full_name["first_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$last_name = htmlentities(trim($full_name["last_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$visible_username = $first_name . " " . substr($last_name, 0 , 1) . ".";

		if($post["anonymous"]){
			$visible_username = "Anonymous";
		} 
		$classes = "post_box";						
		
		if($first){
			$classes .= " first_post";
		}

		if($this->core->getQueries()->isStaffPost($post["author_user_id"])){
			$classes .= " important";
		}

		$offset = ($reply_level-1)*30;
							$return = <<<HTML
								<div class="$classes" id="$post_id" style="margin-left:{$offset}px;" reply-level="$reply_level">
HTML;


						if($first){
                            $return .= $title_html;
                        } 

                        //handle converting links 


                        //convert legacy htmlentities being saved in db
                        $post_content = html_entity_decode($post["content"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
                        			$pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', '<a href="' . $decoded_url . '" target="_blank" rel="noopener nofollow">'. $result[2][$pos] .'</a>', $post_content, 1);

                        		} else {
                        			$pre_post = preg_replace('#\&lbrack;url&equals;(.*?)&rsqb;(.*?)(&lbrack;&sol;url&rsqb;)#', htmlentities(htmlspecialchars($decoded_url), ENT_QUOTES | ENT_HTML5, 'UTF-8'), $post_content, 1);
                        		}
                        		if(!empty($pre_post)){
                        			$post_content = $pre_post;
                        		} $pre_post = "";
                        		 $pos++;
                        	}
                        }

                        //This code is for legacy posts that had an extra \r per newline
                        if(strpos($post['content'], "\r") !== false){
                        	$post_content = str_replace("\r","", $post_content);
                        }

                        //end link handling

                        //handle converting code segments

                        $codeBracketString = "&lbrack;&sol;code&rsqb;";
                        if(strpos($post_content, "&NewLine;&lbrack;&sol;code&rsqb;") !== false){
                        	$codeBracketString = "&NewLine;" . $codeBracketString;
                        }

                        $post_content = str_replace($codeBracketString, '</textarea>', str_replace('&lbrack;code&rsqb;', '<textarea id="code">', $post_content));

						//end code segment handling

						$return .= <<<HTML
							<pre><p class="post_content" style="white-space: pre-wrap; ">{$post_content}</p></pre>
							
							
							<hr style="margin-bottom:3px;">

HTML;
							if(!$first){
								$return .= <<<HTML
								<a class="btn btn-primary btn-sm" style=" text-decoration: none;" onClick="replyPost({$post['id']})"> Reply</a>
HTML;
							} else {
								$first = false;
							}

							$return .= <<<HTML
							<span style="margin-top:8px;margin-left:10px;float:right;">

							
HTML;

if($this->core->getUser()->getGroup() <= 2){
						$info_name = $first_name . " " . $last_name . " (" . $post['author_user_id'] . ")";
						$visible_user_json = json_encode($visible_username);
						$info_name = json_encode($info_name);
						$jscriptAnonFix = $post['anonymous'] ? 'true' : 'false' ;
						$jscriptAnonFix = json_encode($jscriptAnonFix);
						$return .= <<<HTML
						<a style=" margin-right:2px;display:inline-block; color:black; " onClick='changeName(this.parentNode, {$info_name}, {$visible_user_json}, {$jscriptAnonFix})' title="Show full user information"><i class="fa fa-eye" aria-hidden="true"></i></a>
HTML;
}

						if($this->core->getUser()->getGroup() <= 2){
							$wrapped_content = json_encode($post['content']);
							$return .= <<<HTML

							<a class="post_button" style="bottom: 1px;position:relative; display:inline-block; color:red; float:right;" onClick="deletePost( {$post['thread_id']}, {$post['id']}, '{$post['author_user_id']}', '{$function_date($date,'m/d/Y g:i A')}' )" title="Remove post"><i class="fa fa-times" aria-hidden="true"></i></a>
							<a class="post_button" style="position:relative; display:inline-block; color:black; float:right;" onClick="editPost({$post['id']}, {$post['thread_id']})" title="Edit post"><i class="fa fa-edit" aria-hidden="true"></i></a>
HTML;
							} 
			$return .= <<<HTML
			
<h7 style="position:relative; right:5px;"><strong id="post_user_id">{$visible_username}</strong> {$function_date($date,"m/d/Y g:i A")} </h7></span>
HTML;

						if($post["has_attachment"]){
							$post_dir = FileUtils::joinPaths($thread_dir, $post["id"]);
							$files = FileUtils::getAllFiles($post_dir);
							foreach($files as $file){
								$path = rawurlencode($file['path']);
								$name = rawurlencode($file['name']);
								$name_display = htmlentities(rawurldecode($file['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
								$return .= <<<HTML
							<a href="#" style="text-decoration:none;display:inline-block;white-space: nowrap;" class="btn-default btn-sm" onclick="openFile('forum_attachments', '{$name}', '{$path}')" > {$name_display} </a>
HTML;

							}
							
						}
						$offset = $offset + 30;
						$return .= <<<HTML
</div>

           	<form class="reply-box" id="$post_id-reply" style="margin-left:{$offset}px" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}" enctype="multipart/form-data">
						<input type="hidden" name="thread_id" value="{$thread_id}" />
						<input type="hidden" name="parent_id" value="{$post_id}" />
	            		<br/>
	            		<div class="form-group row">
	            			<textarea name="post_content" id="post_content" style="white-space: pre-wrap;resize:none;height:100px;width:100%;" rows="10" cols="30" placeholder="Enter your reply here..." required></textarea>
	            		</div>

	            		<br/>

	           			<span style="float:left;display:inline-block;">
            				<label id="file_input_label_{$post_id}" class="btn btn-primary" for="file_input_{$post_id}">
    						<input id="file_input_{$post_id}" name="file_input_{$post_id}[]" accept="image/*" type="file" style="display:none" onchange="checkNumFilesForumUpload(this, '{$post_id}')" multiple>
    						Upload Attachment
							</label>
							<span class='label label-info' id="file_name_{$post_id}"></span>
						</span>

	            		<div style="margin-bottom:20px;float:right;" class="form-group row">
	            			<label style="display:inline-block;" for="Anon">Anonymous?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" value="Anon" /><input type="submit" style="display:inline-block;" name="post" value="Reply" class="btn btn-primary" />
	            		</div>
	            	</form>
HTML;

		return $return;
	}

	public function createThread() {

		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		$this->core->getOutput()->addBreadcrumb("Discussion Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$this->core->getOutput()->addBreadcrumb("Create Thread", $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
		$return = <<<HTML

		<script> 
			$( document ).ready(function() {
			    enableTabsInTextArea('thread_content');
			});
		 </script>

		<div style="margin-top:5px;background-color:transparent; margin: !important auto;padding:0px;box-shadow: none;" class="content">

		<div style="margin-top:10px; height:50px;" id="forum_bar">
			<div style="margin-left:20px; height: 20px; width:148px;" class="create_thread_button">

			<a class="btn btn-primary" style="position:absolute;top:3px;left:2px;vertical-align: middle;" title="Back to threads" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}"><i class="fa fa-arrow-left"></i> Back to Threads</a>
			</div>
		</div>

		<div style="padding-left:20px;padding-top:1vh; padding-bottom: 10px;height:69vh;border-radius:3px;box-shadow: 0 2px 15px -5px #888888;padding-right:20px;background-color: #E9EFEF;" id="forum_wrapper">

		<h3> Create Thread </h3>

			<form style="padding-right:15px;margin-top:15px;margin-left:10px;height:63vh;overflow-y: auto" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_thread'))}" enctype="multipart/form-data">

            	<div class="form-group row">
            		Title: <input type="text" size="45" placeholder="Title" name="title" id="title" required/>
            	</div>
            	<br/>
            	<div style="margin-bottom:10px;" class="form-group row">
            		<button type="button" title="Insert a link" onclick="addBBCode(1, '#thread_content')" style="margin-right:10px;" class="btn btn-primary">Link <i class="fa fa-link fa-1x"></i></button><button title="Insert a code segment" type="button" onclick="addBBCode(0, '#thread_content')" class="btn btn-primary">Code <i class="fa fa-code fa-1x"></i></button>
            	</div>
            	<div class="form-group row">
            		<textarea name="thread_content" id="thread_content" style="resize:none;min-height:40vmin;overflow:hidden;width:100%;" rows="10" cols="30" placeholder="Enter your post here..." required></textarea>
            	</div>

            	<br/>

            	<div style="margin-bottom:10px;" class="form-group row">

            	<span style="float:left;display:inline-block;">
            	<label id="file_input_label" class="btn btn-primary" for="file_input">
    				<input id="file_input" name="file_input[]" accept="image/*" type="file" style="display:none" onchange="checkNumFilesForumUpload(this)" multiple>
    				Upload Attachment
				</label>
				<span class='label label-info' id="file_name"></span>
				</span>

				<span style="display:inline-block;float:right;">
            	<label for="Anon">Anonymous (to class)?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" value="Anon" />
HTML;
				
				if($this->core->getUser()->getGroup() <= 2){
						$return .= <<<HTML
						<label style="display:inline-block;" for="Announcement">Announcement?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Announcement" value="Announcement" />
HTML;

				}
				$return .= <<<HTML
				<input type="submit" style="display:inline-block;" name="post" value="Post" class="btn btn-primary" />
				</span>
            	</div>

            	<br/>

            </form>
		</div>
		</div>
HTML;

if(isset($_SESSION["thread_title"]) && isset($_SESSION["thread_content"]) && isset($_SESSION["thread_recover_active"])){
	$title = html_entity_decode($_SESSION["thread_title"]);
			
	$thread_content = html_entity_decode($_SESSION["thread_content"]);

	$return .= <<<HTML
			<script>
				var titleBox = document.getElementById('title');
				titleBox.value = `{$title}`;
				var contentBox = document.getElementById('thread_content');
				contentBox.innerHTML = `{$thread_content}`;
				document.getElementById('file_input').value = null;
			</script>
HTML;
		unset($_SESSION["thread_recover_active"]);
}
		return $return;
	}

}