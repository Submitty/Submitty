<?php
//CONTROLLER FUNCTIONS


// The instructor, TAs, and other developers who are listed as being
// "on the dev team" can see all assignments, released and unreleased
function on_dev_team($test_user) {
    global $dev_team;
    for ($u = 0; $u < count($dev_team); $u++) {
        if ($test_user == $dev_team[$u]) {
            return true;
        }
    }
    return false;
}


function render($viewpage, $data = array()) {
    $path = 'view/'.$viewpage.'.php';
    if (file_exists($path)) {
        extract($data);
        require_once($path);
    } else {
    	//FIXME: In production, debug information should never be sent to client browser.
    	//       Send debug information to error log, instead.
    	echo "Error, render file path does not exist <br>";
        echo "cwd = ";
        echo getcwd();
        echo "<br>path = ";
        print_r($path);
        //header('Location: index.php');
    }
}


function render_controller($viewpage, $data = array()) {
    $path = 'controller/'.$viewpage.'.php';
    if (file_exists($path)) {
        extract($data);
        require_once($path);
    } else {
        echo "Error, render file path does not exist <br>";
        echo "cwd = ";
        echo getcwd();
        echo "<br>path = ";
        print_r($path);
        //header('Location: index.php');
    }
}


function parse_status() {
    $status = "";
    if (isset($_SESSION["status"])) {
        $status_code = htmlspecialchars($_SESSION["status"]);
        if ($status_code == "uploaded_no_error") {
            $status = "Upload Successful!";
        }
        else if ($status_code == "upload_failed") {
            $status = "Unknown error.  Upload failed.";
        }
        else if ($status_code == "assignment_closed") {
            $status = "Unable to edit assignment, this assignment is closed";
        }
        else if ($status_code != "") {
            $status = $status_code;
        }
        else if ($status_code == "invalid_token") {
            $status = "CSRF token error, try the form again";
        }
        $_SESSION["status"] = "";
    }
    return $status;
}


function parse_assignment_id_with_recent($class_config, $most_recent_assignment_id) {
    if (isset($_GET["assignment_id"])) {//Which homework or which lab the user wants to see
        $assignment_id = htmlspecialchars($_GET["assignment_id"]);
        $username = $_SESSION["id"];
        if (is_open_assignment($class_config, $assignment_id) || on_dev_team($username)) {
            return $assignment_id;
        }
    }
    if (trim($most_recent_assignment_id) == "" || !(is_open_assignment($class_config, $most_recent_assignment_id) || on_dev_team($username) ) ) {
        return $most_recent_assignment_id;
    }
    header("Location: index.php?page=displaymessage&semester=".check_semester()."&course=".check_course()."&assignment_id=".$most_recent_assignment_id);

    //header("Location: index.php?page=displaymessage&semester=".check_semester()."&course=".check_course()."&assignment_id=".check_assignment_id(class_config));
    exit();

}


function get_assignment_version($username, $semester, $course, $assignment_id) {

  // if it's set in the URL, and a valid version (the directory exists), return it
  if (isset($_GET["assignment_version"])) {
    $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    if (is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) {
      return $assignment_version;
    }
  }

  // otherwise, get the "active" assignment version
  $assignment_version = get_active_version($username, $semester,$course, $assignment_id);
  if (is_valid_assignment_version($username, $semester, $course, $assignment_id, $assignment_version)) {
    return $assignment_version;
  }

  // otherwise, return -1 (no submission)
  return -1;

}


function check_semester(){
    include 'controller/defaults.php';

    $semester = $default_semester;

    if (isset($_GET["semester"])) {
        $semester = htmlspecialchars($_GET["semester"]);
    } else {
        $_SESSION["status"] = "No semester specified";
    }
    if (is_valid_semester($semester)) {
        return $semester;
    } else {
        $_SESSION["status"] = "Invalid semester specified";
        $course = $default_course;
        if (isset($_GET["course"])) {
            $course = htmlspecialchars($_GET["course"]);
        }

        header("HTTP/1.0 404 Not Found");
        echo "An error has occured: ";
        echo "Invalid semester ".'"'.$semester.'"';
        exit();
//FIXME:  Please include error HTML file in repo to avoid triggering 404 errors in apache.
//		header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course);
//      header("Location: ERROR_Xbad_semester_error.html");
		return "f00";
    }
}


function check_course() {

    $semester = check_semester();

    include 'controller/defaults.php';

    $course = $default_course;

    if (isset($_GET["course"])) {
        $course = htmlspecialchars($_GET["course"]);
    }
    else{
        $_SESSION["status"] = "No course specified";
    }

    if (is_valid_course($semester,$course)) {
        $_SESSION["status"] = "";

        return $course;
    } else {
        $_SESSION["status"] = "Invalid course specified";

        header("HTTP/1.0 404 Not Found");
        echo "An error has occured: ";
        echo "Invalid course ".'"'.$course.'"';
        exit();
//FIXME:  Please include error HTML file in repo to avoid triggering 404 errors in apache.
//      header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course);
//      header("Location: ERROR_X_bad_course_error.html");
	    return "csci0000";
    }
}

function get_username() {
  return $_SESSION["id"];
}

function check_assignment_id($class_config){
    if (isset($_GET["assignment_id"])) {
        $username = $_SESSION["id"];
        $assignment_id = htmlspecialchars($_GET["assignment_id"]);
        if (is_open_assignment($class_config, $assignment_id) || on_dev_team($username) || trim($assignment_id)=="") {
            return $assignment_id;
        }
        $_SESSION["status"] = "Invalid assignment_id specified";
    }
    else{
        $_SESSION["status"] = "No assignment_id specified";
    }
    // FIXME, displaymesssage does not exist
    header("Location: index.php?page=displaymessage&semester=".check_semester()."&course=".check_course());
    exit();
}


function check_file_name($semester, $course, $assignment_id, $assignment_version){
    if (isset($_GET["file_name"])) {
        $file_name = htmlspecialchars($_GET["file_name"]);
        $username = $_SESSION["id"];

        if ($file_name == "all" || is_valid_file_name($username, $semester, $course, $assignment_id, $assignment_version, $file_name)){
            return $file_name;
        }
        $_SESSION["status"] = "Invalid file name specified";
    }
    else{
        $_SESSION["status"] = "No file name specified";
    }
    // FIXME, displaymesssage does not exist
    header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course."&assignment_id=".$assignment_id."&assignment_version=".$assignment_version);
    exit();
}


function check_class_config($semester,$course){
    $class_config = get_class_config($semester,$course);//Gets class.JSON data
    if ($class_config == NULL) {
        ?><script>alert("Configuration for this class (class.JSON) is invalid.  Quitting");</script>

        <?php
        $_SESSION["status"] = "Configuration for this class (class.JSON) is invalid";

        header("Location: index.php?page=displaymessage&semester=".$semester."&course=".$course);
        exit();
    }
    return $class_config;
}
