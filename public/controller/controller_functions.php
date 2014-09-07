<?php
//CONTROLLER FUNCTIONS
function render($viewpage, $data = array()) {
    $path = 'view/'.$viewpage.'.php';
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
        } else if ($status_code == "upload_failed") {
            $status = "Unknown error.  Upload failed.";
        } else if ($status_code == "assignment_closed") {
            $status = "Unable to edit assignment, this assignment is closed";
        } else if ($status_code != "") {
            $status = $status_code;
        }
        $_SESSION["status"] = "";
    }
    return $status;
}
function parse_course() {
    if (isset($_GET["course"])) {
        $course = htmlspecialchars($_GET["course"]);
        if (!is_valid_course($course)) {
            $course = "CONTROLLER_HOMEWORK_NONE_A";
        } 
    } else {
        $course = "CONTROLLER_HOMEWORK_NONE_B";
    }
    return $course;
}

function parse_assignment_id($class_config, $most_recent_assignment_id) {
    if (isset($_GET["assignment_id"])) {//Which homework or which lab the user wants to see
        $assignment_id = htmlspecialchars($_GET["assignment_id"]);
        if (!is_valid_assignment($class_config, $assignment_id)) {
            $assignment_id = $most_recent_assignment_id;
        }
        return $assignment_id;
    }
    return $most_recent_assignment_id;
}

function parse_assignment_version($username, $course, $assignment_id) {
    if (isset($_GET["assignment_version"])) {
        $assignment_version = htmlspecialchars($_GET["assignment_version"]);
    }
    if (!isset($assignment_version) || !is_valid_assignment_version($username, $course, $assignment_id, $assignment_version) || $assignment_version == "") {
        $assignment_version = most_recent_assignment_version($username, $course, $assignment_id);
    }
    return $assignment_version;
}

function on_dev_team($test_user) {
  global $dev_team;
  for ($u = 0; $u < count($dev_team); $u++) {
    if ($test_user == $dev_team[$u]) return true;
  }  
  return false;
}



