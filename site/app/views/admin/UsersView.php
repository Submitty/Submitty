<?php

namespace app\views\admin;

use app\models\User;
use app\views\AbstractView;

class UsersView extends AbstractView {
    /**
     * @param array  $sorted_students students sorted by registration sections
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param array  $download_info user information for downloading
     * @param bool   $use_database
     * @return string
     */
    public function listStudents($sorted_students, $reg_sections, $rot_sections, $download_info, $use_database = false) {
        $this->core->getOutput()->addBreadcrumb('Manage Students');
        $this->core->getOutput()->addInternalCss('directory.css');
        $this->core->getOutput()->addInternalCss('userform.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalJs('userform.js');
        $this->core->getOutput()->addInternalJs('directory.js');
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate("admin/users/StudentList.twig", [
            "sections" => $sorted_students,
            "reg_sections" => $reg_sections,
            "rot_sections" => $rot_sections,
            "use_database" => $use_database,
            'update_url' => $this->core->buildCourseUrl(['users']) . '?' . http_build_query(['type' => 'users']),
            "return_url_upload_class_list" => $this->core->buildCourseUrl(['users', 'upload']) . '?' . http_build_query(['list_type' => 'classlist']),
            'view_grades_url' => $this->core->buildCourseUrl() . '/users/view_grades',
            "csrf_token" => $this->core->getCsrfToken(),
            "download_info_json" => json_encode($download_info)
        ]);
    }

    /**
     * @param array  $graders_sorted graders sorted by roles
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param array  $download_info grader information for downloading
     * @param bool   $use_database
     * @return string
     */
    public function listGraders($graders_sorted, $reg_sections, $rot_sections, $download_info, $use_database = false) {
        $this->core->getOutput()->addBreadcrumb('Manage Graders');
        $this->core->getOutput()->addInternalCss('directory.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('userform.css');
        $this->core->getOutput()->addInternalJs('userform.js');
        $this->core->getOutput()->addInternalJs('directory.js');
        $this->core->getOutput()->enableMobileViewport();

        return $this->core->getOutput()->renderTwigTemplate("admin/users/GraderList.twig", [
            "graders" => $graders_sorted,
            "groups" => [
                User::GROUP_INSTRUCTOR => [
                    "name" => "Instructor",
                    "all_sections" => true
                ],
                User::GROUP_FULL_ACCESS_GRADER => [
                    "name" => "Full Access Grader (Grad TA)",
                    "all_sections" => false
                ],
                User::GROUP_LIMITED_ACCESS_GRADER => [
                    "name" => "Limited Access Grader (Mentor)",
                    "all_sections" => false
                ]
            ],
            "reg_sections" => $reg_sections,
            "rot_sections" => $rot_sections,
            "use_database" => $use_database,
            "return_url_upload_grader_list" => $this->core->buildCourseUrl(['users', 'upload']) . '?' . http_build_query(['list_type' => 'graderlist']),
            "return_url_assign_reg_sections" => $this->core->buildCourseUrl(['graders', 'assign_registration_sections']),
            'update_url' => $this->core->buildCourseUrl(['users']) . '?' . http_build_query(['type' => 'graders']),
            "csrf_token" => $this->core->getCsrfToken(),
            "download_info_json" => json_encode($download_info)
        ]);
    }

    /**
     * Creates the user form box to be displayed when creating or editing a user on the students/graders pages
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param string $action what action to go to after hitting the submit button (different for student vs grader page)
     * @param bool   $use_database
     * @return string
     */
    public function userForm($reg_sections, $rot_sections, $action, $use_database = false) {
        return $this->core->getOutput()->renderTwigTemplate("admin/users/UserForm.twig", [
            "reg_sections" => $reg_sections,
            "rot_sections" => $rot_sections,
            "action" => $action,
            "use_database" => $use_database
        ]);
    }

    public function sectionsForm($students, $reg_sections, $not_null_counts, $null_counts, $max_section) {
        $this->core->getOutput()->addBreadcrumb('Manage Sections');
        $reg_sections_count = array();
        foreach ($students as $student) {
            $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            if (array_key_exists($registration, $reg_sections_count)) {
                $reg_sections_count[$registration] = $reg_sections_count[$registration] + 1;
            }
            else {
                $reg_sections_count[$registration] = 1;
            }
        }

        $this->core->getOutput()->addInternalCss('rotatingsectionsform.css');

        return $this->core->getOutput()->renderTwigTemplate("admin/users/RotatingSectionsForm.twig", [
            "students" => $students,
            "reg_sections" => $reg_sections,
            "reg_sections_count" => $reg_sections_count,
            "not_null_counts" => $not_null_counts,
            "null_counts" => $null_counts,
            "max_section" => $max_section,
            "update_registration_sections_url" => $this->core->buildCourseUrl(['sections', 'registration']),
            "update_rotating_sections_url" => $this->core->buildCourseUrl(['sections', 'rotating']),
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
