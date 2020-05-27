<?php

namespace app\views;

use app\libraries\DateUtils;
use app\models\User;

class HomePageView extends AbstractView {
    /**
     * @param User $user
     * @param array $unarchived_courses
     * @param array $archived_courses
     * @param string $change_name_text
     */
    public function showHomePage(
        User $user,
        array $unarchived_courses,
        array $archived_courses,
        string $change_name_text,
        bool $database_authentication,
        string $csrf_token
    ) {
        $statuses = array();
        $course_types = [$unarchived_courses, $archived_courses];
        $rank_titles = [
            User::GROUP_INSTRUCTOR              => "Instructor:",
            User::GROUP_FULL_ACCESS_GRADER      => "Full Access Grader:",
            User::GROUP_LIMITED_ACCESS_GRADER   => "Grader:",
            User::GROUP_STUDENT                 => "Student:"
        ];

        foreach ($course_types as $course_type) {
            $ranks = array();

            //Create rank lists
            for ($i = 1; $i < 5; $i++) {
                $ranks[$i] = [];
                $ranks[$i]["title"] = $rank_titles[$i];
                $ranks[$i]["courses"] = [];
            }

            //Assemble courses into rank lists
            foreach ($course_type as $course) {
                array_push($ranks[$course['user_group']]["courses"], $course);
            }

            //Filter any ranks with no courses
            $ranks = array_filter($ranks, function ($rank) {
                return count($rank["courses"]) > 0;
            });
            $statuses[] = $ranks;
        }


        $autofill_preferred_name = [$user->getLegalFirstName(), $user->getLegalLastName()];
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
        $this->output->addInternalJs('homepage.js');
        $this->output->addInternalCss('homepage.css');
        $this->core->getOutput()->enableMobileViewport();
        $this->output->setPageName('Homepage');
        return $this->output->renderTwigTemplate('HomePage.twig', [
            "user" => $user,
            "user_first" => $autofill_preferred_name[0],
            "user_last" => $autofill_preferred_name[1],
            "statuses" => $statuses,
            "change_name_text" => $change_name_text,
            "show_change_password" => $database_authentication,
            "csrf_token" => $csrf_token,
            "access_level" => $access_levels[$user->getAccessLevel()],
            "display_access_level" => $user->accessFaculty(),
            "change_password_url" => $this->output->buildUrl(['current_user', 'change_password']),
            "change_username_url" => $this->output->buildUrl(['current_user', 'change_username']),
            'available_time_zones' => implode(',', DateUtils::getAvailableTimeZones()),
            'user_time_zone' => $user->getTimeZone(),
            'user_utc_offset' => DateUtils::getUTCOffset($user->getTimeZone())
        ]);
    }

    public function showCourseCreationPage($faculty, $head_instructor, $semesters, bool $is_superuser, string $csrf_token) {
        $this->output->addBreadcrumb("New Course");
        return $this->output->renderTwigTemplate('CreateCourseForm.twig', [
            "csrf_token" => $csrf_token,
            "head_instructor" => $head_instructor,
            "faculty" => $faculty,
            "is_superuser" => $is_superuser,
            "semesters" => $semesters,
            "course_creation_url" => $this->output->buildUrl(['home', 'courses', 'new']),
            "course_code_requirements" => $this->core->getConfig()->getCourseCodeRequirements()
        ]);
    }
}
