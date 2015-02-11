<!-- DETAILS ON INDIVIDUAL TESTS -->
<div class="row sub-text">
	<h4>Results:</h4>

<?php if ($assignment_message != "") {
	echo '<span class="message">Note: '.htmlentities($assignment_message)."</span>";
}
?>


	<?php
	if ($view_points == true && $points_visible != 0){
		echo '<div class="box" >';
			echo '<div>';
				echo '<h4 class="diff-header">';
					echo '<span class="badge-cont">';
						echo '<span class="badge">'.$viewing_version_score." / ".$points_visible.'</span>';
					echo '</span>';
					echo 'Total';
				echo '</h4>';
			echo '</div><!-- End div -->';
		echo '</div><!-- End box -->';
	}
	$counter = 0;
	foreach($homework_tests as $test)
		{
			if ( $test["visible"]==true)
			{
				if ($counter != 0){
					?>
					<br clear="all">
						<?php
				}
				?>
		<div class="box">
			<?php //score, points, and points possible are set.Is not hidden and is not extra credit
			if (  isset($test["score"]) &&
			isset($test["points_possible"]) &&
			$test["points_possible"] != 0 &&
			$test["is_hidden"] == false &&
			$test["is_extra_credit"] == false ) {
				if (!($test["points_possible"] > 0)) {
					$part_percent = 1;
				} else {
					$part_percent = $test["score"] / $test["points_possible"];
				}
				if ($part_percent == 1) {
					$class = "badge green-background";
				} else if ($part_percent >= 0.5) {
					$class = "badge yellow-background";
				} else {
					$class = "badge red-background";
				}
					}
					else {
				$class = "badge";
			}
			if($test["is_hidden"] == false && (
				(isset($test["diffs"])
				|| (isset($test["compilation_output"]) && trim($test["compilation_output"])!="")
				|| (isset($test["execute_logfile"]) && trim($test["execute_logfile"])!="")
				))) {
				$show_details=true;
				$click_event='href="#" onclick="return toggleDiv('."'".'sidebysidediff'.$counter."'".');" style="cursor:pointer;"';
			}
			else{
				$show_details=false;
				$click_event='';
			}
			 ?>

			<h4 class="diff-header" <?php echo $click_event ?> >
				<?php
				echo '<!-- BADGE TEST SCORE -->';

				if ($view_points == true && $points_visible != 0){
					echo '<span class="badge-cont">';
					if ($test["view_test_points"] == true && $test["points_possible"] != 0){
						echo '<span class="'.htmlentities($class).'">';
						if ($test["is_hidden"] == true && $view_hidden_points == false) {
							echo 'Hidden';
						}
						else{
							echo $test["score"]." / ".$test["points_possible"];
						}
						echo '</span>';
					}
					echo '</span>';
				}
				if ($test["is_extra_credit"] == true) {
					echo '<span class="test_type">Extra Credit</span>';
				}

				echo htmlentities($test["title"]);
				if (isset ($test["details"])) {
					if ($test["details"] != "") {
						echo " <tt>".htmlentities($test["details"])."</tt>";
					}
				}
				if ($test["is_hidden"]) {
					echo '<span class="hidden view_file">Hidden</span>';
				}
				else
				{
					if ($test["message"] != "") {
						echo '<span class="error_mess">&nbsp;&nbsp;'.htmlentities($test["message"]).'</span>';
					}
					if ($show_details && count($homework_tests) != 1){
						?>
						<a class = "view_file" href="#" >Details</a>
						<?php
					}
					else{
						?>
						<!-- <p class = "view_file" >No Details</p> -->
						<?php

					}
				}

				?>
			</h4>
			<?php
			if ($test["is_hidden"]) {
				echo '</div><!-- End box -->';
				continue;
			}
			?>
			<div id="sidebysidediff<?php echo $counter;?>"  class="view_diffs" style="display:block">
			<?php

				if (isset($test["compilation_output"]) && trim($test["compilation_output"])!="") {
					echo '<div class="diff-block"><b class="sub2">Compilation output:</b><pre class="complation_mess">'.htmlentities($test["compilation_output"]).'</pre></div>';
				}
				if (isset($test["execute_logfile"]) && trim($test["execute_logfile"])!="") {
					echo '<div class="diff-block"><b class="sub2">Execution output:</b><pre class="complation_mess">'.htmlentities($test["execute_logfile"]).'</pre></div>';
				}

				?>
				<!-- MULTIPLE DIFFS -->
					<?php
					if (isset($test["diffs"])) {
						foreach ($test["diffs"] as $diff) {
							if (isset($diff["message"]) && trim($diff["message"])!="") {
								echo '<div class="diff-block"><a class="error_mess_diff">'.htmlentities($diff["message"]).'</a></div>';
								echo '<div class="spacer"></div>';
							}
							if ((!isset($diff["student"]) || trim($diff["student"]) == "") &&
								(!isset($diff["instructor"]) || trim($diff["instructor"]) == ""))
							{
								continue;
							}
							$instructor_row_class = "diff-row";
							?>
							<div class="diff-block"> <!-- diff block -->
								<div class="diff-element"><!-- student diff element -->
									<b>Student <?php if (isset($diff["description"])) { echo htmlentities($diff["description"]); } ?></b>

									<div class="panel panel-default" id="<?php echo htmlentities($diff["diff_id"]); ?>_student">
										<?php
										if (isset($diff["student"]) && trim($diff["student"]) != "")
										{
											echo '<tt class="mono">';
											$str=$diff["student"];
											$str=str_replace("\r","\\r",$str);
											echo htmlentities($str);
											echo '</tt>';
										}
										?>
									</div>
								</div><!-- end student diff element -->



								<?php
								if 	(isset($diff["instructor"]) && trim($diff["instructor"]) != ""){
									?>
									<div class="diff-element"><!-- instructor diff element -->

										<b>Expected <?php if (isset($diff["description"])) { echo htmlentities($diff["description"]); } ?></b>

										<div class="panel panel-default" id="<?php echo htmlentities($diff["diff_id"]); ?>_instructor">
											<?php
											if (isset($diff["instructor"]) && trim($diff["instructor"]) != "")
											{
												echo '<tt class="mono">';
												$str=$diff["instructor"];
												$str=str_replace("\r","\\r",$str);
												echo htmlentities($str);
												echo '</tt>';
											}
											else
											{
												echo "";
											}?>
										</div>
									</div><!-- end instructor diff element -->
									<?php
								}


								if ((isset($diff["student"]) && trim($diff["student"]) != "") &&
									(isset($diff["instructor"]) && trim($diff["instructor"]) != ""))
								{
									?>
									<!-- <div style="clear:both;"></div> -->

									<script><!-- script -->
										diff_queue.push("<?php echo $diff["diff_id"]; ?>");
										diff_objects["<?php echo $diff["diff_id"]; ?>"] = <?php echo $diff["difference"]; ?>;
									</script><!-- end script -->
									<?php
								}
								?>
							</div><!-- end div block -->
							<div class="spacer"></div>
						<!-- </div> end sidebysidediff -->
						<?php
						// first one ends foreach diff in diffs. Second ends if is set of diff
					}
				}
				?>
				<!-- END MULTIPLE DIFFS -->

			</div><!-- end sidebysidediff# -->
			<?php $counter++;?>
		</div><!-- end box -->
		<?php
		// end foreach homework_tests as test
		}
	}

	if (count($homework_tests) > 1){
		?>
		<script>
		hideAllDiv(<?php echo $counter; ?>, 'sidebysidediff');
		</script>
		<?php
	}
	?>
</div> <!-- end row sub-text -->
