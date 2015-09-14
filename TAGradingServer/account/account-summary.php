<?php
	include "../header.php";

	$account_subpages_unlock = true;

	$rubric_id = intval($_GET['hw']);

	$params = array($rubric_id);
	$db->query("SELECT r.rubric_id, r.rubric_name, sum(question_total) as score FROM rubrics AS r,
	questions AS q WHERE r.rubric_id=? AND q.rubric_id=r.rubric_id GROUP BY r.rubric_id", $params);
	$homework_info = $db->row();
	//$rubric_id = $homework_info["rubric_id"];
	$rubric_total = $homework_info["score"];

	$query = "
SELECT
	s.*,
	gt.grade_id,
	gt.rubric_id,
	gt.score
FROM
	students AS s
	LEFT JOIN (
		SELECT
			g.grade_id
			, g.rubric_id
			, g.student_rcs
			, sum(case when gq.grade_question_score is null then -100000 else gq.grade_question_score end) as score
		FROM
			grades AS g
			, grades_questions AS gq
		WHERE
			gq.grade_id=g.grade_id
			AND g.rubric_id=?
		GROUP BY
			gq.grade_id
			, g.grade_id
			, g.rubric_id
	) as gt ON gt.student_rcs=s.student_rcs";

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
	
	<div id="container-rubric">
		<div class="modal-header">
			<h3 id="myModalLabel">{$homework_info['rubric_name']} Summary</h3>
		</div>
	
		<div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
			<table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
				<thead style="background: #E1E1E1;">
					<tr>
						<th>RCS ID</th>
						<th>Status</th>
					</tr>
				</thead>
				
				<tbody style="background: #f9f9f9;">
HTML;


    $where = array();
    $order = array();

	if((isset($_GET["all"]) && $_GET["all"] == "true") || $user_is_administrator == true)
	{
		/*$params = array();
		$db->query("SELECT * FROM sections ORDER BY section_id ASC", $params);
		*/
		$sections = array(array("grading_section_id" => 0));
		$require_section = false;
        
	}
	else
	{
		$params = array($user_id, $rubric_id);
		$db->query("SELECT grading_section_id FROM homework_grading_sections WHERE user_id=? AND rubric_id=? ORDER BY grading_section_id", $params);
		$sections = array();
        foreach($db->rows() as $section) {
            $sections[] = $section['grading_section_id'];
        }
		$require_section = true;
        if (count($sections) > 0) {
            $where[] = "s.student_grading_id IN (" . implode(",", $sections) . ")";
        }
        else {
            $where[] = "s.student_rcs = null";
        }
	}

    $order[] = "s.student_grading_id";
    $order[] = "s.student_rcs";
	
    if (count($where) > 0) {
        $query .= " WHERE ".implode(" AND ",$where);
    }
    if (count($order) > 0) {
        $query .= " ORDER BY ".implode(",",$order);
    }

    $prev_section = null;

    $params = array($rubric_id);
    $db->query($query,$params);
	foreach ($db->rows() as $student) {
        if ($prev_section !== $student['student_grading_id']) {
            $section_id = intval($student['student_grading_id']);
            print <<<HTML

					<tr class="info">
						<td colspan="2" style="text-align:center;">
							Enrolled Students in Grading Section {$section_id}
						</td>
					</tr>
HTML;
            $prev_section = $section_id;
        }
        $row = $student;
        print <<<HTML
                <tr>
                    <td>
                        {$student["student_rcs"]} ({$student["student_last_name"]}, {$student["student_first_name"]})
                    </td>
                    <td>
HTML;
        if (count($db->rows()) > 0) {
            if (isset($row['score'])) {
                if ($row['score'] >= 0) {
                    echo "<a class='btn' href='{$BASE_URL}/account/index.php?hw=" . $_GET["hw"] . "&individual=" . $student["student_rcs"] . "'>[ " . $row['score'] . " / " . $rubric_total . " ]</a>";
                } else {
                    echo "<a class='btn btn-danger' href='{$BASE_URL}/account/index.php?hw=" . $_GET["hw"] . "&individual=" . $student["student_rcs"] . "'>[ GRADING ERROR ]</a>";
                }
            } else {
                echo "<a class='btn btn-primary' href='{$BASE_URL}/account/index.php?hw=" . $_GET["hw"] . "&individual=" . $student["student_rcs"] . "'>Grade</a>";
            }
        }
        else {
            echo "<a class='btn btn-primary' href='{$BASE_URL}/account/index.php?hw=" . $_GET["hw"] . "&individual=" . $student["student_rcs"] . "'>Grade</a>";
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
			<a class="btn" href="{$BASE_URL}/account/index.php?hw={$_GET['hw']}">Grade Next Student</a>
		</div>
	</div>	
HTML;

	print <<<HTML
	<script type="text/javascript">
		createCookie('backup',0,1000);
	</script>
HTML;
	include "../footer.php";
?>