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
		$return = <<<HTML
			<div id="forum_wrapper">
				<div style="width:24%; margin-right:3px; float: left;" class="content">
HTML;
				foreach($threads as $thread){
					$return .= <<<HTML
					<div class="box">
					<b>{$thread["title"]}</b>
					</div>
HTML;
				}
		$return .= <<< HTML
				</div>
				<div style="width:65%; margin-left: 3px; float: right;" class="content">
HTML;
				foreach($posts as $post){
					$return .= <<<HTML
					<div class="box">
					<p>{$post["content"]}</p>
					<p>{$post["timestamp"]}</p>
					<p>{$post["author_user_id"]}</p>
					</div>
HTML;
				}
		$return .= <<<HTML
				</div>
			</div>
HTML;
		return $return;
	}

}