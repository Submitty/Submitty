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

    $path_front_root = get_path_front_root();

    if (!is_valid_semester($semester)) {
       display_error("INVALID SEMESTER: ".$semester);
    }
    if (!is_valid_course($semester,$course)) {
       display_error("INVALID COURSE: ".$course);
    }

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
function upload_homework($username, $semester, $course, $assignment_id, $num_parts, $homework_file, $previous_files, $svn_checkout) {
    // parts in homework_file: 1 to num_parts
    // check if upload succeeded for all parts
    $count = array();
    
    if ($svn_checkout == false) {
      if(isset($homework_file)) {
        for($n=1; $n <= $num_parts; $n++) {
          if(isset($homework_file[$n])) {
            $count[$n] = count($homework_file[$n]["name"]);
            for($i = 0; $i < $count[$n]; $i++) {
              if (!isset($homework_file[$n]["tmp_name"][$i]) || $homework_file[$n]["tmp_name"][$i] == "") {
                $error_text = $homework_file[$n]["name"][$i]." did not upload to POST[tmp_name]. ";
                if (isset($homework_file[$n]["error"][$i])) {
                  $error_text = $error_text."Error code given for upload is ". $homework_file[$n]["error"][$i]." . ";
                }
              }
            }
          }
        }
        if(isset($error_text)) {
         $error_text = $error_text. "Error(s) defined at http://php.net/manual/en/features.file-upload.errors.php";
          // display_error($error_text);
          return array("error"=>"Upload Failed", "message"=>"Upload Failed: ".$error_text);
        }
      }
    }


    // Store the time, right now!
    // 2001-03-10 17:16:18 (the MySQL DATETIME format)
    $TIMESTAMP = date("Y-m-d H:i:s");


    $path_front_root = get_path_front_root();
    $path_front_course = get_path_front_course($semester,$course);

    // Check user and assignment authenticity
    $class_config = get_class_config($semester,$course);
    if ($username !== $_SESSION["id"]) {//Validate the id
        return array("error"=>"", "message"=>"User Id invalid.  ".$username." != ".$_SESSION["id"]);
    }
    if (!is_open_assignment($class_config, $assignment_id)) {
        return array("error"=>"", "message"=>$assignment_id." is not a valid assignment");
    }
    $assignment_config = get_assignment_config($semester, $course, $assignment_id);
    if (!can_edit_assignment($username, $semester, $course, $assignment_id, $assignment_config)) {//Made sure the user can upload to this homework
        return array("error"=>"assignment_closed", "message"=>$assignment_id." is closed.");
    }
    //VALIDATE HOMEWORK CAN BE UPLOADED HERE
    //ex: homework number, due date, late days

    // check if files from previous submission exists
    if($svn_checkout == false) {
      if(isset($previous_files)) {

        // check if folder for this assignment exists
        $assignment_path = $path_front_course."/submissions/".$assignment_id;
        if(!file_exists($assignment_path)) {
          display_error("Files from previous submission not found. Folder for this assignment does not exist. Contact administrator to resolve this issue.");
          return;
        }

        // check if folder for this user exists
        $user_path = $assignment_path."/".$username;
        if(!file_exists($assignment_path)) {
          display_error("Files from previous submission not found. Folder for this user does not exist. Contact administrator to resolve this issue.");
          return;
        }

        //Find the previous homework version number
        $previous_version = 0;
        while (file_exists($user_path."/".(++$previous_version)));
        $previous_version -= 1;

        // No previous submission, should not have entered this if statement
        if($previous_version <= 0) {
          display_error("No submission found. There should not be any files kept from previous submission. Contact administrator to resolve this issue.");
          return;
        }

        $previous_version_path = $user_path."/".$previous_version;

        // check if folders for parts exist
        $previous_part_path = [];
        // ==============================================================================================
        // Comment the if-else statement out if want single-parted hw to be put under subfolder (/part1)
        // ==============================================================================================
        if($num_parts > 1) {
          // check folder for multi-part homework
          for($n=1; $n <= $num_parts; $n++) {
            $previous_part_path[$n] = $previous_version_path."/part".$n;
            if (!file_exists($previous_part_path[$n])) {
              display_error("Files from previous submission not found. Folder for previous submission does not exist. Contact administrator to resolve this issue.");
              return;
            }
          }
        }
        else {
          $previous_part_path[$num_parts] = $previous_version_path;
        }

        // check if files from previous version exist
        for($n=1; $n<=$num_parts; $n++) {
          if(isset($previous_files[$n])) {
            for($i=0; $i<count($previous_files[$n]); $i++) {
              $filename = $previous_part_path[$n]."/".$previous_files[$n][$i];
              if (!file_exists($filename)) {
                display_error("File ".$filename." does not exist in previous submission. Contact administrator to resolve this issue.");
                return;
              }
            }
          }
        }
      }
    }

    // note: all sizes in bytes
    $max_size = 50000;
    if (isset($assignment_config["max_submission_size"])) {
        $max_size = $assignment_config["max_submission_size"];
    }
    
    // check file size of uploaded files and files from previous submission
    if ($svn_checkout==false) {
      $file_size = 0;
      for($n=1; $n <= $num_parts; $n++) {
        if(isset($homework_file[$n])) { // files uploaded
          for($i = 0; $i < $count[$n]; $i++) {
            $filename = explode(".", $homework_file[$n]["name"][$i]);
            $extension = end($filename);
            if($extension == "zip") {
              $file_size += get_zip_size($homework_file[$n]["tmp_name"][$i]);
            }
            else {
              $file_size += $homework_file[$n]["size"][$i];
            }
          }
        }
        if(isset($previous_files[$n])){ // files from previous submission
          for($i=0; $i < count($previous_files[$n]); $i++){
            $file_size += filesize($previous_part_path[$n]."/".$previous_files[$n][$i]);
          }
        }
      }
      if ($file_size > $max_size) {
        return array("error"=>"", "message"=>"File(s) uploaded too large.  Maximum size is ".($max_size/1000)." kb. Uploaded file(s) was ".($file_size/1000)." kb.");
      }
    }


    // make folder for this homework (if it doesn't exist)
    $assignment_path = $path_front_course."/submissions/".$assignment_id;


    if (!file_exists($assignment_path)) {
      if (!mkdir($assignment_path))
      {
        display_error("Failed to make folder for this assignment. Contact administrator to resolve this issue.");
        return;
      }
    }



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




    // NOTE: which group is sticky, umask will set the permissions correctly (0750)

    // make folder for this user (if it doesn't exist)
    $user_path = $assignment_path."/".$username;
    // If user path doesn't exist, create new one
    if (!file_exists($user_path)) {
      if (!mkdir($user_path))
      {
        display_error("Failed to make folder for this user. Contact administrator to resolve this issue.");
        return;
      }
    }

    //Find the next homework version number

    $upload_version = 0;
    while (file_exists($user_path."/".(++$upload_version)));

    // Attempt to create folder for current version
    $version_path = $user_path."/".$upload_version;
    if (!mkdir($version_path)) {//Create a new directory corresponding to a new version number
      display_error("Failed to make folder for the current version. Contact administrator to resolve this issue.");
      return;
    }

    $part_path = [];
        // ==============================================================================================
        // Comment the if-else statement out if want single-parted hw to be put under subfolder (/part1)
        // ==============================================================================================
    if($num_parts > 1){
      // Create folder for multi-part homework
      for($n=1; $n <= $num_parts; $n++){
        $part_path[$n] = $version_path."/part".$n;
        if (!mkdir($part_path[$n])) {//Create a new directory corresponding to a new version number
          display_error("Failed to make folder for part ".$n.". Contact administrator to resolve this issue.");
          return;
        }
      }
    }
    else{ // else if homework is single-parted, put it in the version path folder
      $part_path[$num_parts] = $version_path;
    }

    if ($svn_checkout == false) {
      for($n=1; $n <= $num_parts; $n++){
        // upload files submitted
        if(isset($homework_file[$n])){

          for($i=0; $i < $count[$n]; $i++){
            // Unzip files in folder
            $zip = new ZipArchive;
            $res = $zip->open($homework_file[$n]["tmp_name"][$i]);
            if ($res === TRUE) {
              $zip->extractTo($part_path[$n]);
              $zip->close();
            }
            else{   // copy single file to folder
              // --------------------------------------------------------------

              /*
              // NOT SURE HOW THIS WAS WORKING BEFORE
              // both mv and move_uploaded_file will reset the file group to hwphp
              // we need it to be the sticky bit group csciXXXX_tas_www

              $result = move_uploaded_file($homework_file["tmp_name"], $version_path."/".$homework_file["name"]);
              if (!$result) {
                  display_error("failed to move uploaded file from ".$homework_file["tmp_name"]." to ".$version_path."/".$homework_file["name"]);
                  return;
              }
              */
              // --------------------------------------------------------------

              // SHOULD BE EQUIVALENT TO THIS:
              if (is_uploaded_file($homework_file[$n]["tmp_name"][$i])) {
                $copy_return = copy ($homework_file[$n]["tmp_name"][$i], $part_path[$n]."/".$homework_file[$n]["name"][$i]);
                if (!$copy_return) {
                  display_error("Failed to copy uploaded file ".$homework_file[$n]["name"][$i]." to current submission.");
                  return;
                }
              }
            }
            $unlink_return = unlink ($homework_file[$n]["tmp_name"][$i]);
            if (!$unlink_return) {
               display_error("Failed to unlink(delete) uploaded zip file ".$homework_file[$n]["name"][$i]." from temporary storage.");
               return;
            }
          }
        }
        // copy selected previous submitted files
        if(isset($previous_files[$n])){
          for($i=0; $i < count($previous_files[$n]); $i++){
            $copy_return = copy ($previous_part_path[$n]."/".$previous_files[$n][$i], $part_path[$n]."/".$previous_files[$n][$i]);
            if (!$copy_return) {
              display_error("Failed to copy previously submitted file ".$previous_files[$n][$i]." to current submission.");
              return;
            }
          }
        }
      }
    }


    $settings_file = $user_path."/user_assignment_settings.json";
    if (!file_exists($settings_file)) {
        $json = array("active_version"=>$upload_version, "history"=>array(array("version"=>$upload_version, "time"=>date("Y-m-d H:i:s"))));
        file_put_contents($settings_file, json_encode($json, JSON_PRETTY_PRINT));
    }
    else {
        change_assignment_version($username, $semester, $course, $assignment_id, $upload_version, $assignment_config);
    }


    if ($svn_checkout == true) {
      if (!touch($version_path."/.submit.SVN_CHECKOUT")) {
          // display_error("Failed to touch file ".$version_path."/.submit.SVN_CHECKOUT");
        display_error("Failed to touch file for svn submission.");
          return;
      }
    }


    // CREATE THE TIMESTAMP FILE
    //touch($version_path."/.submit.timestamp");
    if (!file_put_contents($version_path."/.submit.timestamp",$TIMESTAMP."\n")) {
      // display_error("Failed to save timestamp file ".$version_path."/.submit.timestamp",$TIMESTAMP);
      display_error("Failed to save timestamp file for this submission.");
      return;
    }


    // add this assignment to the grading queue
    // FIX ME: If to_be_graded_interactive path doesn't exist, throw an error
    $touchfile = $path_front_root."/to_be_graded_interactive/".$semester."__".$course."__".$assignment_id."__".$username."__".$upload_version;
    touch($touchfile);



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

function get_zip_size($filename) {
    $size = 0;
    $zip = zip_open($filename);
    if (is_resource($zip)) {
        while ($inner_file = zip_read($zip)) {
            $size += zip_entry_filesize($inner_file);
        }
        zip_close($zip);
    }
    return $size;
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
                $date = new DateTime($one["due_date"]);
                return $date;
            }
            else {
                $date = new DateTime("2014-12-01 23:59:59.0");
                return $date;
            }
        }
    }
}


