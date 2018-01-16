<?php
namespace app\views\forum;

use app\authentication\DatabaseAuthentication;
use app\views\AbstractView;
use app\models\Course;



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

		$this->core->getOutput()->addBreadcrumb("Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		
		//Body Style is necessary to make sure that the forum is still readable...
		$return = <<<HTML
		<style>body {min-width: 925px;}</style>

		<div style="margin-top:5px;background-color:transparent; margin: !important auto;padding:0px;box-shadow: none;" class="content">

		<div style="margin-top:10px; margin-bottom:10px; height:50px;  " id="forum_bar">
			<div style="margin-left:20px; height: 50px; width:50px;" class="create_thread_button"><a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))}"><i style="vertical-align: middle; position: absolute; margin-top: 9px; margin-left: 11px;" class="fa fa-plus-circle fa-2x" aria-hidden="true"></i></a>
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
					$current_user = $this->core->getUser()->getId();
					$start = 0;
					$end = 10;
					foreach($threads as $thread){
						$first_post = $this->core->getQueries()->getFirstPostForThread($thread["id"]);
						$date = date_create($first_post['timestamp']);
						$class = "thread_box";
						if(!isset($_REQUEST["thread_id"]) && !$used_active){
							$class .= " active";
							$used_active = true;
							$activeThreadTitle = $thread["title"];
						} else if(isset($_REQUEST["thread_id"]) && $_REQUEST["thread_id"] == $thread["id"]) {
							$class .= " active";
							$activeThreadTitle = $thread["title"];
						}
						if($this->core->getQueries()->viewedThread($current_user, $thread["id"])){
							$class .= " viewed";
						}
						$contentDisplay = substr($first_post["content"], 0, 80);
						$titleDisplay = substr($thread["title"], 0, 30);
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
							<i class="fa fa-star" style="position:relative; float:right; display:inline-block; color:yellow; -webkit-text-stroke-width: 1px;
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
			$function_content = 'nl2br';
			$userAccessToAnon = ($this->core->getUser()->getGroup() < 4) ? true : false;
			$return .= <<< HTML
					</div>
					<div style="display:inline-block;width:70%; float: right;" class="posts_list">
					<h3 style="word-wrap: break-word;margin-top:20px;">{$activeThreadTitle}</h3>
HTML;
					foreach($posts as $post){
						
						if($thread_id == -1) {
							$thread_id = $post["thread_id"];
						}
						$date = date_create($post["timestamp"]);

						if($post["anonymous"] == true){
							if($userAccessToAnon){
								$visible_username = "<a onClick=\"changeName(this, '{$post["author_user_id"]}')\" id=\"anonUser\">Anonymous</a>";
								$return .= <<<HTML
								<script>
								function changeName(element, user){
									if(element.innerHTML.indexOf("Anonymous") != -1) {
										element.innerHTML = user;
									} else {
										element.innerHTML = 'Anonymous';
									}
									
								}
								</script>
HTML;
							} else {
								$visible_username = "Anonymous";
							}
						} else {
							$visible_username = $post["author_user_id"];
						}

						$return .= <<<HTML



							<div class="post_box" style="margin-left:0;">
HTML;
						if($this->core->getUser()->accessAdmin()){
							$return .= <<<HTML
							<a style="position:relative; display:inline-block; color:red; float:right;" onClick="deletePost( {$post['thread_id']}, {$post['id']}, '{$post['author_user_id']}', '{$function_date($date,'m/d/Y g:i A')}' )"><i class="fa fa-times" aria-hidden="true"></i></a>
HTML;
						}
						$return .= <<<HTML
							<p>{$function_content($post["content"])}</p>
							<h7 style="float:right;"><strong>{$visible_username}</a></strong> {$function_date($date,"m/d/Y g:i A")}</h7>
							
							</div>
HTML;
						
					}
			$return .= <<<HTML
					
					<form style="margin:20px;" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}">
					<input type="hidden" name="thread_id" value="{$thread_id}" />
	            	<br/>
	            	<div class="form-group row">
	            		<textarea name="post_content" style="white-space: pre-wrap;resize:none;height:100px;width:100%;" rows="10" cols="30" placeholder="Enter your reply here..." required></textarea>
	            	</div>

	            	<br/>

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
		return $return;
	}

	public function createThread() {

		if(!$this->forumAccess()){
			$this->core->redirect($this->core->buildUrl(array('component' => 'navigation')));
			return;
		}

		$this->core->getOutput()->addBreadcrumb("Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$this->core->getOutput()->addBreadcrumb("Create Thread", $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
		$return = <<<HTML

		<div style="margin-top:5px;background-color:transparent; margin: !important auto;padding:0px;box-shadow: none;" class="content">

		<div style="margin-top:10px; margin-bottom:10px; height:50px;  " id="forum_bar">
			<div style="margin-left:20px; height:50px; width:50px;" class="create_thread_button"><a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}"><i style="vertical-align: middle; position: absolute; margin-top: 8px; margin-left: 10px;" class="fa fa-arrow-left fa-2x" aria-hidden="true"></i></a>
			</div>
		</div>

		<div style="padding-left:20px;padding-top:1vh;height:69vh;border-radius:3px;box-shadow: 0 2px 15px -5px #888888;padding-right:20px;background-color: #E9EFEF;" id="forum_wrapper">

		<h3> Create Thread </h3>

			<form style="padding-right:15px;margin-top:15px;margin-left:10px;height:63vh;overflow-y: auto" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_thread'))}">

            	<div class="form-group row">
            		Title: <input type="text" maxlength="50" size="40" placeholder="Title" name="title" required/>
            	</div>
            	<br/>
            	<div class="form-group row">
            		<textarea name="thread_content" style="white-space: pre-wrap;resize:none;height:50vh;width:100%;" rows="10" cols="30" placeholder="Enter your post here..." required></textarea>
            	</div>

            	<br/>

            	<div style="margin-bottom:10px;float:right;" class="form-group row">
HTML;

				if($this->core->getUser()->getGroup() >= 3){
					$return .= <<<HTML
					<label style="display:inline-block;" for="Anon">Anonymous?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" value="Anon" /><input type="submit" style="display:inline-block;" name="post" value="Post" class="btn btn-primary" />
HTML;

				} else {
						$return .= <<<HTML
						<label style="display:inline-block;" for="Announcement">Announcement?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Announcement" value="Announcement" /><input type="submit" style="display:inline-block;" name="post" value="Post" class="btn btn-primary" />
HTML;

				}
				$return .= <<<HTML
            	</div>

            	<br/>

            </form>
		</div>
		</div>
HTML;
		return $return;
	}

}