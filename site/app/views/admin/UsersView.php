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
     * @param User[] $students
     * @return string
     */
    public function listStudents($students) {
        $section = -1;
        $return = <<<HTML
<div class="content">
    <h2>View Students</h2>
    <table class="table table-striped table-bordered persist-area">
        <thead class="persist-thead">
            <tr>
                <td width="5%"></td>
                <td width="15%">Registration Section</td>
                <td width="20%" style="text-align: left">User ID</td>
                <td width="30%" colspan="2">Name</td>
                
                <td width="15%">Rotating Section</td>
                <td width="15%">Manual Registration</td>
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
            <td colspan="7" style="text-align: center">Students Enrolled in Registration Section {$registration}</td>
        </tr>
        <tbody id="section-{$registration}">
HTML;
                    $tbody_open = true;
                    $section = $student->getRegistrationSection();
                }
                $manual = ($student->isManualRegistration()) ? "TRUE" : "FALSE";
                $rotating_section = ($student->getRotatingSection() === null) ? "NULL" : $student->getRotatingSection();
                $return .= <<<HTML
            
            <tr>
                <td>{$count}</td>
                <td>{$registration}</td>
                <td style="text-align: left">{$student->getId()}</td>
                <td style="text-align: left">{$student->getDisplayedFirstName()}</td> 
                <td style="text-align: left">{$student->getLastName()}</td>
                <td>{$rotating_section}</td>
                <td>{$manual}</td>
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

        $return .= <<<HTML
    </table>
</div>
HTML;

        return $return;
    }

    public function userForm($user, $groups, $sections) {

    }
}