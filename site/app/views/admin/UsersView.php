<?php

namespace app\views\admin;

use app\models\User;
use app\views\AbstractView;

class UsersView extends AbstractView {
    /**
     * @param User[] $students
     * @return string
     */
    public function listStudents($students) {
        $section = -1;
        $return = <<<HTML
<div class="content">
    <div style="float: right; margin-bottom: 20px;">
        <a onclick="newDownloadForm()" class="btn btn-primary">Download Users</a>
        <a onclick="newClassListForm()" class="btn btn-primary">Upload Classlist</a>
        <a onclick="newUserForm()" class="btn btn-primary">New Student</a>
    </div>
    <h2>View Students</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="4%"></td>
                <td width="15%">Registration Section</td>
                <td width="20%" style="text-align: left">User ID</td>
                <td width="30%" colspan="2">Name</td>
                <td width="15%">Rotating Section</td>
                <td width="13%">Manual Registration</td>
                <td width="3%"></td>
            </tr>
        </thead>
HTML;
        if (count($students) > 0) {
            $count = 1;
            $tbody_open = false;
            foreach ($students as $student) {
                $registration = ($student->getRegistrationSection() === null) ? "NULL" : $student->getRegistrationSection();
                if($section !== $student->getRegistrationSection()) {
                    $count = 1;
                    if ($tbody_open) {
                        $return .= <<<HTML

        </tbody>
HTML;
                    }
                    $return .= <<<HTML

        <tr class="info persist-header">
            <td colspan="8" style="text-align: center">Students Enrolled in Registration Section {$registration}</td>
        </tr>
        <tbody id="section-{$registration}">
HTML;
                    $tbody_open = true;
                    $section = $student->getRegistrationSection();
                }
                $manual = ($student->isManualRegistration()) ? "TRUE" : "FALSE";
                $rotating_section = ($student->getRotatingSection() === null) ? "NULL" : $student->getRotatingSection();
                $style = "";
                if ($student->accessGrading()) {
                    $style = "style='background: #7bd0f7;'";
                }
                $return .= <<<HTML

            <tr id="user-{$student->getId()}" {$style}>
                <td>{$count}</td>
                <td>{$registration}</td>
                <td style="text-align: left">{$student->getId()}</td>
                <td style="text-align: left">{$student->getDisplayedFirstName()}</td>
                <td style="text-align: left">{$student->getLastName()}</td>
                <td>{$rotating_section}</td>
                <td>{$manual}</td>
                <td><a onclick="editUserForm('{$student->getId()}');"><i class="fa fa-pencil" aria-hidden="true"></i></a></td>
            </tr>
HTML;
                $count++;
            }
            $return .= <<<HTML

        </tbody>
HTML;
        }
        else {
            $return .= <<<HTML
        <tr>
            <td colspan="8">No students found</td>
        </tr>
HTML;
        }

        $return .= <<<HTML

    </table>
</div>
HTML;

        return $return;
    }

