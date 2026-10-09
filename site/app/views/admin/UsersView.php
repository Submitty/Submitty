<?php

namespace app\views\admin;

use app\models\User;
use app\views\AbstractView;

class UsersView extends AbstractView {
    /**
     * @param array  $sorted_students students sorted by registration sections
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param array<string, bool> $can_rejoin Maps users' names in the null section
     * to whether they can rejoin the course or not.
     * @param array  $download_info user information for downloading
     * @param array  $formatted_tzs array containing a formatted time zone string for each user
     * @param bool   $use_database
     * @param string  $active_student_columns array of bools, columns that are visible (serialized as string)
     * @return string
     */
    public function listStudents(
        array $sorted_students,
        array $reg_sections,
        array $rot_sections,
        array $can_rejoin,
        array $download_info,
        array $formatted_tzs,
        bool $use_database = false,
        string $active_student_columns = ''
    ): string {
        $this->core->getOutput()->addBreadcrumb('Manage Students');
        $this->core->getOutput()->addInternalCss('directory.css');
        $this->core->getOutput()->addInternalCss('userform.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('latedaystableplugin.css');
        $this->core->getOutput()->addInternalJs('userform.js');
        $this->core->getOutput()->addInternalJs('directory.js');
        $this->core->getOutput()->enableMobileViewport();

        $cookie_raw = urldecode($active_student_columns);
        $decoded_columns = json_decode($cookie_raw, true);

        if (!is_array($decoded_columns) && str_contains($cookie_raw, '-')) {
            $bit_flags = explode('-', $cookie_raw);
        }
        else {
            $bit_flags = null;
        }
        $default_columns = [
            'registration-section',
            'user-id',
            'first-name',
            'last-name',
            'pronouns',
            'rotating-section',
            'time-zone',
            'view-grades',
            'late-days',
            'registration-type',
            'edit-student',
        ];
        $all_column_keys = [
            'registration-section',
            'registration-subsection',
            'user-id',
            'first-name',
            'last-name',
            'pronouns',
            'rotating-section',
            'time-zone',
            'view-grades',
            'late-days',
            'registration-type',
            'edit-student',
            'delete-student',
            'user-numeric-id',
            'legal-first-name',
            'legal-last-name',
            'email',
            'secondary-email',
        ];

        $active_flags = [];
        if ($bit_flags !== null) {
            $padded = array_pad($bit_flags, 18, '0');
            foreach ($padded as $val) {
                $active_flags[] = ($val === '1' || $val === 1 || $val === true);
            }
        }
        elseif (is_array($decoded_columns)) {
            $is_assoc = array_keys($decoded_columns) !== range(0, count($decoded_columns) - 1);
            foreach ($all_column_keys as $key) {
                if ($is_assoc) {
                    $active_flags[] = !empty($decoded_columns[$key]);
                } 
                else {
                    $active_flags[] = in_array($key, $decoded_columns, true);
                }
            }
        }
        else {
            foreach ($all_column_keys as $key) {
                $active_flags[] = in_array($key, $default_columns, true);
            }
        }

        return $this->core->getOutput()->renderTwigTemplate("admin/users/StudentList.twig", [
            "sections" => $sorted_students,
            "formatted_tzs" => $formatted_tzs,
            "reg_sections" => $reg_sections,
            "rot_sections" => $rot_sections,
            "can_rejoin" => $can_rejoin,
            "use_database" => $use_database,
            'update_url' => $this->core->buildCourseUrl(['users']) . '?' . http_build_query(['type' => 'users']),
            "delete_user_url" => $this->core->buildCourseUrl(['delete_user']),
            "return_url_upload_class_list" => $this->core->buildCourseUrl(['users', 'upload']) . '?' . http_build_query(['list_type' => 'classlist']),
            'view_grades_url' => $this->core->buildCourseUrl() . '/users/view_grades',
            'view_latedays_url' => $this->core->buildCourseUrl(['users', 'view_latedays']),
            "csrf_token" => $this->core->getCsrfToken(),
            "download_info_json" => json_encode($download_info),
            "course" => $this->core->getConfig()->getCourse(),
            "term" => $this->core->getConfig()->getTerm(),
            "active_student_columns" => $active_flags

        ]);
    }

    /**
     * @param array  $graders_sorted graders sorted by roles
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param array  $download_info grader information for downloading
     * @param bool   $use_database
     * @param string  $active_grader_columns array of bools, columns that are visible (serialized as string)
     * @return string
     */
    public function listGraders($graders_sorted, $reg_sections, $rot_sections, $download_info, $use_database = false, $active_grader_columns = '1-1-1-1-1-1-1') {
        $this->core->getOutput()->addBreadcrumb('Manage Graders');
        $this->core->getOutput()->addInternalCss('directory.css');
        $this->core->getOutput()->addInternalCss('table.css');
        $this->core->getOutput()->addInternalCss('userform.css');
        $this->core->getOutput()->addInternalJs('userform.js');
        $this->core->getOutput()->addInternalJs('manage-graders-columns.js');
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
            "demote_grader_url" => $this->core->buildCourseUrl(['demote_grader']),
            "csrf_token" => $this->core->getCsrfToken(),
            "download_info_json" => json_encode($download_info),
            "course" => $this->core->getConfig()->getCourse(),
            "term" => $this->core->getConfig()->getTerm(),
            "active_grader_columns" => explode('-', $active_grader_columns)
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

    public function sectionsForm($students, $reg_sections, $not_null_counts, $null_counts, $max_section, ?string $default_section, bool $is_self_register) {
        $this->core->getOutput()->addBreadcrumb('Manage Sections');
        $reg_sections_count = [];
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
        $this->core->getOutput()->addInternalJs('rotating-sections.js');

        return $this->core->getOutput()->renderTwigTemplate("admin/users/RotatingSectionsForm.twig", [
            "students" => $students,
            "reg_sections" => $reg_sections,
            "reg_sections_count" => $reg_sections_count,
            "not_null_counts" => $not_null_counts,
            "null_counts" => $null_counts,
            "max_section" => $max_section,
            "update_registration_sections_url" => $this->core->buildCourseUrl(['sections', 'registration']),
            "update_rotating_sections_url" => $this->core->buildCourseUrl(['sections', 'rotating']),
            "csrf_token" => $this->core->getCsrfToken(),
            "default_section" => $default_section,
            "is_self_register" => $is_self_register,
            "update_course_id_url" => $this->core->buildCourseUrl(['sections', 'update_course_id'])
        ]);
    }
}
