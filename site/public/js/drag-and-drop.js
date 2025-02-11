/* exported handleUploadBanner, initializeDropZone, handleEditCourseMaterials, handleUploadCourseMaterials, handleDownloadImages,
            handleSubmission, handleRegrade, handleBulk, deleteSplitItem, submitSplitItem, displayPreviousSubmissionOptions
            displaySubmissionMessage, validateUserId, openFile, handle_input_keypress, addFilesFromInput,
            dropWithMultipleZips, initMaxNoFiles, setUsePrevious, readPrevious, createArray, initializeDragAndDrop */
/* global buildCourseUrl, buildUrl, getFileExtension, csrfToken, removeMessagePopup, newOverwriteCourseMaterialForm, displayErrorMessage */

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
// ========================================================================================
// eslint-disable-next-line no-var
var file_array = []; // contains files uploaded for this submission
// eslint-disable-next-line no-var
var previous_files = []; // contains names of files selected from previous submission
// eslint-disable-next-line no-var
var label_array = [];
// eslint-disable-next-line no-var
var use_previous = false;
// eslint-disable-next-line no-var
var changed = false; // if files from previous submission changed

let total_files_added = 0;
let MAX_NUM_OF_FILES;

// eslint-disable-next-line no-var
var empty_inputs = true;

// eslint-disable-next-line no-unused-vars
let num_clipboard_files = 0;

// eslint-disable-next-line no-unused-vars, no-var
var student_ids = []; // all student ids

function initializeDragAndDrop() {
    file_array = [];
    previous_files = [];
    label_array = [];
    use_previous = false;
    changed = false;
    empty_inputs = true;
    student_ids = [];
    num_clipboard_files = 0;
}

// initializing file_array and previous_files
function createArray(num_parts) {
    if (file_array.length === 0) {
        for (let i = 0; i < num_parts; i++) {
            file_array.push([]);
            previous_files.push([]);
            label_array.push([]);
        }
    }
}

// read in name of previously submitted file
function readPrevious(filename, part) {
    changed = false;
    previous_files[part - 1].push(filename);
}

function setUsePrevious() {
    use_previous = true;
}

// DRAG AND DROP EFFECT
// ========================================================================================
// open a file browser if clicked on drop zone
function clicked_on_box(e) {
    document.getElementById(`input-file${get_part_number(e)}`).click();
    e.stopPropagation();
}

// hover effect
function draghandle(e) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById(`upload${get_part_number(e)}`).style.opacity = (e.type === 'dragenter' || e.type === 'dragover') ? 0.5 : '';
}

// ADD FILES FOR NEW SUBMISSION
// ========================================================================================
// check if adding a file is valid (not exceeding the limit)
function addIsValid(files_to_add, total_added_files) {
    if (files_to_add + total_added_files > MAX_NUM_OF_FILES) {
        alert('Exceeded the max number of files to submit.\nPlease upload your files as a .zip file if it is necessary for you to submit more than this limit.');
        return false;
    }
    return true;
}

// initialize maximum no of files with that of the php_ini value
function initMaxNoFiles(max_no_of_files) {
    MAX_NUM_OF_FILES = max_no_of_files;
}

// add files dragged
function drop(e) {
    draghandle(e);
    const filestream = e.dataTransfer.files;
    if (addIsValid(filestream.length, total_files_added)) {
        const part = get_part_number(e);
        for (let i = 0; i < filestream.length; i++) {
            addFileWithCheck(filestream[i], part); // check for folders
            total_files_added++;
        }
    }
}

// add files dragged
function dropWithMultipleZips(e) {
    draghandle(e);
    const filestream = e.dataTransfer.files;
    if (addIsValid(filestream.length, total_files_added)) {
        const part = get_part_number(e);
        for (let i = 0; i < filestream.length; i++) {
            addFileWithCheck(filestream[i], part, false); // check for folders
            total_files_added++;
        }
    }
}

// show progressbar when uploading files
function progress(e) {
    const progressBar = document.getElementById('loading-bar');

    if (!progressBar) {
        return false;
    }

    if (e.lengthComputable) {
        progressBar.max = e.total;
        progressBar.value = e.loaded;
        const perc = (e.loaded * 100) / e.total;
        $('#loading-bar-percentage').html(`${perc.toFixed(2)} %`);
    }
}

function get_part_number(e) {
    let node = e.target;
    while (node.id.substring(0, 6) !== 'upload') {
        node = node.parentNode;
    }
    return node.id.substring(6);
}

// copy files selected from the file browser
function addFilesFromInput(part, check_duplicate_zip = true) {
    const filestream = document.getElementById(`input-file${part}`).files;
    if (addIsValid(filestream.length, total_files_added)) {
        for (let i = 0; i < filestream.length; i++) {
            addFile(filestream[i], part, check_duplicate_zip); // folders will not be selected in file browser, no need for check
            total_files_added++;
        }
    }
    $(`#input-file${part}`).val('');
}

function handleUploadBanner(closeTime, releaseTime, extraName, linkName) {
    const formData = new FormData();
    formData.append('csrf_token', window.csrfToken);
    formData.append('close_time', closeTime);
    formData.append('release_time', releaseTime);
    formData.append('extra_name', extraName);
    formData.append('link_name', linkName);
    for (let i = 0; i < file_array.length; i++) {
        for (let j = 0; j < file_array[i].length; j++) {
            if (!/^[a-zA-Z0-9_.-]+$/.test(file_array[i][j].name)) {
                alert(`ERROR! Filename "${file_array[i][j].name}" contains invalid characters. Please use only alphanumeric characters, underscores, and dashes.`);
                return;
            }
            const k = fileExists(`/${file_array[i][j].name}`, 1);
            // Check conflict here
            if (k[0] === 1) {
                if (!confirm(`Note: ${file_array[i][j].name} already exists. Do you want to replace it?`)) {
                    continue;
                }
            }
            formData.append(`files${i + 1}[]`, file_array[i][j], file_array[i][j].name);
        }
    }
    $.ajax({
        url: buildUrl(['banner', 'upload']),
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function (data) {
            try {
                const jsondata = JSON.parse(data);

                if (jsondata['status'] === 'success') {
                    window.location.href = buildUrl(['banner']);
                }
                else {
                    alert(jsondata['message']);
                }
            }
            catch (e) {
                alert('Failed to upload a banner!');
                console.log(data);
            }
        },
        error: function () {
            window.location.href = buildUrl(['banner']);
        },
    });
}

