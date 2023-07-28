/**
 -----------------------------

 course_materials_folders_list.js
 Used to control the drop-down menu of possible folders when uploading a file.

 -----------------------------
*/



// ---



// ---------------------------

// Variables

/**
 * The path of every folder for this course's course materials.
 */
var all_paths = new Set();


/**
 * The path being typed by the user. This is updated every time a character is typed.
 */
var current_inquiry = "";

/**
 * A subset of all the paths. Only the paths current_inquiry could lead to.
 */
var current_possible_paths = new Set();

// ---------------------------



// ---



// ---------------------------

// Functions

function setFolderPaths(folders) {
    all_paths = new Set(folders);
}

function updatePossiblePaths(current_text) {
    if (current_inquiry
        && current_text.length == current_inquiry.length+1
        && current_text.substr(0, current_text.length-1) == current_inquiry) {

        current_inquiry = current_text;
        var newChar = current_inquiry.charAt(current_inquiry.length - 1);
        narrowPossiblePaths(newChar);
        console.log("narrow");
    } else {
        current_inquiry = current_text;
        recalculatePossiblePaths();
        console.log("all");
    }
    console.log(current_text);
    console.log(current_possible_paths);
}


function recalculatePossiblePaths() {
    current_possible_paths.clear();
    for (path of all_paths) {
        var pathStart = path.substr(0, current_inquiry.length);
        if (pathStart == current_inquiry) {
            current_possible_paths.add(path);
        }
    }
}


function narrowPossiblePaths(newChar) {
    for (path of current_possible_paths) {
        var pathChar = path.charAt(current_inquiry.length - 1);
        if (pathChar != newChar) {
            current_possible_paths.delete(path);
        }
    }
}