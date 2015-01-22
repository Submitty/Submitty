<?php
// This file is relative to the public directory of the website.  (It
// is run from the location of index.php).
// static $path_to_path_file = "../../site_path.txt";

static $path_to_path_file = "site_path.txt";


//This will be changed to whatever exists in the above file
static $path_front_root = "";


function get_path_front_root() {
   global $path_front_root;
   global $path_to_path_file;
   if ($path_front_root == "") {
      if (!file_exists($path_to_path_file)) {
          display_error($path_to_path_file." does not exist.  Please make this file or edit the path in private/model/homework_model_functions.  The file should contain a single line of the path to courses directory containing the semesters and courses.  No whitespaces or return characters.");
          exit();
      }
      $file = fopen($path_to_path_file, 'r');
      $path_front_root = trim(fgets($file));

     // FIXME: do some error checking on this path (make sure it does not have a trailing slash, has no spaces, etc.)

      fclose($file);
   }
   return $path_front_root;
}


function get_path_front_course($semester,$course) {
    //display_note("get path front: ".$semester." ".$course);
    if (!is_valid_semester($semester)) {
        display_error("INVALID SEMESTER: ".$semester);
    }
    if (!is_valid_course($course)) {
        display_error("INVALID COURSE: ".$course);
    }
    $path_front_root = get_path_front_root();
    return $path_front_root."/courses/".$semester."/".$course;
}




