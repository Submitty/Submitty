<?php
use app\models\User;

	include "../header.php";

	$account_subpages_unlock = true;

	$g_id = $_GET['g_id'];

	$params = array($g_id);
    
    //get the total score for the gradeable
	$db->query("
SELECT 
    g.g_id, 
    g_title, 
    sum(gc_max_value) as score 
FROM 
    gradeable AS g INNER JOIN gradeable_component AS gc ON g.g_id=gc.g_id
WHERE 
    g.g_id=?
    AND NOT gc_is_extra_credit
GROUP BY 
    g.g_id", $params);
	$homework_info = $db->row();
    
// students and their grade data    
$query = "
SELECT
	s.*,
	gt.g_id,
	case when gt.score is null then 0 else gt.score end
FROM
	users AS s
	LEFT JOIN (
		SELECT 
           g_id, 
           gd_user_id,
           sum(gcd_score) AS score
        FROM 
            gradeable_data AS gd INNER JOIN gradeable_component_data AS gcd ON gd.gd_id = gcd.gd_id
        WHERE g_id = ?
        GROUP BY 
            g_id, 
            gd_user_id
	) as gt ON gt.gd_user_id=s.user_id"; 

print <<<HTML
	<style type="text/css">
		body {
			overflow: scroll;
		}

		#container-rubric
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
	</style>
HTML;

if (!isset($homework_info['g_id'])) {
    print <<<HTML
    <div id="container-rubric">
		<div class="modal-header">
			<h3 id="myModalLabel">Invalid Gradeable</h3>
		</div>

		<div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
			Could not find a gradeable with that ID.<br /><br />
			<a class="btn" href="{$BASE_URL}/account/index.php">Select Different Gradeable</a>
        </div>
    </div>
HTML;
}
else {
    if (!User::$is_administrator) {
        if (isset($_GET['all']) && $_GET['all'] == "true") {
            $button = "<a class='btn' href='{$BASE_URL}/account/account-summary.php?g_id={$g_id}&course={$_GET['course']}'>View Your Sections</a>";
        }
        else {
            $button = "<a class='btn' href='{$BASE_URL}/account/account-summary.php?g_id={$g_id}&course={$_GET['course']}&all=true'>View All Sections</a>";
        }
    }
    else {
        $button = "";
    }
    
    
    $rubric_total = $homework_info["score"];
    
    print <<<HTML
	<div id="container-rubric">
		<div class="modal-header">
			<h3 id="myModalLabel" style="width: 75%; display: inline-block">{$homework_info['g_title']} Summary</h3>
			{$button}
		</div>

		<div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
			<table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
				<thead style="background: #E1E1E1;">
					<tr>
						<th>ID</th>
						<th>Status</th>
					</tr>
				</thead>

				<tbody style="background: #f9f9f9;">
HTML;


    $where = array();
    $order = array();

    if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true) {
        $params = array();
        $db->query("SELECT * FROM sections_registration ORDER BY sections_registration_id ASC", $params);
        
        $sections = array(array("sections_registration_id" => 0));
        $require_section = false;

    } else {
        // TODO update with rotating sections
        $params = array($user_id);
        $db->query("SELECT sections_registration_id FROM grading_registration WHERE user_id=? ORDER BY sections_registration_id", $params);
        $sections = array();
        foreach ($db->rows() as $section) {
            $sections[] = $section['sections_registration_id'];
        }
        $require_section = true;
        if(count($sections) > 0) {
            $where[] = "s.registration_section IN (" . implode(",", $sections) . ")";
        } else {
            $where[] = "s.user_id = null";
        }
    }
    
    $where[] = 'user_group=4';

    $order[] = "s.registration_section";
    $order[] = "s.user_id";

    if(count($where) > 0) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    if(count($order) > 0) {
        $query .= " ORDER BY " . implode(",", $order);
    }

    $prev_section = null;

    $params = array($g_id);
    
    $db->query($query, $params);
    $students = $db->rows();

    foreach ($students as $student) {
        if($prev_section !== $student['registration_section']) {
            $section_id = intval($student['registration_section']);
            print <<<HTML

					<tr class="info">
						<td colspan="2" style="text-align:center;">
							Students Assigned to Grading Section {$section_id}
						</td>
					</tr>
HTML;
            $prev_section = $section_id;
        }
        $row = $student;
        print <<<HTML
                <tr>
                    <td>
                        {$student["user_id"]} ({$student["user_lastname"]}, {$student["user_firstname"]})
                    </td>
                    <td>
HTML;
        if(count($students) > 0) {
            if(isset($row['score'])) {
                if($row['score'] >= 0) {
                    echo "<a class='btn' href='{$BASE_URL}/account/index.php?g_id=" . $_GET["g_id"] . "&individual=" . $student["user_id"] . "'>[ " . $row['score'] . " / " . $rubric_total . " ]</a>";
                } else {
                    echo "<a class='btn btn-danger' href='{$BASE_URL}/account/index.php?g_id=" . $_GET["g_id"] . "&individual=" . $student["user_id"] . "'>[ GRADING ERROR ]</a>";
                }
            } else {
                echo "<a class='btn btn-primary' href='{$BASE_URL}/account/index.php?g_id=" . $_GET["g_id"] . "&individual=" . $student["user_id"] . "'>Grade</a>";
            }
        } else {
            echo "<a class='btn btn-primary' href='{$BASE_URL}/account/index.php?g_id=" . $_GET["g_id"] . "&individual=" . $student["user_id"] . "'>Grade</a>";
        }
        print <<<HTML
                    </td>
                </tr>
HTML;
    }
    print <<<HTML
				</tbody>
			</table>
		</div>

		<div class="modal-footer">
			<a class="btn" href="{$BASE_URL}/account/index.php">Select Different Homework</a>
			<a class="btn" href="{$BASE_URL}/account/index.php?g_id={$_GET['g_id']}">Grade Next Student</a>
		</div>
	</div>
HTML;

    print <<<HTML
	<script type="text/javascript">
		createCookie('backup',0,1000);
	</script>
HTML;
}

include "../footer.php";