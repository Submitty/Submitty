<?php

namespace app\controllers\forum;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\Output;
use app\libraries\Utils;

/**
 * Class ForumHomeController
 *
 * Controller to deal with the submitty home page. Once the user has been authenticated, but before they have
 * selected which course they want to access, they are forwarded to the home page.
 */
class ForumController extends AbstractController {

	/**
     * ForumHomeController constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'create_thread':
                $this->showCreateThread();
                break;
            case 'view_thread':
            case 'home_page':
            default:
                $this->showThreads();
                break;
        }
    }

    public function showThreads(){
        $user = $this->core->getUser();
        $threads = 0;//$this->core->getQueries()->loadThreads();
        $posts = null;
        if(isset($_POST["thread_id"])){
            $posts = $this->core->getQueries()->getPostsForThread($threads);
        } else {
            //We are at the "Home page"
            //Show the first post
            $posts = $this->core->getQueries()->getPostsForThread($threads);
            
        }
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $posts, $threads);
    }

}