function display_file_permissions($perms) {
    if (($perms & 0xC000) == 0xC000) {
        // Socket
        $info = 's';
    } elseif (($perms & 0xA000) == 0xA000) {
        // Symbolic Link
        $info = 'l';
    } elseif (($perms & 0x8000) == 0x8000) {
        // Regular
        $info = '-';
    } elseif (($perms & 0x6000) == 0x6000) {
        // Block special
        $info = 'b';
    } elseif (($perms & 0x4000) == 0x4000) {
        // Directory
        $info = 'd';
    } elseif (($perms & 0x2000) == 0x2000) {
        // Character special
        $info = 'c';
    } elseif (($perms & 0x1000) == 0x1000) {
        // FIFO pipe
        $info = 'p';
    } else {
        // Unknown
        $info = 'u';
    }

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
            (($perms & 0x0800) ? 's' : 'x' ) :
            (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
            (($perms & 0x0400) ? 's' : 'x' ) :
            (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
            (($perms & 0x0200) ? 't' : 'x' ) :
            (($perms & 0x0200) ? 'T' : '-'));

    echo $info;
}


// Upload HW Assignment to server and unzip
function upload_homework($username, $semester, $course, $assignment_id, $homework_file) {

    if (!is_valid_semester($semester)) { display_error("upload_homework, INVALID SEMESTER: ".$semester); }
    if (!is_valid_course($course))     { display_error("upload_homework, INVALID COURSE: ".$course); }

    // display_note ("trying to upload homework ".$semester." ".$course." ");

    if (!isset($homework_file["tmp_name"]) || $homework_file["tmp_name"] == "") {
        $error_text = "The file did not upload to POST[tmp_name].";
        if (isset($homework_file["error"])) {
            $error_text = $error_text." Error code given for upload is ". $homework_file["error"]. " . This is defined at http://php.net/manual/en/features.file-upload.errors.php";
        }
        // display_error($error_text);
        return array("error"=>"Upload Failed", "message"=>"Upload Failed: ".$error_text);

    }
    // Store the time, right now!
    // 2001-03-10 17:16:18 (the MySQL DATETIME format)
    date_default_timezone_set('America/New_York');
    $TIMESTAMP = date("Y-m-d H:i:s");


    $path_front_root = get_path_front_root();
    $path_front_course = get_path_front_course($semester,$course);

    // Check user and assignment authenticity
    $class_config = get_class_config($semester,$course);
    if ($username !== $_SESSION["id"]) {//Validate the id
        return array("error"=>"", "message"=>"User Id invalid.  ".$username." != ".$_SESSION["id"]);
    }
    if (!is_valid_assignment($class_config, $assignment_id)) {
        return array("error"=>"", "message"=>$assignment_id." is not a valid assignment");
    }
    $assignment_config = get_assignment_config($semester, $course, $assignment_id);
    if (!can_edit_assignment($username, $semester, $course, $assignment_id, $assignment_config)) {//Made sure the user can upload to this homework
        return array("error"=>"assignment_closed", "message"=>$assignment_id." is closed.");
    }
    //VALIDATE HOMEWORK CAN BE UPLOADED HERE
    //ex: homework number, due date, late days

    $max_size = 50;
    if (isset($assignment_config["max_submission_size"])) {
        $max_size = $assignment_config["max_submission_size"];
    }
    if ($homework_file["size"] / 1024 > $max_size || !is_valid_zip_size($homework_file["tmp_name"], $max_size)) {
        return array("error"=>"", "message"=>"File uploaded is too large.  Maximum size is ".$max_size." kb. Uploaded file was ".$homework_file["size"] / 1024 ." kb.");
    }

    $filename = explode(".", $homework_file["name"]);
    $extension = end($filename);


    /*$allowed   = array("application/zip",
                       "application/x-zip",
                       "application/x-zip-compressed",
                       "application/octet-stream",
                       "text/x-python-script",
                       "text/plain",
                       "text/x-c++src",
                       "application/download");*/
    /*
    if (!(in_array($homework_file["type"], $allowed))) {
        //display_error("Incorrect file upload type.  Got ".htmlspecialchars($homework_file["type"]));
        return array("error"=>"", "message"=>"Incorrect file upload type.  Got ".htmlspecialchars($homework_file["type"]));
    }
    */
    // make folder for this homework (if it doesn't exist)
    $assignment_path = $path_front_course."/submissions/".$assignment_id;
    if (!file_exists($assignment_path)) {
        if (!mkdir($assignment_path))
        {
            display_error("Failed to make folder ".$assignment_path);
            return;
        }
    }

    // NOTE: which group is sticky, umask will set the permissions correctly (0750)

    // make folder for this user (if it doesn't exist)
    $user_path = $assignment_path."/".$username;
    // If user path doesn't exist, create new one
    if (!file_exists($user_path)) {
        if (!mkdir($user_path))
        {
            display_error("Failed to make folder ".$user_path);
            return;
        }
    }

    //Find the next homework version number

    $upload_version = 1;
    while (file_exists($user_path."/".$upload_version)) {
        // FIXME: Replace with symlink
        $upload_version++;
    }

    // Attempt to create folder
    $version_path = $user_path."/".$upload_version;
    if (!mkdir($version_path)) {//Create a new directory corresponding to a new version number
        display_error("Failed to make folder ".$version_path);
        return;
    }


    // Unzip files in folder
    $zip = new ZipArchive;
    $res = $zip->open($homework_file["tmp_name"]);
    if ($res === TRUE) {
        $zip->extractTo($version_path."/");
        $zip->close();
    }
    else {
        $result = move_uploaded_file($homework_file["tmp_name"], $version_path."/".$homework_file["name"]);
        if (!$result) {
            display_error("failed to move uploaded file from ".$homework_file["tmp_name"]." to ".$version_path."/".$homework_file["name"]);
            return;
        }
    }
    $settings_file = $user_path."/user_assignment_settings.json";
    if (!file_exists($settings_file)) {
        $json = array("active_assignment"=>$upload_version);
        file_put_contents($settings_file, json_encode($json));
    }
    else {
        change_assignment_version($username, $semester, $course, $assignment_id, $upload_version, $assignment_config);
    }

    // add this assignment to the grading queue
    // FIX ME: If to_be_graded path doesn't exist, create new one

    $touchfile = $path_front_root."/to_be_graded/".$semester."__".$course."__".$assignment_id."__".$username."__".$upload_version;
    touch($touchfile);

    // CREATE THE TIMESTAMP FILE
    //touch($version_path."/.submit.timestamp");
    if (!file_put_contents($version_path."/.submit.timestamp",$TIMESTAMP."\n")) {
        display_error("Failed to save timestamp file ".$version_path."/.submit.timestamp",$TIMESTAMP);
        return;
    }


    /* // php symlinks disabled on server for security reasons
    ////// set LAST symlink
    //
    //     if (is_link("$user_path/LAST")){
    //         unlink("$user_path/LAST");
    //     }
    //     symlink ($version_path,$user_path."/LAST");
    //
    //     if (is_link("$user_path/ACTIVE")){
    //         unlink("$user_path/ACTIVE");
    //     }
    //
    //     // set ACTIVE symlink
    //     symlink ($version_path,$user_path."/ACTIVE");
    */
    return array("success"=>"File uploaded successfully");
}

function is_valid_zip_size($filename, $max_size) {
    $size = 0;
    $zip = zip_open($filename);
    //
    // FIXME: bug with this check?  Getting this warning when a non zip is uploaded
    //
    // Warning: zip_read() expects parameter 1 to be resource, integer given
    // Warning: zip_close() expects parameter 1 to be resource, integer given
    //

    if ($zip) {
        while ($inner_file = zip_read($zip)) {
            $size += zip_entry_filesize($inner_file);
            if ($size / 1024 > $max_size) {

                // FIXME: Added this, but it is a popup message, would be better to be like the other error messages
                display_error("When unzipped, too big: ".$size." > ".$max_size);

                return false;
            }
        }
        zip_close($zip);
    }
    return true;
}

// Check if user has permission to edit homework
function can_edit_assignment($username, $semester, $course, $assignment_id, $assignment_config) {


	// FIXME: HACK!  To not check due date
    // TODO: FIXME: late submissions should be allowed (there are excused absenses)
    // TODOL FIXME: but the ACTIVE should not be updated ( we can manually adjust active for excused absenses)

    return true;


    //$due_date = get_due_date($username, $semester, $course, $assignment_id, $assignment_config);
    $class_config = get_class_config($semester,$course);

    $due_date = get_due_date($class_config,$assignment_id);
    $last_edit_date = $due_date->add(new DateInterval("P2D"));
    $now = new DateTime("NOW");

    return $now <= $last_edit_date;
}

//function get_due_date($username, $semester, $course, $assignment_id) {
function get_due_date($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if ($one["assignment_id"] == $assignment_id) {

            if (isset($one["due_date"])) {

                // TODO: Put this in cofig file
                date_default_timezone_set('America/New_York');
                $date = new DateTime($one["due_date"]);
                return $date;
            }
            else {
                date_default_timezone_set('America/New_York');
                $date = new DateTime("2014-12-01 23:59:59.0");
                return $date;
            }
        }
    }
}