    /**
     * @param User[] $graders
     * @param array  $reg_sections associative array representing registration sections in the system
     * @param array  $rot_sections associative array representing rotating sections in the system
     * @param bool   $use_database
     * @return string
     */
    public function listGraders($graders, $reg_sections, $rot_sections, $use_database=false) {
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
            "use_database" => $use_database
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
                $grp = "";
                switch ($student->getGroup()) {
                    case 0:
                        $grp = 'Developer';
                        break;
                    case 1:
                        $grp = 'Instructor';
                        break;
                    case 2:
                        $grp = 'Full Access Grader (Grad TA)';
                        break;
                    case 3:
                        $grp = 'Limited Access Grader (Mentor)';
                        break;
                    default:
                        $grp = 'Student';
                        break;
                }
                $first_name = str_replace("'", "&#039;", $student->getDisplayedFirstName());
                $last_name = str_replace("'", "&#039;", $student->getLastName());
                array_push($download_info, ['first_name' => $first_name, 'last_name' => $last_name, 'user_id' => $student->getId(), 'email' => $student->getEmail(), 'reg_section' => "$reg_sec", 'rot_section' => "$rot_sec", 'group' => "$grp"]);
            }
        }
        else if ($code === 'grader') {
            foreach ($graders as $grader) {
                $rot_sec = ($grader->getRotatingSection() === null) ? 'NULL' : $grader->getRotatingSection();
                switch ($grader->getGroup()) {
                    case 0:
                        $reg_sec = 'All';
                        $grp = 'Developer';
                        break;
                    case 1:
                        $reg_sec = 'All';
                        $grp = 'Instructor';
                        break;
                    case 2:
                        $grp = 'Full Access Grader (Grad TA)';
                        $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                        break;
                    case 3:
                        $grp = 'Limited Access Grader (Mentor)';
                        $reg_sec = implode(',', $grader->getGradingRegistrationSections());
                        break;
                    default:
                        $grp = 'UNKNOWN';
                        $reg_sec = "";
                        break;
                }
                $first_name = str_replace("'", "&#039;", $grader->getDisplayedFirstName());
                $last_name = str_replace("'", "&#039;", $grader->getLastName());
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

    public function rotatingUserForm($students, $reg_sections, $not_null_counts, $null_counts, $max_section) {
        $return = <<<HTML
<script type="text/javascript">
$(function() {
    $("[name='rotating_type']").change(function() {
        if ($(this).val() == "alphabetically") {
            $("[name='fewest']").prop('checked', false).attr('onclick', 'return false').addClass("disabled");

        }
        else {
            $("[name='fewest']").attr('onclick', '').removeClass('disabled');
        }
    });

    $("[name='sort_type']").change(function() {
        var val = $(this).val();
        if (val == "fewest") {
            $("[name='sections']").val({$max_section}).addClass('disabled').attr('readonly', 'readonly');
            $("[name='rotating_type']").val("random").addClass('disabled').attr('disabled', 'true');
        }
        else if (val == "drop_null") {
            $("[name='sections']").addClass('disabled').attr('readonly', 'readonly');
            $("[name='rotating_type']").addClass('disabled').attr('disabled', 'true');
        }
        else {
            $("[name='sections']").removeClass('disabled').removeAttr('readonly');
            $("[name='rotating_type']").removeClass('disabled').removeAttr('disabled');
        }
    });
});
</script>
<div class="content">
    <h2>Setup Registration Sections</h2>
    <p>
    Large courses are often split into multiple <em>registrations sections</em> for laboratory or recitation class time.<br>
    Courses that are cross-listed in different departments may have multiple course codes/prefixes.<br>
    <br>
    Each student in the course is assigned to one registration section.<br>
    Students who have dropped the course will be assigned to the <em>NULL</em> registration section.<br>
    <br>
    From the "Graders" tab in the top menu, each grader may be assigned to grade zero, one, or multiple registration sections.<br>
    Assigning grading <em>by registration section</em> facilitates routine grading of the <em>same set of students</em> throughout the term.<br>
    </p>
    <br />
    <form action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'update_registration_sections'))}" method="POST">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="sub">
        <div class="box half">
            <h2>Current Registration Section Counts</h2>
            <div class="half">
                <table class="table table-bordered table-striped">
HTML;
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
        foreach ($reg_sections as $section) {
            $section = $section['sections_registration_id'];
            if (array_key_exists($section, $reg_sections_count)) {
                $return .= <<<HTML
                    <tr>
                        <td>Section {$section}</td>
                        <td>{$reg_sections_count[$section]}</td>
HTML;
            }
            else {
                $return .= <<<HTML
                    <tr>
                        <td>Section {$section}</td>
                        <td>0</td>
HTML;
            }
        }
        if (array_key_exists('NULL', $reg_sections_count)) {
            $return .= <<<HTML
                <tr>
                    <td>Section NULL</td>
                    <td>{$reg_sections_count['NULL']}</td>
HTML;
        }
        else {
            $return .= <<<HTML
                    <tr>
                        <td>Section NULL</td>
                        <td>0</td>
HTML;
        }
        $return .= <<<HTML
                </table>
            </div>
        </div>
        <div class="box half">
            <br /><br />
            <div class="option">
                <div class="option-input"><input type="text" name="add_reg_section" value="" placeholder="Eg: 3" /></div>
                <div class="option-desc">
                    <div class="option-title">Add Registration Section</div>
                    <div class="option-alt">
                        Enter a registration section which is not already a registration section.
                    </div>
                </div>
            </div>
            <div class="option">
                <div class="option-input"><input type="text" name="delete_reg_section" value="" placeholder="Eg: 3" /></div>
                <div class="option-desc">
                    <div class="option-title">Delete a Registration Section</div>
                    <div class="option-alt">
                        Registration Section to be deleted should not have any student enrolled in it and no grader should be assigned to grade the section.
                    </div>
                </div><br />
                <input style="margin-top: 20px; margin-right: 20px; float:right" type="submit" class="btn btn-primary" value="Submit" />
            </div>
        </div>
    </div>
    </form>
