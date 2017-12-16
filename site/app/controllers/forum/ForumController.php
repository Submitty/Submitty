<?php

namespace app\controllers\forum;

use app\libraries\Core;
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
                $this->showHomepage();
                break;
            case 'view_thread':
                $this->showHomepage();
                break;
            case 'home_page':
            default:
                $this->showHomepage();
                break;
        }
    }

}