//Gets the class information for assignments



function get_class_config($semester,$course) {
   if (!is_valid_semester($semester)) { display_error("get_class_config, INVALID SEMESTER: ".$semester); }
   if (!is_valid_course($course))     { display_error("get_class_config, INVALID COURSE: ".$course); }

    $path_front = get_path_front_course($semester,$course);
    $file = $path_front."/config/class.json";
    //    $file = $path_front."/results/class.json";
    if (!file_exists($file)) {
        ?><script>alert("Configuration for this class (<?php echo $file ?>) does not exist. Quitting.");</script>
        <?php exit();
    }
    return json_decode(removeTrailingCommas(file_get_contents($file)), true);
}


function most_recent_released_assignment_id($class_config) {
    // eliminating "default_assignment" in class.json, always used last "released" homework!
    // return $class_config["default_assignment"];
    $assignments = $class_config["assignments"];
    $last="";
    foreach ($assignments as $one) {
        if ($one["released"] == true) {
             $last=$one["assignment_id"];
        }
    }
    return $last;
}


// Get a list of uploaded files

function get_submitted_files($username, $semester, $course, $assignment_id, $assignment_version) {

    if (!is_valid_semester($semester)) { display_error("get_submitted_files, INVALID SEMESTER: ".$semester); }
    if (!is_valid_course($course))     { display_error("get_submitted_files, INVALID COURSE: ".$course); }

    $path_front = get_path_front_course($semester,$course);
    $folder = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
    $contents = array();
    if ($assignment_version != 0) {
        $contents = get_contents($folder, 5);
    }
    for ($i = 0; $i < count($contents); $i++) {
        $contents[$i]["name"] = substr($contents[$i]["name"], strlen($folder) + 1);
    }
    return $contents;
}

