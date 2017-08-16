<?php

namespace app\controllers;

use app\libraries\Core;
use app\libraries\Output;
use app\libraries\Utils;

/**
 * Class HomePageController
 *
 * Controller to deal with the submitty home page. Once the user has been authenticated, but before they have
 * selected which course they want to access, they are forwarded to the home page.
 */
class HomePageController extends AbstractController {
    /**
     * HomePageController constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'home_page':
            default:
                $this->showHomepage();
                break;
        }
    }

    /**
     * Display the HomePageView to the student.
     */
    public function showHomepage() {
        $user = $this->core->getUser();
        $courses = $this->core->getQueries()->getStudentCoursesById($user->getId());
        $this->core->getOutput()->renderOutput('HomePage', 'showHomePage', $user, $courses);
    }
}
