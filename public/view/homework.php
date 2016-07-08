<?php
require_once("controller/controller_functions.php");
if($assignment_version <= 0 && $active_version != $assignment_version){
  header ("Location: index.php?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id);
}
?>

<html>
<head>

  <!-- CSS Styles and Scripts-->
  <link href='https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300,300italic,700' rel='stylesheet' type='text/css'>
  <link href='https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic,700italic' rel='stylesheet' type='text/css'>
  <link href='https://fonts.googleapis.com/css?family=PT+Sans:700,700italic' rel='stylesheet' type='text/css'>
  <link href='https://fonts.googleapis.com/css?family=Inconsolata' rel='stylesheet' type='text/css'>

  <link href="resources/override.css" rel="stylesheet"></link>
  <link href="resources/bootmin.css" rel="stylesheet"></link>
  <link href="resources/badge.css" rel="stylesheet"></link>
  <script src="resources/script/main.js"></script>

  <!-- DIFF VIEWER STUFF -->
  <script src='diff-viewer/jquery.js'></script>
  <script src='diff-viewer/underscore.js'></script>
  <script src='diff-viewer/highlight.js'></script>
  <script src='diff-viewer/diff.js'></script>
  <script src='diff-viewer/diff_queue.js'></script>
  <link href="diff-viewer/diff.css" rel="stylesheet"></link>

  <!-- DRAG & DROP -->
  <script src='drag-and-drop/drag_and_drop.js'></script>

  <!-- PHP Vars Needed -->
  <?php
  echo '<title>'.$course.'</title>';
  if (file_exists("custom_resources/".$semester."_".$course."_main.css")) {
    print('<link href="custom_resources/'.$semester."_".$course.'_main.css" rel="stylesheet"></link>');
  } else {
    print('<link href="resources/default_main.css" rel="stylesheet"></link>');
  }
  ?>

</head>

<body>

<?php

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



echo '<div id="HWsubmission">'; // 4 .HWsubmission

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


echo '<div class="sub">'; // 5 .sub
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
echo '</div>'; // end 5 .sub


echo '<h2 class="label">Assignment: '.$assignment_name.'</h2>';


echo '<div class="panel-body">'; // 6 .panel-body

if ($status && $status != "") {
  echo '  <div class="outer_box">';
  echo '  <h3 class="label2">';
  echo $status;
  echo '  </h3>';
  echo '</div>';
}

// UPLOAD NEW VERSION

echo '<div class="outer_box">'; // 8 .outer_box
echo '<h3 class="label">Upload New Version</h3>';
echo '<p class="sub">'.$upload_message.'</p>';

if ($svn_checkout == true) {  // svn upload
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
} else {  // file upload

  //  GET NAMES AND SIZES OF PREVIOUSLY SUBMITTED FILES
  $previous_names = [];
  $previous_sizes = [];
  for($i = 1; $i <= $num_parts; $i++){
    $previous_names[$i] = [];
    $previous_sizes[$i] = [];
  }
  foreach($submitted_files as $f){
  // ==============================================================================================
  // Comment the if-else statement out if want single-parted hw to be put under subfolder (/part1)
  // ==============================================================================================
    if($num_parts == 1){  // files in version folder for 1-part hw
      $previous_names[$num_parts][] = $f['name'];
      $previous_sizes[$num_parts][] = $f['size'];
    }
    else{ // files in version_path/partn folders for multi-part hw
      // if(substr($f['name'], 0, 3) != "part") {} // TODO: Error check here
      $previous_names[$f['name'][4]][] = substr($f['name'], 6);
      $previous_sizes[$f['name'][4]][] = $f['size'];
    }
  }

  // DRAG AND DROP STARTS
  // ============================================================================
  // DROP ZONES FOR MULTIPLE PARTS
  for($i = 1; $i <= $num_parts; $i++){
    echo '<div class="outer_box" id="upload'.$i.'" style="cursor: pointer; text-align: center; border: dashed 2px lightgrey;">';
    // echo '<h3 class="label" id="label'.$i.'" >Drag your files here or click to open browser</h3>';
    echo '<h3 class="label" id="label'.$i.'" >Drag your '.$part_names[$i-1].' here or click to open file browser</h3>';
    echo '<input type="file" name="files" id="input_file'.$i.'" style="display:none" onchange="addFilesFromInput('.$i.')" multiple/>';
    // Uncomment if want buttons for emptying single bucket
    // echo '<button class="btn btn-primary" id="delete'.$i.'" active>Delete All</button>';
    echo '</div>';
  }
  echo '<button type="button" id= "submit" class="btn btn-primary" active>Submit</button>';
  echo '&nbsp&nbsp&nbsp&nbsp';
  echo '<button type="button" id= "startnew" class="btn btn-primary" active>Start New</button>';
  if($highest_version > 0){
    echo '&nbsp&nbsp&nbsp&nbsp';
    echo '<button type="button" id= "getprev" class="btn btn-primary" active>Start from Version '.$highest_version.'</button>';
  }
  ?>

  <script type="text/javascript">
  // CLICK ON THE DRAG-AND-DROP ZONE TO OPEN A FILE BROWSER OR DRAG AND DROP FILES TO UPLOAD
  var num_parts = <?php echo $num_parts; ?>;
  createArray(num_parts);
  var assignment_version = <?php echo $assignment_version; ?>;
  var active_version = <?php echo $active_version; ?>;
  var highest_version = <?php echo $highest_version; ?>;
  for(var i = 1; i <= num_parts; i++ ){
    var dropzone = document.getElementById("upload" + i);
    dropzone.addEventListener("click", clicked_on_box, false);
    dropzone.addEventListener("dragenter", draghandle, false);
    dropzone.addEventListener("dragover", draghandle, false);
    dropzone.addEventListener("dragleave", draghandle, false);
    dropzone.addEventListener("drop", drop, false);
    /*
    // Uncomment if want buttons for emptying single bucket
    $("#delete" + i).click(function(e){
    //document.getElementById("delete").addEventListener("click", function(e){
      deleteFiles(get_part_number(e));
      e.stopPropagation();
    })
    */
  }

  $("#startnew").click(function(e){ // Clear all the selected files in the buckets
    for(var i=1; i<= num_parts; i++){
      deleteFiles(i);
    }
    e.stopPropagation();
  })
  $("#submit").click(function(e){ // Submit button
    handle_submission(<?php
      echo "'?page=upload&semester=".$semester.'&course='.$course.'&assignment_id='.$assignment_id."', ";
      echo "'{$_SESSION['csrf']}', ";
      echo 'false';
      ?>);
    e.stopPropagation();
  })

  // START FROM FILES OF THE HIGHEST VERSION
  if(assignment_version == highest_version && highest_version > 0){ // get highest version files if in highest version
    document.getElementById("getprev").innerHTML = "Get Version " + assignment_version + " Files";
    $("#getprev").click(function(e){
      $("#startnew").click();
      <?php
      for($i = 1; $i <= $num_parts; $i++){
        for($j = 0; $j < count($previous_names[$i]); $j++){
        ?>
          addLabel(<?php echo '"'.$previous_names[$i][$j].'", '.$previous_sizes[$i][$j].', '.$i.", true"; ?>);
          readPrevious(<?php echo '"'.$previous_names[$i][$j].'", '.$i; ?>);
        <?php
        }
      }
      ?>
      e.stopPropagation();
    })
  }
  else if(highest_version > 0){ // else go to the highest version
    $('#getprev').click(function(e){
      window.location.href =
      <?php
        echo "'?semester=".$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$highest_version."';";
        ?>
    })
  }
  </script>

  <?php
}
echo '</div>'; // end 8 .outer_box
// DRAG AND DROP ENDS
// ============================================================================

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
  echo '<div class="outer_box">'; // 9 outer_box
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
    echo '<form class="form_submit" action="?page=update&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version='.$assignment_version.'" method="POST"';
    echo 'onsubmit="return check_version_change()"';
    echo'>';
    echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf'].'"" />';
    echo '<input type="submit" class="btn btn-primary" value="Set Version '.$assignment_version.' as ACTIVE Submission Version"></input>';
    echo '</form>';
  }

  if ($active_version != 0) {
    echo '&nbsp;&nbsp;&nbsp;&nbsp;';
    echo '<form class="form_submit" action="?page=update&semester='.$semester.'&course='.$course.'&assignment_id='.$assignment_id.'&assignment_version=0" method="POST"';
    echo 'onsubmit="return check_version_change()"';
    echo'>';
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

  echo '</div>';  // end 9 .outer_box



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

    function handle_submission(url, csrf_token, svn_checkout){
      var days_late = <?php echo calculate_days_late($semester, $course, $assignment_id);?>;
      var late_days_allowed = <?php echo get_late_days_allowed($semester, $course, $assignment_id);?>;
      var versions_used = <?php echo $highest_version;?>;
      var versions_allowed = <?php echo $max_submissions;?>;

      var message = "";
      // check versions used
      if(versions_used >= versions_allowed) {
        message = "You have already made " + versions_used + "/" + versions_allowed + " submissions. Are you sure you want to continue? Uploading may result in loss of points.";
        if(!confirm(message)) return;
      }
      // check due date
      if(days_late > 0 && days_late <= late_days_allowed) {
        message = "Your submission will be " + days_late + " day(s) late. Are you sure you want to use " +days_late + " late day(s)?";
        if(!confirm(message)) return;
      } else if(days_late > 0){
        message = "Your submission will be " + days_late + " days late. You are not supposed to submit unless you have an excused absense. Are you sure you want to continue?";
        if(!confirm(message)) return;
      }
      var loc = <?php echo "'"."?semester=".$semester."&course=".$course."&assignment_id=".$assignment_id."'" ?>;
      submit(url, csrf_token, svn_checkout, loc);
    }

    function check_version_change(){
      var days_late = <?php echo calculate_days_late($semester, $course, $assignment_id);?>;
      var late_days_allowed = <?php echo get_late_days_allowed($semester, $course, $assignment_id);?>;
      if(days_late > late_days_allowed){
        var message = "The max late days allowed for this assignment is " + late_days_allowed + " days. ";
        message += "You are not supposed to change your active version after this time unless you have permission from the instructor. Are you sure you want to continue?";
        return confirm(message);
      }
      return true;
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

</body>
</html>