function get_contents($dir, $max_depth) {
    if ($max_depth == 0) {
        return array();
    }
    $contents = array();
    if (is_dir($dir)) {
        if ($handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if ($file[0] && $file[0] != ".") {
                    if (is_dir($dir."/".$file)) {
                        $children = get_contents($dir."/".$file, $max_depth - 1);
                        foreach ($children as $child) {
                            array_push($contents, $child);
                        }
                    } else {
                        array_push($contents, array("name"=>$dir."/".$file, "size"=>floor(filesize($dir."/".$file) / 1024)));
                    }
                }
            }
        }
    }
    return $contents;
}

// Find most recent submission from user
function most_recent_assignment_version($username, $semester, $course, $assignment_id) {
    $path_front = get_path_front_course($semester,$course);
    $path = $path_front."/submissions/".$assignment_id."/".$username;
    $i = 1;
    while (file_exists($path."/".$i)) {
        $i++;
    }
    return $i - 1;

}

// Get name for assignment
function name_for_assignment_id($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if ($one["assignment_id"] == $assignment_id) {
            return $one["assignment_name"];
        }
    }
    return "";//TODO Error handling
}


// Get name for assignment
function is_ta_grade_released($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if ($one["assignment_id"] == $assignment_id) {
            if (isset($one["ta_grade_released"]) && $one["ta_grade_released"] == true) {
                return true;
            }
            else {
                return false;
            }
        }
    }
    return ""; //TODO Error handling
}

function is_points_visible($class_config, $assignment_id) {
  $assignments = $class_config["assignments"];
  foreach ($assignments as $one) {
    if ($one["assignment_id"] == $assignment_id) {
      if (isset($one["view_points"]) && $one["view_points"] == false) {
        return false;
      }
      else {
        return true;
      }
    }
  }
  return ""; //TODO Error handling
}

function is_hidden_points_visible($class_config, $assignment_id) {
  $assignments = $class_config["assignments"];
  foreach ($assignments as $one) {
    if ($one["assignment_id"] == $assignment_id) {
      if (isset($one["view_hidden_points"]) && $one["view_hidden_points"] == true) {
        return true;
      }
      else {
        return false;
      }
    }
  }
  return ""; //TODO Error handling
}

// Check to make sure instructor has added this assignment
function is_valid_semester($semester) {
    if ($semester == "f14") {
        return true;
    }
    if ($semester == "s15") {
        return true;
    }
    //For auto-setup script:
    //if ($semester == "default") {return true;}
    return false;
}


// Check to make sure instructor has added this assignment
function is_valid_course($course) {
    if ($course == "csci1200") {
        return true;
    }
    if ($course == "csci1100") {
        return true;
    }
    if ($course == "csci1200test") {
        return true;
    }
    if ($course == "csci1100test") {
        return true;
    }
    if ($course == "csci4960") {
        return true;
    }
    if ($course == "csci4530") {
        return true;
    }
    //For auto-setup script:
    //if ($course == "default") {return true;}
    return false;
}


// Check to make sure instructor has added this assignment
function is_valid_assignment($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if ($one["assignment_id"] == $assignment_id) {
            return true;
        }
    }
    return false;
}

// Make sure student has actually submitted this version of an assignment
function is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version) {
    $path_front = get_path_front_course($semester,$course);
    $path = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
    return file_exists($path);
}

function is_valid_file_name($username, $semester, $course, $assignment_id, $assignment_version, $file_name){
        if (!is_valid_semester($semester)) { display_error("get_submitted_files, INVALID SEMESTER: ".$semester); }
        if (!is_valid_course($course))     { display_error("get_submitted_files, INVALID COURSE: ".$course); }
        if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) { display_error("get_submitted_files, INVALID Assignment Version: ".$course); }

        $path_front = get_path_front_course($semester,$course);
        $folder = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
        $contents = array();
        if ($assignment_version != 0) {
            $contents = get_contents($folder, 5);
        }
        for ($i = 0; $i < count($contents); $i++) {
            if ($file_name == substr($contents[$i]["name"], strlen($folder) + 1)){
                return true;
            }
        }
        return false;
}

