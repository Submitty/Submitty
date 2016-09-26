<?php

namespace app\views\admin;

use app\libraries\Core;
use app\models\User;

class UsersView {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    /**
     * @param User[]    $students
     * @param integer[] $sections
     * @return string
     */
    public function listStudents($students, $sections) {
        $section = -1;
        $return = <<<HTML
<div class="content">
    <div style="float: right; margin-bottom: 20px;">
        <a href="{$this->core->getConfig()->getTABaseUrl()}account/admin-classlist.php?course=csci1000&semester=f16&this[]=Students&this[]=Upload%20ClassList" class="btn btn-primary">Upload Classlist</a>
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
            <td colspan="3">No students found</td>
        </tr>
HTML;
        }

        $return .= $this->userForm($sections, 'update_student');

        $return .= <<<HTML

    </table>
</div>
HTML;

        return $return;
    }

    /**
     * @param User[]    $graders
     * @param integer[] $sections
     * @return string
     */
    public function listGraders($graders, $sections) {
        $return = <<<HTML
<div class="content">
    <div style="float: right; margin-bottom: 20px;">
        <a onclick="newUserForm(); 
            $('[name=\'user_group\'] option[value=\'3\']').prop('selected', true);" class="btn btn-primary">New Grader</a>
    </div>
    <h2>View Graders</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="4%"></td>
                <td width="20%" style="text-align: left">User ID</td>
                <td width="30%" colspan="2">Name</td>
                <td width="23%">User Group</td>
                <td width="20%">Registration Sections</td>
                <td width="3%"></td>
            </tr>
        </thead>
HTML;
        if (count($graders) > 0) {
            $count = 1;
            foreach ($graders as $grader) {
                switch ($grader->getGroup()) {
                    case 0:
                        $group = "Developer";
                        $registration_sections = "All";
                        break;
                    case 1:
                        $group = "Instructor";
                        $registration_sections = "All";
                        break;
                    case 2:
                        $group = "Full Access Grader (Grad TA)";
                        $registration_sections = implode(", ", $grader->getGradingRegistrationSections());
                        break;
                    case 3:
                        $group = "Limited Access Grader (Mentor)";
                        $registration_sections = implode(", ", $grader->getGradingRegistrationSections());
                        break;
                    default:
                        $group = "UNKNOWN";
                        $registration_sections = "";
                        break;
                }
                $return .= <<<HTML
            
            <tr id="user-{$grader->getId()}">
                <td>{$count}</td>
                <td style="text-align: left">{$grader->getId()}</td>
                <td style="text-align: left">{$grader->getDisplayedFirstName()}</td> 
                <td style="text-align: left">{$grader->getLastName()}</td>
                <td>{$group}</td>
                <td>{$registration_sections}</td>
                <td><a onclick="editUserForm('{$grader->getId()}');"><i class="fa fa-pencil" aria-hidden="true"></i></a></td>
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
            <td colspan="3">No graders found</td>
        </tr>
HTML;
        }

        $return .= $this->userForm($sections, 'update_grader');

        $return .= <<<HTML

    </table>
</div>
HTML;

        return $return;
    }

    public function userForm($sections, $action) {
        $url = array('component' => 'admin', 'page' => 'users', 'action' => $action);

        $select_html = "";
        foreach($sections as $section) {
            $section = $section['sections_registration_id'];
            $select_html .= "<option value='{$section}'>Section {$section}</option>\n";
        }

        $return = <<<HTML
<div id="edit-user-form">
<form method="post" action="{$this->core->buildUrl($url)}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <input type="hidden" name="edit_user" value="false" />
    <div style="">
            User ID:<br />
        <input class="readonly" type="text" name="user_id" readonly="readonly" />
    </div>
    <div>
        First Name:<br />
        <input type="text" name="user_firstname" />
    </div>
    <div>
        Preferred First Name:<br />
        <input type="text" name="user_preferred_firstname" />
    </div>
    <div>
        Last Name:<br />
        <input type="text" name="user_lastname" />
    </div>
    <div>
        Email:<br />
        <input type="text" name="user_email" />
    </div>
    <div>
        Registered Section:<br />
        <select name="registered_section">
            <option value="null">Not Registered</option>
            {$select_html}
        </select>
    </div>
    <div style="width: 50%">
            Group:<br />
        <select name="user_group">
            <option value="1">Instructor</option>
            <option value="2">Full Access Grader (Grad TA)</option>
            <option value="3">Limited Access Grader (Mentor)</option>
            <option value="4">Student</option>
        </select>
    </div>
    <div style="width: 100%">
        <input type="checkbox" id="manual_registration" name="manual_registration"> 
        <label for="manual_registration">Manually Registered User (no automatic updates)</label>
    </div>
    <div style="width: 100%">
        <h3>Assigned Sections (Graders Only)</h3>
HTML;
        foreach ($sections as $section) {
            $section = $section['sections_registration_id'];
            $return .= <<<HTML

        <div style="width: 20%">
            <input type="checkbox" id="grs_{$section}" name="grading_registration_section[]" value="{$section}">
            <label for="grs_{$section}">Section {$section}</label>
        </div>
HTML;
        }
        $return .= <<<HTML
    </div>
    <div style="float: right; width: auto">
        <a onclick="$('#edit-user-form').css('display', 'none');" class="btn btn-danger">Cancel</a>
        <input class="btn btn-primary" type="submit" value="Submit" />
    </div>
</form>
</div>
HTML;
        return $return;
    }
}