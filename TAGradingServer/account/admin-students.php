<?php
include "../header.php";

use lib\Database;

print <<<HTML
<style type="text/css">
    body {
        overflow: scroll;
    }

    #container-students
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

<div id="container-students">
    <div class="modal-header">
        <h3 id="myModalLabel" style="width:20%; display:inline-block;">Students</h3>
    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <table class="table table-bordered striped-table" id="labsTable" style=" border: 1px solid #AAA;">
            <thead style="background: #E1E1E1;">
                <tr>
                    <th>RCS ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Grading Section</th>
                </tr>
            </thead>
HTML;

Database::query("SELECT * FROM students ORDER BY student_section_id, student_rcs");
$last_section = -1;
foreach(Database::rows() as $student) {
    if ($student['student_section_id'] != $last_section) {
        if ($last_section != -1) {
            print "            </tbody>\n";
        }
        print <<<HTML
            <tr class="info">
                <td colspan="4" style="text-align:center;" id="section-{$student['student_section_id']}">
                        Students Enrolled in Section {$student["student_section_id"]}
                </td>
            </tr>
            <tbody>
HTML;
        $last_section = $student['student_section_id'];
    }

    print <<<HTML
                <tr>
                    <td>{$student['student_rcs']}</td>
                    <td>{$student['student_first_name']}</td>
                    <td>{$student['student_last_name']}</td>
                    <td>{$student['student_grading_id']}</td>
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