//Gets the class information for assignments



function get_class_config($semester,$course) {
    $path_front = get_path_front_course($semester,$course);
    $file = $path_front."/config/class.json";
    //    $file = $path_front."/results/class.json";
    if (!file_exists($file)) {
        ?><script>alert("Configuration for this class (<?php echo $file ?>) does not exist. Quitting.");</script>
        <?php exit();
    }
    return json_decode(removeTrailingCommas(file_get_contents($file)), true);
}

function get_num_parts($assignment_config) {
  if(isset($assignment_config["part_names"])) {
    return count($assignment_config["part_names"]);
  }
  return 1;
}

function get_part_names($assignment_config) {
  if(isset($assignment_config["part_names"])){
    $part_names = $assignment_config["part_names"];
  } else {
    $part_names = [];
    $num_parts = get_num_parts($assignment_config);
    for($i=0; $i < $num_parts; $i++){
      $part_names[] = "Part ".(++$i);
    }
  }
  return $part_names;
}

function most_recent_released_assignment_id($class_config) {
    // eliminating "default_assignment" in class.json, always used last "released" homework!
    // return $class_config["default_assignment"];
    $assignments = $class_config["assignments"];
    $last="";
    foreach ($assignments as $one) {
        if (isset($one["released"]) && $one["released"] == true) {
             $last=$one["assignment_id"];
        }
    }
    return $last;
}


