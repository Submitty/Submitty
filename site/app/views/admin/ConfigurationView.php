<?php

namespace app\views\admin;

use app\libraries\Core;

class ConfigurationView {
    private $core;
    public function __construct(Core $core) {
        $this->core = $core;
    }

    public function viewConfig($fields) {
        $zero_checked = ($fields['zero_rubric_grades'] === true) ? 'checked' : '';
        $ta_grades = ($fields['ta_grades'] === true) ? 'checked' : '';

        return <<<HTML
<div class="content">
    <h2>Course Settings</h2>
    <form id="configForm" method="post" action="{$this->core->buildUrl(array('component' => 'admin', 
                                                                             'page'      => 'configuration', 
                                                                             'action'    => 'update'))}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="panel">
        <div class="option">
            <div class="option-input"><input type="text" name="course_name" value="{$fields['course_name']}" /></div>
            <div class="option-desc">
                <div class="option-title">Course Name</div>
                <div class="option-alt">Input the course name that should appear in the header of the site</div>
            </div>
        </div>

        <div class="option">
            <div class="option-input"><input type="text" name="default_student_late_days" value="{$fields['default_student_late_days']}" /></div>
            <div class="option-desc">
              <div class="option-title">Initial Allowed Late Days (Per Student, Per Semester)</div>
                <div class="option-alt">Number of allowed late days given to each student when they are added to
                                        the course.  Additional late days can be granted (e.g., as incentives)
                                        using the "Late Days Allowed" form.</div>
            </div>
        </div>

        <div class="option">
            <div class="option-input"><input type="text" name="default_hw_late_days" value="{$fields['default_hw_late_days']}" /></div>
            <div class="option-desc">
                <div class="option-title">Default Maximum Late Days Per Assignment</div>
                <div class="option-alt">Specify the default number of late days that may be used on a single homework.  This can be adjusted
                            per assignment on the "Create/Edit Gradeable" form.</div>
            </div>
        </div>


        <div class="option">
            <div class="option-input"><input type="checkbox" name="zero_rubric_grades" value="true" {$zero_checked} /></div>
            <div class="option-desc">
                <div class="option-title">Zero Rubric Grading</div>
                <div class="option-alt">Should each rubric item score default to zero?  If disabled, the grading rubric will
                  default at full credit.   Note: Assignments that are not submitted/submitted too late always be set to zero.</div>
            </div>
        </div>
        <div class="option">
            <div class="option-input"><textarea style="height: 50px" name="upload_message">{$fields['upload_message']}</textarea></div>
            <div class="option-desc">
                <div class="option-title">Upload Message</div>
                <div class="option-alt">What is the message that should be shown to students above the upload area
                on the submission page.</div>
            </div>
        </div>
        <div class="option">
            <div class="option-input"><input type="checkbox" name="ta_grades" value="true" {$ta_grades} /></div>
            <div class="option-desc">
                <div class="option-title">Enable TA Grades</div>
                <div class="option-alt">Should TA grade reports ever be shown to students on their submissions.</div>
            </div>
        </div>
    </div>
    <div class="post-panel-btn">
        <button class="btn btn-primary" style="float: right" type="submit" form="configForm">
            <i class="fa fa-save fa-fw"></i> Submit
        </button>
    </div>
    </form>
</div>
HTML;

    }

}