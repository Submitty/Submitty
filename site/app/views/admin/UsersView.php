<?php

namespace app\views\admin;

use app\models\User;
use app\views\AbstractView;

class UsersView extends AbstractView {
    /**
     * @param User[] $students
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param bool   $use_database
     * @return string
     */
    public function listStudents($students, $reg_sections, $rot_sections, $use_database=false) {
        $this->core->getOutput()->addBreadcrumb('Manage Students');
        //Assemble students into sections
        $sections = [];
        foreach ($students as $student) {
            $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            $sections[$registration][] = $student;
        }

        $this->core->getOutput()->addInternalCss('studentlist.css');
        $this->core->getOutput()->addInternalCss('userform.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalJs('userform.js');

        return $this->core->getOutput()->renderTwigTemplate("admin/users/StudentList.twig", [
            "sections" => $sections,
            "reg_sections" => $reg_sections,
            "rot_sections" => $rot_sections,
            "use_database" => $use_database,
            'update_url' => $this->core->buildNewCourseUrl(['users']) . '?' . http_build_query(['type' => 'users']),
            "return_url_upload_class_list" => $this->core->buildNewCourseUrl(['users', 'upload']) . '?' . http_build_query(['list_type' => 'classlist']),
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }

    /**
     * @param User[] $graders
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param bool   $use_database
     * @return string
     */
    public function listGraders($graders, $reg_sections, $rot_sections, $use_database=false) {
        $this->core->getOutput()->addBreadcrumb('Manage Graders');
        $this->core->getOutput()->addInternalCss('userform.css');
        $this->core->getOutput()->addInternalJs('userform.js');

        return $this->core->getOutput()->renderTwigTemplate("admin/users/GraderList.twig", [
            "graders" => $graders,
            "groups" => [
                0 => [
                    "name" => "Developer",
                    "all_sections" => true
                ],
                1 => [
                    "name" => "Instructor",
                    "all_sections" => true
                ],
                2 => [
                    "name" => "Full Access Grader (Grad TA)",
                    "all_sections" => false
                ],
                3 => [
                    "name" => "Limited Access Grader (Mentor)",
                    "all_sections" => false
                ]
            ],
            "reg_sections" => $reg_sections,
            "rot_sections" => $rot_sections,
            "use_database" => $use_database,
            "return_url_upload_grader_list" => $this->core->buildNewCourseUrl(['users', 'upload']) . '?' . http_build_query(['list_type' => 'graderlist']),
            "return_url_assign_reg_sections" => $this->core->buildNewCourseUrl(['graders', 'assign_registration_sections']),
            'update_url' => $this->core->buildNewCourseUrl(['users']) . '?' . http_build_query(['type' => 'graders']),
            "csrf_token" => $this->core->getCsrfToken()
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
    public function userForm($reg_sections, $rot_sections, $action, $use_database=false) {
        return $this->core->getOutput()->renderTwigTemplate("admin/users/UserForm.twig", [
            "reg_sections" => $reg_sections,
            "rot_sections" => $rot_sections,
            "action" => $action,
            "use_database" => $use_database
        ]);
    }


    /**
     * Creates the form box to be displayed when copying or downloading emails on the students/graders pages
     * @param string $code to specify whether it is grader tab or student tab
     * @param User[] $students
     * @param User[] $graders
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param bool   $use_database
     * @return string
     */
    public function downloadForm($code, $students, $graders, $reg_sections, $use_database=false) {
        $download_info = array();
        if ($code === 'user') {
            foreach ($students as $student) {
                $rot_sec = ($student->getRotatingSection() === null) ? 'NULL' : $student->getRotatingSection();
                $reg_sec = ($student->getRegistrationSection() === null) ? 'NULL' : $student->getRegistrationSection();
                switch ($student->getGroup()) {
                    case USER::GROUP_INSTRUCTOR:
                        $grp = 'Instructor';
                        break;
                    case USER::GROUP_FULL_ACCESS_GRADER:
                        $grp = 'Full Access Grader (Grad TA)';
                        break;
                    case USER::GROUP_LIMITED_ACCESS_GRADER:
                        $grp = 'Limited Access Grader (Mentor)';
                        break;
                    default:
                        $grp = 'Student';
                        break;
                }
                $first_name = str_replace("'", "&#039;", $student->getDisplayedFirstName());
                $last_name = str_replace("'", "&#039;", $student->getDisplayedLastName());
                array_push($download_info, ['first_name' => $first_name, 'last_name' => $last_name, 'user_id' => $student->getId(), 'email' => $student->getEmail(), 'reg_section' => "$reg_sec", 'rot_section' => "$rot_sec", 'group' => "$grp"]);
            }
        }
        else if ($code === 'grader') {
            foreach ($graders as $grader) {
                $rot_sec = ($grader->getRotatingSection() === null) ? 'NULL' : $grader->getRotatingSection();
                switch ($grader->getGroup()) {
                    case USER::GROUP_INSTRUCTOR:
                        $reg_sec = 'All';
                        $grp = 'Instructor';
                        break;
                    case USER::GROUP_FULL_ACCESS_GRADER:
                        $grp = 'Full Access Grader (Grad TA)';
                        $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                        break;
                    case USER::GROUP_LIMITED_ACCESS_GRADER:
                        $grp = 'Limited Access Grader (Mentor)';
                        $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                        break;
                    default:
                        $grp = 'UNKNOWN';
                        $reg_sec = "";
                        break;
                }
                $first_name = str_replace("'", "&#039;", $grader->getDisplayedFirstName());
                $last_name = str_replace("'", "&#039;", $grader->getDisplayedLastName());
                array_push($download_info, ['first_name' => $first_name, 'last_name' => $last_name, 'user_id' => $grader->getId(), 'email' => $grader->getEmail(), 'reg_section' => "$reg_sec", 'rot_section' => "$rot_sec", 'group' => $grp]);
            }
        }
        $download_info_json = json_encode($download_info);
        return $this->core->getOutput()->renderTwigTemplate("admin/users/DownloadForm.twig", [
            "reg_sections" => $reg_sections,
            "use_database" => $use_database,
            "code" => $code,
            "download_info_json" => $download_info_json
        ]);
    }

    public function sectionsForm($students, $reg_sections, $not_null_counts, $null_counts, $max_section) {
        $this->core->getOutput()->addBreadcrumb('Manage Sections');
        $reg_sections_count = array();
        foreach ($students as $student) {
            $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
            if (array_key_exists($registration, $reg_sections_count)) {
                $reg_sections_count[$registration] = $reg_sections_count[$registration]+1;
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
            "update_registration_sections_url" => $this->core->buildNewCourseUrl(['sections', 'registration']),
            "update_rotating_sections_url" => $this->core->buildNewCourseUrl(['sections', 'rotating']),
            "csrf_token" => $this->core->getCsrfToken()
        ]);
    }
}
