/*
https://developer.mozilla.org/en-US/docs/Using_files_from_web_applications
https://www.sitepoint.com/html5-file-drag-and-drop/
https://www.sitepoint.com/html5-ajax-file-upload/
http://www.html5rocks.com/en/tutorials/file/dndfiles/
*/

var name_array = [];	// stores the name of the files uploaded
var files_to_upload = new FormData();

function clicked_on_box(e){
  document.getElementById("input_file").click();
  e.stopPropagation();
}

function draghandle(e){
	e.preventDefault();
	e.stopPropagation();
	document.getElementById("upload").style.opacity = (e.type == "dragenter" || e.type == "dragover") ? .5 : "";
}

function drop(e){
	draghandle(e);
	var error = "";
	// copy files dragged
	for(var i=0; i<e.dataTransfer.files.length; i++){
		// Uploading folder not allowed
		if(e.dataTransfer.files[i].type == ""){
			error = error + e.dataTransfer.files[i].name + "\n";
			continue;
		}
		files_to_upload.append("files[]", e.dataTransfer.files[i]);
		name_array.push(e.dataTransfer.files[i].name);
	}
	display_uploaded_files();
	if(error) alert("Uploading folders is not allowed!\nFolder(s) not added to submission:\n" + error);
}

// copy files selected from the dialog box
function addFile(){
	var f = document.getElementById("input_file").files;
	for(var i=0; i<f.length; i++){
		files_to_upload.append("files[]", f[i]);
		name_array.push(f[i].name);
	}
	display_uploaded_files();
}

// remove all files uploaded
function deleteFiles(){
	files_to_upload = new FormData();
	name_array = [];
	display_uploaded_files();
}

// display file names
function display_uploaded_files(){
	document.getElementById("disp").innerHTML = "<br>Drop files here:<br>";
	if(name_array.length == 0){
		return;
	}
	var fname = "";
	for(var i=0; i<name_array.length; i++){
		fname += name_array[i];
		fname += "<br>";
	}
	document.getElementById("disp").innerHTML += fname;
}

function submit(url, csrf_token, svn_checkout){
	/*
	if(name_array.length == 0){
		alert("No files selected.");
		return;
	}*/

	// Prepare files
	files_to_upload.append('csrf_token', csrf_token);
	files_to_upload.append('svn_checkout', svn_checkout);

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