// Check for duplicate file names. This function returns an array.
// First element:
// 1 - a file with the same name found in previous submission
// 0 - a file with the same name already selected for this version
// -1 - does not exist files with the same name
// Second element: index of the file with the same name (if found)
function fileExists(filename, part) {
    for (let i = 0; i < previous_files[part - 1].length; i++) {
        if (previous_files[part - 1][i] === filename) {
            return [1, i];
        }
    }

    for (let j = 0; j < file_array[part - 1].length; j++) {
        if (file_array[part - 1][j].name === filename) {
            return [0, j];
        }
    }
    return [-1];
}

// add file with folder check
function addFileWithCheck(file, part, check_duplicate_zip = true) {
    // try to open file if it looks suspicious:
    // no type, or with size of a typical folder size
    if (!file.type || file.size % 4096 === 0) {
        const reader = new FileReader();
        reader.onload = notFolder(file, part);
        reader.onerror = isFolder(file);
        reader.readAsBinaryString(file);
    }
    else {
        addFile(file, part, check_duplicate_zip);
    }
}

// add file if is not a folder
function notFolder(file, part) {
    return function () {
        addFile(file, part);
    };
}

function isFolder(file) {
    return function () {
        alert(`Upload failed: ${file.name} might be a folder.`);
    };
}

function addFile(file, part, check_duplicate_zip = true) {
    const i = fileExists(file.name, part);
    // eslint-disable-next-line eqeqeq
    if (i[0] == -1) { // file does not exist
        // When uploading a zip, we confirm with the user to empty the bucket and then only add the zip
        if (check_duplicate_zip && file.name.substring(file.name.length - 4, file.name.length) === '.zip' && file_array[part - 1].length + previous_files[part - 1].length > 0) {
            if (confirm(`Note: All files currently in the bucket will be deleted if you try to upload a zip: ${file.name}. Do you want to continue?`)) {
                deleteFiles(part);
                file_array[part - 1].push(file);
                addLabel(file.name, (file.size / 1024).toFixed(2), part, false);
            }
        }
        else {
            file_array[part - 1].push(file);
            addLabel(file.name, (file.size / 1024).toFixed(2), part, false);
        }
    }
    // eslint-disable-next-line eqeqeq
    else if (i[0] == 0) { // file already selected
        if (confirm(`Note: ${file_array[part - 1][i[1]].name} is already selected. Do you want to replace it?`)) {
            file_array[part - 1].splice(i[1], 1, file);
            removeLabel(file.name, part);
            addLabel(file.name, (file.size / 1024).toFixed(2), part, false);
        }
    }
    else { // file in previous submission
        if (confirm(`Note: ${previous_files[part - 1][i[1]]} was in your previous submission. Do you want to replace it?`)) {
            file_array[part - 1].push(file);
            previous_files[part - 1].splice(i[1], 1);
            removeLabel(file.name, part);
            addLabel(file.name, (file.size / 1024).toFixed(2), part, false);
            changed = true;
        }
    }

    setButtonStatus();
}

// REMOVE FILES
// ========================================================================================
// delete files selected for a part
function deleteFiles(part) {
    if (file_array.length !== 0) {
        file_array[part - 1] = [];
    }
    if (previous_files.length !== 0) {
        previous_files[part - 1] = [];
    }
    const dropzone = document.getElementById(`file-upload-table-${part}`);
    const labels = dropzone.getElementsByClassName('file-label');
    while (labels[0]) {
        dropzone.removeChild(labels[0]);
        total_files_added--;
    }
    label_array[part - 1] = [];
    changed = true;
    setButtonStatus();
}

function deleteSingleFile(filename, part, previous) {
    // Remove files from previous submission
    if (previous) {
        for (let i = 0; i < previous_files[part - 1].length; i++) {
            if (previous_files[part - 1][i] === filename) {
                previous_files[part - 1].splice(i, 1);
                label_array[part - 1].splice(i, 1);
                changed = true;
                break;
            }
        }
    }
    // Remove files uploaded for submission
    else {
        for (let j = 0; j < file_array[part - 1].length; j++) {
            if (file_array[part - 1][j].name === filename) {
                file_array[part - 1].splice(j, 1);
                label_array[part - 1].splice(j, 1);
                total_files_added--;
                break;
            }
        }
    }
    setButtonStatus();
}

function setButtonStatus(inactive_version = false) {
    // we only want to clear buckets if there's any labels in it (otherwise it's "blank")
    let labels = 0;
    for (let i = 0; i < label_array.length; i++) {
        labels += label_array[i].length;
    }

    if (labels === 0) {
        $('#startnew').prop('disabled', true);
        if (empty_inputs) {
            $('#submit').prop('disabled', true);
        }
        else {
            $('#submit').prop('disabled', false);
        }
    }
    else {
        $('#startnew').prop('disabled', false);
        $('#submit').prop('disabled', false);
    }

    $('.popup-submit').prop('disabled', false);
    if (inactive_version) {
        $('#submit').prop('disabled', true);
    }
    // We only have "non-previous" submissions if there's stuff in the file array as well as if we've
    // toggled the necessary flag that we're on a submission that would have previous (to prevent costly dom
    // lookups for the existence of #getprev id in the page)
    let files = 0;
    for (let j = 0; j < file_array.length; j++) {
        files += file_array[j].length;
    }

    if (use_previous && !changed && files === 0) {
        $('#getprev').prop('disabled', true);
    }
    else if (use_previous) {
        $('#getprev').prop('disabled', false);
    }
}

