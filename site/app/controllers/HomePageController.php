<?php

namespace app\controllers;

use app\libraries\response\RedirectResponse;
use app\models\Course;
use app\libraries\Core;
use app\libraries\response\Response;
use app\libraries\response\WebResponse;
use app\libraries\response\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

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
                break;
            case 'change_password':
                $this->changePassword();
                break;
            case 'home_page':
            default:
                $this->showHomepage();
                break;
        }
    }

    /**
     * @Route("/home/change_password", methods={"POST"})
     * @return Response
     */
    public function changePassword(){
        $user = $this->core->getUser();
        if(!empty($_POST['new_password']) && !empty($_POST['confirm_new_password'])
            && $_POST['new_password'] == $_POST['confirm_new_password']) {
            $user->setPassword($_POST['new_password']);
            $this->core->getQueries()->updateUser($user);
            $this->core->addSuccessMessage("Updated password");
        }
        else {
            $this->core->addErrorMessage("Must put same password in both boxes.");
        }
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildNewUrl(['home']))
        );
    }

    /**
     * @Route("/home/change_username", methods={"POST"})
     * @return Response
     */
    public function changeUserName(){
        $user = $this->core->getUser();
        if(isset($_POST['user_firstname_change']) && isset($_POST['user_lastname_change'])) {
            $newFirstName = trim($_POST['user_firstname_change']);
            $newLastName = trim($_POST['user_lastname_change']);
            // validateUserData() checks both for length (not to exceed 30) and for valid characters.
            if ($user->validateUserData('user_preferred_firstname', $newFirstName) === true && $user->validateUserData('user_preferred_lastname', $newLastName) === true) {
				$user->setPreferredFirstName($newFirstName);
				$user->setPreferredLastName($newLastName);
				//User updated flag tells auto feed to not clobber some of the user's data.
				$user->setUserUpdated(true);
				$this->core->getQueries()->updateUser($user);
            }
            else {
                $this->core->addErrorMessage("Preferred names must not exceed 30 chars.  Letters, spaces, hyphens, apostrophes, periods, parentheses, and backquotes permitted.");
            }
        }
        return Response::RedirectOnlyResponse(
            new RedirectResponse($this->core->buildNewUrl(['home']))
        );
    }

    /**
     * Display the HomePageView to the student.
     *
     * @Route("/home")
     * @return Response
     */
    public function showHomepage() {
        $courses = $this->getCourses();

        return new Response(
            null,
            new WebResponse(
                ['HomePage'],
                'showHomePage',
                $user = $this->core->getUser(),
                $courses["unarchived_courses"],
                $courses["archived_courses"],
                $this->core->getConfig()->getUsernameChangeText()
            )
        );
    }

    /**
     * @Route("/api/courses")
     * @return Response
     */
    public function getApiCourses() {
        $courses = $this->getCourses();
        return new Response(
            JsonResponse::getSuccessResponse([
                "unarchived_courses" => array_map(
                    function(Course $course) {
                        return $course->getCourseInfo();
                    },
                    $courses["unarchived_courses"]
                ),
                "archived_courses" => array_map(
                    function(Course $course) {
                        return $course->getCourseInfo();
                    },
                    $courses["archived_courses"]
                )
            ])
        );
    }

    private function getCourses() {
        $user = $this->core->getUser();
        $unarchived_courses = $this->core->getQueries()->getUnarchivedCoursesById($user->getId());
        $archived_courses = $this->core->getQueries()->getArchivedCoursesById($user->getId());

        // Filter out any courses a student has dropped so they do not appear on the homepage.
        // Do not filter courses for non-students.

        $unarchived_courses = array_filter($unarchived_courses, function(Course $course) use($user) {
            return $this->core->getQueries()->checkStudentActiveInCourse($user->getId(), $course->getTitle(), $course->getSemester());
        });
        $archived_courses = array_filter($archived_courses, function(Course $course) use($user) {
            return $this->core->getQueries()->checkStudentActiveInCourse($user->getId(), $course->getTitle(), $course->getSemester());
        });

        return [
            "unarchived_courses" => $unarchived_courses,
            "archived_courses" => $archived_courses
        ];
    }
}
