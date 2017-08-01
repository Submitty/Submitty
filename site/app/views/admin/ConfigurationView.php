<?php

namespace app\views\admin;

use app\views\AbstractView;

class ConfigurationView extends AbstractView {
    public function viewConfig($fields) {
        $zero_checked = ($fields['zero_rubric_grades'] === true) ? 'checked' : '';
        $keep_previous_files = ($fields['keep_previous_files'] === true) ? 'checked' : '';
        $display_iris_grades_summary = ($fields['display_iris_grades_summary'] === true) ? 'checked' : '';
        $display_custom_message = ($fields['display_custom_message'] === true) ? 'checked' : '';


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
            <div class="option-input"><input type="text" name="course_home_url" value="{$fields['course_home_url']}" /></div>
            <div class="option-desc">
                <div class="option-title">Course Home URL</div>
                <div class="option-alt">Input the url that will link to your course page from the Course Name</div>
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
            <div class="option-input"><input type="checkbox" name="keep_previous_files" value="true" {$keep_previous_files} /></div>
            <div class="option-desc">
                <div class="option-title">Keep previous files uploaded</div>
                <div class="option-alt">Should the files from previous submission be in the upload box by default.</div>
            </div>
        </div>

        <div class="option">
            <div class="option-input"><input type="checkbox" name="display_iris_grades_summary" value="true" {$display_iris_grades_summary} /></div>
            <div class="option-desc">
                <div class="option-title">Display Iris Grades Summary</div>
                <div class="option-alt">Should Iris Grades Summary be displayed to students.</div>
            </div>
        </div>

        <div class="option">
            <div class="option-input"><input type="checkbox" name="display_custom_message" value="true" {$display_custom_message} /></div>
            <div class="option-desc">
                <div class="option-title">Display Iris Custom Message</div>
                <div class="option-alt">The primary use of the custom message is to announce Exam Zone Seating Assignments.</div>
            </div>
        </div>

        <div class="option">
            <div class="option-input"><input type="text" name="vcs_base_url" value="{$fields['vcs_base_url']}" /></div>
            <div class="option-desc">
                <div class="option-title">VCS Base URL</div>
                <div class="option-alt">Base URL if students are submitting via VCS repository.</div>
            </div>
        </div>

        <div class="option">
            <div class="option-input">
                <input type="radio" name="vcs_type" value="git"/> Git
                <input type="radio" name="vcs_type" value="svn"/> SVN
                <input type="radio" name="vcs_type" value="mer"/> Mercurial
            </div>
            <div class="option-desc">
                <div class="option-title">VCS Type</div>
                <div class="option-alt">Choose the type of VCS if students are submitting via VCS repository.</div>
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

    $return .= <<<HTML
<script type="text/javascript">
    $(document).ready(function() {
        
    });
</script>
HTML;

    }

}