function get_file($username, $semester, $course, $assignment_id, $assignment_version, $file_name){
    if (!is_valid_semester($semester)) { display_error("get_submitted_files, INVALID SEMESTER: ".$semester); }
    if (!is_valid_course($course))     { display_error("get_submitted_files, INVALID COURSE: ".$course); }
    if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) { display_error("get_submitted_files, INVALID Assignment Version: ".$course); }

    $path_front = get_path_front_course($semester,$course);
    $folder = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
    $contents = array();
    if ($assignment_version != 0) {
        $contents = get_contents($folder, 5);
    }
    for ($i = 0; $i < count($contents); $i++) {
        if ($file_name == substr($contents[$i]["name"], strlen($folder) + 1)){
            return $contents[$i]["name"];
        }
    }
    return "";
}
function get_all_files($username, $semester, $course, $assignment_id, $assignment_version){
    if (!is_valid_semester($semester)) { display_error("get_submitted_files, INVALID SEMESTER: ".$semester); }
    if (!is_valid_course($course))     { display_error("get_submitted_files, INVALID COURSE: ".$course); }
    if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) { display_error("get_submitted_files, INVALID Assignment Version: ".$course); }

    $path_front = get_path_front_course($semester,$course);
    $folder = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
    return $folder;
}

function version_in_grading_queue($username, $semester, $course, $assignment_id, $assignment_version) {
    $path_front = get_path_front_course($semester,$course);
    if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) {//If its not in the submissions folder
        return false;
    }
    $file = $path_front."/results/".$assignment_id."/".$username."/".$assignment_version;
    if (file_exists($file)) {//If the version has already been graded
        return false;
    }
    return true;
}


//RESULTS DATA

function get_submission_time($username, $semester,$course, $assignment_id, $assignment_version) {
    $version_results = get_assignment_results($username, $semester,$course, $assignment_id, $assignment_version);//Gets user results data from submission.json for the specific version of the assignment
    if ($version_results &&
	      isset($version_results["submission_time"])) {
	      return $version_results["submission_time"];
    } else {
	      return "";
    }
}

function get_homework_tests($username, $semester,$course, $assignment_id, $assignment_version, $assignment_config, $include_diffs = true) {
    $testcases_info = $assignment_config["testcases"];//These are the tests run on a homework (for grading etc.)
    $version_results = get_assignment_results($username, $semester,$course, $assignment_id, $assignment_version);//Gets user results data from submission.json for the specific version of the assignment
    if ($version_results) {
        $testcases_results = $version_results["testcases"];
    } else {
        $testcases_results = array();
    }
    $path_front = get_path_front_course($semester,$course);
	$student_path = "$path_front/results/$assignment_id/$username/$assignment_version/";
    $homework_tests = array();
    for ($i = 0; $i < count($testcases_info); $i++) {
        for ($u = 0; $u < count($testcases_results); $u++){
            //Match the assignment results (user specific) with the configuration (class specific)
            if ($testcases_info[$i]["title"] == $testcases_results[$u]["test_name"]){
                $data = array();
                $data["title"] = $testcases_info[$i]["title"];
                $data["details"] = $testcases_info[$i]["details"];
                $data["points_possible"] = $testcases_info[$i]["points"];
                $data["score"] = $testcases_results[$u]["points_awarded"];
                $data["message"] = isset($testcases_results[$u]["message"]) ? $testcases_results[$u]["message"] : "";
                $data["is_hidden"] = $testcases_info[$i]["hidden"];
                $data["is_extra_credit"] = $testcases_info[$i]["extracredit"];
                $data["visible"] = $testcases_info[$i]["visible"];
                $data["view_test_points"] = $testcases_info[$i]["view_test_points"];

                if (isset($testcases_results[$u]["execute_logfile"])) {
                    $data["execute_logfile"] = get_student_file($student_path . $testcases_results[$u]["execute_logfile"]);
                }
                if (isset($testcases_results[$u]["compilation_output"])) {
                    $data["compilation_output"] = get_compilation_output($student_path . $testcases_results[$u]["compilation_output"]);
                }
                if ($include_diffs && isset($testcases_results[$u]["diffs"])) {
                    $data["diffs"] = get_all_testcase_diffs($username, $semester,$course, $assignment_id, $assignment_version, $testcases_results[$u]["diffs"]);
                }

                array_push($homework_tests, $data);
                break;
            }
        }
    }
    return $homework_tests;
}

