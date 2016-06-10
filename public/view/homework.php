<?php

require_once("controller/controller_functions.php");

if($assignment_version <= 0 && $active_version != $assignment_version){
  header ("Location: index.php?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
}

echo '<html>';
echo '<title>'.$course.'</title>';
echo "<link href='https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700' rel='stylesheet' type='text/css'>";
echo '<body>';

echo '<div>';


// =================================================================================

/*
echo '<div class="title-box">';
echo '<h1 class="title">Homework Submissions for '.$course.'</h1>';
echo '</div>';
*/

// =================================================================================

echo '<div>';
echo '<div class="submissions">';

if (file_exists("custom_resources/".$semester."_".$course."_main.css")) {
  print('<link href="custom_resources/'.$semester."_".$course.'_main.css" rel="stylesheet"></link>');
} else {
  print('<link href="resources/default_main.css" rel="stylesheet"></link>');
}


// =================================================================================

print('<link href="resources/override.css" rel="stylesheet"></link>');

echo "<link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic' rel='stylesheet' type='text/css'>";
echo "<link href='https://fonts.googleapis.com/css?family=PT+Sans:700,700italic' rel='stylesheet' type='text/css'>";
echo '<link href="resources/bootmin.css" rel="stylesheet"></link>';
echo '<link href="resources/badge.css" rel="stylesheet"></link>';
echo '<script src="resources/script/main.js"></script>';



// DIFF VIEWER STUFF
echo "<script src='diff-viewer/jquery.js'></script>";
echo "<script src='diff-viewer/underscore.js'></script>";
echo "<script src='diff-viewer/highlight.js'></script>";
echo "<script src='diff-viewer/diff.js'></script>";
echo "<script src='diff-viewer/diff_queue.js'></script>";
echo '<link href="diff-viewer/diff.css" rel="stylesheet"></link>';

echo "<link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>";

// DRAG AND DROP
echo "<script src='drag-and-drop/drag_and_drop.js'></script>";

// =================================================================================

$user = $_SESSION["id"];


// =================================================================================
// FUNCTIONS USED BY THE PULL-DOWN MENUS


?>

<script type="text/javascript">;

function assignment_changed(){
   var php_course = "<?php echo $course; ?>";
   var php_semester = "<?php echo $semester; ?>";
   window.location.href="?semester="+php_semester+"&course="+php_course+"&assignment_id="+document.getElementById('hwlist').value+'#scroll=' + window.scrollY;
}
function version_changed(){
   var php_course = "<?php echo $course; ?>";
   var php_semester = "<?php echo $semester; ?>";
  window.location.href="?semester="+php_semester+"&course="+php_course+"&assignment_id="+document.getElementById('hwlist').value+"&assignment_version="+document.getElementById('versionlist').value+'#scroll=' + window.scrollY;
}

window.addEventListener('load', function() {
    // Do we have a #scroll in the URL hash?
    if(window.location.hash && /#scroll/.test(window.location.hash)) {
      // Scroll to the #scroll value
        window.scrollTo(0, window.location.hash.replace('#scroll=', ''));
    }
});

</script>

<?php


// =================================================================================
// IDENTIFY USER & SELECT WHICH HOMEWORK NUMBER



echo '<div id="HWsubmission">';

//echo '<div class="confetti">';


$random_logo = mt_rand(1,10);
$random_logo_filename = "resources/logo_fake" . $random_logo . ".png";
if (file_exists ($random_logo_filename)) {
  echo '<a target="_top" href="http://tinyurl.com/gqvbyv9"><img align=right width=35% hspace="20" vspace="10" style="opacity: 0.2;filter: alpha(opacity=20);" src="';
  echo $random_logo_filename;
  echo '"></a>';
}



echo '<h2 class="label">Homework Submission for <em>'.$user.'</em>';
if (on_dev_team($user)) {
  echo "&nbsp;&nbsp;<font color=\"ff0000\"> [ dev team ]</font>";
}
echo '</h2>';




// =================================================================================
// TOP MESSAGE (TEST ZONE ASSIGNMENT & PRIORITY HELP QUEUE)


$path_front = get_path_front_course($semester,$course);;
$message_path = "$path_front/reports/summary_html/".$username."_message.html";
if (file_exists($message_path)){
  $message_file = file_get_contents($message_path);
  echo $message_file;
}


echo '<div class="sub">';
echo '<form class="form_submit" action="">';
echo '<label class="label">Select Assignment:</label>';
echo '<select id="hwlist" name="assignment_id" onchange="assignment_changed();">';

for ($i = 0; $i < count($all_assignments); $i++) {
  $FLAG = "";
  if ($all_assignments[$i]["released"] != true)
    {
      if (on_dev_team($user)) {
        $FLAG = " NOT RELEASED";
      } else {
        continue;
      }
      }
  echo "<option value=".$all_assignments[$i]["assignment_id"];
  if ($all_assignments[$i]["assignment_id"] == $assignment_id)
    {
      echo " selected";
    }
  echo '>'.$all_assignments[$i]["assignment_name"].$FLAG;
  echo "</option>";
}


echo '</select>';
echo '</form>';
echo '</div>'; // end sub


echo '<h2 class="label">Assignment: '.$assignment_name.'</h2>';


echo '<div class="panel-body">';

if ($status && $status != "") {
  echo '  <div class="outer_box">';
  echo '  <h3 class="label2">';
  echo $status;
  echo '  </h3>';
  echo '</div>';
}

// UPLOAD NEW VERSION

echo '<div class="outer_box">';
echo '<h3 class="label">Upload New Version</h3>';
echo '<p class="sub">'.$upload_message.'</p>';

if ($svn_checkout == true) {
    echo '<form ';
    echo ' class="form_submit"';
    echo ' action="?page=upload&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'"';
    echo ' method="post"';
    echo ' enctype="multipart/form-data"';
    echo ' onsubmit="return check_for_upload('."'".$assignment_name."'".', '.$highest_version.', '.$max_submissions.')"';
    echo '>';
    echo "<input type='hidden' name='csrf_token' value='{$_SESSION['csrf']}' />";

    // NO FILE SUBMISSION, PULL FILES FROM SVN
    echo '<input type="submit" name="submit" value="GRADE SVN" class="btn btn-primary">';
    echo '<input type="hidden" name="svn_checkout" value="true">';
    echo '</form>';
} else {
/*
    // MULTIPLE FILES OR ZIP FILES SUBMISSION
    echo '<label for="file" class="label">Select File:</label>';
    echo '<input type="file" name="files[]" id="file" multiple/>';
    echo '<input type="submit" name="submit" value="Submit File" class="btn btn-primary">';
    echo '<input type="hidden" name="svn_checkout" value="false">';
}

echo '</form>';
echo '</div>';

*/

//  PARSE PREVIOUSLY SUBMITTED FILES
  $names = [];
  $sizes = [];
  for($i = 1; $i <= $num_parts; $i++){
    $names[$i] = [];
    $sizes[$i] = [];
  }
  foreach($submitted_files as $f){
    if($num_parts == 1){
      $names[$num_parts][] = $f['name'];
      $sizes[$num_parts][] = $f['size'];
    }
    else{
      //if(substr($f['name'], 0, 3) != "part") { // Error}
      $names[$f['name'][4]][] = substr($f['name'], 6);
      $sizes[$f['name'][4]][] = $f['size'];
    }
  }
// DRAG AND DROP STARTS
// ============================================================================

// MULTIPLE PARTS
  for($i = 1; $i <= $num_parts; $i++){
    echo '<div class="outer_box" id="upload'.$i.'" style="cursor: pointer; text-align: center">';
    echo '<h3 class="label" id="label'.$i.'" >Click or drag your files here';
    echo '<input type="file" name="files" id="input_file'.$i.'" style="display:none" onchange="addFile('.$i.')" multiple/>';
    echo '</h3>';

    // ADD LABELS FOR FILES FROM PREVIOUS SUBMISSION
    ?> <script type="text/javascript"> createArray(<?php echo $num_parts; ?>); </script> <?php

    for($j = 0; $j < count($names[$i]); $j++){
      ?> <script type="text/javascript">
      addLabel(<?php echo '"'.$names[$i][$j].'", '.$sizes[$i][$j].', '.$i.", true"; ?>);
      readPrevious(<?php echo '"'.$names[$i][$j].'", '.$i; ?>);
      </script> <?php
    }
    echo '<hr id="line'.$i.'" style="border-top:dotted 2px">';
    echo '<button class="btn btn-primary" id="delete'.$i.'" active>Delete All</button>';
    echo '</div>';
  }
  echo '<button type="button" id= "submit" class="btn btn-primary" active>Submit</button>';
  ?>

  <script type="text/javascript">
  // ONLY ALLOW ADDING/DELETING FILES IN HIGHEST VERSION / NO SUBMISSIONS / SUBMISSION CANCELLED
  var assignment_version = <?php echo $assignment_version; ?>;
  var active_version = <?php echo $active_version; ?>;
  var highest_version = <?php echo $highest_version; ?>;
  if((active_version == assignment_version && active_version == highest_version)|| assignment_version <= 0){

    for(var i = 1; i <= <?php echo $num_parts; ?>; i++ ){
      var dropzone = document.getElementById("upload" + i);
      dropzone.addEventListener("click", clicked_on_box, false);
      dropzone.addEventListener("dragenter", draghandle, false);
      dropzone.addEventListener("dragover", draghandle, false);
      dropzone.addEventListener("dragleave", draghandle, false);
      dropzone.addEventListener("drop", drop, false);

      $("#delete" + i).click(function(e){
      //document.getElementById("delete").addEventListener("click", function(e){
        deleteFiles(get_part_number(e));
        e.stopPropagation();
      })
    }
    //document.getElementById("submit").addEventListener("click", function(e){
    $("#submit").click(function(e){
      handle_submission(<?php
        echo "'".check_version($assignment_name, $highest_version, $max_submissions)."', ";
        echo "'".check_due_date($semester, $course, $assignment_id)."', ";
        echo "'?page=upload&semester=".$semester.'&course='.$course.'&assignment_id='.$assignment_id."', ";
        echo "'{$_SESSION['csrf']}', ";
        echo 'false';
        ?>);
      e.stopPropagation();
    })
  }
  else{
    for(var i = 1; i <= <?php echo $num_parts; ?>; i++ ){
      var dropzone = document.getElementById("upload" + i);
      dropzone.style.background = "lightgrey";
      // disable labels and buttons
      var children = dropzone.childNodes;
      for(var j=0; j<children.length; j++){
        children[j].onclick = "";
        children[j].disabled = true;
      }
      document.getElementById("upload" + i).style.cursor = "";
      document.getElementById("submit").disabled = true;
    }
  }

  </script>

  <?php
// DRAG AND DROP ENDS
// ============================================================================
}

echo '</div>';

/*
// helpful for debugging
echo "HIGHEST VERSION ".$highest_version." (most recent)<br>";
echo "ASSIGNMENT VERSION ".$assignment_version." (what to view)<br>";
echo "ACTIVE VERSION ".$active_version." (for TA grading)<br>";  // active
*/
// ============================================================================
// INFO ON ALL VERSIONS

if ($highest_version == -1) {

  // no submissions
  echo '<div class="outer_box">';
  echo '<em>No submission for this assignment.</em>';
  echo '</div>';

} else if ($highest_version < 1) {

  // this should not happen
  // $highest_version should not be negative -or- zero!
  echo '<div class="outer_box">';
  echo '<b>ERROR!  THIS SHOULD NOT HAPPEN!</b>';
  echo '</div>';

} else {

  // $highest_version is >= 1
  //  echo '<div class="outer_box_confetti">';
  //echo '<div class="outer_box_confetti_a">';
  echo '<div class="outer_box">';
  echo '<h3 class="label">Review Submissions</h3>';


  // -----------------------------------------------------
  // SELECT A PREVIOUS SUBMISSION PULL DOWN

  echo '<div class="sub-text">';
  echo '<div class="split-row">';  // left half of split row

  echo '<form class="form_submit" action="">';
  echo '<label class="label"><em>Select Submission Version:</em></label>';
  echo '<input type="input" readonly="readonly" name="assignment_id" value="'.$assignment_id.'" style="display: none">';
  echo '<select id="versionlist" name="assignment_version" onchange="version_changed();">';

  // special blank option when an assignment is cancelled
  if ($active_version == 0) {
    echo '<option value="0"></option>';
  }

  for ($i = 1; $i <= $highest_version; $i++) {
    echo '<option value="'.$i.'"';
    if ($i == $assignment_version) {
        echo 'selected';
    }
    echo ' > ';
    echo 'Version #'.$i;
    echo '&nbsp;&nbsp';
    if ($points_visible != 0){
      echo 'Score: '.$select_submission_data[$i-1]["score"].'&nbsp;&nbsp';
    }
    else {
      // don't display the score when there are no points
    }
    if ($select_submission_data[$i-1]["days_late"] != "") {
      echo 'Days Late: '.$select_submission_data[$i-1]["days_late"];
    }
    if ($i == $active_version) {
        echo '&nbsp;&nbsp ACTIVE';
    }
    echo ' </option>';
  }
  echo '</select>';
  echo '</form>';
  echo '</div>'; // left half of split row

  // -----------------------------------------------------
  // CHANGE ACTIVE VERSION & CANCEL SUBMISSION BUTTONS

  echo '<div class="split-row" style="margin-left: 15px;">';  // right half of split row

  if ($assignment_version != $active_version) {
    echo '&nbsp;&nbsp;&nbsp;&nbsp;';
    echo '<form class="form_submit" action="?page=update&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'" method="POST">';
    echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf'].'"" />';
    echo '<input type="submit" class="btn btn-primary" value="Set Version '.$assignment_version.' as ACTIVE Submission Version"></input>';
    echo '</form>';
  }

  if ($active_version != 0) {
    echo '&nbsp;&nbsp;&nbsp;&nbsp;';
    echo '<form class="form_submit" action="?page=update&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version=0" method="POST">';
    echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf'].'"" />';
    echo '<input type="submit" class="btn btn-primary" value="Cancel Submission" />';
    echo '</form>';
  }

  echo '</div>'; // right half of split row
  echo '</div>'; // sub-text


  // -----------------------------------------------------
  // ACTIVE ASSIGNMENT & CANCELLED ASSIGNMENT MESSAGES

  echo '<div class="sub-text">';
  if ($active_version == 0) {
    echo '<span class="error_mess">Note: You have </span>';
    echo '<b><span class="error_mess">CANCELLED</span></b>';
    echo '<span class="error_mess"> all submissions for this assignment.<br>';
    echo 'This assignment will not be graded by the instructor/TAs and a zero will be recorded in the gradebook.</span>';
  }
  else if ($assignment_version == $active_version) {
    echo '<span class="message">Note: This is your "ACTIVE" submission version, which will be graded by the instructor/TAs and the score recorded in the gradebook.</span>';
  }
  echo '</div>'; // sub-text

  // -----------------------------------------------------
  // DETAILS OF SELECTED ASSIGNMENT


  if ($assignment_version != 0) {

    //Box with name, size, and content of submitted files
    render("filecontent_display",
           array("download_files"       => $download_files,
                 "submitted_files"      => $submitted_files,
                 "semester"             => $semester,
                 "course"               => $course,
                 "username"             => $username,
                 "assignment_id"        => $assignment_id,
                 "assignment_version"   => $assignment_version,
                 "files_to_view"        => $files_to_view
           )
    );


    if ($assignment_version_in_grading_queue2 == "batch_queue" ||
        $assignment_version_in_grading_queue2 == "interactive_queue") {
      echo "<span>Version ".$assignment_version." is in the queue to be graded</span>";
    }
    else if ($assignment_version_in_grading_queue2 == "currently_grading") {
      echo "<span>Version ".$assignment_version." is now currently being graded</span>";
    }
    else if ($assignment_version_in_grading_queue2 == "error_does_not_exist") {
      echo "<span> ERROR! Version ".$assignment_version." does not exist!  Please report this issue to your instructor/TA.</span>";
    }
    else if ($assignment_version_in_grading_queue2 == "error_not_graded_and_not_in_queue") {
      echo "<span> ERROR! Version ".$assignment_version." has not been graded!  Please report this issue to your instructor/TA.</span>";
    }
    else if ($assignment_version_in_grading_queue2 == "graded") {
      //echo "<span>".$assignment_version." has been graded</span>";
    }
    else {
      echo "<span> ERROR! Version ".$assignment_version." has an unknown state ".$assignment_version_in_grading_queue."</span>";
    }

    if ($assignment_version_in_grading_queue2 == "graded") {
      //Box with grades, outputs and diffs
      render("homework_graded_display",
             array("assignment_message"=>$assignment_message,
                   "homework_tests"=>$homework_tests,
                   "viewing_version_score"=>$viewing_version_score,
                   "points_visible"=>$points_visible,
                   "view_points"=>$view_points,
                   "view_hidden_points"=>$view_hidden_points,
                   ));
    }
  }

  echo '</div>';  // confetti_a
  //echo '</div>';  // confetti



  // ============================================================================
  // TA GRADES SECTION

  if (!isset($ta_grades) || (isset($ta_grades) && $ta_grades == true)){
    if ($ta_grade_released == true) {
      echo '<div class="outer_box"> <!-- outer_box -->';

      //<!--- TA GRADE -->
      $path_front = get_path_front_course($semester,$course);;
      $gradefile_path = "$path_front/reports/$assignment_id/".$username.".txt";
      if (!file_exists($gradefile_path)) {
        echo '<h3 class="label2">TA grade not available</h3>';
      }
      else
        {
          $grade_file = file_get_contents($gradefile_path);
          echo '<h3 class="label">TA grade</h3>';
          // echo "<em><p>Please see the <a href=\"https://www.cs.rpi.edu/academics/courses/fall14/csci1200/announcements.php\">Announcements</a> page for the curve for this homework.</p></em>";
          echo "<pre>".$grade_file."</pre>";
        }
      echo "</div> <!-- end outer_box -->";
    }
    else
      {
        //echo '<div class="outer_box"> <!-- outer_box -->';
        //echo '<h3 class="label2">TA grades for this homework not released yet</h3>';
        //echo "</div> <!-- end outer_box -->";

      }
    //<!-- END OF "IF AT LEAST ONE SUBMISSION... " -->
  }

}

// ============================================================================
// GRADES SUMMARY SECTION


if (!isset($grade_summary) || (isset($grade_summary) && $grade_summary == true)){
  $path_front = get_path_front_course($semester,$course);;
  $gradefile_path = "$path_front/reports/summary_html/".$username."_summary.html";
  if (!file_exists($gradefile_path))
    {
      // echo '<div class="outer_box"> <!-- outer_box -->';
      // echo '<h3 class="label2">Grade Summary not available</h3>';
      // echo "</div> <!-- end outer_box -->";

    }
  else
    {
      echo '<div class="outer_box"> <!-- outer_box -->';
      $grade_file = file_get_contents($gradefile_path);
      echo $grade_file;
      echo "</div> <!-- end outer_box -->";

    }
}

echo '</div>'; // end panel-body
//echo '</div>'; // end confetti
echo '</div>'; // end HWsubmission














// ============================================================================
// ============================================================================

?>

<script type="text/javascript">
    function check_for_upload(assignment, versions_used, versions_allowed) {
        versions_used = parseInt(versions_used);
        versions_allowed = parseInt(versions_allowed);
        if (versions_used >= versions_allowed) {
            var message = confirm("Are you sure you want to upload for " + assignment + "? You have already used up all of your free submissions (" + versions_used + " / " + versions_allowed + "). Uploading may result in loss of points.");
            return message;
        }
        return true;
    }

    function handle_submission(version_check, due_date_check, url, csrf_token, svn_checkout){
      if((!version_check || confirm(version_check)) && (!due_date_check || confirm(due_date_check))){
        var loc = "?semester="+<?php echo '"'.$semester.'"';?>+"&course="+<?php echo '"'.$course.'"';?>+"&assignment_id="+<?php echo '"'.$assignment_id.'"';?>;
        submit(url, csrf_token, svn_checkout, loc);
      }
    }

    // Go through diff queue and run viewer
    loadDiffQueue();


    // Set time between asking server if the homework has been graded
    // Last argument in ms
    // Currently at 1 seconds = 1000ms
    // Previously seconds = 5000ms

    <?php
    if ($assignment_version_in_grading_queue || $active_version_in_grading_queue) {
      echo 'init_refresh_on_update("'.$semester.'", "'.$course.'", "'.$assignment_id.'", "'.$assignment_version.'", "'.$active_version.'", "'.!$assignment_version_in_grading_queue.'", "'.!$active_version_in_grading_queue.'", 1000);';
    }
    ?>
    </script>
</div>

</body>
</html>
