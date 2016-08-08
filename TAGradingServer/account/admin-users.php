<?php
include "../header.php";

use lib\Database;

check_administrator();

print <<<HTML
<style type="text/css">
    body {
        overflow: scroll;
    }

    #container-users
    {
        width:700px;
        margin:100px auto;
        margin-top: 130px;
        background-color: #fff;
        border: 1px solid #999;
        border: 1px solid rgba(0,0,0,0.3);
        -webkit-border-radius: 6px;
        -moz-border-radius: 6px;
        border-radius: 6px;outline: 0;
        -webkit-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -moz-box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        box-shadow: 0 3px 7px rgba(0,0,0,0.3);
        -webkit-background-clip: padding-box;
        -moz-background-clip: padding-box;
        background-clip: padding-box;
    }
    #labsTable td
    {
        -webkit-touch-callout: none;
        -webkit-user-select: none;
        -khtml-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    .tooltip-inner {
        white-space:pre-wrap;
    }
</style>

<div id="container-users">
    <div class="modal-header">
        <h3 id="myModalLabel" style="width:20%; display:inline-block;">Users</h3>
    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <table class="table table-bordered striped-table" id="labsTable" style=" border: 1px solid #AAA;">
            <thead style="background: #E1E1E1;">
                <tr>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Registration Sections</th>
                    <th>Administrator</th>
                </tr>
            </thead>
            <tbody>
HTML;

Database::query("SELECT * FROM users WHERE user_group <=?", array(3));
foreach (Database::rows() as $user) {
    $is_admin = $user['user_group'] <=1;
    if ($user['user_group'] <=1){
        $sections = 'All';
    }
    else{
        $sections = '';
        Database::query("SELECT * FROM grading_registration WHERE user_id=?", array($user['user_id']));
        foreach (Database::rows() as $section){
            if($sections != ''){
                $sections .= ',';
            }
            $sections .= $section['sections_registration_id'];
        }
    }
    print <<<HTML
                <tr>
                    <td>{$user['user_id']}</td>
                    <td>{$user['user_firstname']}</td>
                    <td>{$user['user_lastname']}</td>
                    <td>{$sections}</td>
                    <td>{$is_admin}</td>
                </tr>
HTML;
}
print <<<HTML
            </tbody>
        </table>
    </div>
</div>
HTML;

include '../footer.php';