function get_awarded_points_visible($homework_tests){
    $version_score = 0;
    foreach ($homework_tests as $testcase) {
        if ($testcase["is_hidden"] === false) {
            $version_score += $testcase["score"];
        }
    }
    return $version_score;
}

function get_points_visible($homework_tests){
    $points_visible = 0;
    foreach ($homework_tests as $testcase) {
        if ($testcase["is_hidden"] === false) {
            if ($testcase["is_extra_credit"] === false) {
                $points_visible += $testcase["points_possible"];
            }
        }
    }
    return $points_visible;
}

function get_select_submission_data($username, $semester,$course, $assignment_id, $assignment_config, $highest_version) {
    $select_data = array();
    for ($i = 1; $i <= $highest_version; $i++) {
        $homework_tests = get_homework_tests($username, $semester,$course, $assignment_id, $i, $assignment_config, false);
        $points_awarded_visible = get_awarded_points_visible($homework_tests);
        $points_visible = get_points_visible($homework_tests);
        $score = $points_awarded_visible." / ".$points_visible;
        if (version_in_grading_queue($username, $semester,$course, $assignment_id, $i)) {
            $score = "Grading in progress";
        }


        $class_config = get_class_config($semester,$course);
        $due_date = get_due_date($class_config,$assignment_id);
        //        $due_date = get_due_date($username, $semester,$course, $assignment_id, $assignment_config);
        //	if (!isset($due_date) || !defined("due_date")) {
        //		       $due_date = "";
        //		       }

        $date_submitted = get_submission_time($username,$semester,$course,$assignment_id,$i);


       //echo "due_date = $due_date";
       //echo "date_submitted = $date_submitted <br>";

       $date_submitted2 = new DateTime($date_submitted);
       if ($date_submitted == "") $date_submitted2 = $due_date;

        //$now = new DateTime("NOW");
        $days_late = "";
        if ($date_submitted2 > $due_date) {
            $date_submitted2->add(new DateInterval("P1D"));
            $interval = $date_submitted2->diff($due_date);
            $days_late = $interval->format("%d");

	           //echo "days_late = $days_late<br>";
        }
        $entry = array("score"=> $score, "days_late"=>$days_late);
        array_push($select_data, $entry);
    }
    return $select_data;
}


// Get the test cases from the instructor configuration file
function get_assignment_config($semester,$course, $assignment_id) {
    $path_front = get_path_front_course($semester,$course);
    $file = $path_front."/config/".$assignment_id."_assignment_config.json";
    if (!file_exists($file)) {
        return false;//TODO Handle this case
    }
    return json_decode(removeTrailingCommas(file_get_contents($file)), true);
}

// Get results from test cases for a student submission
function get_assignment_results($username, $semester,$course, $assignment_id, $assignment_version) {
    $path_front = get_path_front_course($semester,$course);
    $file = $path_front."/results/".$assignment_id."/".$username."/".$assignment_version."/submission.json";
    if (!file_exists($file)) {
        return array();
    }

    $contents = file_get_contents($file);
    $contents = removeTrailingCommas($contents);

    $tmp = json_decode($contents, true);

    if ($tmp == NULL) {
        echo "DECODE FAILURE<br>";
        echo "GET_ASSIGNMENT_RESULTS FROM FILE: $file<br>";
        echo "contents $contents<br>";
    }
    return $tmp;
}


// FROM http://www.php.net/manual/en/function.json-decode.php
function removeTrailingCommas($json){
    $json=preg_replace('/,\s*([\]}])/m', '$1', $json);
    return $json;
}


//SUBMITTING VERSION

function get_user_submitting_version($username, $semester,$course, $assignment_id) {
    $path_front = get_path_front_course($semester,$course);
    $file = $path_front."/submissions/".$assignment_id."/".$username."/user_assignment_settings.json";
    if (!file_exists($file)) {
        return 0;
    }
    $json = json_decode(removeTrailingCommas(file_get_contents($file)), true);
    return $json["active_assignment"];
}

