<?php if ($assignment_message != "") {
	echo "<p class='error_mess'>".$assignment_message."</p>"; }
?>
<!-- DETAILS ON INDIVIDUAL TESTS -->
<div class="row sub-text">
	<h4>Results:</h4>

	<div class="box" >
		<div>
			<h4 class="diff-header">
				Total
				<span class="badge">
					<?php echo $viewing_version_score." / ".$points_visible;?>
				</span>
			</h4>

		</div><!-- End div -->
	</div><!-- End box -->
	<?php
	$counter = 0;
	foreach($homework_tests as $test)
		{
			if ( $test["visible"]==true)
			{

				?>
		<br clear="all">
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
			} ?>
			<h4 class="diff-header">
				<?php
				echo $test["title"];
				if (isset ($test["details"])) {
					if ($test["details"] != "") {
						echo " <tt>".$test["details"]."</tt>";
					}
				}
				echo '<!-- BADGE TEST SCORE -->';
				if ($test["is_extra_credit"] === true) {
					echo '<span class="test_type">Extra Credit</span>';
				}
				if ($view_points === true && $test["points_visible"] === false){
					echo '<span class="'.$class.'">';
					if ($test["is_hidden"] === true && $view_hidden_points === false) {
						echo 'Hidden';
					}
					else{
						echo $test["score"]." / ".$test["points_possible"];
					}
					echo '</span>';
				}
				if ($test["is_hidden"]) {
					echo '<span class="hidden view_file">Hidden</span>';
					echo '</div><!-- End box -->';
					continue;
				}
				if (/* (isset($test["diff"]) && $test["diff"] != "") || */
					(isset($test["diffs"]) && count($test["diffs"]) > 0) ||
					(isset($test["compilation_output"])) )
				{
					if ($test["message"] != "") {
						echo '<span class="error_mess">'.$test["message"].'</span>';
					}
					?>
					<a class = "view_file" href="#" onclick="return toggleDiv('sidebysidediff<?php echo $counter;?>');">Details</a>
					<?php
				}
				?>
			</h4>
			<div id="sidebysidediff<?php echo $counter;?>"  class="view_diffs" style="display:none">
				<!-- DIFF (FIX FROM HERE) -->
				<?php
				if (isset($test["compilation_output"]) && $test["compilation_output"]!=" ") {
					echo '<div class="diff-block"><b class="sub2">Compilation output:</b><pre class="complation_mess">'.$test["compilation_output"].'</pre></div>'; //TODO: remove table here
				}

				?>
				<!-- MULTIPLE DIFFS -->
				<!--<div>--> <!--table -->
					<?php
					if (isset($test["diffs"])) {
						foreach ($test["diffs"] as $diff) {
							if (	isset($diff["student"]) &&
							!isset($diff["instructor"])  &&
							!isset($diff["description"]) &&
							!isset($diff["message"])
							)
							{
								continue;
							}
							if (0) /*isset($diff["description"]))*/
							{
								echo '<b>'.$diff["description"].'</b>';
								echo '<br />';
							}
							if (isset($diff["message"]) && $diff["message"]!=" ") {
								echo '<div class="diff-block"><a class="error_mess_diff">'.$diff["message"].'</a></div>';
								echo '<div class="spacer"></div>';
							}
							if (!isset($diff["student"]) && !isset($diff["instructor"]))
							{
								continue;
							}
							if (isset($diff["instructor"])) {
								$instructor_row_class = "diff-row";
							}
							else
							{
								$instructor_row_class = "diff-row-none";
							}
							?>
							<div class="diff-block"> <!-- diff block -->
								<div class="diff-element"><!-- student diff element -->

									<b>Student <?php if (isset($diff["description"])) { echo $diff["description"]; } ?></b>

									<div class="panel panel-default" id="<?php echo $diff["diff_id"]; ?>_student">
										<?php
										if (isset($diff["student"]))
										{
											//echo str_replace(" ", "&nbsp;", $diff["student"]);
											echo '<tt class="mono">'.$diff["student"].'</tt>';

										}
										else
										{
											echo "";
										}?>
									</div>
								</div><!-- end student diff element -->
								<div class="diff-element"><!-- instructor diff element -->

										<b>Expected <?php if (isset($diff["description"])) { echo $diff["description"]; } ?></b>

									<div class="panel panel-default" id="<?php echo $diff["diff_id"]; ?>_instructor">
										<?php
										if (isset($diff["instructor"]))
										{
											// echo str_replace(" ", "&nbsp;", $diff["instructor"]);
											// echo "~~~~~~~~~~~~~~~~~~~~~~\n";
											echo '<tt class="mono">'.$diff["instructor"].'</tt>';
										}
										else
										{
											echo "";
										}?>
									</div>
								</div><!-- end instructor diff element -->
								<!-- <div style="clear:both;"></div> -->
								<script><!-- script -->
									diff_queue.push("<?php echo $diff["diff_id"]; ?>");
									diff_objects["<?php echo $diff["diff_id"]; ?>"] = <?php echo $diff["difference"]; ?>;
								</script><!-- end script -->
							</div><!-- end div block -->
							<div class="spacer"></div>
						<!-- first one ends foreach diff in diffs. Second ends if is set of diff -->
						<!-- </div> end sidebysidediff -->
						<?php
					}
				}
				?>
				<!-- END MULTIPLE DIFFS -->
				<!-- </div>-->  <!-- end table -->
			</div><!-- end sidebysidediff# -->
			<?php $counter++;?>
		</div><!-- end box -->
	<?php
			}
	}?><!-- end foreach homework_tests as test-->
</div> <!-- end row sub2 -->
<?php
