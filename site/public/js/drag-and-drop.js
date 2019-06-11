/*
References:
https://developer.mozilla.org/en-US/docs/Using_files_from_web_applications
https://developer.mozilla.org/en-US/docs/Web/API/FormData/Using_FormData_Objects
https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/Using_XMLHttpRequest#Submitting_forms_and_uploading_files
https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Dragging_and_Dropping_Multiple_Items
https://www.sitepoint.com/html5-file-drag-and-drop/
https://www.sitepoint.com/html5-ajax-file-upload/
http://www.html5rocks.com/en/tutorials/file/dndfiles/
*/

// INITIALIZATION
//========================================================================================
var file_array = [];        // contains files uploaded for this submission
var previous_files = [];    // contains names of files selected from previous submission
var label_array = [];
var use_previous = false;
var changed = false;        // if files from previous submission changed

var empty_inputs = true;

var student_ids = [];           // all student ids
var student_without_ids = [];   // student ids for those w/o submissions

// initializing file_array and previous_files
function createArray(num_parts){
    if(file_array.length == 0){
        for(var i=0; i<num_parts; i++){
            file_array.push([]);
            previous_files.push([]);
            label_array.push([]);
        }
    }
}

// read in name of previously submitted file
function readPrevious(filename, part){
    changed = false;
    previous_files[part-1].push(filename);
}

function setUsePrevious() {
    use_previous = true;
}

// DRAG AND DROP EFFECT
//========================================================================================
// open a file browser if clicked on drop zone
function clicked_on_box(e){
  document.getElementById("input_file" + get_part_number(e)).click();
  e.stopPropagation();
}

// hover effect
function draghandle(e){
    e.preventDefault();
    e.stopPropagation();
    document.getElementById("upload" + get_part_number(e)).style.opacity = (e.type == "dragenter" || e.type == "dragover") ? .5 : "";
}

// ADD FILES FOR NEW SUBMISSION
//========================================================================================
// add files dragged
function drop(e){
    draghandle(e);
    var filestream= e.dataTransfer.files;
    var part = get_part_number(e);
    for(var i=0; i<filestream.length; i++){
        addFileWithCheck(filestream[i], part); // check for folders
    }
}

// add files dragged
function dropWithMultipleZips(e){
    draghandle(e);
    var filestream= e.dataTransfer.files;
    var part = get_part_number(e);
    for(var i=0; i<filestream.length; i++){
        addFileWithCheck(filestream[i], part, false); // check for folders
    }
}

function get_part_number(e){
    if(e.target.id.substring(0, 6) == "upload"){
        return e.target.id.substring(6);
    }
    else{
        return e.target.parentNode.id.substring(6);
    }
}

// copy files selected from the file browser
function addFilesFromInput(part, check_duplicate_zip=true){
    var filestream = document.getElementById("input_file" + part).files;
    for(var i=0; i<filestream.length; i++){
        addFile(filestream[i], part, check_duplicate_zip); // folders will not be selected in file browser, no need for check
    }
    $('#input_file' + part).val("");
}

// Check for duplicate file names. This function returns an array.
// First element:
// 1 - a file with the same name found in previous submission
// 0 - a file with the same name already selected for this version
// -1 - does not exist files with the same name
// Second element: index of the file with the same name (if found)
function fileExists(file, part){
    for(var i = 0; i < previous_files[part-1].length; i++){
        if(previous_files[part-1][i] == file.name){
            return [1, i];
        }
    }

    for(var j = 0; j < file_array[part-1].length; j++){
        if(file_array[part-1][j].name == file.name){
            return [0, j];
        }
    }
    return [-1];
}

// add file with folder check
function addFileWithCheck(file, part, check_duplicate_zip=true){
    // try to open file if it looks suspicious:
    // no type, or with size of a typical folder size
    if(!file.type || file.size%4096 == 0){
        var reader = new FileReader();
        reader.onload = notFolder(file, part);
        reader.onerror = isFolder(file);
        reader.readAsBinaryString(file);
    }
    else{
        addFile(file, part, check_duplicate_zip);
    }
}

// add file if is not a folder
function notFolder(file, part){
    return function(e){ addFile(file, part); }
}