// Get a list of uploaded files

function get_submitted_files($username, $semester, $course, $assignment_id, $assignment_version) {

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
                if (isset($file[0]) && $file[0] != ".") {
                    if (is_dir($dir."/".$file)) {
                        $children = get_contents($dir."/".$file, $max_depth - 1);
                        foreach ($children as $child) {
                            array_push($contents, $child);
                        }
                    } else {
                        array_push($contents, array("name"=>$dir."/".$file, "size"=>number_format((filesize($dir."/".$file) / 1024),2,".","")));
                    }
                }
            }
        }
    }
    return $contents;
}

// Find most recent submission from user
function get_highest_assignment_version($username, $semester, $course, $assignment_id) {
    $path_front = get_path_front_course($semester,$course);
    $path = $path_front."/submissions/".$assignment_id."/".$username;
    $recent = -1;
    $x = 1;
    while (file_exists($path."/".$x)) {
      $recent = $x;
      $x++;
    }
    return $recent;
}

// Get name for assignment
function name_for_assignment_id($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
            return isset($one["assignment_name"]) ? $one["assignment_name"] : "";
        }
    }
    return "";//TODO Error handling
}

//get link for assignment page
function link_for_assignment_id($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
            return isset($one["assignment_link"]) ? $one["assignment_link"] : '#';
        }
    }
    return "";//TODO Error handling
}

