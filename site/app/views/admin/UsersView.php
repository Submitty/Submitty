<?php

namespace app\views\admin;

use app\libraries\Core;

class UsersView {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function listStudents($students) {
        $section = null;
        $return = <<<HTML
<div class="content">
    <h2>View students</h2>
    <div class="panel">
        <table>
HTML;
        if (count($students) > 0) {
            foreach ($students as $student) {
                if($section != $student['user_course_section']) {
                    $return .= <<<HTML
                <tr>
                    <td colspan="3">{$student['section_title']}</td>
                </tr>
HTML;
                    $section = $student['user_course_section'];
                }
                $return .= <<<HTML
                <tr>
                    <td>{$student['user_id']}</td>
                    <td>{$student['user_firstname']} {$student['user_lastname']}</td>
                    <td>{$student['user_assignment_section']}</td>
                </tr>
HTML;
            }
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
    <div class="post-panel-btn">
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin',
                                                                      'page'      => 'users',
                                                                      'action'    => 'add_student'))}" style="float: right">
        <i class="fa fa-plus fa-fw"></i> New Student</a>
    </div>
</div>
HTML;

        return $return;
    }

    public function userForm($user, $groups, $sections) {

    }
}