function isFolder(file){
    return function(e){ alert("Upload failed: " + file.name + " might be a folder."); }
}

function addFile(file, part, check_duplicate_zip=true){
    var i = fileExists(file, part);
    if( i[0] == -1 ){    // file does not exist
        // empty bucket if file is a zip and bucket is not empty
        if(check_duplicate_zip && file.name.substring(file.name.length - 4, file.name.length) == ".zip" && file_array[part-1].length + previous_files[part-1].length > 0 ){
            if(confirm("Note: All files currently in the bucket will be deleted if you try to upload a zip: " + file.name + ". Do you want to continue?")){
                deleteFiles(part);
            }
        }
        file_array[part-1].push(file);
        addLabel(file.name, (file.size/1024).toFixed(2), part, false);
    }
    else if(i[0] == 0){    // file already selected
        if(confirm("Note: " + file_array[part-1][i[1]].name + " is already selected. Do you want to replace it?")){
            file_array[part-1].splice(i[1], 1, file);
            removeLabel(file.name, part);
            addLabel(file.name, (file.size/1024).toFixed(2), part, false);
        }
    }
    else{    // file in previous submission
        if(confirm("Note: " + previous_files[part-1][i[1]] + " was in your previous submission. Do you want to replace it?")){
            file_array[part-1].push(file);
            previous_files[part-1].splice(i[1], 1);
            removeLabel(file.name, part);
            addLabel(file.name, (file.size/1024).toFixed(2), part, false);
            changed = true;
        }
    }

    setButtonStatus()
}

// REMOVE FILES
//========================================================================================
// delete files selected for a part
function deleteFiles(part) {
    if(file_array.length != 0){
        file_array[part-1] = [];
    }
    if(previous_files.length != 0){
        previous_files[part-1] = [];
    }
    var dropzone = document.getElementById("upload" + part);
    var labels = dropzone.getElementsByClassName("mylabel");
    while(labels[0]){
        dropzone.removeChild(labels[0]);
    }
    label_array[part-1] = [];
    changed = true;
    setButtonStatus();
}

function deleteSingleFile(filename, part, previous) {
    // Remove files from previous submission
    if (previous) {
        for (var i = 0; i < previous_files[part-1].length; i++){
            if(previous_files[part-1][i] == filename){
                previous_files[part-1].splice(i, 1);
                label_array[part-1].splice(i, 1);
                changed = true;
                break;
            }
        }
    }
    // Remove files uploaded for submission
    else{
        for (var j = 0; j < file_array[part-1].length; j++){
            if (file_array[part-1][j].name == filename) {
                file_array[part-1].splice(j, 1);
                label_array[part-1].splice(j, 1);
                break;
            }
        }
    }
    setButtonStatus();
}

function setButtonStatus() {

    // we only want to clear buckets if there's any labels in it (otherwise it's "blank")
    var labels = 0;
    for (var i = 0; i < label_array.length; i++) {
        labels += label_array[i].length;
    }

    if (labels == 0) {
        $("#startnew").prop("disabled", true);
        if (empty_inputs) {
            $("#submit").prop("disabled", true);
        } else {
            $("#submit").prop("disabled", false);
        }
    }
    else {
        $("#startnew").prop("disabled", false);
        $("#submit").prop("disabled", false);
    }

    // We only have "non-previous" submissions if there's stuff in the file array as well as if we've
    // toggled the necessary flag that we're on a submission that would have previous (to prevent costly dom
    // lookups for the existance of #getprev id in the page)
    var files = 0;
    for (var j = 0; j < file_array.length; j++) {
        files += file_array[j].length;
    }

    if (use_previous && !changed && files == 0) {
        $("#getprev").prop("disabled", true);
    }
    else if (use_previous) {
        $("#getprev").prop("disabled", false);
    }
}

// LABELS FOR SELECTED FILES
//========================================================================================
function removeLabel(filename, part){
    var dropzone = document.getElementById("upload" + part);
    var labels = dropzone.getElementsByClassName("mylabel");
    for(var i = 0 ; i < labels.length; i++){
        if(labels[i].getAttribute("fname") == filename){
            dropzone.removeChild(labels[i]);
            label_array[part-1].splice(i, 1);
            break;
        }
    }
}

