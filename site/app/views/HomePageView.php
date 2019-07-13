<?php
namespace app\views;

use app\authentication\DatabaseAuthentication;
use app\models\User;



class HomePageView extends AbstractView {


    /*
    *@param List of courses the student is in.
    */
    public function showHomePage(User $user, $unarchived_courses = array(), $archived_courses = array(), $change_name_text) {
        $statuses = array();
        $course_types = [$unarchived_courses, $archived_courses];
        $rank_titles = [
            User::GROUP_INSTRUCTOR              => "Instructor:",
            User::GROUP_FULL_ACCESS_GRADER      => "Full Access Grader:",
            User::GROUP_LIMITED_ACCESS_GRADER   => "Grader:",
            User::GROUP_STUDENT                 => "Student:"
        ];

        foreach($course_types as $course_type) {
            $ranks = array();

            //Create rank lists
            for ($i = 1; $i < 5; $i++){
                $ranks[$i] = [];
                $ranks[$i]["title"] = $rank_titles[$i];
                $ranks[$i]["courses"] = [];
            }

            //Assemble courses into rank lists
            foreach ($course_type as $course) {
                $rank = $this->core->getQueries()->getGroupForUserInClass($course->getSemester(), $course->getTitle(), $user->getId());
                array_push($ranks[$rank]["courses"], $course);
            }

            //Filter any ranks with no courses
            $ranks = array_filter($ranks, function($rank) {
                return count($rank["courses"]) > 0;
            });
            $statuses[] = $ranks;
        }


        $autofill_preferred_name = [$user->getLegalFirstName(),$user->getLegalLastName()];
        if ($user->getPreferredFirstName() != "") {
            $autofill_preferred_name[0] = $user->getPreferredFirstName();
        }
        if ($user->getPreferredLastName() != "") {
            $autofill_preferred_name[1] = $user->getPreferredLastName();
        }

        $access_levels = [
            User::LEVEL_USER        => "user",
            User::LEVEL_FACULTY     => "faculty",
            User::LEVEL_SUPERUSER   => "superuser"
        ];

        $this->core->getOutput()->addInternalCss('homepage.css');
        return $this->core->getOutput()->renderTwigTemplate('HomePage.twig', [
            "user" => $user,
            "user_first" => $autofill_preferred_name[0],
            "user_last" => $autofill_preferred_name[1],
            "statuses" => $statuses,
            "change_name_text" => $change_name_text,
            "show_change_password" => $this->core->getAuthentication() instanceof DatabaseAuthentication,
            "csrf_token" => $this->core->getCsrfToken(),
            "access_level" => $access_levels[$user->getAccessLevel()]
        ]);
    }

    public function showCourseCreationPage($courses, $head_instructor) {
        $base_courses = array_merge($courses['unarchived_courses'], $courses['archived_courses']);
        return $this->core->getOutput()->renderTwigTemplate('CreateCourseForm.twig', [
            "csrf_token" => $this->core->getCsrfToken(),
            "base_courses" => $base_courses,
            "head_instructor" => $head_instructor,
            "course_creation_url" => $this->core->buildNewUrl(['home', 'new_course'])
        ]);
    }
}
