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
            case 'change_username':
                $this->changeUserName();
                $this->showHomepage();
                break;
            case 'change_password':
                $this->changePassword();
                $this->showHomepage();
                break;
            case 'home_page':
            default:
                $this->showHomepage();
                break;
        }
    }

    public function changePassword(){
        $user = $this->core->getUser();
        if(isset($_POST['new_password']) && isset($_POST['confirm_new_password'])
            && $_POST['new_password'] == $_POST['confirm_new_password']) {
            $user->setPassword($_POST['new_password']);
            $this->core->getQueries()->updateUser($user);
            $this->core->addSuccessMessage("Updated password");
        }
        else {
            $this->core->addErrorMessage("Must put same password in both boxes.");
        }
    }

    public function changeUserName(){
        $user = $this->core->getUser();
        if(isset($_POST['user_name_change']))
        {
            $newName = trim($_POST['user_name_change']);
            if (User::validateUserData('user_preferred_firstname', $newName) === true) {
                if(strlen($newName) <= 30)
                {
                    $user->setPreferredFirstName($newName);
					//User updated flag tells auto feed to not clobber some of the users data.
                    $user->setUserUpdated(true);
                    $this->core->getQueries()->updateUser($user);
                }
                else
                {
                    $this->core->addErrorMessage("Invalid Username. Please use 30 characters or fewer.");
                }
            }
            else
            {
                $this->core->addErrorMessage("Invalid Username.  Letters, spaces, hyphens, apostrophes, periods, and backquotes permitted.");
            }
        }
    }

    /**
     * Display the HomePageView to the student.
     */
    public function showHomepage() {
        $user = $this->core->getUser();
        $submitty_path = $this->core->getConfig()->getSubmittyPath();
        $courses = $this->core->getQueries()->getStudentCoursesById($user->getId(), $submitty_path);
        $changeNameText = $this->core->getConfig()->getUsernameChangeText();
        $this->core->getOutput()->renderOutput('HomePage', 'showHomePage', $user, $courses, $changeNameText);
    }
}