function change_assignment_version($username, $semester,$course, $assignment_id, $assignment_version, $assignment_config) {
    if (!can_edit_assignment($username, $semester, $course, $assignment_id, $assignment_config)) {
        display_error("Error: This assignment ".$assignment_id." is not open.  You may not edit this assignment.");
        return;
    }
    if (!is_valid_assignment_version($username, $semester,$course, $assignment_id, $assignment_version)) {
        display_error("This assignment version ".$assignment_version." does not exist");
        return;
    }

    $path_front = get_path_front_course($semester,$course);

    $user_path = $path_front."/submissions/".$assignment_id."/".$username;
    //    $file = $path_front."/submissions/".$assignment_id."/".$username."/user_assignment_settings.json";
    $file = $user_path."/user_assignment_settings.json";

    if (!file_exists($file)) {
        display_error("Unable to find user settings.  Looking for ".$file);
        return;
    }
    $json = json_decode(removeTrailingCommas(file_get_contents($file)), true);
    $json["active_assignment"] = $assignment_version;

/* // php symlinks disabled on server for security reasons

    //symlink (version_path,$user_path."/ACTIVE");
    if (is_link("$user_path/ACTIVE")){
        $success=unlink("$user_path/ACTIVE");
    }

    $success = symlink ($user_path."/".$assignment_version,$user_path."/ACTIVE");
*/

    file_put_contents($file, json_encode($json));
    return array("success"=>"Success");
}

function get_compilation_output($file) {
    if (!file_exists($file)) {
      return "FILE DOES NOT EXIST $file";
    }

    $contents = file_get_contents($file);
    $contents = str_replace(">","&gt;",$contents);
    $contents = str_replace("<","&lt;",$contents);

    return $contents;

}

function get_student_file($file) {
    if (!file_exists($file)) {
        return "FILE DOES NOT EXIST $file";
    }

    $contents = file_get_contents($file);
    $contents = str_replace(">","&gt;",$contents);
    $contents = str_replace("<","&lt;",$contents);

    return $contents;

}

//DIFF FUNCTIONS

// Converts the JSON "diff" field from submission.json to an array containing
// file contents
function get_testcase_diff($username, $semester,$course, $assignment_id, $assignment_version, $diff){
    $path_front = get_path_front_course($semester,$course);
    $student_path = "$path_front/results/$assignment_id/$username/$assignment_version/";

    $data = array();
    $data["difference"] = "{differences:[]}";//This needs to be here to render the diff viewer without a teacher file

    if (isset($diff["instructor_file"])) {
        $instructor_file_path = "$path_front/".$diff["instructor_file"];
        if (file_exists($instructor_file_path)) {
            $data["instructor"] = file_get_contents($instructor_file_path);
        }
    }
    if (isset($diff["student_file"]) && file_exists($student_path . $diff["student_file"])) {
        $file_size = filesize($student_path. $diff["student_file"]);
        if ($file_size / 1024 < 10000) {
            $data["student"] = file_get_contents($student_path.$diff["student_file"]);
        } else {
            $data["student"] = "ERROR: Unable to read student output file.  Student output file is greater than or equal to ". ($file_size / 1024). " kb.  File could be corrupted or is too large.";
        }
    }
    if (isset($diff["difference"]) && file_exists($student_path . $diff["difference"])) {
        $data["difference"] = file_get_contents($student_path.$diff["difference"]);
    }
    return $data;
}

function get_all_testcase_diffs($username, $semester,$course, $assignment_id, $assignment_version, $diffs) {
    $results = array();
    foreach ($diffs as $diff) {
        $diff_result = get_testcase_diff($username, $semester,$course, $assignment_id, $assignment_version, $diff);
        $diff_result["diff_id"] = $diff["diff_id"];
        if (isset($diff["message"]) && $diff["message"] != "") {
            $diff_result["message"] = $diff["message"];
        }
        if (isset($diff["description"]) && $diff["description"] != "") {
            $diff_result["description"] = $diff["description"];
        }

        array_push($results, $diff_result);
    }
    return $results;
}

//ERRORS

function display_error($error) {
    ?><script>alert("Error: <?php echo $error;?>");</script><?php
    //       echo get_current_user();
    exit();
}
function display_note($note) {
    ?><script>alert("Note: <?php echo $note;?>");</script><?php
}
?>
