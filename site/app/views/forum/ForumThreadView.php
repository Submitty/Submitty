<?php
namespace app\views\forum;

use app\authentication\DatabaseAuthentication;
use app\views\AbstractView;
use app\models\Course;



class ForumThreadView extends AbstractView {

	
	/** Shows Forums thread splash page, including all posts
		for a specific thread, in addition to all of the threads
		that have been created to be displayed in the left panel.
	*/
	public function showForumThreads($user, $posts, $threads) {
		$this->core->getOutput()->addBreadcrumb("Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$return = <<<HTML

		<div style="background-color:transparent; margin: !important auto;padding:3px;" class="content">

		<div style="margin-bottom:10px; height:50px; " id="forum_bar">
			<div style="float:right; height:50px;width:50px;" class="create_thread_button"><a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'))}">Create Thread</a>
			</div>
		</div>

HTML;
		if(count($threads) == 0){
			//throw new Exception("dfks");
		$return .= <<<HTML
					<div style="margin: !important auto;padding:25px; text-align:center;" class="content">
						<h4>A thread hasn't been created yet. Be the first to do so!</h4>
					</div>
				</div>
HTML;
		} else {


			$return .= <<<HTML
				<div style="clear:both;" id="forum_wrapper">
					<div style="display:inline-block;width:25%; margin-right:2px; height:100%; float: left;" class="thread_list">
HTML;
					$used_active = false; //used for the first one if there is not thread_id set
					foreach($threads as $thread){
						$class = "box";
						if(!isset($_REQUEST["thread_id"]) && !$used_active){
							$class .= " active";
							$used_active = true;
						} else if(isset($_REQUEST["thread_id"]) && $_REQUEST["thread_id"] == $thread["id"])
							$class .= " active";
						$return .= <<<HTML
						<div class="{$class}" style="height:100%;margin-left:0px;">
						<b><a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread['id']))}">{$thread["title"]}</a></b>
						</div>
HTML;
					}

			$thread_id = -1;
			$return .= <<< HTML
					</div>
					<div style="display:inline-block;width:70%; margin-left: 2px; float: right;" class="posts_list">
HTML;
					foreach($posts as $post){
						
						if($thread_id == -1) {
							$thread_id = $post["thread_id"];
						}
						$return .= <<<HTML



						<div class="box" style="margin-left:0;">
						<p>{$post["content"]}</p>
						<p>{$post["timestamp"]}</p>
						<p>{$post["author_user_id"]}</p>
						</div>
HTML;
					}
			$return .= <<<HTML
					
					<form style="margin:10px;" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_post'))}">
					<input type="hidden" name="thread_id" value="{$thread_id}" />
	            	<br/>
	            	<div class="form-group row">
	            		<textarea name="post_content" style="resize:none;height:100px;width:100%;" rows="10" cols="30" placeholder="Enter your reply here..." required></textarea>
	            	</div>

	            	<br/>

	            	<div style="margin-bottom:10px;float:right;" class="form-group row">
	            		<label style="display:inline-block;" for="Anon">Anonymous?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" /><input type="submit" style="display:inline-block;" name="post" value="Reply" class="btn btn-primary" />
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
		$this->core->getOutput()->addBreadcrumb("Forum", $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
		$this->core->getOutput()->addBreadcrumb("Create Thread", $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
		$return = <<<HTML

		<div style="margin-bottom:10px; height:50px; " id="forum_bar">
			<div style="float:right; height:50px;width:50px;" class="create_thread_button"><a href="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread'))}">LINK</a>
			</div>
		</div>

		<div style="margin: !important auto; padding:20px;" class="content">

			<form style="margin:10px;" method="POST" action="{$this->core->buildUrl(array('component' => 'forum', 'page' => 'publish_thread'))}">

            	<div class="form-group row">
            		Title: <input type="text" placeholder="Title" name="title" required/>
            	</div>
            	<br/>
            	<div class="form-group row">
            		<textarea name="thread_content" style="resize:none;height:500px;width:100%;" rows="10" cols="30" placeholder="Enter your post here..." required></textarea>
            	</div>

            	<br/>

            	<div style="margin-bottom:10px;float:right;" class="form-group row">
            		<label style="display:inline-block;" for="Anon">Anonymous?</label> <input type="checkbox" style="margin-right:15px;display:inline-block;" name="Anon" /><input type="submit" style="display:inline-block;" name="post" value="Post" class="btn btn-primary" />
            	</div>

            	<br/>

            </form>
		</div>
HTML;
		return $return;
	}

}