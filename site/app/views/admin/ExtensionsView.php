<?php

namespace app\views\admin;

use app\views\AbstractView;

class ExtensionsView extends AbstractView {
    public function displayExtensions($g_ids) {
        $return = <<<HTML
<div type="hidden" id="message"></div>
<div class="content">
    <h2>Excused Absense Extensions</h2>
    <form id="excusedAbsenseForm" method="post" enctype="multipart/form-data" action="" onsubmit="return updateHomeworkExtensions($(this));">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="panel">
        <div class="option">
            <p> Use this form to grant an extension (e.g., for an excused absense) to a user on a specific assignment.<br><br><br></p>
            <div class="option">Select Rubric:<br>
                <select name="g_id" onchange="loadHomeworkExtensions($(this).val());" style="margin-top: 10px; width:50%">
                    <option disabled selected value> -- select an option -- </option>
HTML;
        foreach($g_ids as $index => $value) {
            $return .= <<<HTML
                    <option value="{$value['g_id']}">{$value['g_title']}</option>
HTML;
        }
        $return .= <<<HTML
                </select>
            </div>
        </div>
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
        <div class="option-title">Single Student Entry<br></div>
        <div class="option" style="width:25%; display:inline-block;">Student ID:<br><input class="option-input" type="text" name="user_id" id="user_id" style="float:left"></div>
        <div class="option" style="width:25%; display:inline-block;">Late Days:<br><input class="option-input" type="text" name="late_days" id="late_days" style="float:left"></div>
        <div class="option" style="width:10%; display:inline-block; vertical-align: bottom;"><input class="btn btn-primary" type="submit" style="float:left"></div>
        <div class="option-title">Multiple Student Entry Via CSV Upload</div>
        <div>Do not use column headers. CSV must be of the following form: student_id, gradeable_id, late_days</div>
        <div style="padding-bottom:20px;"><input type="file" name="csv_upload" id="csv_upload" onchange="return updateHomeworkExtensions($(this));"></div>
    </form>
    </div>
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
</div>
HTML;
        return $return;
    }
}
