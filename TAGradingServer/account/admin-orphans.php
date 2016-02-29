<?php
	include "../header.php";

	check_administrator();

	if($user_is_administrator)
	{
		$account_subpages_unlock = true;

	?>

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
			<h3 id="myModalLabel">Orphans</h3>
		</div>

		<div class="modal-body" style="padding-bottom:10px; padding-top:25px;">
			<table class="table table-bordered" id="rubricTable" style=" border: 1px solid #AAA;">
				<thead style="background: #E1E1E1;">
					<tr>
						<th style="width:110px;">Homework</th>
						<th style="width:110px;">Part</th>
						<th>Student RCS ID</th>
					</tr>
				</thead>

				<tbody style="background: #f9f9f9;">
					<?php
                        $db->query("SELECT student_id, student_rcs FROM students");
                        $students = array();
                        foreach ($db->rows() as $row) {
                            $students[$row['student_rcs']] = $row['student_id'];
                        }
						$total = 0;
						$params = array();
						$db->query("SELECT rubric_name, rubric_parts_sep, rubric_submission_id, rubric_parts_submission_id FROM rubrics ORDER BY rubric_due_date ASC", $params);
						foreach($db->rows() as $row) {
							$homework_name = $row['rubric_name'];
							$hw = $row['rubric_submission_id'];
							$parts = explode(",", $row['rubric_pats_submission_id']);

							$part_number = 0;
                            if ($row['rubric_parts_sep']) {
                                $hw .= $parts[$part_number];
                            }
                            $homework_directory_part = implode("/", array(__SUBMISSION_SERVER__, "results", $hw));
							//print $homework_directory_part;
							while(is_dir($homework_directory_part))
							{
								$rcs_ids = array();

								if($handle = opendir($homework_directory_part))
								{
									while(($temp_filename = readdir($handle)) !== false)
									{
										if(is_dir(implode("/", array($homework_directory_part, $temp_filename))) && $temp_filename != "." && $temp_filename != "..")
										{
											if(!isset($students[$temp_filename]) && !in_array($temp_filename, $rcs_ids))
											{
												array_push($rcs_ids, $temp_filename);
											}
										}
									}
									closedir($handle);
									for($i = 0; $i < count($rcs_ids); $i++)
									{
										$total++;
								?>
									<tr>
										<?php if($i == 0) { ?>
											<td rowspan="<?php echo count($rcs_ids); ?>">
												<?php echo $homework_name; ?>
											</td>

											<td rowspan="<?php echo count($rcs_ids); ?>">
												<?php echo $parts[$part_number]; ?>
											</td>
										<?php } ?>

										<td style="overflow: hidden;">
											<?php echo $rcs_ids[$i]; ?>
										</td>
									</tr>
								<?php
									}
								}

								$hw = $row['rubric_submission_id'];
								if ($row['rubric_parts_sep']) {
									$hw .= $parts[++$part_number];
								}
								else {
									break;
								}
                                $homework_directory_part = implode("/", array(__SUBMISSION_SERVER__, "results", $hw));
							}
						}

						if($total == 0)
						{
						?>
							<tr>
								<td colspan="3" style="text-align:center;">
									No Orphans
								</td>
							</tr>
						<?php
						}
					?>
				</tbody>
			</table>
		</div>
	</div>

	<script type="text/javascript">

	</script>

	<?php include "../footer.php";
	}
?>

