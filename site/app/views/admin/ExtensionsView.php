<?php

namespace app\views\admin;

use app\views\AbstractView;

class ExtensionsView extends AbstractView {
    public function displayExtensions($g_id, $g_ids,$user_table) {
        $return = <<<HTML
    <div class="inner-message alert alert-error" style="text-align:center; vertical-align: top; position: fixed;">
        <a class="fa fa-times message-close"></a>
        <i class="fa fa-times-circle"></i> my error text
    </div>


<div class="content">
    <h2>Excused Absense Extensions</h2>
    <!--
    <form id="hwDefault" method="post" enctype="multipart/form-data" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'view_extension'))}">
    -->
    <form id="excusedAbsenseForm" method="post" enctype="multipart/form-data" action="" onsubmit="return updateHomeworkExtensions2($(this));">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="panel">

        <input type="hidden" name="load_extensions" value="false" />

        <div class="option">
            <p> Use this form to grant an extension (e.g., for an excused absense) to a user on a specific assignment.<br><br><br></p>
            <div class="option">Select Rubric:<br>
<!--
                <select name="g_id" onchange="this.form.submit()" style="margin-top: 10px; width:50%">
-->
                <select name="g_id" id="myid" onchange="loadHomeworkExtensions($(this).val());" style="margin-top: 10px; width:50%">
                    <option disabled selected value> -- select an option -- </option>
HTML;
        foreach($g_ids as $index => $value) {
//             if($value[0] == $g_id){
//                 $return .= <<<HTML
//                     <option value="$value[0]" selected="true">$value[0]</option>
// HTML;
//             }
//             else{
                $return .= <<<HTML
                    <option value="$value[0]">$value[0]</option>
HTML;
            // }
        }
        // onsubmit="updateHomeworkExtensions($(this).val());"
        $return .= <<<HTML
                </select>
            </div>
        </div>
    <!--
    </form>
    -->

    <!--
    <form id="excusedAbsenseForm" method="post" enctype="multipart/form-data" action="javascript:updateHomeworkExtensions($(this).val());">
    -->
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <!--
    <input type="hidden" name="g_id" value="{$g_id}" />
    -->
        <div class="option-title">Single Student Entry<br></div>
        <div class="option" style="width:25%; display:inline-block;">Student ID:<br><input class="option-input" type="text" name="user_id" id="myid" style="float:left"></div>
        <div class="option" style="width:25%; display:inline-block;">Late Days:<br><input class="option-input" type="text" name="late_days" id="myid" style="float:left"></div>
        <div class="option" style="width:10%; display:inline-block; vertical-align: bottom;"><input class="btn btn-primary" type="submit" id="myid" style="float:left"></div>
        <!--
        <a class="btn btn-default"
            onclick="console.log($(this).val()); console.log($([id='user_id']);">
                mytext
        </a>
        -->
        <div class="option-title">Multiple Student Entry Via CSV Upload
            <i class="fa fa-question-circle tooltip" aria-hidden="true">
                <span class="tooltiptext">Do not use column headers. CSV must be of the following form:<br>student_id,gradeable_id,late_days</span>
            </i><br>
        </div>
        <!--
        <div style="padding-bottom:20px;"><input type="file" name="csv_upload" onchange="this.form.submit()"></div>
        -->

        <div style="padding-bottom:20px;"><input id="myid" type="file" name="csv_upload" onchange="updateHomeworkExtensions2($(this));"></div>
    </form>
    </div>
HTML;
//         if (!is_array($user_table) || count($user_table) < 1) {
//         //No late days in DB -- indicate as much.
//             $return .= <<<HTML
//     <div class="panel">
//         <p>No late day exceptions are currently entered for this assignment.
//     </div>
// HTML;
//         } else {
//         //Late days found in DB -- build table to display
//             $return .= <<<HTML
//     <div class="panel" id="load-homework-extensions" align="center">
//     <table class="table table-striped table-bordered persist-area" style="width:50%">
//         <div class="option-title">Current Late Day Exceptions</div>
//         <!--
//         <input name="g_id" value='This is my fake g_id' />
//         -->
//         <td class="option-small-output"><input name="g_id" class="option-small-box" border="none" value="THIS IS MY TEXT" readonly></td>
//         <thead class="persist-thead">
//             <td>Student ID</td>
//             <td>First Name</td>
//             <td>Last Name</td>
//             <td>Late Day Exceptions</td>
//         </thead>
//         <tbody>
// HTML;


//                 $return .= <<<HTML
//             <tr>
//             <td><input name='user_id' value=""></td>
//             <td><input name='user_firstname' value=""></td>
//             <td><input name='user_lastname' value=""></td>
//             <td><input name='late_day_exceptions' value=""></td>
//             </tr>
// HTML;




//             foreach ($user_table as $index => $record) {
//                 $return .= <<<HTML
//             <tr>
//             <td name="user_id">{$record['user_id']}</td>
//             <td name="user_firstname">{$record['user_firstname']}</td>
//             <td name="user_lastname">{$record['user_lastname']}</td>
//             <td name="late_day_exceptions">{$record['late_day_exceptions']}</td>
//             </tr>
// HTML;

//             }
//             $return .= <<<HTML
//         </tbody>
//     </table>

//     </div>
// HTML;
//         }
        $return .= <<<HTML
    <div class="panel" id="load-homework-extensions" align="center">
    <table id="my_table" class="table table-striped table-bordered persist-area" style="width:70%" align="center">
        <div class="option-title" id="title"></div>
        <div>
        <thead class="persist-thead">
            <td>Student ID</td>
            <td>First Name</td>
            <td>Last Name</td>
            <td>Late Day Exceptions</td>
        </thead>
        </div>
    </table>
    </div>

    </form>
</div>
HTML;


        return $return;
    }
}
