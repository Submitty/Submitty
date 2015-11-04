<?php
include "../header.php";

$account_subpages_unlock = true;

?>

<style type="text/css">
    body {
        overflow: scroll;
    }

    #container-labs
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

<div id="container-labs">
    <div class="modal-header">
        <h3 id="myModalLabel" style="width:50%; display:inline-block;">Labs</h3>
        <div style="text-align:right; width:49%; display:inline-block;">
            <i class="icon-question-sign" rel="tooltip" title="No Color - No Credit
Dark Blue - Full Credit
Light Blue - Half Credit
Red - [SAVE ERROR] Refresh Page"></i>
        </div>

    </div>

    <div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
        <div class="bs-docs-example">
            <ul id="myTab" class="nav nav-tabs">
                <?php

                $params = array();
                $db->query("SELECT * FROM labs ORDER BY lab_number ASC", $params);

                $first = true;

                foreach($db->rows() as $lab_row)
                {
                    if($first)
                    {
                        ?>
                        <li class="active"><a href="#lab<?php echo $lab_row["lab_id"]; ?>" data-toggle="tab"><?php echo $lab_row["lab_title"]; ?></a></li>
                    <?php
                    }
                    else
                    {
                        ?>
                        <li><a href="#lab<?php echo $lab_row["lab_id"]; ?>" data-toggle="tab"><?php echo $lab_row["lab_title"]; ?></a></li>
                    <?php
                    }

                    $first = false;

                }
                ?>


            </ul>

            <div id="myTabContent" class="tab-content">

                <?php

                $first = true;

                foreach($db->rows() as $lab_row)
                {
                    $lab_row_checkpoints = explode(",", $lab_row["lab_checkpoints"]);

                    ?>
                    <div class="tab-pane fade<?php echo ($first ? ' active in' : ''); ?>" id="lab<?php echo $lab_row["lab_id"]; ?>">
                        <table class="table table-bordered table-striped" id="labsTable" style=" border: 1px solid #AAA;">
                            <thead>
                            <tr>
                                <th>RCS ID</th>

                                <?php
                                foreach($lab_row_checkpoints as $checkpoint)
                                {
                                    ?>
                                    <th><?php echo $checkpoint; ?></th>
                                <?php
                                }
                                ?>
                            </tr>
                            </thead>

                            <tbody style="background: #f9f9f9;">
                            <?php

                            $params = array($user_id);
                            if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true)
                            {
                                $params = array();
                                $db->query("SELECT * FROM sections ORDER BY section_id ASC", $params);
                            }
                            else
                            {
                                $params = array($user_id);
                                $db->query("SELECT * FROM relationships_users WHERE user_id=? ORDER BY section_id ASC", $params);
                            }

                            foreach($db->rows() as $section)
                            {
                                ?>

                                <tr class="info">
                                    <td colspan="<?php echo count($lab_row_checkpoints) + 1; ?>" style="text-align:center;">
                                        Enrolled Students in Section <?php echo intval($section["section_id"]); ?>
                                        <a href="<?php echo $BASE_URL; ?>/account/print/print_lab.php?course=<?php echo $_GET['course']; ?>&lab_id=<?php echo $lab_row['lab_id']; ?>&section_id=<?php echo $section['section_id']; ?>"><div class="icon-print"></div></a>
                                    </td>
                                </tr>
                                <?php
                                $params = array(intval($lab_row["lab_id"]),intval($section["section_id"]));
                                $db->query("
SELECT
    s.student_rcs
    , s.student_id
    , s.student_first_name
    , s.student_last_name
    , case when gl.grade_value_array is null then '{}' else gl.grade_value_array end
    , case when gl.grade_checkpoint_array is null then '{}' else gl.grade_checkpoint_array end
    , gl.lab_id
FROM
    students AS s
    LEFT JOIN (
        SELECT
            array_agg(grade_lab_value) as grade_value_array
            , array_agg(grade_lab_checkpoint) as grade_checkpoint_array
            , student_rcs
            , lab_id
        FROM
            grades_labs
        WHERE
            lab_id=?
        GROUP BY
            student_rcs
            , lab_id
    ) AS gl ON s.student_rcs = gl.student_rcs
WHERE
    s.student_section_id=?
ORDER BY
    s.student_rcs
                                ", $params);

                                foreach($db->rows() as $row)
                                {
                                    $grade_value_array = pgArrayToPhp($row['grade_value_array']);
                                    $grade_checkpoint_array = pgArrayToPhp($row['grade_checkpoint_array']);
                                    if (count($grade_checkpoint_array) > 0 && count($grade_value_array) == count($grade_checkpoint_array)) {
                                        $grades = array_combine($grade_checkpoint_array,$grade_value_array);
                                    }
                                    else {
                                        $grades = array();
                                    }


                                    //$params = array(intval($row["student_id"]));
                                    //$db->query("SELECT * FROM students WHERE student_id=?", $params);
                                    $student_info = $row;
                                    ?>
                                    <tr>
                                        <td class="cell-all" id="cell-<?php echo $lab_row["lab_id"]; ?>-all-<?php echo $row["student_rcs"]; ?>" cell-status="0">
                                            <?php echo $student_info["student_rcs"]; ?> (<?php echo $student_info["student_last_name"]; ?>, <?php echo $student_info["student_first_name"]; ?>)
                                        </td>

                                        <?php
                                        $count = 1;

                                        foreach($lab_row_checkpoints as $checkpoint)
                                        {
                                            if(isset($grades[$count])) {
                                                $grade_value = $grades[$count];
                                            }
                                            else {
                                                $grade_value = 0;
                                            }
                                            //$params = array(intval($lab_row["lab_id"]), intval($row["student_rcs"]), $count);
                                            //$db->query("SELECT grade_lab_value FROM grades_labs WHERE lab_id=? AND student_id=? AND grade_lab_checkpoint=?", $params);
                                            $mode = $grade_value;

                                            if($mode == 0)
                                            {
                                                $background_color = "";
                                            }
                                            elseif($mode == 1)
                                            {
                                                $background_color = "background-color:#149bdf";
                                            }
                                            elseif($mode == 2)
                                            {
                                                $background_color = "background-color:#88d0f4";
                                            }

                                            ?>
                                            <td id="cell-<?php echo $lab_row["lab_id"]; ?>-check<?php echo $count; $count++; ?>-<?php echo $row["student_rcs"]; ?>" cell-status="<?php echo $mode; ?>" style="<?php echo $background_color; ?>"></td>
                                        <?php
                                        }
                                        ?>
                                    </tr>
                                <?php
                                }
                            }
                            ?>
                            </tbody>
                        </table>
                    </div>
                    <?php

                    $first = false;
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php

print <<<HTML
<script type="text/javascript">

    $("td[id^=cell-]").click(function() {

        var cell_status = (parseInt($(this).attr('cell-status')) + 1) % 3;
        var name = $(this).attr("id");
        name = name.split("-");
        var lab = name[1];
        var check = name[2].replace("check", "");
        var rcs = name[3];
        var url = "{$BASE_URL}/account/ajax/account-labs.php?course={$_GET['course']}&lab=" + lab + "&check=" + check + "&rcs=" + rcs + "&mode=" + cell_status;

        if($(this).hasClass("cell-all"))
        {
            // Named cell
            $(this).attr('cell-status', cell_status);
            updateColor("td[id^=cell-" + lab + "-check][id$=-" + rcs + "]", cell_status, url);
        }
        else
        {
            // Non-named cell
            updateColor(this, cell_status, url);
        }
    });

    function updateColor(item, mode, url)
    {
        $(item).attr('cell-status', mode);

        if(mode == 0)
        {
            $(item).css("background-color", "");
            $(item).css("border-right", "15px solid #ddd");
        }
        else if(mode == 1)
        {
            $(item).css("background-color", "#149bdf");
            $(item).css("border-right", "15px solid #f9f9f9");
        }
        else if(mode == 2)
        {
            $(item).css("background-color", "#88d0f4");
            $(item).css("border-right", "15px solid #f9f9f9");
        }

        // alert(url);
        submitAJAX(url, updateSuccess, updateFail, item);
    }

    function updateSuccess(item)
    {
        $(item).stop(true, true).animate({"border-right-width":"0px"}, 400);
    }

    function updateFail(item)
    {
        $(item).css("border-right-width", "15px");
        $(item).stop(true, true).animate({"border-right-color":"#DA4F49"}, 400);
    }

    function submitAJAX(url, callBackSucess, callBackFail, item)
    {
        $.ajax(url)
            .done(function(response) {
                if(response == "updated")
                {
                    callBackSucess(item);
                }
                else
                {
                    callBackFail(item);
                    console.log(response);
                }
            })
            .fail(function() { window.alert("[SAVE ERROR] Refresh Page"); });
    }

    $(document).ready(function(){
        $("[rel=tooltip]").tooltip({ placement: 'top'});
    });
</script>
HTML;

include "../footer.php";
?> 
        
        
