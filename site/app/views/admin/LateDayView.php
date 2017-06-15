<?php

namespace app\views\admin;

use app\views\AbstractView;

class LateDayView extends AbstractView {
    public function displayLateDays() {
        $return = <<<HTML
<div class="content">
    <h2>Late Days Allowed</h2>
    <form id="lateDayForm" method="post" enctype="multipart/form-data" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'late_day', 'action' => 'update'))}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="panel">
        <div class="option">
            <p> Use this form to grant students additional late days (beyond the initial number specified in the course configuration).<br>
                Students may use these additional late days for any future homeworks (after the specificed date).<br><br><br>
            </p>
                <div class="option-title">Single Student Entry<br></div>
                <div class="option" style="width:30%; display:inline-block;">Student ID:<br><input class="option-input" type="text" name="user_id" style="float:left"></div>
                <div class="option" style="width:30%; display:inline-block;">Datestamp (MM/DD/YY):<br><input class="option-input" type="text" name="datestamp" style="float:left"></div>
                <div class="option" style="width:15%; display:inline-block;">Late Days:<br><input class="option-input" type="text" name="late_days" style="float:left"></div>
                <div class="option" style="width:15%; display:inline-block; float:right;"><br><input class="btn btn-primary" type="submit" style="float:left"></div>
                <div class="option-title"><br><br>Multiple Student Entry Via CSV Upload<br></div>
                <div style="padding-bottom:20px;"><input type="file" name="csv_upload" onchange="this.form.submit()"></div>


        </div>
    </div>
    </form>
</div>
HTML;
        return $return;
    }

}


                // <div class="btn btn-primary" type="submit" form="configForm"><i class="fa fa-save fa-fw"></i> Submit2</div>

                // <div class="option" style="display:inline-block; width: 250px;">Student ID:<br><input class="option-input" style="width: 200px" type="text" name="course_name" value="myvaluehere" /></div>
                // <div class="option" style="display:inline-block; width: 250px;">Datestamp (MM/DD/YY):<br><input class="option-input" style="width: 200px" type="text" name="course_name" value="myvaluehere" /></div>
                // <div class="option" style="display:inline-block; width: 250px;">Late Days:<br><input class="option-input" style="width: 200px" type="text" name="course_name" value="myvaluehere" /></div>























//         $zero_checked = ($fields['zero_rubric_grades'] === true) ? 'checked' : '';
//         $keep_previous_files = ($fields['keep_previous_files'] === true) ? 'checked' : '';
//         $display_iris_grades_summary = ($fields['display_iris_grades_summary'] === true) ? 'checked' : '';
//         $display_custom_message = ($fields['display_custom_message'] === true) ? 'checked' : '';


//         return <<<HTML
// <div class="content">
//     <h2>Course Settings</h2>
//     <form id="configForm" method="post" action="{$this->core->buildUrl(array('component' => 'admin', 
//                                                                              'page'      => 'configuration', 
//                                                                              'action'    => 'update'))}">
//     <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
//     <div class="panel">
//         <div class="option">
//             <div class="option-input"><input type="text" name="course_name" value="{$fields['course_name']}" /></div>
//             <div class="option-desc">
//                 <div class="option-title">Course Name</div>
//                 <div class="option-alt">Input the course name that should appear in the header of the site</div>
//             </div>
//         </div>

//     </div>
//     <div class="post-panel-btn">
//         <button class="btn btn-primary" style="float: right" type="submit" form="configForm">
//             <i class="fa fa-save fa-fw"></i> Submit
//         </button>
//     </div>
//     </form>
// </div>
// HTML;