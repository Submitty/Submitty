<?php

namespace app\views\admin;

use app\views\AbstractView;

class LateDayView extends AbstractView {
    public function displayLateDays($user_table) {
        $return = <<<HTML
<div class="content">
    <h2>Late Days Allowed</h2>
    <form id="lateDayForm" method="post" enctype="multipart/form-data" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'late', 'action' => 'update_late'))}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="panel">
            <p> Use this form to grant students additional late days (beyond the initial number specified in the course configuration).<br>
                Students may use these additional late days for any future homeworks (after the specificed date).<br><br><br>
            </p>
            <div class="option-title">Single Student Entry<br></div>
            <div class="option" style="width:30%; display:inline-block;">Student ID:<br><input class="option-input" type="text" name="user_id" style="float:left" value=""/></div>
            <div class="option" style="width:30%; display:inline-block;">Datestamp (MM/DD/YY):<br><input class="option-input" type="text" name="datestamp" style="float:left"></div>
            <div class="option" style="width:15%; display:inline-block;">Late Days:<br><input class="option-input" type="text" name="late_days" style="float:left"></div>
            <div class="option" style="width:15%; display:inline-block; float:right;"><br><input class="btn btn-primary" type="submit" style="float:left"></div>
            <div class="option-title"><br><br>Multiple Student Entry Via CSV Upload
                <i class="fa fa-question-circle tooltip" aria-hidden="true">
                    <span class="tooltiptext">Do not use column headers. CSV must be of the following form:<br>student_id,MM/DD/YY,late_days</span>
                </i><br>
            </div>
            <div style="padding-bottom:20px;"><input type="file" name="csv_upload" onchange="this.form.submit()"></div>
    </div>
HTML;
        if (!is_array($user_table) || count($user_table) < 1) {
        //No late days in DB -- indicate as much.
            $return .= <<<HTML
    <div class="panel">
    <p>No late days are currently entered.</p>
    </div>
HTML;
        } else {
        //Late days found in DB -- build table to display
            $return .= <<<HTML
    <div class="panel" align="center">
    <table class="table table-striped table-bordered persist-area" style="width:60%">
        <div class="option-title">Late Days Allowed</div>
        <thead class="persist-thead">
            <td>Student ID</td>
            <td>First Name</td>
            <td>Last Name</td>
            <td>Total Allowed Late Days</td>
            <td>Effective Date</td>
        </thead>
        <tbody>
HTML;
            foreach ($user_table as $index => $record) {
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
            $return .= <<<HTML
        </tbody>
    </table>
    </div>
HTML;
        }
        $return .= <<<HTML
    </form>
</div>
HTML;

        return $return;
    }
}

