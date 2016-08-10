<!-- DETAILS ON INDIVIDUAL TESTS -->
<div class="row sub-text">
	<h4>Results:</h4>

<?php if ($assignment_message != "") {
	echo '<span class="message">Note: '.htmlentities($assignment_message)."</span>";
}
?>

	<?php
	   //echo "debug: $points_visible";
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
				(isset($test["autochecks"])
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

			 if (isset($test["autochecks"])) {
		
			   foreach ($test["autochecks"] as $diff) {
			     if (isset($diff["messages"])) {
                               foreach ($diff["messages"] as $message) {
                                 echo '<div class="error_mess_diff">'.html_entity_decode($message).'</div>';
                               }
                             }
			     if ((!isset($diff["actual"]) || trim($diff["actual"]) == "") &&
				 (!isset($diff["expected"]) || trim($diff["expected"]) == "")) {
			       continue;
			     }
			     $instructor_row_class = "diff-row";
			     echo '<div class="diff-block">'; // <!-- diff block -->
			     echo '<div class="diff-element">'; //<!-- student diff element -->
			     
			     echo '<b>Student ';
			     if (isset($diff["description"])) { echo htmlentities($diff["description"]); }
			     echo '</b>';
			     if (isset($diff["actual"]) && trim($diff["actual"]) != "" && !isset($diff["display_mode"])) {
			       echo '<div class="panel panel-default" id="';
			       echo htmlentities($diff["autocheck_id"]);
			       echo '_student">';
			       echo '<tt class="mono">';
			       $str=$diff["actual"];
			       $str=str_replace("\r","\\r",$str);
			       echo htmlentities($str);
			       echo '</tt>';
			       echo '</div>';
			     }

			     if (isset($diff["actual"]) && trim($diff["actual"]) != "" && isset($diff["display_mode"]) && $diff["display_mode"] == "svg_validated") {
			       $str=$diff["actual"];
			       echo $str;
			     }

			     echo '</div>'; //<!-- end student diff element -->
			     if (isset($diff["expected"]) && trim($diff["expected"]) != ""){
			       echo '<div class="diff-element">'; //<!-- instructor diff element -->
			       echo '<b>Expected ';
			       if (isset($diff["description"])) { echo htmlentities($diff["description"]); }
			       echo '</b>';
			       echo '<div class="panel panel-default" id="';
			       echo htmlentities($diff["autocheck_id"]);
			       echo '_instructor">';
			       if (isset($diff["expected"]) && trim($diff["expected"]) != "") {
				 echo '<tt class="mono">';
				 $str=$diff["expected"];
				 $str=str_replace("\r","\\r",$str);
				 echo htmlentities($str);
				 echo '</tt>';
			       } else {
				 echo "";
			       }
			       echo '</div>';
			       echo '</div>'; //<!-- end instructor diff element -->
			     }
			     if ((isset($diff["actual"]) && trim($diff["actual"]) != "") &&
				 (isset($diff["expected"]) && trim($diff["expected"]) != "")) {
			       //					 <!-- <div style="clear:both;"></div> -->
			       echo '<script>'; //<!-- script -->
			       echo 'diff_queue.push("';
			       echo $diff["autocheck_id"];
			       echo '");';
			       echo 'diff_objects["';
			       echo $diff["autocheck_id"];
			       echo '"] = ';
			       echo $diff["difference_file"];
			       echo ';';
			       echo '</script>'; //<!-- end script -->
			     }
			     echo '</div>'; //<!-- end div block -->
			     echo '<div class="spacer"></div>';
			     //<!-- </div> end sidebysidediff -->
			     // first one ends foreach diff in diffs. Second ends if is set of diff
			   }
			 }
			 //				<!-- END MULTIPLE DIFFS -->
			 echo '</div>'; //<!-- end sidebysidediff# -->
			 $counter++;
			 echo '</div>'; //<!-- end box -->
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
