<?php

namespace app\views\admin;

use app\libraries\Core;

class AssignmentsView {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function assignmentsTable($assignments) {
        $return = <<<HTML
<div id="content">
    <h2>Manage Assignments</h2>
    <div class="panel">
        <table>
            <tr class="table-header">
                <th>ID</th>
                <th>Name</th>
                <th>Due Date</th>
                <th>Late Days</th>
                <th>Has Rubric</th>
                <th>Options</th>
            </tr>
HTML;

        foreach ($assignments as $assignment) {
            $assignment['has_rubric'] = ($assignment['has_rubric'] == true) ?
                "<div class='fa fa-check fa-lg fa-green'></div>" :
                "<div class='fa fa-times fa-lg fa-red'></div>";
            $return .= <<<HTML
            <tr>
                <td>{$assignment['rubric_id']}</td>
                <td>{$assignment['rubric_name']}</td>
                <td>{$assignment['rubric_due_date']}</td>
                <td>{$assignment['rubric_late_days']}</td>
                <td>{$assignment['has_rubric']}</td>
                <td><a href="#" class="fa fa-edit" title="Edit Assignment"></a> 
                    <a href="#" class="fa fa-times" title="Delete Assignment"></a></td>
            </tr>
HTML;
        }

        $return .= <<<HTML
        </table>
    </div>
    <div class="post-panel-btn">
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 
                                                                      'page' => 'assignments', 
                                                                      'action' => 'new'))}" style="float: right">
        <i class="fa fa-plus fa-fw"></i> New Assignment</a>
    </div>
</div>

HTML;

        return $return;
    }

    public function assignmentForm() {
        return <<<HTML
<div id="content">
    <h2>New Assignment</h2>
    <div class="panel">
        <form method="post">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            Assignment ID: <input type="text" name="assignment_id" value="" />
            <span style="margin-left: 40px;">Assignment Name: <input type="text" name="assignment_name" value="" /></span><br />
           Assignment Due Date: <input type="text" name="assignment_due_date" value="" />
        </form>
    </div>
</div>
HTML;

    }

}