function addLabel(filename, filesize, part, previous){
    // create element
    var tmp = document.createElement('label');
    tmp.setAttribute("class", "mylabel");
    tmp.setAttribute("fname", filename);
    tmp.innerHTML =  filename + " " + filesize + "kb <i role='text' aria-label='Press enter to remove file " + filename + "' tabindex='0' class='fas fa-trash'></i><br />";

    // styling
    tmp.children[0].onmouseover = function(e){
        e.stopPropagation();
        this.style.color = "#FF3933";
    };
    tmp.children[0].onmouseout = function(e){
        e.stopPropagation();
        this.style.color = "black";
    };
    // remove file and label on click
    tmp.children[0].onclick = function(e){
        e.stopPropagation();
        this.parentNode.parentNode.removeChild(this.parentNode);
        deleteSingleFile(filename, part, previous);
    };

    // FOR VPAT if trash can has focus and key is pressed it will delete item
    tmp.children[0].onkeypress = function(e){
        e.stopPropagation();
        this.parentNode.parentNode.removeChild(this.parentNode);
        deleteSingleFile(filename, part, previous);
    };

    // add to parent div
    var dropzone = document.getElementById("upload" + part);
    // Uncomment if want buttons for emptying single bucket
    // var deletebutton = document.getElementById("delete" + part);
    dropzone.appendChild(tmp);
    // dropzone.insertBefore(tmp, deletebutton);
    label_array[part-1].push(filename);
}

function handle_input_keypress() {
    empty_inputs = false;
    setButtonStatus();
}

// BULK UPLOAD
//========================================================================================
function openFile(url_full) {
    window.open(url_full,"_blank","toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600");
}

// moving to next input for split item submissions
// referenced https://stackoverflow.com/questions/18150090/jquery-scroll-element-to-the-middle-of-the-screen-instead-of-to-the-top-with-a
function moveNextInput(count) {
    var next_count = count+1;
    var next_input = "#users_" + next_count + " :first";
    if ($(next_input).length) {
        $(next_input).focus();
        $(next_input).select();

        var inputOffset = $(next_input).offset().top;
        var inputHeight = $(next_input).height();
        var windowHeight = $(window).height();
        var offset;

        if (inputHeight < windowHeight) {
            offset = inputOffset - ((windowHeight / 2) - (inputHeight / 2));
        }
        else {
            offset = inputOffset;
        }
        var speed = 500;
        $('html, body').animate({scrollTop:offset}, speed);
    }
}



// HANDLE SUBMISSION
//========================================================================================
function isValidSubmission(){
    // check if new files added
    for (var i=0; i < file_array.length; i++) {
        if(file_array[i].length != 0){
            return true;
        }
    }
    // check if files from previous submission changed
    if (changed) {
        // check if previous submission files are emptied
        for (var j = 0; j < previous_files.length; j++) {
            if (previous_files[j] != 0) {
                return true;
            }
        }
    }
    return false;
}

/**
 * @param csrf_token
 * @param gradeable_id
 * @param user_id
 * Ajax call to check if user id is valid and has a corresponding user and gradeable.
 * user_id can be an array of ids to validate multiple at once for teams
 */
function validateUserId(csrf_token, gradeable_id, user_id){
    var url = buildUrl({'component': 'student', 'page': 'submission', 
                        'action': 'verify', 'gradeable_id': gradeable_id});
    return new Promise(function (resolve, reject) {
        $.ajax({
            url : url,
            data : {
                'csrf_token' : csrf_token,
                'user_id' : user_id
            },
            type : 'POST',
            success : function(response){ 
                response = JSON.parse(response);
                if(response['status'] === 'success'){
                    resolve(response['data']);
                }else{
                    reject(response['message']);
                }
            },
            error : function(err){
                console.log("Error while trying to validate user id" + user_id);
                reject(new Error(err));
            }
        });
    });
}