//get description
function description_for_assignment_id($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
            return isset($one["assignment_description"]) ? $one["assignment_description"] : '#';
        }
    }
    return "";//TODO Error handling
}


// Get name for assignment
function is_ta_grade_released($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
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


// Get name for assignment
function get_upload_message($class_config) {
   if (isset($class_config["upload_message"])) {
      return $class_config["upload_message"];
   }
   return "ERROR: no \"upload_message\" specified in the class.json file";
}


// Get name for assignment
function is_svn_checkout($class_config, $assignment_id) {
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
            if (isset($one["svn_checkout"]) && $one["svn_checkout"] == true) {
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
    if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
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
    if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
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


// This function returns true if the input string looks like it's
// trying to traverse directories (a possible malicious attack).
function contains_directory_traversal($string) {

  // the string should not contain '/' or '..'
  if (strpos($string, '/') !== FALSE)
    return true;
  if (strpos($string, '..') !== FALSE)
    return true;

  // otherwise, things are probably ok
  return false;
}



function is_valid_semester($semester) {

  if (contains_directory_traversal($semester)) {
    return false;
  }

  // RPI SPECIFIC CHECKS e.g., f15 s16
  // must be 3 characters long
  if(strlen($semester) != 3) return false;
  // first character must be alphabetic
  if(!ctype_alpha(substr($semester,0,1))) return false;
  // last 2 characters must be a number
  if(!is_numeric(substr($semester,1,2))) return false;


  $path_front_root = get_path_front_root();
  $semester_directory = $path_front_root."/courses/".$semester;

  return is_dir($semester_directory);

}


function is_valid_course($semester,$course) {

  if (!is_valid_semester($semester)) {
    return false;
  }
  if (contains_directory_traversal($course)) {
    return false;
  }

  // RPI SPECIFIC CHECKS e.g., csci1100 biol1010
  // must be 8 characters long
  if(strlen($course) != 8) return false;
  // first 4 characters must be alphabetic
  if(!ctype_alpha(substr($course,0,4))) return false;
  // last 4 characters must be a number
  if(!is_numeric(substr($course,4,4))) return false;


  // CSCI SPECIFIC CHECKS
  // prefix must be csci
  if(strtolower(substr($course,0,4)) != "csci") return false;

  $path_front_root = get_path_front_root();
  $course_directory = $path_front_root."/courses/".$semester."/".$course;

  return is_dir($course_directory);

}

/*
// Check to make sure instructor has added this assignment
function is_valid_assignment($class_config, $assignment_id) {

  if (contains_directory_traversal($assignment_id)) {
    return false;
  }

   $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {

            return true;
        }
    }
    return false;
}
*/


function is_open_assignment($class_config, $assignment_id){
    $assignments = $class_config["assignments"];
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id) {
            if (isset($one["released"]) && $one["released"] == true)
            {
                return true;
            }
            else{
                $user = $_SESSION["id"];

                if (on_dev_team($user)) {
                    return true;
                }
            }
        }
    }
    return false;
}

// Make sure student has actually submitted this version of an assignment
function is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version) {

  if (!is_valid_course($semester,$course)) {
    return false;
  }
  if (contains_directory_traversal($assignment_id)) {
    return false;
  }
  if (contains_directory_traversal($assignment_version)) {
    return false;
  }
  if(!is_numeric($assignment_version)) return false;

  if($assignment_version < -1) return false;

  // "no submission" = -1 = a valid assignment version
  if ($assignment_version == -1) return true;

  // "cancel" = 0 = a valid assignment version
  if ($assignment_version == 0) return true;

  $path_front = get_path_front_course($semester,$course);
  $path = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version;
  return file_exists($path);
}

