<?php

namespace app\views\admin;

use app\views\AbstractView;

class ExtensionsView extends AbstractView {
    public function displayExtensions($g_id, $g_ids,$user_table_db_data) {
        $return = <<<HTML
<div class="content">
    <h2>Excused Absense Extensions</h2>
    <form id="hwDefault" method="post" enctype="multipart/form-data" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'view'))}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <div class="panel">
        <div class="option">
            <p> Use this form to grant an extension (e.g., for an excused absense) to a user on a specific assignment.<br><br><br></p>
            <div class="option" style="width:30%; display:inline-block; vertical-align: bottom;">Select Rubric:<br>
                <select name="selected_gradeable" onchange="this.form.submit()" style="margin-top: 10px; width:250px">
HTML;
        foreach($g_ids as $index => $value) {
            if($value[0] == $g_id){
                $return .= <<<HTML
                    <option value="$value[0]" selected="true">$value[0]</option>
HTML;
            }
            else{
                $return .= <<<HTML
                    <option value="$value[0]">$value[0]</option>
HTML;
            }
        }
        $return .= <<<HTML
                </select>
            </div>
        </div>
    </form>
    <form id="excusedAbsenseForm" method="post" enctype="multipart/form-data" action="{$this->core->buildUrl(array('component' => 'admin', 'page' => 'extension', 'action' => 'update'))}">
    <input type="hidden" name="csrf_token" value="{$this->core->getCsrfToken()}" />
    <input type="hidden" name="selected_gradeable" value="{$g_id}" />
        <div class="option-title">Single Student Entry<br></div>
        <div class="option" style="width:25%; display:inline-block;">Student ID:<br><input class="option-input" type="text" name="user_id" style="float:left"></div>
        <div class="option" style="width:25%; display:inline-block;">Late Days:<br><input class="option-input" type="text" name="late_days" style="float:left"></div>
        <div class="option" style="width:10%; display:inline-block; vertical-align: bottom;"><input class="btn btn-primary" type="submit" style="float:left"></div>
        <div class="option-title">Multiple Student Entry Via CSV Upload<br></div>
        <div style="padding-bottom:20px;"><input type="file" name="csv_upload" onchange="this.form.submit()"></div>
    </div>
HTML;
        if (!is_array($user_table_db_data) || count($user_table_db_data) < 1) {
        //No late days in DB -- indicate as much.
            $return .= <<<HTML
    <div class="panel">
        <p>No late day exceptions are currently entered for this assignment.($g_id)
    </div>
HTML;
        } else {
        //Late days found in DB -- build table to display
            $return .= <<<HTML
    <div class="panel" align="center">
    <table class="table table-striped table-bordered persist-area" style="width:50%">
    <div class="option-title">Current Late Day Exceptions($g_id)</div>
    <div></div>
    <thead class="persist-thead">
        <td>Student ID</td>
        <td>First Name</td>
        <td>Last Name</td>
        <td>Late Day Exceptions</td>
    </thead>
HTML;
            foreach ($user_table_db_data as $index => $record) {
                $return .= <<<HTML
    <tbody>
        <td>{$record['user_id']}</td>
        <td>{$record['user_firstname']}</td>
        <td>{$record['user_lastname']}</td>
        <td>{$record['late_day_exceptions']}</td>
    </tbody>
HTML;
            }
            $return .= <<<HTML
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