//@param json a dictionary {success : true/false, message : string}
//@param index used for id
//function to display pop-up notification after bulk submission/delete
function displaySubmissionMessage(json, index = 0){
    var message ='<div id="bulk_message_' + String(index) + '" class="inner-message alert alert-' +
                        (json['status'] === 'success' ? 'success' : 'error') + '">\
                    <a class="fas fa-times message-close" onclick="removeMessagePopup(\'bulk_message_' + String(index) + '\');"></a>\
                    <i class="' + (json['status'] === 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle') +'"></i>' + json['status'] === 'success' ? json['data'] : json['message'] +
                 '</div>';

    $('#messages').append(message);
    setTimeout(function() {
        $("#bulk_message_" + String(index)).fadeOut().empty();
    }, 5000);
}

//@param callback to function when user selects an option
//function to display the different options when submiting a split item to a student with previous submissions
function displayPreviousSubmissionOptions(callback){
    var form = $("#previous-submission-form");
    var submit_btn = form.find(".submit-button");
    var closer_btn = form.find(".close-button");

    var option;
    // on click, make submission based on which radio input was checked
    submit_btn.on('click', function() { 
        if($("#instructor-submit-option-new").is(":checked")) {
            localStorage.setItem("instructor-submit-option", "0");
            option = 1;
        }else if($("#instructor-submit-option-merge-1").is(":checked")) {
            localStorage.setItem("instructor-submit-option", "1");
            option = 2;
        }else if($("#instructor-submit-option-merge-2").is(":checked")) {
            localStorage.setItem("instructor-submit-option", "2");
            option = 3;
        }
        form.css("display", "none");
        callback(option);
    });

    //on close, save the option selected
    closer_btn.on('click', function() {
        if($("#instructor-submit-option-new").is(":checked")) {
            localStorage.setItem("instructor-submit-option", "0");
        }else if($("#instructor-submit-option-merge-1").is(":checked")) {
            localStorage.setItem("instructor-submit-option", "1");
        }else if($("#instructor-submit-option-merge-2").is(":checked")) {
            localStorage.setItem("instructor-submit-option", "2");
        }
        form.css("display", "none");
        callback(-1);
    });

    $('.popup-form').css('display', 'none');
    form.css("display", "block");

    //check the option from whatever option was saved
    var radio_idx;
    if(localStorage.getItem("instructor-submit-option") === null) {
        radio_idx = 0;
    }else {
        radio_idx = parseInt(localStorage.getItem("instructor-submit-option"));
    }
    form.find('input:radio')[radio_idx].checked = true;
}

/**
 * @param csrf_token
 * @param gradeable_id
 * @param user_id
 * @param path
 * @param merge_previous
 * @param clobber
 * @return promise resolve on success, reject otherwise. Contains fail/success message
 * Ajax call to submit a split item to a student. Optional params to merge and or clobber previous submissions
 */
function submitSplitItem(csrf_token, gradeable_id, user_id, path, merge_previous=false, clobber=false) {
    var url = buildUrl({'component': 'student', 'page': 'submission', 
                        'action': 'upload_split', 'gradeable_id': gradeable_id, 
                        'merge': merge_previous, 'clobber': clobber});

    return new Promise(function (resolve, reject) {
        $.ajax({
            url: url,
            data: {
                'csrf_token' : csrf_token,
                'user_id' : user_id,
                'path' : path
            },
            type: 'POST',
            success: function(response) {     
                response = JSON.parse(response);
                if (response['status'] === 'success') {
                    resolve(response);
                }
                else {
                    reject(response);
                }    
            },
            error: function(err) {
                console.log("Failed while submiting split item");
                reject(new Error(err));
            }
        });
    });
}

/**
* @param csrf_token
* @param gradeable_id
* @param path
* @return promise resolve on success, reject otherwise. Contains fail/success message
*/
function deleteSplitItem(csrf_token, gradeable_id, path) {

    var submit_url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'delete_split', 'gradeable_id': gradeable_id});

    return new Promise(function (resolve, reject) {
        $.ajax({
            url: submit_url,
            data: {
                'csrf_token' : csrf_token,
                'path' : path
            },
            type: 'POST',
            success: function(response) {
                response = JSON.parse(response);
                if (response['status'] === 'success') {
                    resolve(response);
                }else {
                    reject(response);
                }
            },
            error: function(err) {
                console.log("Failed while deleting split item");
                reject(new Error(err));
            }
        });
    });
}

