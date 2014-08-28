<?php umask (0027);
/*The user's umask is ignored for the user running php, so we need
to set it from inside of php to make sure the group read & execute
permissions aren't lost for newly created files & directories.*/

// This file is relative to the public directory of the website.  (It
// is run from the location of index.php). 
// static $path_to_path_file = "../../site_path.txt"; 
static $path_to_path_file = "site_path.txt";



//This will be changed to whatever exists in the above file
static $path_front = "";
function get_path_front($course) {

   if (!is_valid_course($course)) {
        display_error("INVALID COURSE");
    }
    global $path_front;
    global $path_to_path_file;
    if ($path_front == "") {
        if (!file_exists($path_to_path_file)) {
            display_error($path_to_path_file." does not exist.  Please make this file or edit the path in private/model/homework_model_functions.  The file should contain a single line of the path to the directory folder (ex: csci1200).  No whitespaces or return characters.");
            exit();
        }

        $file = fopen($path_to_path_file, 'r');
        $path_front = trim(fgets($file))."/".$course;
        fclose($file);
    }
    return $path_front;
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
function upload_homework($username, $course, $assignment_id, $homework_file) {
    $path_front = get_path_front($course);

    // Check user and assignment authenticity
    $class_config = get_class_config($course);
    if ($username !== $_SESSION["id"]) {//Validate the id
        display_error("User Id invalid.  ".$username." != ".$_SESSION["id"]);
        return;
    }
    if (!is_valid_assignment($class_config, $assignment_id)) {
        display_error($assignment_id." is not a valid assignment");
        return;
    }
    $assignment_config = get_assignment_config($username, $course, $assignment_id);
    if (!can_edit_assignment($username, $course, $assignment_id, $assignment_config)) {//Made sure the user can upload to this homework
        display_error($assignment_id." is closed.  Unable to change");
        return;
    }
    //VALIDATE HOMEWORK CAN BE UPLOADED HERE
    //ex: homework number, due date, late days

    $max_size = 50000;//CHANGE THIS TO GET VALUE FROM APPROPRIATE FILE
    $zip_types = array("application/zip", "application/x-zip-compressed","application/octet-stream");  // FIXME: trying adding octet stream
    $allowed   = array("application/zip", "application/x-zip-compressed","application/octet-stream","text/x-python-script", "text/plain", "text/x-c++src");
    $filename = explode(".", $homework_file["name"]);
    $extension = end($filename);

//    // FIXME TODO should support more than zip (.tar.gz etc.)
//    if (!($homework_file["type"] === "application/zip") && 
//	!($homework_file["type"] === "application/octet-stream") && 
//	!($homework_file["type"] === "application/x-zip-compressed")) {  //Make sure the file is a zip file

    // TODO should support more than zip (.tar.gz etc.)
    if (!(in_array($homework_file["type"], $allowed))) {
        display_error("Incorrect file upload type.  Not a zip, got ".htmlspecialchars($homework_file["type"]));
        return;
    }

    // make folder for this homework (if it doesn't exist)
    $assignment_path = $path_front."/submissions/".$assignment_id;
    if (!file_exists($assignment_path)) {
        if (!mkdir($assignment_path)) //, 0771, true))
        {
            display_error("Failed to make folder ".$assignment_path);
            return;
        }
    }
    // which group is sticky, but need to set group read access	  
		  //chmod($assignment_path,"0750");

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
   // which group is sticky, but need to set group read access	  
//   chmod($user_path,"0750");

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

    $perms = fileperms($version_path);
    //display_file_permissions($perms);

    // which group is sticky, but need to set group read access	  
		  //chmod($version_path,"0750");

    $perms = fileperms($version_path);
    //display_file_permissions($perms);

    // which group is sticky, but need to set group read access	  
		  //chmod($version_path,"0544");

    $perms = fileperms($version_path);
    //display_file_permissions($perms);

    // Unzip files in folder
    if (in_array($homework_file["type"], $zip_types)) {
        $zip = new ZipArchive;
        $res = $zip->open($homework_file["tmp_name"]);
        if ($res === TRUE) {
          $zip->extractTo($version_path."/");
          $zip->close();
        } else {
            display_error("failed to move uploaded file from ".$homework_file["tmp_name"]." to ". $version_path."/".$homework_file["name"]);
            return;
        }
    } else {
        if (!move_uploaded_file($homework_file["tmp_name"], $version_path."/".$homework_file["name"])) {
            display_error("failed to move uploaded file from ".$homework_file["tmp_name"]." to ".$version_path."/".$homework_file["name"]);
        }
    }
    $settings_file = $user_path."/user_assignment_settings.json";
    if (!file_exists($settings_file)) {
        $json = array("selected_assignment"=>$upload_version);
        file_put_contents($settings_file, json_encode($json));
    } else {
        change_assignment_version($username, $course, $assignment_id, $upload_version, $assignment_config);
    }
/*
    $to_be_compiled = $path_front."/submissions/to_be_compiled.txt";
    if (!file_exists($to_be_compiled)) {
        file_put_contents($to_be_compiled, $assignment_id."/".$username."/".$upload_version."\n");
    } else {
        $text = file_get_contents($to_be_compiled, false);
        $text = $text.$assignment_id."/".$username."/".$upload_version."\n";
        file_put_contents($to_be_compiled, $text);
    }
*/

   $course = htmlspecialchars($_GET["course"]);
   // FIXME: RESTRUCTURE CODE/ HANDLE THIS ERROR PROPERLY
   // if (!is_valid_course($course)) {

    // If to be graded path doesn't exist, create new one

    touch($path_front."/../to_be_graded/".$course."__".$assignment_id."__".$username."__".$upload_version);

    // which group is sticky, but need to set group read access	  
	    //chmod($version_path."/*".$i,"g+r");

    return array("success"=>"File uploaded successfully");
}

// Check if user has permission to edit homework
function can_edit_assignment($username, $course, $assignment_id, $assignment_config) {

	    // FIXME: HACK!  To not check due date
	    return true;
    
    $due_date = get_due_date($username, $course, $assignment_id, $assignment_config);
    $now = new DateTime("NOW");
    return $now <= $due_date;
}

function get_due_date($username, $course, $assignment_id, $assignment_config) {
    $path_front = get_path_front($course);
    date_default_timezone_set('America/New_York');
    $file = $path_front."/results/".$assignment_id."/".$username."/user_assignment_config.json";
    if (file_exists($file)) {
        $json = json_decode(removeTrailingCommas(file_get_contents($file)), true);
        if (isset($json["due_date"]) && $json["due_date"] != "default") {
            $date = new DateTime($json["due_date"]);
            return $date;
        }
    }
    $date = new DateTime($assignment_config["due_date"]);
    return $date;
}


//Gets the class information for assignments

function get_class_config($course) {
    $path_front = get_path_front($course);
    $file = $path_front."/config/class.json";
//    $file = $path_front."/results/class.json";
    if (!file_exists($file)) {
        ?><script>alert("Configuration for this class (<?php echo $file ?>) does not exist. Quitting.");</script>
        <?php exit();
    }
    return json_decode(removeTrailingCommas(file_get_contents($file)), true);
}

// Get a list of uploaded files
function get_submitted_files($username, $course, $assignment_id, $assignment_version) {
    $path_front = get_path_front($course);
    $folder = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
    if ($assignment_version != 0) {
        $contents = scandir($folder);}
    else {
        return array();
    }
    if (!$contents) {
        return array();
    }

    $filtered_contents = array();
    foreach ($contents as $item) {
        if ($item != "." && $item != "..") {
            array_push($filtered_contents, $item);
        }
    }
    return $filtered_contents;
}

// Find most recent submission from user
function most_recent_assignment_version($username, $course, $assignment_id) {
    $path_front = get_path_front($course);
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
function is_valid_assignment_version($username, $course, $assignment_id, $assignment_version) {
    $path_front = get_path_front($course);
    $path = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
    return file_exists($path);
}


// Get TA grade for assignment
function TA_grade($username, $assignment_id) {
    //TODO
    return false;
}

function version_in_grading_queue($username, $course, $assignment_id, $assignment_version) {
    $path_front = get_path_front($course);
    if (!is_valid_assignment_version($username, $course, $assignment_id, $assignment_version)) {//If its not in the submissions folder
        return false;
    }
    $file = $path_front."/results/".$assignment_id."/".$username."/".$assignment_version;
    if (file_exists($file)) {//If the version has already been graded
        return false;
    }
    return true;
}


//RESULTS DATA

function get_homework_tests($username, $course, $assignment_id, $assignment_version, $assignment_config) {
    $testcases_info = $assignment_config["testcases"];//These are the tests run on a homework (for grading etc.)
    $version_results = get_assignment_results($username, $course, $assignment_id, $assignment_version);//Gets user results data from submission.json for the specific version of the assignment
    if ($version_results) { 
        $testcases_results = $version_results["testcases"];
    } else {
        $testcases_results = array();
    }
    $path_front = get_path_front($course);
	$student_path = "$path_front/results/$assignment_id/$username/$assignment_version/";
    $homework_tests = array();
    for ($i = 0; $i < count($testcases_info); $i++) {
        for ($u = 0; $u < count($testcases_results); $u++){
            //Match the assignment results (user specific) with the configuration (class specific)
            if ($testcases_info[$i]["title"] == $testcases_results[$u]["test_name"]){
                 array_push($homework_tests, array(
                    "title"=>$testcases_info[$i]["title"],
                    "points_possible"=>$testcases_info[$i]["points"],
                    "score"=>$testcases_results[$u]["points_awarded"],
                    "message"=> isset($testcases_results[$u]["message"]) ? $testcases_results[$u]["message"] : "",

//));

//	        array_push($homework_tests,array(
                    "is_hidden"=>$testcases_info[$i]["hidden"],
                    "is_extra_credit"=>$testcases_info[$i]["extracredit"],
                    "compilation_output"=> isset($testcases_results[$u]["compilation_output"]) ? get_compilation_output($student_path . $testcases_results[$u]["compilation_output"]) : "UNDEFINED",
                    "diff"=> isset($testcases_results[$u]["diff"]) ? get_testcase_diff($username,$course, $assignment_id, $assignment_version,$testcases_results[$u]["diff"]) : "",
                    "diffs"=>isset($testcases_results[$u]["diffs"]) ? get_all_testcase_diffs($username, $course, $assignment_id, $assignment_version, $testcases_results[$u]["diffs"]) : array()
                ));

                break;
            }
        }
    }
    return $homework_tests;
}

function get_awarded_points_visible($homework_tests)
{
    $version_score = 0;
    //TODO Add extra credit
    foreach ($homework_tests as $testcase) {
        if ($testcase["is_hidden"] === false || $testcase["is_hidden"] === "false" || $testcase["is_hidden"] === "False") {
            $version_score += $testcase["score"];
        }
    }
    return $version_score;
}

function get_points_visible($homework_tests)
{
    $points_visible = 0;
    foreach ($homework_tests as $testcase) {
        if ($testcase["is_hidden"] === false || $testcase["is_hidden"] === "false" || $testcase["is_hidden"] === "False") {
            if ($testcase["is_extra_credit"] === false || $testcase["is_extra_credit"] === "false" || $testcase["is_extra_credit"] === "False") {
                $points_visible += $testcase["points_possible"];
            }
        }
    }
    return $points_visible;
}

function get_select_submission_data($username, $course, $assignment_id, $assignment_config, $highest_version) {
    $select_data = array();
    for ($i = 1; $i <= $highest_version; $i++) {
        $homework_tests = get_homework_tests($username, $course, $assignment_id, $i, $assignment_config);
        $points_awarded_visible = get_awarded_points_visible($homework_tests);
        $points_visible = get_points_visible($homework_tests);
        $score = $points_awarded_visible." / ".$points_visible;
        if (version_in_grading_queue($username, $course, $assignment_id, $i)) {
            $score = "Grading in progress";
        }

        $due_date = get_due_date($username, $course, $assignment_id, $assignment_config);
        $now = new DateTime("NOW");
        $days_late = "";
        if ($now > $due_date) {
            $now->add(new DateInterval("P1D"));
            $interval = $now->diff($due_date);
            $days_late = $interval->format("%d");
        }
        $entry = array("score"=> $score, "days_late"=>$days_late);
        array_push($select_data, $entry);
    }
    return $select_data;
}


// Get the test cases from the instructor configuration file
function get_assignment_config($username, $course, $assignment_id) {
    $path_front = get_path_front($course);
//    $file = $path_front."/results/".$assignment_id."/assignment_config.json";
    $file = $path_front."/config/".$assignment_id."_assignment_config.json";

//	      echo "GET ASSIGNMENT CONFIG ".$file."<br>";

    if (!file_exists($file)) {
        return false;//TODO Handle this case
    }
    return json_decode(removeTrailingCommas(file_get_contents($file)), true);
}

// Get results from test cases for a student submission
function get_assignment_results($username, $course, $assignment_id, $assignment_version) {
    $path_front = get_path_front($course);
    $file = $path_front."/results/".$assignment_id."/".$username."/".$assignment_version."/submission.json";
    if (!file_exists($file)) {
        return false;
    }

$contents = file_get_contents($file);
$contents = removeTrailingCommas($contents);

	      $tmp = json_decode($contents, true);


	      

//echo "GET ASSIGN $tmp foo<br>";
if ($tmp == NULL) {
echo "DECODE FAILURE<br>";
echo "GET_ASSIGNMENT_RESULTS FROM FILE: $file<br>";
echo "contents $contents<br>";
} else {
//echo "DECODE OK!<br>";
}

	      return $tmp;
}



// FROM http://www.php.net/manual/en/function.json-decode.php
function removeTrailingCommas($json)
{
    $json=preg_replace('/,\s*([\]}])/m', '$1', $json);
    return $json;
}



//SUBMITTING VERSION

function get_user_submitting_version($username, $course, $assignment_id) {
    $path_front = get_path_front($course);
    $file = $path_front."/submissions/".$assignment_id."/".$username."/user_assignment_settings.json";
    if (!file_exists($file)) {
        return 0;
    }
    $json = json_decode(removeTrailingCommas(file_get_contents($file)), true);
    return $json["selected_assignment"];
}

function change_assignment_version($username, $course, $assignment_id, $assignment_version, $assignment_config) {
    if (!can_edit_assignment($username, $course, $assignment_id, $assignment_config)) {
        display_error("Error: This assignment ".$assignment_id." is not open.  You may not edit this assignment.");
        return;
    }
    if (!is_valid_assignment_version($username, $course, $assignment_id, $assignment_version)) {
        display_error("This assignment version ".$assignment_version." does not exist");
        return;
    }
    $path_front = get_path_front($course);
    $file = $path_front."/submissions/".$assignment_id."/".$username."/user_assignment_settings.json";
    if (!file_exists($file)) {
        display_error("Unable to find user settings.  Looking for ".$file);
        return;
    }
    $json = json_decode(removeTrailingCommas(file_get_contents($file)), true);
    $json["selected_assignment"] = $assignment_version;
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


//DIFF FUNCTIONS

// Converts the JSON "diff" field from submission.json to an array containing
// file contents
function get_testcase_diff($username, $course, $assignment_id, $assignment_version, $diff){
    $path_front = get_path_front($course);
    $student_path = "$path_front/results/$assignment_id/$username/$assignment_version/";
    
    $student_content = "";
    $instructor_content = "";
    $difference = "{differences:[]}";

    if (isset($diff["instructor_file"])) {
        $instructor_file_path = "$path_front/".$diff["instructor_file"];
        if (file_exists($instructor_file_path)) {
            $instructor_content = file_get_contents($instructor_file_path);
        }
    }
    if (isset($diff["student_file"]) && file_exists($student_path . $diff["student_file"])) {
        $student_content = file_get_contents($student_path.$diff["student_file"]);
    }
    if (isset($diff["difference"]) && file_exists($student_path . $diff["difference"])) {
        $difference = file_get_contents($student_path.$diff["difference"]);
    }

    return array(
            "student" => $student_content,
            "instructor"=> $instructor_content,
            "difference" => $difference
        );
}

function get_all_testcase_diffs($username, $course, $assignment_id, $assignment_version, $diffs) {
    $results = array();
    foreach ($diffs as $diff) {
        $diff_result = get_testcase_diff($username, $course, $assignment_id, $assignment_version, $diff);
        $diff_result["diff_id"] = $diff["diff_id"];
        if (isset($diff["message"])) {
            $diff_result["message"] = $diff["message"];
        }
        if (isset($diff["description"])) {
            $diff_result["description"] = $diff["description"];
        }

        array_push($results, $diff_result);
    }
    return $results;
}

//ERRORS

function display_error($error) {
    ?>
    <script>alert("Error: <?php echo $error;?>");</script>
    <?php
//       echo get_current_user();
    exit();
}
