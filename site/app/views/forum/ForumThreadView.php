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
				<div style="width:30%; float: left;" class="content">
HTML;
				foreach($threads as $thread){
					$return .= <<<HTML
					<div class="box">
					<p>Thread</p>
					</div>
HTML;
				}
		$return .= <<< HTML
				</div>
				<div style="width:65%; float: right;" class="content">
HTML;
				foreach($posts as $post){
					$return .= <<<HTML
					<div class="box">
					<p>Post</p>
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