function is_valid_file_name($username, $semester, $course, $assignment_id, $assignment_version, $file_name){
        $path_front = get_path_front_course($semester,$course);

        if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) { display_error("get_submitted_files, INVALID Assignment Version: ".$course); }


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

    $path_front = get_path_front_course($semester,$course);

    if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) { display_error("get_submitted_files, INVALID Assignment Version: ".$course); }


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

    $path_front = get_path_front_course($semester,$course);

    if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) { display_error("get_submitted_files, INVALID Assignment Version: ".$course); }


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


function version_in_grading_queue2($username, $semester, $course, $assignment_id, $assignment_version) {

    $path_front_root = get_path_front_root();

    $queue_file = $semester."__".$course."__".$assignment_id."__".$username."__".$assignment_version;

    $interactive_queue_file = $path_front_root."/to_be_graded_interactive/".$queue_file;
    $batch_queue_file = $path_front_root."/to_be_graded_batch/".$queue_file;

    $GRADING_interactive_queue_file = $path_front_root."/to_be_graded_interactive/GRADING_".$queue_file;
    $GRADING_batch_queue_file = $path_front_root."/to_be_graded_batch/GRADING_".$queue_file;

    if (file_exists($interactive_queue_file)) {
      if (file_exists($GRADING_interactive_queue_file)) {
        return "interactive_queue";
      } else {
        return "currently_grading";
      }
    }

    if (file_exists($batch_queue_file)) {
      if (file_exists($GRADING_batch_queue_file)) {
        return "batch_queue";
      } else {
        return "currently_grading";
      }
    }

    // otherwise, should be graded!
    $path_front = get_path_front_course($semester,$course);
    if (!is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) {//If its not in the submissions folder
      return "error_does_not_exist";
    }
    $file = $path_front."/results/".$assignment_id."/".$username."/".$assignment_version;
    if (file_exists($file)) {//If the version has already been graded
      return "graded";
    }

    return "error_not_graded_and_not_in_queue";
}


//RESULTS DATA

function get_submission_time($username, $semester,$course, $assignment_id, $assignment_version) {
    $version_results = get_assignment_results($username, $semester,$course, $assignment_id, $assignment_version);//Gets user results data from submission.json for the specific version of the assignment
    if ($version_results && isset($version_results["submission_time"])) {
      return $version_results["submission_time"];
    } else {
      return "";
    }
}