// LABELS FOR SELECTED FILES
// ========================================================================================
function removeLabel(filename, part) {
    const dropzone = document.getElementById(`file-upload-table-${part}`);
    const labels = dropzone.getElementsByClassName('file-label');

    for (let i = 0; i < labels.length; i++) {
        if (labels[i].getAttribute('fname') === filename) {
            dropzone.removeChild(labels[i]);
            label_array[part - 1].splice(i, 1);
            break;
        }
    }
}

function addLabel(filename, filesize, part, previous) {
    // create element
    const uploadRowElement = document.createElement('tr');
    uploadRowElement.setAttribute('fname', filename);
    uploadRowElement.setAttribute('class', 'file-label');

    const fileDataElement = document.createElement('td');
    const fileTrashElement = document.createElement('td');
    fileTrashElement.setAttribute('class', 'file-trash');

    fileDataElement.innerHTML = filename;
    fileTrashElement.innerHTML = `${filesize}KB  <i aria-label='Press enter to remove file ${filename}' tabindex='0' class='fas fa-trash custom-focus'></i>`;

    uploadRowElement.appendChild(fileDataElement);
    uploadRowElement.appendChild(fileTrashElement);

    // styling
    fileTrashElement.onmouseover = function (e) {
        e.stopPropagation();
        this.style.color = '#FF3933';
    };
    fileTrashElement.onmouseout = function (e) {
        e.stopPropagation();
        this.style.color = 'var(--text-black)';
    };

    // onclick : remove file and label-row in table on click event
    // onkeypress : FOR VPAT if trash can has focus and key is pressed it will delete item
    fileTrashElement.onclick = fileTrashElement.onkeypress = function (e) {
        e.stopPropagation();
        this.parentNode.parentNode.removeChild(this.parentNode);
        deleteSingleFile(filename, part, previous);

        const textArea = document.querySelector(`#reply_box_${part}`);
        if (textArea) {
            // Dispatch input event on existing forum textarea to disable forum reply button on empty input or no remaining files
            textArea.dispatchEvent(new Event('input', { bubbles: false, cancelable: false }));
        }
    };

    // adding the file in `table` in the parent div
    const fileTable = document.getElementById(`file-upload-table-${part}`);
    // Uncomment if want buttons for emptying single bucket
    // var deletebutton = document.getElementById("delete" + part);
    fileTable.appendChild(uploadRowElement);
    // fileTable.insertBefore(tmp, deletebutton);
    label_array[part - 1].push(filename);
}

function handle_input_keypress(inactive_version) {
    empty_inputs = false;
    // eslint-disable-next-line no-undef
    show_popup = true;
    if (!inactive_version) {
        setButtonStatus();
    }
}

// BULK UPLOAD
// ========================================================================================
function openFile(url_full) {
    window.open(url_full, '_blank', 'toolbar=no,scrollbars=yes,resizable=yes, width=700, height=600');
}

