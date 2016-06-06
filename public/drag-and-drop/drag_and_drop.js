/*
https://developer.mozilla.org/en-US/docs/Using_files_from_web_applications
https://www.sitepoint.com/html5-file-drag-and-drop/
https://www.sitepoint.com/html5-ajax-file-upload/
http://www.html5rocks.com/en/tutorials/file/dndfiles/
*/

var file_array = [];
function create_file_array(num_parts){
	if(file_array.length == 0){
		for(var i=0; i<num_parts; i++){
			file_array.push([]);
		}
	}
}

// open a file browser if clicked on drop zone
function clicked_on_box(e){
  document.getElementById("input_file" + get_part_number(e)).click();
  e.stopPropagation();
}

function draghandle(e){
	e.preventDefault();
	e.stopPropagation();
	document.getElementById("upload" + get_part_number(e)).style.opacity = (e.type == "dragenter" || e.type == "dragover") ? .5 : "";
}

function drop(e){
	draghandle(e);
	addFiles(e.dataTransfer.files, get_part_number(e));
}

function get_part_number(e){
	return e.target.id.substring(e.target.id.length - 1);
}

// copy files selected from the dialog box
function addFile(part){
	addFiles(document.getElementById("input_file" + part).files, part);
}

// Check for duplicate file names
function fileExists(file, part){
	for(var j=0; j<file_array[part-1].length; j++){
		if(file_array[part-1][j].name == file.name){
			return j;
		}
	}
	return -1;
}

function addFiles(filestream, part){
	var error = "";
	// copy files dragged
	for(var i=0; i<filestream.length; i++){
		var f = filestream[i];
		// Uploading folder not allowed
		if(f.type == ""){
			error = error + f.name + "\n";
			continue;
		}
		var j = fileExists(f, part);
		if( j == -1 ){	// file does not exist
			file_array[part-1].push(f);
		}
		else if(confirm("Note: " + file_array[part-1][j].name + " already exists. Do you want to replace it?")){ // replace with new
				file_array[part-1][j] = f;
		}
		// TODO: Maybe storing the names in a separate array and rename when uploading
	}
	display_uploaded_files(part);
	if(error) alert("Uploading folders is not allowed!\nFolder(s) not added to submission:\n" + error);
}

// remove all files uploaded
function deleteFiles(part){
	if(file_array.length != 0){
		file_array[part-1] = [];
	}
	display_uploaded_files(part);
}

// TODO: Finish implementation of single file deletion
function deleteSingleFile(index){
	// var removed = file_array.splice(i,1);
	// display_uploaded_files();
	alert("removed: " + removed.name);
}

// display file names
function display_uploaded_files(part){
	if(file_array.length == 0 || file_array[part-1].length == 0){
		document.getElementById("disp" + part).innerHTML = "<br>";
		return;
	}
	var fname = "";
	for(var i=0; i<file_array[part-1].length; i++){
		fname += file_array[part-1][i].name;
		fname += "<br>";
	}
	document.getElementById("disp" + part).innerHTML = "<br>" + fname + "<br>";
}

function submit(url, csrf_token, svn_checkout){
	/*
	if(file_array.length == 0){
		alert("No files selected.");
		return;
	}*/
	var files_to_upload = new FormData();
	// Prepare files
	files_to_upload.append('csrf_token', csrf_token);
	files_to_upload.append('svn_checkout', svn_checkout);
	
	for(var i=0; i<file_array.length; i++){
		for(var j=0; j<file_array[i].length; j++){
			files_to_upload.append('files' + (i+1) + '[]', file_array[i][j]);
		}
	}

	// xhr
	var xhr = new XMLHttpRequest();
	xhr.open("POST", url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            // Handle response.
            // alert(xhr.responseText);
            window.location.reload();
        }
    };
	xhr.send(files_to_upload);
	/*
	//
	jQuery.ajax(url, {
		data: files_to_upload,
		method: "post"
	})
	.complete(function() {

	})
	.error(function() {

	})
*/
}
