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

		<div style="margin-top:10px; margin-bottom:10px; height:50px;  " id="forum_bar">
			<div style="margin-left:20px; height: 50px; width:50px;" class="create_thread_button"><a title="Create thread" href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))}"><i style="vertical-align: middle; position: absolute; margin-top: 9px; margin-left: 11px;" class="fa fa-plus-circle fa-2x" aria-hidden="true"></i></a>
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
						$first_post_content = str_replace("&lbrack;&sol;code&rsqb;", "", str_replace("&lbrack;code&rsqb;", "", strip_tags($first_post["content"])));
						$sizeOfContent = strlen($first_post_content);
						$contentDisplay = substr($first_post_content, 0, ($sizeOfContent < 80) ? $sizeOfContent : strpos($first_post_content, " ", 70));
						$titleLength = strlen($thread['title']);
						$titleDisplay = substr($thread["title"], 0, ($titleLength < 30) ? $titleLength : strpos($thread['title'], " ", 29));
						if(strlen($first_post["content"]) > 80){
							$contentDisplay .= "...";
						}
						if(strlen($thread["title"]) > 30){
							$titleDisplay .= "...";
						}
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
					foreach($posts as $post){
						
						if($thread_id == -1) {
							$thread_id = $post["thread_id"];
							$thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);
						}
						$date = date_create($post["timestamp"]);

						$full_name = $this->core->getQueries()->getDisplayUserNameFromUserId($post["author_user_id"]);
						$first_name = htmlentities(trim($full_name["first_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$last_name = htmlentities(trim($full_name["last_name"]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

						if($post["anonymous"]){
							$visible_username = "Anonymous";
						} else {
							$visible_username = $first_name . " " . substr($last_name, 0 , 1);
						}

						$classes = "post_box";

						if($first){
						    $classes .= " first_post";
                        }

						if($this->core->getQueries()->isStaffPost($post["author_user_id"])){
							$classes .= " important";
						}

                        $return .= <<<HTML
							<div class="$classes" style="margin-left:0;">
HTML;
						if($first){
                            $first = false;
                            $return .= $title_html;
                        }

                        $codeBracketString = "&lbrack;&sol;code&rsqb;";
                        if(strpos($post['content'], "&NewLine;&lbrack;&sol;code&rsqb;") !== false){
                        	$codeBracketString = "&NewLine;" . $codeBracketString;
                        }


                        $post_content = str_replace($codeBracketString, '</textarea>', str_replace('&lbrack;code&rsqb;', '<textarea id="code">', $post["content"]));

                        //This code is for legacy posts that had an extra \r per newline
                        if(strpos($post['content'], "\r") !== false){
                        	$post_content = str_replace("\r","", $post_content);
                        }

						if($this->core->getUser()->getGroup() <= 2){
							$return .= <<<HTML
							<a class="post_button" style="position:absolute; display:inline-block; color:red; float:right;" onClick="deletePost( {$post['thread_id']}, {$post['id']}, '{$post['author_user_id']}', '{$function_date($date,'m/d/Y g:i A')}' )" title="Remove post"><i class="fa fa-times" aria-hidden="true"></i></a>
HTML;
							} 
						
						$return .= <<<HTML
							<pre><p class="post_content" style="white-space: pre-wrap; ">{$post_content}</p></pre>
							
							
							<hr style="margin-bottom:3px;"><span style="margin-top:5px;margin-left:10px;float:right;">
							
HTML;

if($this->core->getUser()->getGroup() <= 2){
						$info_name = $first_name . $last_name . " (" . $post['author_user_id'] . ")";
						$visible_user_json = json_encode($visible_username);
						$info_name = json_encode($info_name);
						$jscriptAnonFix = $post['anonymous'] ? 'true' : 'false' ;
						$jscriptAnonFix = json_encode($jscriptAnonFix);
						$return .= <<<HTML
						<a style=" margin-right:2px;display:inline-block; color:black; " onClick='changeName(this.parentNode, {$info_name}, {$visible_user_json}, {$jscriptAnonFix})' title="Show full user information"><i class="fa fa-eye" aria-hidden="true"></i></a>
HTML;
}
			$return .= <<<HTML
			
<h7><strong id="post_user_id">{$visible_username}</strong> {$function_date($date,"m/d/Y g:i A")}</h7></span>
HTML;

						if($post["has_attachment"]){
							$post_dir = FileUtils::joinPaths($thread_dir, $post["id"]);
							$files = FileUtils::getAllFiles($post_dir);
							foreach($files as $file){
								$path = rawurlencode(htmlspecialchars($file['path']));
								$name = rawurlencode(htmlspecialchars($file['name']));
								$name_display = htmlentities(rawurldecode($file['name']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
								$return .= <<<HTML
							<a href="#" style="text-decoration:none;display:inline-block;white-space: nowrap;" class="btn-default btn-sm" onclick="openFile('forum_attachments', '{$name}', '{$path}')" > {$name_display} </a>
HTML;

							}
							
						}
						$return .= <<<HTML
</div>
HTML;
						
					}

			$return .= <<<HTML
			
					<form style="margin:20px;" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}" enctype="multipart/form-data">
					<input type="hidden" name="thread_id" value="{$thread_id}" />
	            	<br/>
	            	<div style="margin-bottom:10px;" class="form-group row">
            		<button type="button" title="Insert a link" onclick="addBBCode(1, '#post_content')" style="margin-right:10px;" class="btn btn-primary">Link <i class="fa fa-link fa-1x"></i></button><button title="Insert a code segment" type="button" onclick="addBBCode(0, '#post_content')" class="btn btn-primary">Code <i class="fa fa-code fa-1x"></i></button>
            		</div>
	            	<div class="form-group row">
	            		<textarea name="post_content" id="post_content" style="white-space: pre-wrap;resize:none;overflow:hidden;min-height:100px;width:100%;" rows="10" cols="30" placeholder="Enter your reply here..." required></textarea>
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
	            		<label style="display:inline-block;" for="Anon">Anonymous (to class)?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" value="Anon" /><input type="submit" style="display:inline-block;" name="post" value="Reply" class="btn btn-primary" />
	            	</div>
	            	</form>
	            	<br/>

					</div>

				</div>
				</div>
HTML;
		}

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

		<div style="margin-top:10px; margin-bottom:10px; height:50px;  " id="forum_bar">
			<div style="margin-left:20px; height:50px; width:50px;" class="create_thread_button"><a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}"><i style="vertical-align: middle; position: absolute; margin-top: 8px; margin-left: 10px;" class="fa fa-arrow-left fa-2x" aria-hidden="true"></i></a>
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