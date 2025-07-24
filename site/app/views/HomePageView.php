<?php

namespace app\views;

use app\models\User;

class HomePageView extends AbstractView {
    /**
     * @param User $user
     * @param array $unarchived_courses
     * @param array<array<string, string>> $dropped_courses
     * @param array $archived_courses
     * @param array<mixed> $self_registration_courses
     */
    public function showHomePage(
        User $user,
        array $unarchived_courses,
        array $dropped_courses,
        array $archived_courses,
        array $self_registration_courses,
    ) {
        $statuses = [];
        $course_types = [
            "unarchived_courses" => $unarchived_courses,
            "dropped_courses" => $dropped_courses,
            "self_registration_courses" => $self_registration_courses,
            "archived_courses" => $archived_courses
        ];
        $rank_titles = [
            User::GROUP_INSTRUCTOR              => "Instructor:",
            User::GROUP_FULL_ACCESS_GRADER      => "Full Access Grader:",
            User::GROUP_LIMITED_ACCESS_GRADER   => "Grader:",
            User::GROUP_STUDENT                 => "Student:"
        ];
        foreach ($course_types as $course_type_name => $course_type) {
            $ranks = [];
            //Create rank lists
            for ($i = 1; $i < 5; $i++) {
                $ranks[$i] = [
                    'title' => $rank_titles[$i],
                    'courses' => [],
                ];
            }

            //Assemble courses into rank lists
            foreach ($course_type as $course) {
                $ranks[$course['user_group']]['courses'][] = $course;
            }

            //Filter any ranks with no courses
            $ranks = array_filter($ranks, function ($rank) {
                return count($rank["courses"]) > 0;
            });
            $statuses[$course_type_name] = $ranks;
        }

        $user_id = $this->core->getUser()->getId();
        $courses = $this->core->getQueries()->getCourseForUserId($user_id);
        $notifications = $this->core->getQueries()->getAllRecentUserNotifications($user_id, $courses);

        $this->core->getOutput()->enableMobileViewport();
        $this->output->setPageName('Homepage');
        return $this->output->renderTwigTemplate('Vue.twig', [
            "type" => "page",
            "name" => "HomePage",
            "args" => [
                "statuses" => $statuses,
                "notifications" => $notifications,
            ]
        ]);
    }

    public function showCourseCreationPage($faculty, $head_instructor, $semesters, bool $is_superuser, string $csrf_token, array $courses) {
        $this->output->addBreadcrumb("New Course");
        $course_names = [];
        foreach ($courses as $course) {
            $course_names[] = $course->getTitle();
        }
        $course_names = array_unique($course_names);
        sort($course_names);
        return $this->output->renderTwigTemplate('CreateCourseForm.twig', [
            "csrf_token" => $csrf_token,
            "head_instructor" => $head_instructor,
            "faculty" => $faculty,
            "is_superuser" => $is_superuser,
            "semesters" => $semesters,
            "course_creation_url" => $this->output->buildUrl(['home', 'courses', 'new']),
            "course_code_requirements" => $this->core->getConfig()->getCourseCodeRequirements(),
            "add_term_url" => $this->output->buildUrl(['term', 'new']),
            "courses" => $course_names
        ]);
    }

    public function showSystemUpdatePage(string $csrf_token): string {
        $this->output->addBreadcrumb("System Update");
        return $this->output->renderTwigTemplate('admin/SystemUpdate.twig', [
            "csrf_token" => $csrf_token,
            "latest_tag" => $this->core->getConfig()->getLatestTag()
        ]);
    }
}