function get_homework_tests($username, $semester,$course, $assignment_id, $assignment_version, $assignment_config, $include_diffs = true) {
    //These are the tests run on a homework (for grading etc.)
    $testcases_info = (isset($assignment_config["testcases"])) ? $assignment_config["testcases"] : array();
    //Gets user results data from submission.json for the specific version of the assignment
    $version_results = get_assignment_results($username, $semester,$course, $assignment_id, $assignment_version);
    if (isset($version_results["testcases"])) {
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
            if (isset($testcases_info[$i]["title"]) && isset( $testcases_results[$u]["test_name"]) && $testcases_info[$i]["title"] == $testcases_results[$u]["test_name"]){
                $data = array();
                $data["title"] = isset($testcases_info[$i]["title"]) ? $testcases_info[$i]["title"] : "";
                $data["details"] = isset($testcases_info[$i]["details"]) ? $testcases_info[$i]["details"] : "";
                $data["points_possible"] = isset($testcases_info[$i]["points"]) ? $testcases_info[$i]["points"] : 0;
                $data["score"] = isset($testcases_results[$u]["points_awarded"]) ? $testcases_results[$u]["points_awarded"] : 0;
                $data["is_hidden"] = isset($testcases_info[$i]["hidden"]) ? $testcases_info[$i]["hidden"] : false;
                $data["is_extra_credit"] = isset($testcases_info[$i]["extra_credit"]) ? $testcases_info[$i]["extra_credit"] : false;
                $data["visible"] = isset($testcases_info[$i]["visible"]) ? $testcases_info[$i]["visible"] : true;
                $data["view_test_points"] = isset($testcases_info[$i]["view_test_points"]) ? $testcases_info[$i]["view_test_points"] : true;
                $data["message"] = isset($testcases_results[$u]["message"]) ? $testcases_results[$u]["message"] : "";

                if (isset($testcases_results[$u]["execute_logfile"])) {
                    $data["execute_logfile"] = get_student_file($student_path . $testcases_results[$u]["execute_logfile"]);
                }
                if (isset($testcases_results[$u]["compilation_output"])) {
                    $data["compilation_output"] = get_compilation_output($student_path . $testcases_results[$u]["compilation_output"]);
                }
                if ($include_diffs && isset($testcases_results[$u]["autochecks"])) {
                    $data["autochecks"] = get_all_testcase_diffs($username, $semester,$course, $assignment_id, $assignment_version, $testcases_results[$u]["autochecks"]);
                }

                array_push($homework_tests, $data);
                break;
            }
        }
        // still get testcases when assignment version is 0 and give a score of 0 on every testcase
        if($assignment_version == 0 && isset($testcases_info[$i]["title"])) {
            $data = array();
            $data["title"] = isset($testcases_info[$i]["title"]) ? $testcases_info[$i]["title"] : "";
            $data["details"] = isset($testcases_info[$i]["details"]) ? $testcases_info[$i]["details"] : "";
            $data["points_possible"] = isset($testcases_info[$i]["points"]) ? $testcases_info[$i]["points"] : 0;
            $data["score"] = 0;
            $data["is_hidden"] = isset($testcases_info[$i]["hidden"]) ? $testcases_info[$i]["hidden"] : false;
            $data["is_extra_credit"] = isset($testcases_info[$i]["extra_credit"]) ? $testcases_info[$i]["extra_credit"] : false;
            $data["visible"] = isset($testcases_info[$i]["visible"]) ? $testcases_info[$i]["visible"] : true;
            $data["view_test_points"] = isset($testcases_info[$i]["view_test_points"]) ? $testcases_info[$i]["view_test_points"] : true;
            array_push($homework_tests, $data);
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
        //  if (!isset($due_date) || !defined("due_date")) {
        //           $due_date = "";
        //           }

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
            $days_late = $interval->format("%a");

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
    $file = $path_front."/config/build/build_".$assignment_id.".json";
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

//Get list of student-submitted files that students are allowed to view
function get_files_to_view($class_config,$semester,$course,$assignment_id, $username,$assignment_version){
    $assignments            = $class_config["assignments"];
    $list_with_wildcards    = array();
    $result                 = array();

    //Grab files_to_view from current assignment id
    foreach ($assignments as $one) {
        if (isset($one["assignment_id"]) && $one["assignment_id"] == $assignment_id){
            if (isset($one["files_to_view"]) ){
                $list_with_wildcards =  $one["files_to_view"];
                break;
            }
            else {
                return $result;
            }
        }
    }


    //Get absolute path to the directory that contains student's submitted files
    $path_front = get_path_front_course($semester,$course);
    $folder = $path_front."/submissions/".$assignment_id."/".$username."/".$assignment_version."/";

    //Store all the files that match with each pattern in list_with_wildcards
    foreach ($list_with_wildcards as $pattern){
        foreach (glob($folder.$pattern) as $file){
            if (!in_array($file, $result)){
                array_push($result, substr($file, strlen($folder)) );
            }
        }
    }

    return $result;

}


// FROM http://www.php.net/manual/en/function.json-decode.php
function removeTrailingCommas($json){
    $json=preg_replace('/,\s*([\]}])/m', '$1', $json);
    return $json;
}


//SUBMITTING VERSION

function get_active_version($username, $semester,$course, $assignment_id) {
    $path_front = get_path_front_course($semester,$course);
    $file = $path_front."/submissions/".$assignment_id."/".$username."/user_assignment_settings.json";
    if (!file_exists($file)) {
      return -1; // no submissions
    }
    $json = json_decode(removeTrailingCommas(file_get_contents($file)), true);
    return $json["active_version"];
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
    $json["active_version"] = (int)$assignment_version;
    $json["history"][] = array("version"=>(int)$assignment_version, "time"=>date("Y-m-d H:i:s"));

/* // php symlinks disabled on server for security reasons

    //symlink (version_path,$user_path."/ACTIVE");
    if (is_link("$user_path/ACTIVE")){
        $success=unlink("$user_path/ACTIVE");
    }

    $success = symlink ($user_path."/".$assignment_version,$user_path."/ACTIVE");
*/

    file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT));
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
        return "";
    }

    $contents = file_get_contents($file);
    $contents = str_replace(">","&gt;",$contents);
    $contents = str_replace("<","&lt;",$contents);

    return "$contents";

}