/**
 * @param gradeable_id
 * @param num_pages
 * @param use_qr_codes
 * @param qr_prefix
 */
function handleBulk(gradeable_id, num_pages, use_qr_codes = false, qr_prefix = "", qr_suffix="") {
    $("#submit").prop("disabled", true);

    var formData = new FormData();

    if(!use_qr_codes){
        if(num_pages == "") {
            alert("You didn't enter the # of page(s)!");
            $("#submit").prop("disabled", false);
            return;
        }
        else if(num_pages < 1 || num_pages % 1 != 0) {
            alert(num_pages + " is not a valid # of page(s)!");
            $("#submit").prop("disabled", false);
            return;
        }
    }
    formData.append('num_pages', num_pages);
    formData.append('use_qr_codes', use_qr_codes);
    //encode qr prefix and suffix incase URLs are used
    formData.append('qr_prefix', encodeURIComponent(qr_prefix));
    formData.append('qr_suffix', encodeURIComponent(qr_suffix));

    for (var i = 0; i < file_array.length; i++) {
        for (var j = 0; j < file_array[i].length; j++) {
            if (file_array[i][j].name.indexOf("'") != -1 ||
                file_array[i][j].name.indexOf("\"") != -1) {
                alert("ERROR! You may not use quotes in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("\\") != -1 ||
                file_array[i][j].name.indexOf("/") != -1) {
                alert("ERROR! You may not use a slash in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("<") != -1 ||
                file_array[i][j].name.indexOf(">") != -1) {
                alert("ERROR! You may not use angle brackets in your filename: " + file_array[i][j].name);
                return;
            }
            formData.append('files' + (i + 1) + '[]', file_array[i][j], file_array[i][j].name);
        }
    }

    var url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'bulk', 'gradeable_id': gradeable_id});
    var return_url = buildUrl({'component': 'student', 'gradeable_id': gradeable_id});

    $.ajax({
        url: url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            $("#submit").prop("disabled", false);
            try {
                data = JSON.parse(data);
                if (data['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    if (data['message'] == "You do not have access to that page.") {
                        window.location.href = return_url;
                    }
                    else {
                        alert("ERROR! \n\n" + data['message']);
                    }
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading.");
                console.log(data);
            }
        },
        error: function() {
            $("#submit").prop("disabled", false);
            alert("ERROR! Please contact administrator that you could not upload files.");
        }
    });
}

/**
 * @param type
 */
function gatherInputAnswersByType(type){
    var input_answers = {};

    // If type is codebox only grab 'div' but not buttons with similar ids
    if(type == "codebox")
    {
        var inputs = $("div[id^="+type+"_]");
    }
    else
    {
        var inputs = $("[id^="+type+"_]");
    }

    if(type != "codebox"){
        inputs = inputs.serializeArray();
    }

    for(var i = 0; i < inputs.length; i++){
        var this_input_answer = inputs[i];
        var key = "";
        var value = "";
        if(type == "codebox"){
            key = this_input_answer.id;
            var editor = this_input_answer.querySelector(".CodeMirror").CodeMirror;
            value = editor.getValue();
        }else{
            key = this_input_answer.name;
            value = this_input_answer.value;
        }

        if(!(key in input_answers)){
            input_answers[key] = Array();
        }
        input_answers[key].push(value);
    }

    return input_answers;
}

/**
 * @param days_late
 * @param late_days_allowed
 * @param versions_used
 * @param versions_allowed
 * @param csrf_token
 * @param vcs_checkout
 * @param num_inputs
 * @param user_id
 * @param repo_id
 * @param student_page
 * @param num_components
 * @param merge_previous
 */
