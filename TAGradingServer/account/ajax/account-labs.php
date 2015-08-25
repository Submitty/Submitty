<?php 
	include "../../toolbox/functions.php";
	
	$lab = intval($_GET["lab"]);
	$check = intval($_GET["check"]);
	$rcs = $_GET["rcs"];
	$mode = intval($_GET["mode"]);

	$db->query("SELECT student_id FROM students WHERE student_rcs=?",array($rcs));
	$row = $db->row();
	$id = $row['student_id'];
	
	if($check == "all")
	{
		$params = array($lab);
		$db->query("SELECT * FROM labs WHERE lab_id=?", $params);		
		$lab_row = $db->row();
		$lab_row_checkpoints = explode(",", $lab_row["lab_checkpoints"]);

		$i = 1;
		$checks = array();
		while($i <= count($lab_row_checkpoints))
		{	
			array_push($checks, $i);
			$i++;
		}
	}
	else
	{
		$checks = array($check);
	}

	foreach($checks as $check)
	{
		$params = array($lab, $rcs, $check);
		$db->query("SELECT * FROM grades_labs WHERE lab_id=? AND student_rcs=? AND grade_lab_checkpoint=?", $params);
		$temp = $db->row();

		$old_mode = (isset($temp["grade_lab_value"]) ? $temp["grade_lab_value"] : 0);

		if($mode != $old_mode or true)
		{
			if(isset($temp["grade_lab_value"]))
			{
				// UPDATE	
				$params = array($user_id, $mode, $temp["grade_lab_id"]);
				$db->query("UPDATE grades_labs SET grade_lab_user_id=?, grade_finish_timestamp=NOW(), grade_lab_value=? WHERE grade_lab_id=?", $params);
			}
			else
			{
				// INSERT
				$params = array($lab, $id, $check, $mode, $user_id, $rcs);
				$db->query("INSERT INTO grades_labs (lab_id, student_id, grade_lab_checkpoint, grade_lab_value, grade_lab_user_id, grade_finish_timestamp, student_rcs) VALUES (?,?,?,?,?,NOW(),?)", $params);
			}
		}
	}
	
    echo "updated";
?>