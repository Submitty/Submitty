<?php

namespace app\views\admin;

use app\views\AbstractView;

class LateDayView extends AbstractView {
    public function displayLateDays($users) {
        $return = <<<HTML
<div type="hidden" id="message"></div>
<div class="content">
    <h2>Late Days Allowed</h2>
    <form id="lateDayForm" method="post" enctype="multipart/form-data" onsubmit="return updateLateDays($(this));">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="panel">
            <p> Use this form to grant students additional late days (beyond the initial number specified in the course configuration).<br>
                Students may use these additional late days for any future homeworks (after the specificed date).<br><br><br>
            </p>
            <div class="option-title">Single Student Entry<br></div>
            <div class="option" style="width:30%; display:inline-block;">Student ID:<br><input class="option-input" type="text" name="user_id" id="user_id" style="float:left"></div>
            <div class="option" style="width:30%; display:inline-block;">Datestamp (MM/DD/YY):<br><input class="option-input" type="text" name="datestamp" id="datestamp" style="float:left"></div>
            <div class="option" style="width:15%; display:inline-block;">Late Days:<br><input class="option-input" type="text" name="late_days" id="late_days" style="float:left"></div>
            <div class="option" style="width:15%; display:inline-block; float:right;"><br><input class="btn btn-primary" type="submit" style="float:left"></div>
            <div class="option-title"><br><br>Multiple Student Entry Via CSV Upload</div>
            <div>Do not use column headers. CSV must be of the following form: student_id, MM/DD/YY, late_days</div>
            <div style="padding-bottom:20px;"><input type="file" name="csv_upload" id="csv_upload" onchange="return updateLateDays($(this));"></div>
    </form>
    </div>

    <div class="panel" id="load-late-days" align="center">
    <table id="late_day_table" class="table table-striped table-bordered persist-area" style="width:80%">
        <div class="option-title" id="late_day_title">Late Days Allowed</div>
        <thead class="persist-thead">
            <td>Student ID</td>
            <td>First Name</td>
            <td>Last Name</td>
            <td>Total Allowed Late Days</td>
            <td>Effective Date</td>
        </thead>
        <tbody>
HTML;
        if (!is_array($users) || count($users) < 1) {
            $return .= <<<HTML
    <tr><td colspan="4">No late days are currently entered.</td></tr>
HTML;
        }
        else{
            foreach ($users as $user) {
                $return .= <<<HTML
            <tr>
            <td>{$user->getId()}</td>
            <td>{$user->getDisplayedFirstName()}</td>
            <td>{$user->getLastName()}</td>
            <td>{$user->getAllowedLateDays()}</td>
            <td>{$user->getSinceTimestamp()}</td>
            </tr>
HTML;
            }
        }
            $return .= <<<HTML
        </tbody>
    </table>
    </div>
</div>
HTML;
        return $return;
    }
}