function handleSubmission(days_late, late_days_allowed, versions_used, versions_allowed, csrf_token, vcs_checkout, num_inputs, gradeable_id, user_id, git_user_id, git_repo_id, student_page, num_components, merge_previous=false, clobber=false) {
    $("#submit").prop("disabled", true);

    var submit_url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'upload', 'gradeable_id': gradeable_id, "merge": merge_previous, "clobber": clobber});
    var return_url = buildUrl({'component': 'student','gradeable_id': gradeable_id});

    var message = "";
    // check versions used
    if(versions_used >= versions_allowed) {
        message = "You have already made " + versions_used + " submissions.  You are allowed " + versions_allowed + " submissions before a small point penalty will be applied. Are you sure you want to continue?";
        if (!confirm(message)) {
            return;
        }
    }
    // check due date
    if (days_late > 0 && days_late <= late_days_allowed) {
        message = "Your submission will be " + days_late + " day(s) late. Are you sure you want to use " +days_late + " late day(s)?";
        if (!confirm(message)) {
            return;
        }
    }
    else if (days_late > 0) {
        message = "Your submission will be " + days_late + " days late. You are not supposed to submit unless you have an excused absence. Are you sure you want to continue?";
        if (!confirm(message)) {
            return;
        }
    }

    var formData = new FormData();

    formData.append('csrf_token', csrf_token);
    formData.append('vcs_checkout', vcs_checkout);
    formData.append('user_id', user_id);
    formData.append('git_user_id', git_user_id);
    formData.append('git_repo_id', git_repo_id);
    formData.append('student_page', student_page)

    if (!vcs_checkout) {
        // Check if new submission
        if (!isValidSubmission() && empty_inputs) {
            alert("Not a new submission.");
            window.location.reload();
            return;
        }

        // Files selected
        for (var i = 0; i < file_array.length; i++) {
            for (var j = 0; j < file_array[i].length; j++) {
                if (file_array[i][j].name.indexOf("'") != -1 ||
                    file_array[i][j].name.indexOf("\"") != -1) {
                    alert("ERROR! You may not use quotes in your filename: " + file_array[i][j].name);
                    return;
                }
                else if (file_array[i][j].name.indexOf("\\") != -1 ||
                    file_array[i][j].name.indexOf("/") != -1) {
                    alert("ERROR! You may not use a slash in your filename: " + file_array[i][j].name);
                    return;
                }
                else if (file_array[i][j].name.indexOf("<") != -1 ||
                    file_array[i][j].name.indexOf(">") != -1) {
                    alert("ERROR! You may not use angle brackets in your filename: " + file_array[i][j].name);
                    return;
                }
            formData.append('files' + (i + 1) + '[]', file_array[i][j], file_array[i][j].name);
            }
        }
        // Files from previous submission
        formData.append('previous_files', JSON.stringify(previous_files));
    }

    
    var short_answer_object    = gatherInputAnswersByType("short_answer");
    var multiple_choice_object = gatherInputAnswersByType("multiple_choice");
    var codebox_object         = gatherInputAnswersByType("codebox");
    formData.append('short_answer_answers'   , JSON.stringify(short_answer_object));
    formData.append('multiple_choice_answers', JSON.stringify(multiple_choice_object));
    formData.append('codebox_answers'        , JSON.stringify(codebox_object));


    if (student_page) {
        var pages = [];
        for (var i = 0; i < num_components; i++) {
            pages[i] = $("#page_"+i).val();
            if (pages[i] == "") {
                alert("You cannot leave a page input empty.");
                $("#submit").prop("disabled", false);
                return;
            }
            if (parseInt(pages[i]) < 1) {
                alert("Page numbers cannot be less than 1.");
                $("#submit").prop("disabled", false);
                return;
            }
        }
        formData.append('pages', JSON.stringify(pages));
    }

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            $("#submit").prop("disabled", false);
            try {
                data = JSON.parse(data);
                if (data['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    if (data['message'] == "You do not have access to that page.") {
                        window.location.href = return_url;
                    }
                    else {
                        alert("ERROR! Please contact administrator with following error:\n\n" + data['message']);
                    }
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading.");
                console.log(data);
            }
        },
        error: function(error) {
            $("#submit").prop("disabled", false);
            alert("ERROR! Please contact administrator that you could not upload files.");
        }
    });
}

/**
 * @param csrf_token
 */
