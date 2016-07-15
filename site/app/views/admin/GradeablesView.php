<?php

namespace app\views\admin;

use app\libraries\Core;

class GradeablesView {
    private $core;

    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function gradeablesTable($gradeables) {
        $return = <<<HTML
<div class="content">
    <h2>Manage Gradeables</h2>
    <div class="panel">
        <table>
            <tr class="table-header">
                <th>ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Options</th>
            </tr>
HTML;

        if (count($gradeables) > 0) {
            foreach ($gradeables as $gradeable) {
                $return .= <<<HTML
            <tr>
                <td>{$gradeable['rubric_id']}</td>
                <td>{$gradeable['rubric_name']}</td>
                <td>{$gradeable['rubric_type']}</td>
                <td><a href="#" class="fa fa-edit" title="Edit Gradeable"></a> 
                    <a href="#" class="fa fa-times" title="Delete Gradeable"></a></td>
            </tr>
HTML;
            }
        }

        $return .= <<<HTML
        </table>
    </div>
    <div class="post-panel-btn">
        <a class="btn btn-primary" href="{$this->core->buildUrl(array('component' => 'admin', 
                                                                      'page' => 'gradeables', 
                                                                      'action' => 'add'))}" style="float: right">
        <i class="fa fa-plus fa-fw"></i> New Gradeable</a>
    </div>
</div>

HTML;

        return $return;
    }

    public function gradeableForm() {
        return <<<HTML
<div class="content">
    <h2>New Gradeable</h2>
    <div class="panel">
        <form method="post">
            <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
            <div class="option">
                <div class="option-input"><input type="text" name="gradeable_id" value="" /></div>
                <div class="option-desc">
                    <div class="option-title">Gradeable ID</div>
                    <div class="option-alt"></div>
                </div>
            </div>
            <div class="option">
                <div class="option-input"><input type="text" name="gradeable_id" value="" /></div>
                <div class="option-desc">
                    <div class="option-title">Gradeable Name</div>
                    <div class="option-alt"></div>
                </div>
            </div>
            <div class="option">
                <div class="option-input"><textarea name="gradeable_note"></textarea></div>
                <div class="option-desc">
                    <div class="option-title">TA Note</div>
                    <div class="option-alt">Overall instructions that should be provided to the TA for grading</div>
                </div>
            </div>
            <div class="option">
                <div class="option-input"><input type="text" name="gradeable_due_date" value="" /></div>
                <div class="option-desc">
                    <div class="option-title">Gradeable Due Date</div>
                    <div class="option-alt"></div>
                </div>
            </div>
            <div class="option">
                <div class="option-input">
                    <input type="radio" name="gradeable_type" value="checkpoint" />Checkpoints<br />
                    <input type="radio" name="gradeable_type" value="numeric" />Numeric<br />
                    <input type="radio" name="gradeable_type" value="rubric" />Rubric
                </div>
                <div class="option-desc">
                    <div class="option-title">Type of Gradeable</div>
                    <div class="option-alt"></div>
                </div>
            </div>
        </form>
    </div>
</div>
HTML;

    }

}