</div>
<div class="content">
    <h2>Setup Rotating Sections</h2>
    <p>
    Rotating sections are an alternate way to divide the task of grading a large course enrollment among multiple graders.<br>
    If the registration sections are of significantly different size, rotating sections will allow a more equitable assignment of grading tasks.<br>
    Furthermore, shuffling or rotating the assignment of graders to rotating sections for each assignment will ensure that each student <br>
    receives feedback from multiple graders throughout the term and can mitigate the variations in ease or strictness between the graders.<br>
    <br>
    Each registered student is assigned to a rotating section for the duration of the course.<br>
    For each assignment with manual/TA grading assigned by rotating sections, each grader is assigned zero, one, or multiple rotating sections.<br>
    The rotating assignments for each gradeable are made via the "Create/Edit Gradeable" page for the specific gradeable.<br>
    </p>
    <br />
    <form action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'update_rotating_sections'))}" method="POST">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="sub">
        <div class="box half">
            <h2>Current Rotating Section Counts</h2>
            <div class="half">
                <h3>Registered Students<br>(non NULL registration section)</h3>
                <table class="table table-bordered table-striped">
HTML;
        foreach($not_null_counts as $row) {
            if ($row['rotating_section'] === null) {
                $row['rotating_section'] = "NULL";
            }
            $return .= <<<HTML
                    <tr>
                        <td>Section {$row['rotating_section']}</td>
                        <td>{$row['count']}</td>
HTML;
        }
        $return .= <<<HTML
                </table>
            </div>
            <div class="half">
                <h3>Users with<br>Registration Section=NULL</h3>
                <table class="table table-bordered table-striped">
HTML;
        foreach ($null_counts as $row) {
            if ($row['rotating_section'] === null) {
                $row['rotating_section'] = "NULL";
            }
            $return .= <<<HTML
                    <tr>
                        <td>Section {$row['rotating_section']}</td>
                        <td>{$row['count']}</td>
HTML;
        }
        $return .= <<<HTML
                </table>
            </div>
        </div>
        <div class="box half">
            Place students in <input type="text" name="sections" placeholder="#" style="width: 25px" /> rotating sections
            <select name="rotating_type">
                <option value="random">randomly</option>
                <option value="alphabetically">alphabetically</option>
            </select><br /><br />
            <label>
                <input type="radio" style="margin-top: -2px" name="sort_type" value="drop_null" /> Only remove unregistered students (registration section=NULL) from rotating sections
            </label><br /><br />
            <label>
                <input type="radio" style="margin-top: -2px" name="sort_type" value="fewest" /> Remove unregistered students (registration section=NULL) from rotating sections and put newly registered students into rotating section with fewest members
            </label><br /><br />
            <label>
                <input type="radio" style="margin-top: -2px" name="sort_type" value="redo" /> Redo rotating sections completely
            </label><br />
            <input style="margin-top: 20px; margin-right: 20px; float:right" type="submit" class="btn btn-primary" value="Submit" />
        </div>
     </div>
    </form>
</div>
HTML;
        return $return;
    }

    public function graderListForm($use_database) {
        return $this->core->getOutput()->renderTwigTemplate("admin/users/GraderListForm.twig", [
            "use_database" => $use_database
        ]);
    }

    public function classListForm($use_database) {
        if ($use_database) {
            $num_cols = 7;
            $password_col = "password, ";
        }
        else {
            $num_cols = 6;
            $password_col = "";
        }
        $return = <<<HTML
<div class="popup-form" id="class-list-form">
    <h2>Upload Classlist</h2>
    <p>&emsp;</p>
    <p>
        Format your class list as an .xlsx or .csv file with {$num_cols} columns:<br>
        &emsp;username, first name, last name, email, registration section, {$password_col}preferred first name<br>
    </p>
    <p>&emsp;</p>
    <p>
        Preferred first name is optional.<br>
        Registration section can be null.<br>
        Do not use a header row.<br>
    </p>
    <br />
    <form method="post" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'users', 'action' => 'upload_class_list'))}" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        Move students missing from the classlist to NULL section?<input type="checkbox" name="move_missing" /><br>
        <br />
        <div>
            <input type="file" name="upload" accept=".xlsx, .csv">
        </div>
        <div style="float: right; width: auto">
            <a onclick="$('#class-list-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
            <input class="btn btn-primary" type="submit" value="Submit" />
        </div>
    </form>
</div>
HTML;
        return $return;
    }
}