function handleDownloadImages(csrf_token) {
    var image_submit_url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'upload_images_files'});
    var return_url = buildUrl({'component': 'grading', 'page': 'images', 'action': 'view_images_page'});
    var formData = new FormData();
    formData.append('csrf_token', csrf_token);
    formData.append('file_count', file_array.length);


    // Files selected
    for (var i = 0; i < file_array.length; i++) {
        for (var j = 0; j < file_array[i].length; j++) {
            if (file_array[i][j].name.indexOf("'") != -1 ||
                file_array[i][j].name.indexOf("\"") != -1) {
                alert("ERROR! You may not use quotes in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("\\") != -1 ||
                file_array[i][j].name.indexOf("/") != -1) {
                alert("ERROR! You may not use a slash in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("<") != -1 ||
                file_array[i][j].name.indexOf(">") != -1) {
                alert("ERROR! You may not use angle brackets in your filename: " + file_array[i][j].name);
                return;
            }
            formData.append('files' + (i + 1) + '[]', file_array[i][j], file_array[i][j].name);
        }
    }

    $.ajax({
        url: image_submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            try {
                data = JSON.parse(data);

                if (data['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    alert("ERROR! Please contact administrator with following error:\n\n" + data['message']);
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading.");
            }
        },
        error: function(data) {
            window.location.href = buildUrl({'component': 'grading', 'page': 'images', 'action': 'view_images_page'});
        }
    });
}

/**
 * @param csrf_token
 */

function handleUploadCourseMaterials(csrf_token, expand_zip, cmPath, requested_path) {
    var submit_url = buildUrl({'component': 'student', 'page': 'submission', 'action': 'upload_course_materials_files'});
    var return_url = buildUrl({'component': 'grading', 'page': 'course_materials', 'action': 'view_course_materials_page'});
    var formData = new FormData();

    formData.append('csrf_token', csrf_token);
    formData.append('expand_zip', expand_zip);
    formData.append('requested_path', requested_path);

    var target_path = cmPath; // this one has slash at the end.
    if (requested_path && requested_path.trim().length) {
        target_path = cmPath + requested_path;
    }

    if (target_path[target_path.length-1] == '/')
        target_path = target_path.slice(0, -1); // remove slash

	var filesToBeAdded = false;
    // Files selected
    for (var i = 0; i < file_array.length; i++) {
        for (var j = 0; j < file_array[i].length; j++) {
            if (file_array[i][j].name.indexOf("'") != -1 ||
                file_array[i][j].name.indexOf("\"") != -1) {
                alert("ERROR! You may not use quotes in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("\\") != -1 ||
                file_array[i][j].name.indexOf("/") != -1) {
                alert("ERROR! You may not use a slash in your filename: " + file_array[i][j].name);
                return;
            }
            else if (file_array[i][j].name.indexOf("<") != -1 ||
                file_array[i][j].name.indexOf(">") != -1) {
                alert("ERROR! You may not use angle brackets in your filename: " + file_array[i][j].name);
                return;
            }

            var file = new File([""], target_path + "/" + file_array[i][j].name);
            var k = fileExists(file, 1);
            // Check conflict here
            if ( k[0] == 1 )
            {
                var skip_confirmation = false;
                if (expand_zip == 'on') {
                    var extension = getFileExtension(file_array[i][j].name);
                    if (extension.toLowerCase() == "zip") {
                        skip_confirmation = true; // skip the zip if there is conflict when in expand zip choice.
                    }
                }
                if(!skip_confirmation && !confirm("Note: " + file_array[i][j].name + " already exists. Do you want to replace it?")){
                    continue;
                }
            }

            formData.append('files' + (i + 1) + '[]', file_array[i][j], file_array[i][j].name);
            filesToBeAdded = true;
        }
    }

    if (filesToBeAdded == false){
        return;
    }


    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function(data) {
            try {
                var jsondata = JSON.parse(data);

                if (jsondata['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    alert("ERROR! Please contact administrator with following error:\n\n" + jsondata['message']);
                }
            }
            catch (e) {
                alert("Error parsing response from server. Please copy the contents of your Javascript Console and " +
                    "send it to an administrator, as well as what you were doing and what files you were uploading. - [handleUploadCourseMaterials]");
            }
        },
        error: function(data) {
            window.location.href = buildUrl({'component': 'grading', 'page': 'course_materials', 'action': 'view_course_materials_page'});
        }
    });
}
