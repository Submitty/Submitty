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
    <h2>Manage Class Configuration</h2>
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
            <div class="option-input"><input type="text" name="default_hw_late_days" value="{$fields['default_hw_late_days']}" /></div>
            <div class="option-desc">
                <div class="option-title">Default HW Late Days</div>
                <div class="option-alt">Number of late days that a homework will have by default (can be changed when making/editing 
                and assignment)</div>
            </div>
        </div>
        <div class="option">
            <div class="option-input"><input type="text" name="default_student_late_days" value="{$fields['default_student_late_days']}" /></div>
            <div class="option-desc">
                <div class="option-title">Default Student Late Days</div>
                <div class="option-alt">Number of late days a student has when added to the server. Additional late days can be
                given to the student throughout the semester.</div>
            </div>  
        </div>
        <div class="option">
            <div class="option-input"><input type="checkbox" name="zero_rubric_grades" value="true" {$zero_checked} /></div>
            <div class="option-desc">
                <div class="option-title">Zero Rubric Grading</div>
                <div class="option-alt">Should rubrics start out at zero when TAs are grading? If disabled, the rubric will
                start at full credit unless the submission was too late/not submitted at which point it'll be zeroed out
                automatically.</div>
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