// HANDLE SUBMISSION
// ========================================================================================
function isValidSubmission() {
    // check if new files added
    for (let i = 0; i < file_array.length; i++) {
        if (file_array[i].length !== 0) {
            return true;
        }
    }
    // check if files from previous submission changed
    if (changed) {
        // check if previous submission files are emptied
        for (let j = 0; j < previous_files.length; j++) {
            // eslint-disable-next-line eqeqeq
            if (previous_files[j] != 0) {
                return true;
            }
        }
    }

    // If is_notebook is set then always valid submission
    if (Object.prototype.hasOwnProperty.call(window, 'is_notebook')) {
        return true;
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
function validateUserId(csrf_token, gradeable_id, user_id) {
    const url = buildCourseUrl(['gradeable', gradeable_id, 'verify']);
    return new Promise((resolve, reject) => {
        $.ajax({
            url: url,
            data: {
                csrf_token: csrf_token,
                user_id: user_id,
            },
            type: 'POST',
            success: function (response) {
                response = JSON.parse(response);
                if (response['status'] === 'success') {
                    resolve(response);
                }
                else {
                    reject(response);
                }
            },
            error: function (err) {
                console.log(`Error while trying to validate user id${user_id}`);
                reject({ status: 'failed', message: err });
            },
        });
    });
}

// @param json a dictionary {success : true/false, message : string}
// @param index used for id
// function to display pop-up notification after bulk submission/delete
function displaySubmissionMessage(json) {
    // let the id be the date to prevent closing the wrong message
    const d = new Date();
    const t = String(d.getTime());

    const class_str = `class="inner-message alert ${json['status'] === 'success' ? 'alert-success' : 'alert-error'}"`;
    const close_btn = `<a class="fas fa-times message-close" onclick="removeMessagePopup(${t});"></a>`;
    const fa_icon = `<i class="${json['status'] === 'success' ? 'fas fa-check-circle' : 'fas fa-times-circle'}"></i>`;
    const response = (json['status'] === 'success' ? json['data'] : json['message']);

    const message = `<div id="${t}"${class_str}>${fa_icon}${response}${close_btn}</div>`;
    $('#messages').append(message);

    if (json['status'] === 'success') {
        setTimeout(() => {
            removeMessagePopup(t);
        }, 5000);
    }
}

// @param callback to function when user selects an option
// function to display the different options when submiting a split item to a student with previous submissions
function displayPreviousSubmissionOptions(callback) {
    const form = $('#previous-submission-form');
    const submit_btn = form.find('.submit-button');
    const closer_btn = form.find('.close-button');

    let option;
    submit_btn.attr('tabindex', '0');
    closer_btn.attr('tabindex', '0');
    // on click, make submission based on which radio input was checked
    submit_btn.on('click', () => {
        if ($('#instructor-submit-option-new').is(':checked')) {
            localStorage.setItem('instructor-submit-option', '0');
            option = 1;
        }
        else if ($('#instructor-submit-option-merge-1').is(':checked')) {
            localStorage.setItem('instructor-submit-option', '1');
            option = 2;
        }
        else if ($('#instructor-submit-option-merge-2').is(':checked')) {
            localStorage.setItem('instructor-submit-option', '2');
            option = 3;
        }
        form.css('display', 'none');
        callback(option);
    });

    // on close, save the option selected
    closer_btn.on('click', () => {
        if ($('#instructor-submit-option-new').is(':checked')) {
            localStorage.setItem('instructor-submit-option', '0');
        }
        else if ($('#instructor-submit-option-merge-1').is(':checked')) {
            localStorage.setItem('instructor-submit-option', '1');
        }
        else if ($('#instructor-submit-option-merge-2').is(':checked')) {
            localStorage.setItem('instructor-submit-option', '2');
        }
        form.css('display', 'none');
        callback(-1);
    });

    $('.popup-form').css('display', 'none');
    form.css('display', 'block');

    // check the option from whatever option was saved
    let radio_idx;
    if (localStorage.getItem('instructor-submit-option') === null) {
        radio_idx = 0;
    }
    else {
        radio_idx = parseInt(localStorage.getItem('instructor-submit-option'));
    }
    form.find('input:radio')[radio_idx].checked = true;
    // since the modal object isn't rendered on the page manually set what the tab button does
    $('#instructor-submit-option-new').attr('tabindex', '0');
    $('#instructor-submit-option-merge-1').attr('tabindex', '0');
    $('#instructor-submit-option-merge-2').attr('tabindex', '0');
    submit_btn.focus();
    let current_btn = 4;
    if (form.css('display') !== 'none') {
        document.addEventListener('keydown', (e) => {
            if (e.code === 'Tab') {
                // on tab update the focus, cycle through the radio buttons and then
                // the close/submit buttons and then back to the radio buttons
                $('input[name=instructor-submit]').css({ outline: 'none' });
                e.preventDefault();
                if (current_btn === 0) {
                    $('#instructor-submit-option-merge-1').focus();
                    $('#instructor-submit-option-merge-1').css({ outline: '2px solid #C1E0FF' });
                }
                else if (current_btn === 1) {
                    $('#instructor-submit-option-merge-2').focus();
                    $('#instructor-submit-option-merge-2').css({ outline: '2px solid #C1E0FF' });
                }
                else if (current_btn === 2) {
                    closer_btn.focus();
                }
                else if (current_btn === 3) {
                    submit_btn.focus();
                }
                else if (current_btn === 4) {
                    $('#instructor-submit-option-new').focus();
                    $('#instructor-submit-option-new').css({ outline: '2px solid #C1E0FF' });
                }
                current_btn = (current_btn === 4) ? 0 : current_btn + 1;
            }
            else if (e.code === 'Escape') {
                // close the modal box on escape
                closer_btn.click();
            }
            else if (e.code === 'Enter') {
                // on enter update whatever the user is focussing on
                // uncheck everything and then recheck the desired button to make sure it actually updates
                if (current_btn === 1) {
                    $('input[name=instructor-submit]').prop('checked', false);
                    $('#instructor-submit-option-merge-1').prop('checked', true);
                }
                else if (current_btn === 2) {
                    $('input[name=instructor-submit]').prop('checked', false);
                    $('#instructor-submit-option-merge-2').prop('checked', true);
                }
                else if (current_btn === 0) {
                    $('input[name=instructor-submit]').prop('checked', false);
                    $('#instructor-submit-option-new').prop('checked', true);
                }
                else if (current_btn === 3) {
                    // close the modal if the close button is selected
                    closer_btn.click();
                }
                else if (current_btn === 4) {
                    submit_btn.click();
                }
            }
        });
    }
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
function submitSplitItem(csrf_token, gradeable_id, user_id, path, merge_previous = false, clobber = false) {
    const url = `${buildCourseUrl(['gradeable', gradeable_id, 'split_pdf', 'upload'])}?merge=${merge_previous}&clobber=${clobber}`;

    return new Promise((resolve, reject) => {
        $.ajax({
            url: url,
            data: {
                csrf_token: csrf_token,
                user_id: user_id,
                path: path,
            },
            type: 'POST',
            success: function (response) {
                response = JSON.parse(response);
                if (response['status'] === 'success') {
                    resolve(response);
                }
                else {
                    reject(response);
                }
            },
            error: function (err) {
                console.log('Failed while submiting split item');
                reject({ status: 'failed', message: err });
            },
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
    const submit_url = buildCourseUrl(['gradeable', gradeable_id, 'split_pdf', 'delete']);

    return new Promise((resolve, reject) => {
        $.ajax({
            url: submit_url,
            data: {
                csrf_token: csrf_token,
                path: path,
            },
            type: 'POST',
            success: function (response) {
                response = JSON.parse(response);
                if (response['status'] === 'success') {
                    resolve(response);
                }
                else {
                    reject(response);
                }
            },
            error: function (jqXHR, err_msg) {
                console.error('Failed while deleting split item');
                reject({ status: 'failed', message: err_msg });
            },
        });
    });
}

/**
 * Handle sending a bulk pdf to be split by the server
 *
 * @param {String} gradeable_id
 * @param {Number} max_file_size
 * @param {Number} max_post_size
 * @param {Number} num_pages
 * @param {Boolean} use_qr_codes
 * @param {Boolean} use_ocr,
 * @param {String} qr_prefix
 * @param {String} qr_suffix
 */
function handleBulk(gradeable_id, max_file_size, max_post_size, num_pages, use_qr_codes, use_ocr, qr_prefix, qr_suffix) {
    $('#submit').prop('disabled', true);

    const formData = new FormData();

    if (!use_qr_codes) {
        // eslint-disable-next-line eqeqeq
        if (num_pages == '') {
            alert("You didn't enter the # of page(s)!");
            $('#submit').prop('disabled', false);
            return;
        }
        // eslint-disable-next-line eqeqeq
        else if (num_pages < 1 || num_pages % 1 != 0) {
            alert(`${num_pages} is not a valid # of page(s)!`);
            $('#submit').prop('disabled', false);
            return;
        }
    }
    formData.append('num_pages', num_pages);
    formData.append('use_qr_codes', use_qr_codes);
    formData.append('use_ocr', use_ocr && use_qr_codes);
    // encode qr prefix and suffix in case URLs are used
    formData.append('qr_prefix', encodeURIComponent(qr_prefix));
    formData.append('qr_suffix', encodeURIComponent(qr_suffix));
    formData.append('csrf_token', csrfToken);

    let total_size = 0;
    for (let i = 0; i < file_array.length; i++) {
        for (let j = 0; j < file_array[i].length; j++) {
            if (file_array[i][j].name.indexOf("'") !== -1
                || file_array[i][j].name.indexOf('"') !== -1) {
                alert(`ERROR! You may not use quotes in your filename: ${file_array[i][j].name}`);
                $('#submit').prop('disabled', false);
                return;
            }
            else if (file_array[i][j].name.indexOf('\\') !== -1
                || file_array[i][j].name.indexOf('/') !== -1) {
                alert(`ERROR! You may not use a slash in your filename: ${file_array[i][j].name}`);
                $('#submit').prop('disabled', false);
                return;
            }
            else if (file_array[i][j].name.indexOf('<') !== -1
                || file_array[i][j].name.indexOf('>') !== -1) {
                alert(`ERROR! You may not use angle brackets in your filename: ${file_array[i][j].name}`);
                $('#submit').prop('disabled', false);
                return;
            }

            total_size += file_array[i][j].size;

            if (total_size >= max_file_size) {
                alert('ERROR! Uploaded file(s) exceed max file size.\n'
                    + 'Please visit https://submitty.org/sysadmin/system_customization for configuration instructions.');
                $('#submit').prop('disabled', false);
                return;
            }

            if (total_size >= max_post_size) {
                alert('ERROR! Uploaded file(s) exceed max PHP POST size.\n'
                    + 'Please visit https://submitty.org/sysadmin/system_customization for configuration instructions.');
                $('#submit').prop('disabled', false);
                return;
            }

            formData.append(`files${i + 1}[]`, file_array[i][j], file_array[i][j].name);
        }
    }

    const url = buildCourseUrl(['gradeable', gradeable_id, 'bulk']);
    const return_url = buildCourseUrl(['gradeable', gradeable_id]);

    $.ajax({
        url: url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function (data) {
            $('#submit').prop('disabled', false);
            try {
                data = JSON.parse(data);
                if (data['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    if (data['message'] === 'You do not have access to that page.') {
                        window.location.href = return_url;
                    }
                    else {
                        displayErrorMessage(`ERROR! ${data['message']}`);
                    }
                }
            }
            catch (e) {
                alert('Error parsing response from server. Please copy the contents of your Javascript Console and '
                    + 'send it to an administrator, as well as what you were doing and what files you were uploading.');
                console.log(data);
            }
        },
        error: function () {
            $('#submit').prop('disabled', false);
            alert('ERROR! Please contact administrator that you could not upload files.');
        },
    });
}

/**
 * @param type
 */
function gatherInputAnswersByType(type) {
    const input_answers = {};

    // If type is codebox only grab 'div' but not buttons with similar ids
    const inputs = type === 'codebox' ? $(`div[id^=${type}_]`) : $(`[id^=${type}_]`).serializeArray();

    for (let i = 0; i < inputs.length; i++) {
        const this_input_answer = inputs[i];
        let key = '';
        let value = '';
        if (type === 'codebox') {
            key = this_input_answer.id;
            const editor = this_input_answer.querySelector('.CodeMirror').CodeMirror;
            value = editor.getValue();
        }
        else {
            key = this_input_answer.name;
            value = this_input_answer.value;
        }

        if (!(key in input_answers)) {
            input_answers[key] = Array();
        }
        input_answers[key].push(value);
    }
    return input_answers;
}

/**
 * @param versions_used
 * @param versions_allowed
 * @param csrf_token
 * @param gradeable_id
 * @param user_id
 * @param regrade
 * @param regrade_all
 * @param submissions
 * @param regrade_all_students
 * @param regrade_all_students_all
 * differences between regrade, regrade_all, regrade_all_students and regrade_all_students_all
 * regrade - regrade the active version for one selected student who submitted a certain gradeable
 * regrade_all - regrade every version for one selected student who submitted a certain gradeable
 * regrade_all_students - regrade the active version for every student who submitted a certain gradeable
 * regrade_all_students_all regrade every version for every student who submitted a certain gradeable
 */
function handleRegrade(versions_used, csrf_token, gradeable_id, user_id, regrade = false, regrade_all = false, regrade_all_students = false, regrade_all_students_all = false) {
    const submit_url = buildCourseUrl(['gradeable', gradeable_id, 'regrade']);
    const formData = new FormData();
    formData.append('csrf_token', csrf_token);
    formData.append('user_id', user_id);
    formData.append('regrade', regrade);
    formData.append('regrade_all', regrade_all);
    formData.append('version_to_regrade', versions_used);
    formData.append('regrade_all_students', regrade_all_students);
    formData.append('regrade_all_students_all', regrade_all_students_all);
    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        headers: {
            Accept: 'application/json',
        },
        contentType: false,
        type: 'POST',
        success: function (data) {
            try {
                data = JSON.parse(data);
                if (data['status'] === 'success') {
                    window.location.reload();
                }
                else {
                    alert(`ERROR! Please contact administrator with following error:\n\n${data['message']}`);
                }
            }
            catch (e) {
                alert('Error parsing response from server. Please copy the contents of your Javascript Console and '
                    + 'send it to an administrator, as well as what you were doing and what files you were uploading.');
                console.log(data);
            }
        },
        error: function () {
            $('#submit').prop('disabled', false);
            alert('ERROR! Please contact administrator that you could not regrade files.');
        },
    });
}

/**
 * @param remaining_late_days_for_gradeable
 * @param charged_late_days
 * @param days_past_deadline
 * @param late_day_exceptions
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
function handleSubmission(gradeable_status, remaining_late_days_for_gradeable, charged_late_days, days_past_deadline, late_day_exceptions, late_days_allowed, is_team_assignment, min_team_member_late_days, min_team_member_late_days_exception, versions_used, versions_allowed, csrf_token, vcs_checkout, num_inputs, gradeable_id, user_id, git_user_id, git_repo_id, student_page, num_components, merge_previous = false, clobber = false, viewing_inactive_version = false) {
    $('#submit').prop('disabled', true);
    const submit_url = `${buildCourseUrl(['gradeable', gradeable_id, 'upload'])}?merge=${merge_previous.toString()}&clobber=${clobber.toString()}`;
    const return_url = buildCourseUrl(['gradeable', gradeable_id]);
    let message = '';
    // check versions used
    if (versions_used >= versions_allowed) {
        message = `You have already made ${versions_used} submissions.  You are allowed ${versions_allowed} submissions before a small point penalty will be applied. Are you sure you want to continue?`;
        if (!confirm(message)) {
            $('#submit').prop('disabled', false);
            return;
        }
    }

    let late_warning_seen = false;

    const days_to_be_charged = Math.max(0, days_past_deadline - late_day_exceptions);
    // gradeable_status == 3 is a bad submission (too many late days used) and therefore no need to show a warning message anymore

    if (days_past_deadline > 0 && gradeable_status !== 3) {
        /* days_to_be_charged !== charged_late_days will make sure that both messages won't appear multiple times if it already appeared once and the user made a submission */

        if (days_to_be_charged <= late_days_allowed && remaining_late_days_for_gradeable > 0 && days_to_be_charged !== charged_late_days && days_to_be_charged > 0) {
            message = `Your submission will be ${days_past_deadline} day(s) late. Are you sure you want to use ${days_to_be_charged} late day(s)?`;
            if (!confirm(message)) {
                $('#submit').prop('disabled', false);
                return;
            }
        }
        else if ((days_to_be_charged > late_days_allowed || remaining_late_days_for_gradeable === 0) && days_to_be_charged !== charged_late_days && days_to_be_charged > 0) {
            late_warning_seen = true;
            message = `Your submission will be ${days_past_deadline} day(s) late. You are not supposed to submit unless you have an excused absence. Are you sure you want to continue?`;
            if (!confirm(message)) {
                $('#submit').prop('disabled', false);
                return;
            }
        }

        // check team date
        if (!late_warning_seen && is_team_assignment && (min_team_member_late_days - days_to_be_charged + charged_late_days < 0 || min_team_member_late_days_exception + days_to_be_charged < days_past_deadline)) {
            message = 'There is at least 1 member on your team that does not have enough late days for this submission. This will result in them receiving a marked grade of zero. Are you sure you want to continue?';
            if (!confirm(message)) {
                return;
            }
        }
    }

    const formData = new FormData();

    formData.append('csrf_token', csrf_token);
    formData.append('vcs_checkout', vcs_checkout);
    formData.append('user_id', user_id);
    formData.append('git_user_id', git_user_id);
    formData.append('git_repo_id', git_repo_id);
    formData.append('student_page', student_page);
    formData.append('viewing_inactive_version', viewing_inactive_version);

    let filesize = 0;

    if (!vcs_checkout) {
        // Check if new submission
        if (!isValidSubmission() && empty_inputs) {
            alert('Not a new submission.');
            window.location.reload();
            return;
        }

        // Files selected
        for (let i = 0; i < file_array.length; i++) {
            for (let j = 0; j < file_array[i].length; j++) {
                if (file_array[i][j].name.indexOf("'") !== -1
                    || file_array[i][j].name.indexOf('"') !== -1) {
                    alert(`ERROR! You may not use quotes in your filename: ${file_array[i][j].name}`);
                    return;
                }
                else if (file_array[i][j].name.indexOf('\\') !== -1
                    || file_array[i][j].name.indexOf('/') !== -1) {
                    alert(`ERROR! You may not use a slash in your filename: ${file_array[i][j].name}`);
                    return;
                }
                else if (file_array[i][j].name.indexOf('<') !== -1
                    || file_array[i][j].name.indexOf('>') !== -1) {
                    alert(`ERROR! You may not use angle brackets in your filename: ${file_array[i][j].name}`);
                    return;
                }

                filesize += file_array[i][j].size;
                formData.append(`files${i + 1}[]`, file_array[i][j], file_array[i][j].name);
            }
        }
        // Files from previous submission
        formData.append('previous_files', JSON.stringify(previous_files));
    }

    // check if filesize greater than 1,25 MB, then turn on the progressbar
    if (filesize > 1250000) {
        $('.loading-bar-wrapper').fadeIn(100);
    }

    const multiple_choice_object = gatherInputAnswersByType('multiple_choice');
    const codebox_object = gatherInputAnswersByType('codebox');
    formData.append('multiple_choice_answers', JSON.stringify(multiple_choice_object));
    formData.append('codebox_answers', JSON.stringify(codebox_object));

    if (student_page) {
        const pages = [];
        for (let i = 0; i < num_components; i++) {
            pages[i] = $(`#page_${i}`).val();
            // eslint-disable-next-line eqeqeq
            if (pages[i] == '') {
                alert('You cannot leave a page input empty.');
                $('#submit').prop('disabled', false);
                return;
            }
            if (parseInt(pages[i]) < 1) {
                alert('Page numbers cannot be less than 1.');
                $('#submit').prop('disabled', false);
                return;
            }
        }
        formData.append('pages', JSON.stringify(pages));
    }

    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        xhr: function () {
            const myXhr = $.ajaxSettings.xhr();
            if (myXhr.upload) {
                myXhr.upload.addEventListener('progress', progress, false);
            }
            return myXhr;
        },
        headers: {
            Accept: 'application/json',
        },
        contentType: false,
        type: 'POST',
        success: function (data) {
            $('#submit').prop('disabled', false);
            try {
                data = JSON.parse(data);
                if (data['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    if (data['message'] === 'You do not have access to that page.') {
                        window.location.href = return_url;
                    }
                    // eslint-disable-next-line valid-typeof
                    else if (typeof data['code'] !== undefined && data['code'] === 302) {
                        window.location.href = data['data'];
                    }
                    else {
                        alert(`ERROR! Please contact administrator with following error:\n\n${data['message']}`);
                    }
                }
            }
            catch (e) {
                alert('Error parsing response from server. Please copy the contents of your Javascript Console and '
                    + 'send it to an administrator, as well as what you were doing and what files you were uploading.');
                console.log(data);
            }
        },
        error: function () {
            $('#submit').prop('disabled', false);
            alert('ERROR! Please contact administrator that you could not upload files.');
        },
    });
}

/**
 * @param csrf_token
 */
function handleDownloadImages(csrf_token) {
    const image_submit_url = buildCourseUrl(['student_photos', 'upload']);
    const return_url = buildCourseUrl(['student_photos']);
    const formData = new FormData();
    formData.append('csrf_token', csrf_token);
    formData.append('file_count', file_array[0].length);

    // Files selected
    for (let i = 0; i < file_array.length; i++) {
        for (let j = 0; j < file_array[i].length; j++) {
            if (file_array[i][j].name.indexOf("'") !== -1
                || file_array[i][j].name.indexOf('"') !== -1) {
                alert(`ERROR! You may not use quotes in your filename: ${file_array[i][j].name}`);
                return;
            }
            else if (file_array[i][j].name.indexOf('\\') !== -1
                || file_array[i][j].name.indexOf('/') !== -1) {
                alert(`ERROR! You may not use a slash in your filename: ${file_array[i][j].name}`);
                return;
            }
            else if (file_array[i][j].name.indexOf('<') !== -1
                || file_array[i][j].name.indexOf('>') !== -1) {
                alert(`ERROR! You may not use angle brackets in your filename: ${file_array[i][j].name}`);
                return;
            }
            formData.append(`files${i + 1}[]`, file_array[i][j], file_array[i][j].name);
        }
    }

    $.ajax({
        url: image_submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function (data) {
            try {
                data = JSON.parse(data);

                if (data['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    alert(`ERROR! Please contact administrator with following error:\n\n${data['message']}`);
                }
            }
            catch (e) {
                alert('Error parsing response from server. Please copy the contents of your Javascript Console and '
                    + 'send it to an administrator, as well as what you were doing and what files you were uploading.');
                console.log(data);
            }
        },
        error: function () {
            window.location.href = buildCourseUrl(['student_photos']);
        },
    });
}

/**
 * @param csrf_token
 */

function handleUploadCourseMaterials(csrf_token, expand_zip, hide_from_students, cmPath, requested_path, cmTime, sortPriority, sections, sections_lock, overwrite_all, calenderMenu, gradeableInputValue) {
    const submit_url = buildCourseUrl(['course_materials', 'upload']);
    const return_url = buildCourseUrl(['course_materials']);
    const formData = new FormData();
    const priority = parseFloat(sortPriority);

    if (priority < 0 || isNaN(priority)) {
        alert('Floating point priority must be a number greater than 0.');
        return;
    }

    formData.append('calenderMenu', calenderMenu);
    formData.append('gradeableInputValue', gradeableInputValue);
    formData.append('csrf_token', csrf_token);
    formData.append('expand_zip', expand_zip);
    formData.append('hide_from_students', hide_from_students);
    formData.append('requested_path', requested_path);
    formData.append('release_time', cmTime);
    formData.append('sort_priority', priority);
    formData.append('sections_lock', sections_lock);

    if (sections !== null) {
        formData.append('sections', sections);
    }

    if (overwrite_all !== null) {
        formData.append('overwrite_all', overwrite_all);
    }
    let target_path = cmPath; // this one has slash at the end.

    if (requested_path && requested_path.trim().length) {
        target_path = cmPath + requested_path;
    }

    if (target_path[target_path.length - 1] === '/') {
        target_path = target_path.slice(0, -1);
    } // remove slash

    let filesToBeAdded = false;

    if ($('#file_selection').is(':visible')) {
        // Files selected
        for (let i = 0; i < file_array.length; i++) {
            for (let j = 0; j < file_array[i].length; j++) {
                if (file_array[i][j].name.indexOf("'") !== -1
                    || file_array[i][j].name.indexOf('"') !== -1) {
                    alert(`ERROR! You may not use quotes in your filename: ${file_array[i][j].name}`);
                    return;
                }
                else if (file_array[i][j].name.indexOf('\\') !== -1
                    || file_array[i][j].name.indexOf('/') !== -1) {
                    alert(`ERROR! You may not use a slash in your filename: ${file_array[i][j].name}`);
                    return;
                }
                else if (file_array[i][j].name.indexOf('<') !== -1
                    || file_array[i][j].name.indexOf('>') !== -1) {
                    alert(`ERROR! You may not use angle brackets in your filename: ${file_array[i][j].name}`);
                    return;
                }

                const k = fileExists(`${target_path}/${file_array[i][j].name}`, 1);
                // Check conflict here
                if (k[0] === 1) {
                    let skip_confirmation = false;
                    if (expand_zip === 'on') {
                        const extension = getFileExtension(file_array[i][j].name);
                        if (extension.toLowerCase() === 'zip') {
                            skip_confirmation = true; // skip the zip if there is conflict when in expand zip choice.
                        }
                    }
                    if (!skip_confirmation && !confirm(`Note: ${file_array[i][j].name} already exists. Do you want to replace it?`)) {
                        continue;
                    }
                }

                formData.append(`files${i + 1}[]`, file_array[i][j], file_array[i][j].name);
                filesToBeAdded = true;
            }
        }
    }

    let linkToBeAdded = false;

    if ($('#url_selection').is(':visible')) {
        if ($('#title').val() !== '' && $('#url_url').val() !== '' && window.isValidFileName($('#title').val())) {
            linkToBeAdded = true;

            let title = $('#title').val();
            formData.append('original_title', title);
            title = encodeURIComponent(`link-${title}`);

            formData.append('title', title);
            formData.append('url_url', $('#url_url').val());
        }
    }

    if (filesToBeAdded === false && linkToBeAdded === false) {
        alert('You must add a file or specify link AND title!');
        return;
    }
    $.ajax({
        url: submit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function (data) {
            try {
                const jsondata = JSON.parse(data);

                if (jsondata['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    if (jsondata['message'].indexOf('Name clash') !== -1) {
                        newOverwriteCourseMaterialForm(jsondata['data'], linkToBeAdded, false);
                    }
                    else {
                        alert(jsondata['message']);
                    }
                }
            }
            catch (e) {
                alert('Error parsing response from server. Please copy the contents of your Javascript Console and '
                    + 'send it to an administrator, as well as what you were doing and what files you were uploading. - [handleUploadCourseMaterials]');
                console.log(data);
            }
        },
        error: function () {
            window.location.href = buildCourseUrl(['course_materials']);
        },
    });
}

/**
 * @param csrf_token
 */
function handleEditCourseMaterials(csrf_token, hide_from_students, id, sectionsEdit, partialSections, cmTime, sortPriority, sections_lock, folderUpdate, link_url, title, overwrite, file_path) {
    const edit_url = buildCourseUrl(['course_materials', 'edit']);
    const return_url = buildCourseUrl(['course_materials']);
    const formData = new FormData();
    const priority = parseFloat(sortPriority);

    if (priority < 0 || isNaN(priority)) {
        alert('Floating point priority must be a number greater than 0.');
        return;
    }

    let numSections = 0;
    if (sections_lock === true) {
        numSections = sectionsEdit.length;
        if (partialSections !== null) {
            numSections += partialSections.length;
            formData.append('partial_sections', partialSections);
        }
    }

    if (sections_lock === true && numSections === 0) {
        alert("Restrict to at least one section or select 'No' button where asked about whether you want to restrict this material/folder to some sections.");
        return;
    }

    formData.append('csrf_token', csrf_token);
    formData.append('id', id);
    formData.append('release_time', cmTime);
    formData.append('sort_priority', priority);
    formData.append('sections_lock', sections_lock);

    if (hide_from_students !== null) {
        formData.append('hide_from_students', hide_from_students);
    }
    if (link_url !== null) {
        formData.append('link_url', link_url);
    }

    if (file_path !== null && file_path !== '') {
        const file_name = file_path.split('/').pop();
        if (link_url !== null) {
            const lastSlashIndex = file_path.lastIndexOf('/');
            const new_file_name = encodeURIComponent(`link-${file_path.substring(lastSlashIndex + 1)}`);
            file_path = `${file_path.substring(0, lastSlashIndex + 1)}${new_file_name}`;
        }
        if (window.isValidFileName(file_name)) {
            formData.append('file_path', file_path);
        }
    }

    if (title !== null && window.isValidFileName(title)) {
        formData.append('original_title', title);
        if (link_url !== null) {
            title = encodeURIComponent(`link-${title}`);
        }
        formData.append('title', title);
    }

    if (overwrite !== null) {
        formData.append('overwrite', overwrite);
    }
    if (folderUpdate !== null) {
        formData.append('folder_update', folderUpdate);
    }

    if (sectionsEdit !== null) {
        formData.append('sections', sectionsEdit);
    }

    $.ajax({
        url: edit_url,
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function (data) {
            try {
                const jsondata = JSON.parse(data);

                if (jsondata['status'] === 'success') {
                    window.location.href = return_url;
                }
                else {
                    if (link_url !== null && jsondata['message'].indexOf('Name clash') !== -1) {
                        newOverwriteCourseMaterialForm(jsondata['data'], true, true);
                    }
                    else if (jsondata['message'].indexOf('Name clash') !== -1) {
                        newOverwriteCourseMaterialForm(jsondata['data'], false, true);
                    }
                    else {
                        alert(jsondata['message']);
                    }
                }
            }
            catch (e) {
                alert('Error parsing response from server. Please copy the contents of your Javascript Console and '
                    + 'send it to an administrator, as well as what you were doing and what files you were editing. - [handleEditCourseMaterials]');
                console.log(data);
            }
        },
        error: function () {
            window.location.href = buildCourseUrl(['course_materials']);
        },
    });
}

function initializeDropZone(id) {
    const dropzone = document.getElementById(id);
    dropzone.addEventListener('click', clicked_on_box, false);
    dropzone.addEventListener('dragenter', draghandle, false);
    dropzone.addEventListener('dragover', draghandle, false);
    dropzone.addEventListener('dragleave', draghandle, false);
    dropzone.addEventListener('drop', drop, false);
}