//DIFF FUNCTIONS

// Converts the JSON "diff" field from submission.json to an array containing
// file contents
function get_testcase_diff($username, $semester,$course, $assignment_id, $assignment_version, $diff){
    $path_front = get_path_front_course($semester,$course);
    $student_path = "$path_front/results/$assignment_id/$username/$assignment_version/";

    $data = array();
    $data["difference_file"] = "{differences:[]}";//This needs to be here to render the diff viewer without a teacher file

    if (isset($diff["expected_file"])) {
        $expected_file_path = "$path_front/".$diff["expected_file"];
        if (file_exists($expected_file_path)) {
            $data["instructor"] = file_get_contents($expected_file_path);
        }
    }
    if (isset($diff["actual_file"]) &&
        file_exists($student_path . $diff["actual_file"])) {
        $file_size = filesize($student_path. $diff["actual_file"]);
        if ($file_size / 1024 < 10000) {
            $data["student"] = file_get_contents($student_path.$diff["actual_file"]);
        } else {
            $data["student"] = "ERROR: Unable to read student output file.  Student output file is greater than or equal to ". ($file_size / 1024). " kb.  File could be corrupted or is too large.";
        }
    }
    if (isset($diff["difference_file"]) && file_exists($student_path . $diff["difference_file"])) {
        $data["difference_file"] = file_get_contents($student_path.$diff["difference_file"]);
    }
    return $data;
}

function get_all_testcase_diffs($username, $semester,$course, $assignment_id, $assignment_version, $diffs) {
    $results = array();
    foreach ($diffs as $diff) {
        $diff_result = get_testcase_diff($username, $semester,$course, $assignment_id, $assignment_version, $diff);
        $diff_result["autocheck_id"] = $diff["autocheck_id"];

        if (isset($diff["display_mode"]) && $diff["display_mode"] != "") {
             $diff_result["display_mode"] = $diff["display_mode"];
        }
        if (isset($diff["messages"])) {
            $diff_result["messages"] = $diff["messages"];
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

function calculate_days_late($semester, $course, $assignment_id){
  $due_date = get_due_date(get_class_config($semester, $course), $assignment_id);
  $now = new DateTime("NOW");
  $due_date->sub(new DateInterval("P1D"));  // ceiling up late days
  return date_diff($due_date, $now)->format('%r%a');
}

function get_late_days_allowed($assignment_config){
  return 2;
  // TODO: Get max late days allowed for an assignment from the config
}

function get_gradeable_addresses($semester, $course) {
    $path = get_path_front_course($semester,$course)."/config/form";
    $addresses = array();
    if (is_dir($path)) {
        if ($handle = opendir($path)) {
            while (($file = readdir($handle)) !== false) {
                if (isset($file[0]) && $file[0] != ".") {
                    $addresses[] = $path."/".$file;
                }
            }
        }
    }
    return $addresses;
}
?>
