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
                    <th>RCS ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Sections</th>
                    <th>Administrator</th>
                </tr>
            </thead>
            <tbody>
HTML;

Database::query("SELECT u.*, array_agg(r.section_id) as sections FROM users as u LEFT JOIN (SELECT user_id, section_id FROM relationships_users ORDER BY user_id, section_id) as r ON u.user_id = r.user_id GROUP BY u.user_id");
foreach (Database::rows() as $user) {
    $user['sections'] = \lib\DatabaseUtils::fromPGToPHPArray($user['sections']);
    $user['sections'] = implode(", ", $user['sections']);
    print <<<HTML
                <tr>
                    <td>{$user['user_rcs']}</td>
                    <td>{$user['user_firstname']}</td>
                    <td>{$user['user_lastname']}</td>
                    <td>{$user['sections']}</td>
                    <td>{$user['user_is_administrator']}</td>
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

