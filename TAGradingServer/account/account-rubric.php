<?php 

if($account_subpages_unlock) {    
    print <<<HTML
	<div id="rubric" style="overflow-y:scroll;">
		<div id="inner-container">
HTML;
    /* 
        Variables defined in account-homework.php or index.php:
            $rubric_id
            $rubric_lates
            $homework_number
    */
    
    $previous_grade = array();
    
    $params = array($student_rcs, $rubric_id);
    $db->query("SELECT grade_id, grade_user_id, grade_comment, grade_finish_timestamp, grade_days_late FROM grades WHERE student_rcs=? AND rubric_id=?", $params);
    $row = $db->row();

    $grade_days_late = -1;
    if(isset($row["grade_id"]))
    {
        $grade_comment = $row["grade_comment"];
        $grade_finish_timestamp = "Last Graded: " . date("m/d/y g:i A", strtotime($row["grade_finish_timestamp"]));
        //$grade_days_late = intval($row["grade_days_late"]);
        $params = array($row["grade_id"]);
        $db->query("SELECT * FROM grades_questions WHERE grade_id=?", $params);
    
        foreach($db->rows() as $temp_row)
        {
            $previous_grade[intval($temp_row["question_id"])] = array(floatval($temp_row["grade_question_score"]), $temp_row["grade_question_comment"]);
        }
        
        $db->query("SELECT * FROM users WHERE user_id='{$row['grade_user_id']}'");
        $user = $db->row();
        $submitted = 1;
    }
    else
    {
        $grade_comment = "";
        $grade_finish_timestamp = "";
        $grade_days_late = -1;
        $user = array();
    }
    
    $params = array($student_rcs, $rubric_id);
    $db->query("SELECT * FROM late_day_exceptions WHERE ex_student_rcs=? AND ex_rubric_id=?", $params);
    
    if (count($db->rows())) {
        $row = $db->row();
    }
    else {
        $row = array("ex_late_days"=>0);
    }

    $max_late = 0;
    $min_late = 1000;
    foreach($rubric_lates as $late)
    {
        $max_late = max($max_late, $late);
        $min_late = min($min_late, $late);
    }

    // TODO: Should only consider homeworks due previous to this one
    $params = array($student_rcs, $rubric_id);
    $db->query("
SELECT (SUM(g.grade_days_late) - SUM(s.ex_late_days)) as used_late_days
FROM grades as g 
LEFT JOIN 
	(
	SELECT * FROM late_day_exceptions
	) as s 
	on s.ex_rubric_id = g.rubric_id and s.ex_student_rcs=g.student_rcs
WHERE g.student_rcs=? AND g.status=1 AND g.rubric_id<?", $params);
    $late_row = $db->row();
    if (empty($late_row)) {
        $late_row['used_late_days'] = 0;
    }
    //$used_late_days = (isset($late_row["used_late_days"]) && $late_row["used_late_days"] >= 0) ? $late_row["used_late_days"] : 0;
    $used_late_days = (isset($late_row['used_late_days']) && $late_row["used_late_days"] >= 0) ? $late_row["used_late_days"] : 0;
    $late_days_to_use = $max_late - $row['ex_late_days'];
    $late_days_to_use = ($late_days_to_use < 0) ? 0 : $late_days_to_use;
    //print $used_late_days." ".$late_days_to_use." ".$student_allowed_lates;
    if(($used_late_days + $late_days_to_use <= $student_allowed_lates || $student_allowed_lates < 0) && ($late_days_to_use <= $rubric_late_days || $rubric_late_days < 0) || $max_late <= 0) {
        $late_days_max_surpassed = false;
    }
    else {
        $late_days_max_surpassed = true;
    }

    if ($submitted == 1 && $late_days_max_surpassed == false) {
        $status = 1;
    }
    else {
        $status = 0;
    }
    
    $individual = intval(isset($_GET["individual"]));
    if (isset($_COOKIE['auto'])) { 
        $cookie_auto = (intval($_COOKIE["auto"]) == 1 ? "checked" : ""); 
    }
    else {
        $cookie_auto = "";
    }
    
    print <<<HTML
			<div id="rubric-title">
				<div class="span2" style="float:left; text-align: left;"><b>Homework {$homework_number}</b></div>
				<div class="span2" style="float:right; text-align: right; margin-top: -20px;"><b>{$student_last_name}, {$student_first_name}<br/>RCS: {$student_rcs}</b></div>
			</div>
			
			<form action="{$BASE_URL}/account/submit/account-rubric.php?course={$_GET['course']}&hw={$homework_number}&student={$student_rcs}&individual={$individual}" method="post">
			    <input type="hidden" name="submitted" value="{$submitted}" />
			    <input type="hidden" name="status" value="{$status}" />
			    <input type="hidden" name="late" value="{$max_late}" />
				<div id="inner-container-seperator" style="background-color:#AAA; margin-top: 0; margin-bottom:0;"></div>
				
				<div style="margin-top: 0; margin-bottom:35px;">
					<input type="checkbox" style="margin-top:0; margin-right:5px;" id="rubric-autoscroll-checkbox" {$cookie_auto} /><span style="font-size:11px;">Rubric Auto Scroll</span>
				</div>
				
				<!--<div style="width:100%;"></div>-->
HTML;
    if ($rubric_late_days >= 0) {
        print <<<HTML
				<span style="color: black">Homework allows {$rubric_late_days} late day(s).</span><br />
HTML;
    }
    if ($student_allowed_lates >= 0) {
        print <<<HTML
                <span style="color: black">Student has used {$used_late_days}/{$student_allowed_lates} late day(s) this semester.</span><br />
HTML;
    }
    print <<<HTML
				Late Days Used on Assignment:&nbsp;{$max_late}<br />
HTML;
    
    if ($row['ex_late_days'] > 0) {
        print <<<HTML
                <span style="color: green">Student has an exception of {$row['ex_late_days']} late day(s).</span><br />                  
HTML;
    }
    print <<<HTML
				<b>Late Days Used:</b>&nbsp;{$late_days_to_use}<br />
HTML;
    if($late_days_max_surpassed) {
        print <<<HTML
					<b style="color:#DA4F49;">Too many total late days used for this assignment</b><br />
HTML;
    }
    $print_status = ($status == 1) ? "Good" : "Bad";
    print <<<HTML
        <b>Status: </b>$print_status
HTML;
    
    print <<<HTML
				<br/><br/>
				<table class="table table-bordered table-striped" id="rubric-table">
					<thead>
HTML;
    if(isset($_GET["individual"])) { ?>
							<tr style="background-color:#EEE;">
								<th style="padding-left: 1px; padding-right: 0px; border-bottom:5px #FAA732 solid;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
								<th style="width:40px; border-bottom:5px #FAA732 solid;">Part</th>
								<th style="border-bottom:5px #FAA732 solid;">Question</th>
								<th style="width:40px; border-bottom:5px #FAA732 solid;">Points</th>
								<th style="width:40px; border-bottom:5px #FAA732 solid;">Total</th>
							</tr>
    <?php } else { ?>
							<tr style="background-color:#EEE;">
								<th style="padding-left: 1px; padding-right: 0px;"><i class="icon-time" id="progress-icon" style="margin-top: 2px;"></th>
								<th style="width:40px;">Part</th>
								<th>Question</th>
								<th style="width:40px;">Points</th>
								<th style="width:40px;">Total</th>
							</tr>
    <?php }?>
					</thead>
				
					<tbody>
					
						<?php 
							$c = 1;
							$rubric_total = 0;
							$last_seen_part = -1;
							$params = array($rubric_id);
							$db->query("SELECT * FROM questions WHERE rubric_id=? ORDER BY question_part_number, question_number", $params);
							foreach($db->rows() as $row)
							{ 
								$total = floatval($row["question_total"]);
								$rubric_total += $total;
								$question_id = intval($row["question_id"]);
								$grade = $total;
								$comment = "";
								
								?>
								
								<tr class="accordion-toggle" data-toggle="collapse" data-target="#rubric-<?php echo $c; ?>">	  
									<?php 
										if($last_seen_part != $row["question_part_number"]) 
										{
											$last_seen_part = $row["question_part_number"]; 
											
											$params = array($rubric_id, $row["question_part_number"]);
											$db->query("SELECT COUNT(question_id) as span_amount FROM questions WHERE rubric_id=? AND question_part_number=?", $params);
											$temp_row = $db->row();
											$span_amount = intval($temp_row["span_amount"]);

											if (!isset($rubric_lates[$row['question_part_number']])) {
												$rubric_lates[$row['question_part_number']] = ($row['question_part_number'] > 0) ? 3 : 3;
											}

											$late_number = $rubric_lates[$row["question_part_number"]];
											
											if($grade_days_late > -1)
											{
												$late_number = $grade_days_late;
											}
											
											if($late_number == 0 && $status == 1)
											{
												$late_color = "green";
												$late_icon = '<i class="icon-ok icon-white"></i>';
											}
											else if($late_number > 0 && $status == 1)
											{														
												$late_color = "#FAA732";
												$late_icon = '<i class="icon-exclamation-sign icon-white"></i><br/>';
											}
                                            /*
											elseif($late_number == 2)
											{
												$late_color = "#FAA732";
												$late_icon = '<i class="icon-exclamation-sign icon-white"></i><br/><i class="icon-exclamation-sign icon-white"></i>';
											}
                                            */
											else
											{
												$late_color = "#DA4F49";
												$late_icon = '<i class="icon-remove icon-white"></i>';
												//$grade = 0;
											}
											
											echo '<td class="lates" rowspan="' . $span_amount * 2 . '" style="padding:8px 0px; width: 1px; line-height:16px; padding-left:1px;background-color:' . $late_color . ';">' . $late_icon . '</td>';	  
											echo '<td rowspan="' . $span_amount * 2 . '">' . $last_seen_part . '</td>';
										}
										else
										{
											if($late_days_max_surpassed)
											{
												//$grade = 0;
											}
										}


										if(isset($previous_grade[intval($row["question_id"])]))
										{
											$grade = min($previous_grade[intval($row["question_id"])][0], $total);
											$comment = $previous_grade[intval($row["question_id"])][1];
										}
                                        else if ($status == 0) {
                                            $grade = 0;
                                        }
                                        else if(__USE_AUTOGRADER__ && $row['question_part_number'] == 0) {
                                            $grade = ($row['question_number'] == 1) ? $autograder : $autograder_ec;
                                        }
										else if($row['question_extra_credit'] == 1)
										{
											$grade = 0;
											$rubric_total -= $total;
										}
                                        else if (__ZERO_RUBRIC_GRADES__) {
                                            $grade = 0;
                                        }
										
										$note = $row["question_grading_note"];
										if($note != "")
										{
											$note = "<br/><br/><div style='margin-bottom:5px; color:#777;'><i><b>Note: </b>" . $note . "</i></div>";
										}
									?>
									
									<td style="font-size: 12px">
										<?php echo htmlentities($row["question_message"]); ?>
										<?php echo $note; ?>
									</td>
									<td>
			                    		<select name="grade-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>" id="changer" class="grades" style="width: 65px; height: 25px; min-width:0px;" onchange="calculatePercentageTotal();">
										<?php
											for($i = 0; $i <= $total * 2; $i++)
											{
												echo '<option' . (($i * 0.5) == $grade ? " selected" : "") . '>' . round(($i * 0.5),1) . '</option>';
											}
										?>
			                    		</select>
									</td>
									<td><strong><?php echo $total; ?></strong></td>
								</tr>
								
								<tr>
						            <td colspan="3" style="padding:0px; border-top:none;">
						            	<div class="accordian-body collapse <?php echo ($comment != "" ? "in" : ""); ?>" id="rubric-<?php echo $c++; ?>">
							            	<textarea name="comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>" rows="2" style="width:100%; padding:0px; resize:none; margin:0px 0px; border-radius:0px; border:none; padding:5px; border-left:3px #DDD solid; float:left; margin-right:-28px;" placeholder="Message for the student..." comment-position="0"><?php echo $comment; ?></textarea>
											<?php 
												$used_comments = array();
												$params = array($question_id, "");
												$db->query("SELECT grade_question_comment FROM grades_questions WHERE question_id=? AND grade_question_comment<>?", $params);
												foreach($db->rows() as $row2)
												{
													$used_comment = clean_string_javascript($row2["grade_question_comment"]);
													if(isset($used_comments[$used_comment]))
													{
														$used_comments[$used_comment] += 1;
													}
													else
													{
														$used_comments[$used_comment] = 1;
													}
												}
												array_multisort($used_comments, SORT_DESC);

												unset($used_comments[$comment]);

												if(count($used_comments) > 0 || ($comment != "" && count($used_comments) > 1))
												{
											?>
								            	<div>
									            	<a class="btn" name="comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>-up" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_<?php echo $row["question_part_number"]; ?>_<?php echo $row["question_number"]; ?>(-1);" disabled="true"><i class="icon-chevron-up" style="height:20px; width:13px;"></i></a>
									            	<br/>
													<a class="btn" name="comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>-down" style="border-radius: 0px; padding:0px;" onclick="updateCommentBox_<?php echo $row["question_part_number"]; ?>_<?php echo $row["question_number"]; ?>(1);"><i class="icon-chevron-down" style="height:20px; width:13px;"></i></a>
								            	</div>

								            	<script type="text/javascript">
													function updateCommentBox_<?php echo $row["question_part_number"]; ?>_<?php echo $row["question_number"]; ?>(delta) 
													{
														var pastComments = Array();
														pastComments[0] = "<?php echo clean_string_javascript($comment); ?>";
										            	<?php 
															$i = 1;
															foreach($used_comments as $used_comment => $used_comment_frequency)
															{
																print 'pastComments[' . $i++ . '] = "' . $used_comment . '";';
																print "\n";
															} 
										            	?>

										            	var new_position = parseInt($('[name=comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>]').attr("comment-position"));
										            	new_position += delta;

										            	if(new_position >= pastComments.length - 1)
										            	{
										            		new_position = pastComments.length - 1;
										            		$('a[name=comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>-down]').attr("disabled", "true");
										            	}
										            	else
										            	{
										            		$('a[name=comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>-down]').removeAttr("disabled");
										            	}

										            	if(new_position <= 0)
										            	{
										            		new_position = 0;
										            		$('a[name=comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>-up]').attr("disabled", "true");
										            	}
										            	else
										            	{
										            		$('a[name=comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>-up]').removeAttr("disabled");
										            	}

										            	$('textarea[name=comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>]').attr("comment-position", new_position);
										            	$('textarea[name=comment-<?php echo $row["question_part_number"]; ?>-<?php echo $row["question_number"]; ?>]').html(pastComments[new_position]);
											        }

												</script>
											<?php } ?>
						            	</div>
						            </td>
						        </tr>
						        
						    <?php }
						?>
					
						<?php if(isset($_GET["individual"])) { ?>
							<tr>	  
								<td style="background-color: #EEE; border-top:5px #FAA732 solid;"></td>	  
								<td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;"></td>	  
								<td style="background-color: #EEE; border-left: 1px solid #EEE; border-top:5px #FAA732 solid;"><strong>CURRENT GRADE</strong></td>
								<td style="background-color: #EEE; border-top:5px #FAA732 solid;"><strong id="score_total">0</strong></td>
								<td style="background-color: #EEE; border-top:5px #FAA732 solid;"><strong><?php echo $rubric_total; ?></strong></td>
							</tr>
						<?php } else { ?>
							<tr>	  
								<td style="background-color: #EEE; border-top: 1px solid #CCC;"></td>	  
								<td style="background-color: #EEE; border-left: 1px solid #EEE; border-top: 1px solid #CCC;"></td>	  
								<td style="background-color: #EEE; border-left: 1px solid #EEE; border-top: 1px solid #CCC;"><strong>CURRENT GRADE</strong></td>
								<td style="background-color: #EEE; border-top: 1px solid #CCC;"><strong id="score_total">0</strong></td>
								<td style="background-color: #EEE; border-top: 1px solid #CCC;"><strong><?php echo $rubric_total; ?></strong></td>
							</tr>
						<?php }?>
						
					</tbody>
				</table>
				
				<div style="width:100%;"><b>General Comment:</b></div>
				<textarea name="comment-general" rows="5" style="width:98%; padding:5px; resize:none;" placeholder="Overall message for student about the homework..."><?php echo $grade_comment; ?></textarea>
				
				<div style="width:100%; height:40px;"></div>
				<?php 
    if (isset($user['user_email'])) { 
        print "Graded By: {$user['user_email']}<br />Overwrite Grader: <input type='checkbox' name='overwrite' /><br /><br />"; 
    } 
    ?>
				<?php if((!isset($_GET["individual"])) || (isset($_GET["individual"]) && !$student_individual_graded))  { ?>
					<input class="btn btn-large btn-primary" type="submit" value="Submit Homework Grade"/> 
					<div id="inner-container-spacer" style="height:75px;"></div>
				<?php } else { ?>
					<input class="btn btn-large btn-warning" type="submit" value="Submit Homework Re-Grade" onclick="createCookie('backup',1,1000);"/>
					<div style="width:100%; text-align:right; color:#777;"><?php echo $grade_finish_timestamp; ?></div>
					<div id="inner-container-spacer" style="height:55px;"></div>
				<?php }	?>
			</form>
		</div>
	</div>
	
	<script type="text/javascript">
	
		calculatePercentageTotal();
		
		function calculatePercentageTotal() 
		{
			var total=0;
			
			$('#rubric-table select.grades').each(function()
			{
				total += parseFloat($(this).val());
/* 				$(this).next('.accordian-body').collapse('show'); */
			});
		
			$("#score_total").html(total);
		}
		
		function updateLates()
		{
			var late_number = parseInt($("select#late-select").val());
			
			$('.lates').each(function()
			{
				if(late_number == 0)
				{
					var late_color = "green";
					var late_icon = '<i class="icon-ok icon-white">';
				}
				else if(late_number == 1)
				{														
					var late_color = "#FAA732";
					var late_icon = '<i class="icon-exclamation-sign icon-white"><br/>';
				}
				else if(late_number == 2)
				{
					var late_color = "#FAA732";
					var late_icon = '<i class="icon-exclamation-sign icon-white"><br/><i class="icon-exclamation-sign icon-white">';
				}
				else
				{
					var late_color = "#DA4F49";
					var late_icon = '<i class="icon-remove icon-white">';
					
					$('#rubric-table select.grades').each(function()
					{
						$(this).val(0);
					});
				}
				
				$(this).css("background-color", late_color);
				$(this).html(late_icon);
			});
			
			calculatePercentageTotal()
		}
		
	</script>
		
<?php } ?>