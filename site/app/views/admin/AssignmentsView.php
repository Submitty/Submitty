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
    <div style="height: 20px; padding-right: 10px;">
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 
                                                                      'page' => 'assignments', 
                                                                      'action' => 'new'))}" style="float: right">
        <i class="fa fa-plus fa-fw"></i> New Assignment</a>
    </div>
</div>

HTML;

        return $return;
    }

}