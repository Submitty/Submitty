<?php

namespace app\views\admin;

use app\views\AbstractView;

class LateDayView extends AbstractView {
    public function displayLateDays($user_table_db_data) {
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
HTML;
        $return .= $this->configure_table($user_table_db_data);
        $return .= <<<HTML
    </form>
</div>
HTML;

        return $return;
    }



                // <div class="btn btn-primary" type="submit" form="configForm"><i class="fa fa-save fa-fw"></i> Submit2</div>

                // <div class="option" style="display:inline-block; width: 250px;">Student ID:<br><input class="option-input" style="width: 200px" type="text" name="course_name" value="myvaluehere" /></div>
                // <div class="option" style="display:inline-block; width: 250px;">Datestamp (MM/DD/YY):<br><input class="option-input" style="width: 200px" type="text" name="course_name" value="myvaluehere" /></div>
                // <div class="option" style="display:inline-block; width: 250px;">Late Days:<br><input class="option-input" style="width: 200px" type="text" name="course_name" value="myvaluehere" /></div>






    public function configure_table($db_data) {
    //IN:  data from database used to build table of granted late day exceptions
    //     for selected gradeable
    //OUT: no return (although private view['student_review_table'] property is
    //     filled)
    //PURPOSE: Craft HTML required to display a table of existing late day
    //         exceptions

        if (!is_array($db_data) || count($db_data) < 1) {
        //No late days in DB -- indicate as much.

            $return = <<<HTML
<p><em style="font-weight:bold; font-size:1.2em; font-style:normal;">No late days are currently entered.</em>
HTML;
        } else {
        //Late days found in DB -- build table to display

            //Table HEAD
            $return = <<<HTML
<table style="border:5px solid white; border-collapse:collapse; margin: 0 auto; text-align:center;">
<caption style="caption-side:top; font-weight:bold; font-size:1.2em;">
Late Days Allowed
</caption>
<th style="background:lavender; width:20%;">Student ID</th>
<th style="background:lavender; width:20%;">First Name</th>
<th style="background:lavender; width:20%;">Last Name</th>
<th style="background:lavender; width:20%;">Total Allowed Late Days</th>
<th style="background:lavender;">Effective Date</th>
HTML;

            //Table BODY
            $cell_color = array('white', 'aliceblue');
            foreach ($db_data as $index => $record) {
            //     $firstname = getDisplayName($record);
            //     // getUser()->getDisplayedFirstName()
                $return .= <<<HTML
<tr>
<td>{$record['user_id']}</td>
<td>{$record['user_firstname']}</td>
<td>{$record['user_lastname']}</td>
<td>{$record['allowed_late_days']}</td>
<td>{$record['since_timestamp']}</td>
</tr>
HTML;
            }

            //Table TAIL
            $return .= <<<HTML
</table>
HTML;
        return $return;
        }
    }

}













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