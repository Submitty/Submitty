/* global csrfToken, buildCourseUrl, NONUPLOADED_CONFIG_VALUES, displayErrorMessage, displaySuccessMessage, gradeable_max_autograder_points,
          is_electronic, onHasReleaseDate, reloadInstructorEditRubric, getItempoolOptions,
          isItempoolAvailable, getGradeableId, closeAllComponents, onHasDueDate, setPdfPageAssignment,
          PDF_PAGE_INSTRUCTOR, PDF_PAGE_STUDENT, PDF_PAGE_NONE, displayWarningMessage, Twig, loadTemplates, CodeMirror,
          updateErrorMessage, clearError, setGradeableUpdateInProgress, setGradeableUpdateComplete, ajaxUpdateGradeableProperty, 
          updateGradeableErrorCallback, ajaxCheckBuildStatus, saveGraders, saveRubric, setRandomGraders, 
          showBuildLog, hideBuildLog, cancelGradeableConfigEdit, loadGradeableEditor, reloadCodeMirror, 
          updateEditorIcons, ajaxGetBuildLogs */

/* exported showBuildLog, ajaxRebuildGradeableButton, onPrecisionChange, onItemPoolOptionChange, updatePdfPageSettings,
          loadGradeableEditor, saveGradeableConfigEdit */

function updatePdfPageSettings() {
    const pdf_page = $('#yes_pdf_page').is(':checked');
    const pdf_page_student = $('#yes_pdf_page_student').is(':checked');
    if (pdf_page === false) {
        $('#no_pdf_page_student').prop('checked', true);
    }
    setPdfPageAssignment(pdf_page === false ? PDF_PAGE_NONE : (pdf_page_student === true ? PDF_PAGE_STUDENT : PDF_PAGE_INSTRUCTOR))
        .catch((err) => {
            alert(`Failed to update pdf page setting! ${err.message}`);
        });
}

async function updateRedactionsDisplay(redactions = null) {
    if (!redactions) {
        const response = await $.get({
            type: 'GET',
            url: buildCourseUrl(['gradeable', $('#g_id').val(), 'redactions']),
            dataType: 'json',
        });
        if (response.status === 'success') {
            redactions = response.data;
        }
        else {
            console.error('Error fetching redactions:', response.message);
            return;
        }
    }
    $('#redactions_container').html(Twig.twig({ ref: 'Redactions' }).render({ redactions: redactions }));
}

async function updateRedactionSettings() {
    const files = $('#redactions_json').prop('files');
    if (files.length === 0) {
        return;
    }
    const file = files[0];
    let data = await file.text();
    try {
        data = JSON.parse(data);
    }
    catch (e) {
        updateErrorMessage();
        alert('Error saving redactions, please check the format of the JSON file.');
        $('#redactions_json').val('');
        return;
    }
    const response = await $.getJSON({
        type: 'POST',
        url: buildCourseUrl(['gradeable', getGradeableId(), 'redactions']),
        data: {
            redactions: data,
            csrf_token: csrfToken,
        },
    });
    if (response.status === 'success') {
        updateErrorMessage();
        $('#remove_redactions').show();
        updateRedactionsDisplay(response.data);
    }
    else {
        updateErrorMessage();
        $('#redactions_json').val('');
        alert(response.message || 'Error saving redactions, please try again.');
    }
}

async function removeRedactions() {
    const response = await $.getJSON({
        type: 'POST',
        url: buildCourseUrl(['gradeable', getGradeableId(), 'redactions']),
        data: {
            redactions: 'none',
            csrf_token: csrfToken,
        },
    });
    if (response.status === 'success') {
        updateErrorMessage();
        $('#remove_redactions').hide();
        $('#redactions_json').val('');
        updateRedactionsDisplay([]);
    }
    else {
        updateErrorMessage();
        alert('Error removing redactions, please try again.');
    }
}

function updateDueDate() {
    const cont = $('#due_date_container');
    const cont1 = $('#late_days_options_container');
    const cont2 = $('#manual_grading_container');
    const cont3 = $('#release_container');
    if ($('#has_due_date_no').is(':checked')) {
        cont.hide();
        cont1.hide();
        cont2.hide();
        cont3.hide();
        $('#has_release_date_no').prop('checked', true);
    }
    else {
        cont.show();
        cont1.show();
        cont2.show();
        cont3.show();
    }
    onHasDueDate();
}

function updateReleaseDate() {
    const cont = $('#release_date_container');
    if ($('#has_release_date_no').is(':checked')) {
        cont.hide();
    }
    else {
        cont.show();
    }
    onHasReleaseDate();
}

function checkWarningBanners() {
    $('#gradeable-dates-warnings-banner').hide();
    if ($('#yes_grade_inquiry_allowed').is(':checked')) {
        const grade_inquiry_start_date = $('#date_grade_inquiry_start').val();
        const grade_inquiry_due_date = $('#date_grade_inquiry_due').val();

        if (grade_inquiry_start_date > grade_inquiry_due_date) {
            $('#grade-inquiry-dates-warning').show();
            $('#gradeable-dates-warnings-banner').show();
        }
        else {
            $('#grade-inquiry-dates-warning').hide();
        }
    }

    if ($('#yes_grade_inquiry_allowed').is(':checked') && $('#has_release_date_yes').is(':checked')) {
        const release_date = $('#date_released').val();
        const grade_inquiry_due_date = $('#date_grade_inquiry_due').val();
        if (release_date > grade_inquiry_due_date) {
            $('#no-grade-inquiry-warning').show();
            $('#gradeable-dates-warnings-banner').show();
        }
        else {
            $('#no-grade-inquiry-warning').hide();
        }
    }
}

$(document).ready(() => {
    window.onbeforeunload = function (event) {
        if (typeof errors !== 'undefined' && Object.keys(errors).length !== 0) {
            event.returnValue = 1;
        }
    };
    if (is_electronic) {
        loadTemplates().then(() => updateRedactionsDisplay());
    }

    ajaxCheckBuildStatus();
    checkWarningBanners();
    $('input:not(#random-peer-graders-list,#number_to_peer_grade),select,textarea').change(function () {
        if ($(this).hasClass('date-radio') && is_electronic) {
            updateDueDate();
        }
        if ($(this).hasClass('date-radio')) {
            updateReleaseDate();
        }
        if ($(this).hasClass('ignore')) {
            return;
        }

        const data = { csrf_token: csrfToken };
        if (this.name === 'hidden_files') {
            data[this.name] = $(this).val().replace(/\s*,\s*/, ',');
        }
        else {
            data[this.name] = $(this).val();
        }

        $('input[name="peer_panel"]').each(function () {
            data[$(this).attr('id')] = $(this).is(':checked');
        });

        const score_notifications_sent = Number(document.querySelector('#container-rubric').dataset.score_notifications_sent);
        const addDataToRequest = function (i, val) {
            if (val.type === 'radio' && !$(val).is(':checked')) {
                return;
            }
            if ($('#no_late_submission').is(':checked') && $(val).attr('name') === 'late_days') {
                $(val).val('0');
            }
            if (score_notifications_sent > 0 && val.name === 'grade_released_date') {
                const updating = new Date($(val).val());
                const original = new Date($(val).attr('data-original'));

                if (original !== updating && updating >= new Date()) {
                    const resend = confirm(
                        'Students have been notified and emailed that grades for this gradeable have been released. '
                        + 'If you change the grades release date, would you like to resend notifications and emails to '
                        + 'students when the new grades release date is reached?',
                    );

                    data['score_notifications_sent'] = resend ? 0 : score_notifications_sent;
                }
            }
            data[val.name] = $(val).val();
        };

        if ($('#gradeable_rubric').find(`[name="${this.name}"]`).length > 0) {
            if (!$('#radio_electronic_file').is(':checked')) {
                saveRubric(false);
            }
            return;
        }
        if ($('#grader_assignment').find(`[name="${this.name}"]`).length > 0) {
            saveGraders();
            return;
        }
        if ($(this).prop('id') === 'minimum_grading_group_autograding' || $(this).prop('id') === 'minimum_grading_group') {
            $('#minimum_grading_group').val($(this).val());
            $('#minimum_grading_group_autograding').val($(this).val());
            saveGraders();
        }
        if ($(this).prop('id') === 'all_access') {
            saveGraders();
        }

        if ($('#gradeable-dates').find(`input[name="${this.name}"]:enabled`).length > 0
            || $(this).hasClass('date-related')) {
            $('#gradeable-dates :input:enabled,.date-related').each(addDataToRequest);
        }

        delete data.peer_panel;
        ajaxUpdateGradeableProperty($('#g_id').val(), data,
            (response_data) => {
                for (const key in response_data) {
                    if (Object.prototype.hasOwnProperty.call(response_data, key)) {
                        clearError(key, response_data[key]);
                    }
                }
                for (const key in data) {
                    if (Object.prototype.hasOwnProperty.call(data, key)) {
                        clearError(key);
                    }
                    if (key === 'grade_released_date' && data['score_notifications_sent'] === 0) {
                        document.getElementById('gradeable-notifications-message').remove();
                        document.querySelector('#container-rubric').dataset.score_notifications_sent = '0';
                    }
                }
                updateErrorMessage();
                checkWarningBanners();
                if (this.id === 'autograding_config_selector' && response_data[0] === 'rebuild_queued') {
                    location.reload();
                }
            }, updateGradeableErrorCallback);
    });

    $('#random_peer_graders_list, #clear_peer_matrix').click(
        function () {
            if ($('input[name="all_grade"]:checked').val() === 'All Grade All') {
                if (confirm('Each student grades every other student! Continue?')) {
                    const data = { csrf_token: csrfToken };
                    data[this.name] = $(this).val();
                    setRandomGraders($('#g_id').val(), data, (response_data) => {
                        for (const key in response_data) {
                            if (Object.prototype.hasOwnProperty.call(response_data, key)) {
                                clearError(key, response_data[key]);
                            }
                        }
                        for (const key in data) {
                            if (Object.prototype.hasOwnProperty.call(data, key)) {
                                clearError(key);
                            }
                        }
                        updateErrorMessage();
                    }, updateGradeableErrorCallback, true);
                    return;
                }
            }
            if (confirm('This will update peer matrix. Are you sure?')) {
                const data = { csrf_token: csrfToken };
                data[this.name] = $(this).val();
                setRandomGraders($('#g_id').val(), data, (response_data) => {
                    for (const key in response_data) {
                        if (Object.prototype.hasOwnProperty.call(response_data, key)) {
                            clearError(key, response_data[key]);
                        }
                    }
                    for (const key in data) {
                        if (Object.prototype.hasOwnProperty.call(data, key)) {
                            clearError(key);
                        }
                    }
                    updateErrorMessage();
                }, updateGradeableErrorCallback, false);
            }
            else {
                return false;
            }
        });
});

let originalConfigContent = null;
let codeMirrorInstance = null;
let current_g_id = null;
let current_file_path = null;
let isConfigEdited = false;

window.addEventListener('beforeunload', (event) => {
    if (isConfigEdited) {
        event.preventDefault();
    }
});

function scrollToBottom() {
    window.scrollTo({ top: 820, left: 0, behavior: 'smooth' });
}

function updateGradeableEditor(g_id, file_path) {
    if ((current_g_id !== g_id || current_file_path !== file_path)) {
        $('#gradeable-config-edit').data('edited', false);
        if (typeof codeMirrorInstance !== 'undefined' && codeMirrorInstance) {
            codeMirrorInstance.toTextArea();
            codeMirrorInstance = null;
        }
        current_g_id = g_id;
        current_file_path = file_path;
        loadGradeableEditor(g_id, file_path);
    }
    else {
        document.querySelectorAll('.key_to_click').forEach((link) => {
            link.classList.remove('selected');
        });
        cancelGradeableConfigEdit();
    }
}

function loadGradeableEditor(g_id, file_path) {
    $.ajax({
        url: buildCourseUrl(['gradeable', 'edit', 'load']),
        type: 'POST',
        data: {
            gradeable_id: g_id,
            file_path: file_path,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                const json = JSON.parse(data);
                if (json.status === 'fail') {
                    displayErrorMessage(json['message']);
                    return;
                }
                if (!$('#gradeable-config-edit-bar').is(':visible')) {
                    $('#gradeable-config-edit-bar').show();
                }
                const configData = json['data'];
                originalConfigContent = configData.config_content;
                const editbox = $('textarea#gradeable-config-edit');
                editbox.val(originalConfigContent);
                editbox.off('input').on('input', function () {
                    const current = $(this).val();
                    $(this).data('edited', current !== originalConfigContent);
                });
                editbox.css({ 'min-width': '-webkit-fill-available' });
                editbox.data('edited', false);
                editbox.data('file-path', file_path);
                loadCodeMirror();
            }
            catch {
                displayErrorMessage('Error parsing data. File type not supported in the editor.');
            }
        },
    });
}

function isUsingDefaultConfig() {
    const selector = document.getElementById('autograding_config_selector');
    const selectedPath = selector.value;
    return NONUPLOADED_CONFIG_VALUES.includes(selectedPath);
}

function updateEditorButtonStyle() {
    const availableMessage = document.getElementById('editor-not-available');
    const editorButton = document.getElementById('open-config-editor');
    if (!editorButton) { return; }
    if (isUsingDefaultConfig()) {
        editorButton.style.display = 'none';
        availableMessage.style.display = 'block';
    }
    else {
        editorButton.style.display = 'inline-block';
        availableMessage.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateEditorButtonStyle();
});

function toggleFolder(id) {
    const div = document.getElementById(id);
    const icon = document.getElementById(`${id}-icon`);
    if (!div) { return; }
    if (div.style.display === 'none') {
        div.style.display = 'block';
        icon.classList.remove('fa-folder');
        icon.classList.add('fa-folder-open');
    }
    else {
        div.style.display = 'none';
        icon.classList.remove('fa-folder-open');
        icon.classList.add('fa-folder');
    }
}

function toggleGradeableConfigEdit() {
    $('#gradeable-config-structure').toggleClass('open').toggle();
    const editorButton = document.getElementById('open-config-editor');
    if (editorButton.innerText === 'Open Editor') {
        editorButton.innerText = 'Close Editor';
        current_g_id = null;
        current_file_path = null;
        scrollToBottom();
    }
    else {
        editorButton.innerText = 'Open Editor';
        cancelGradeableConfigEdit();
    }
}

function cancelGradeableConfigEdit() {
    $('#gradeable-config-edit-bar').hide();
    $('#gradeable-config-edit').data('edited', false);
    isConfigEdited = false;
    current_g_id = null;
    current_file_path = null;
    document.querySelectorAll('.key_to_click').forEach((link) => {
        link.classList.remove('selected');
    });
    if (typeof codeMirrorInstance !== 'undefined' && codeMirrorInstance) {
        codeMirrorInstance.toTextArea();
        codeMirrorInstance = null;
    }
}

function addRootFolder(g_id) {
    const folderName = prompt('Enter a name for the new folder:');
    if (!folderName) { return; }
    const folderPath = `/${folderName}`;
    $.post({
        url: buildCourseUrl(['gradeable', 'edit', 'modify_structure']),
        data: {
            action: 'add_folder',
            gradeable_id: g_id,
            path: folderPath,
            csrf_token: csrfToken,
        },
        success: (res) => {
            const json = JSON.parse(res);
            if (json.status === 'success') {
                displaySuccessMessage('Folder created successfully.');
                location.reload();
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: () => displayErrorMessage('Failed to create folder.'),
    });
}

function addFile(g_id, path) {
    if (!path) {
        openFilePickerAndUpload(null, g_id);
        return;
    }
    const cleaned = path.replace(/^.*config_upload\/\d+\//, '');
    openFilePickerAndUpload(cleaned, g_id);
}

function openFilePickerAndUpload(targetFolderPath, g_id) {
    const input = document.getElementById('hidden-config-file-input');
    input.value = '';
    input.onchange = null;
    input.onchange = (event) => {
        const file = event.target.files[0];
        if (!file) { return; }
        const dest = (targetFolderPath || '').replace(/^\/+/, '');
        const relativePath = dest ? `${dest}/${file.name}` : file.name;
        uploadFile(relativePath, file, g_id);
    };
    input.click();
}

function uploadFile(relativePath, file, g_id) {
    const formData = new FormData();
    formData.append('action', 'add_file');
    formData.append('gradeable_id', g_id);
    formData.append('path', relativePath);
    formData.append('file', file);
    formData.append('csrf_token', csrfToken);

    $.ajax({
        url: buildCourseUrl(['gradeable', 'edit', 'modify_structure']),
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
    })
    .done((raw) => {
        let res;
        try { res = JSON.parse(raw); }
        catch {
            displayErrorMessage('Unexpected server response.');
            return;
        }
        if (res.status === 'success') {
            displaySuccessMessage('File successfully added.');
            location.reload();
        }
        else {
            displayErrorMessage(res.message ?? 'Failed to add file.');
        }
    })
    .fail(() => displayErrorMessage('Something went wrong while uploading.'));
}

function removeFile(g_id, path, isFolder) {
    let confirmed = confirm(`Are you sure you want delete this ${isFolder ? 'folder' : 'file'}? This action cannot be undone.`);
    if (!confirmed) { return; }
    $.post({
        url: buildCourseUrl(['gradeable', 'edit', 'modify_structure']),
        data: {
            action: 'delete',
            gradeable_id: g_id,
            path: path,
            csrf_token: csrfToken,
        },
        success: (res) => {
            const json = JSON.parse(res);
            if (json.status === 'success') {
                displaySuccessMessage('Selected item deleted.');
                location.reload();
            }
            else {
                displayErrorMessage(json.message);
            }
        },
        error: () => displayErrorMessage('Error deleting files/folders.'),
    });
}

function toggleLineNums() {
    const current = localStorage.getItem('enableLineNums');
    const newState = (!current || current === 'false') ? 'true' : 'false';
    localStorage.setItem('enableLineNums', newState);
    reloadCodeMirror();
    updateEditorIcons();
}

function toggleTabLength() {
    const tabLength = Number(localStorage.getItem('setTabLength'));
    const newLength = (!tabLength || tabLength === 2) ? 4 : 2;
    localStorage.setItem('setTabLength', newLength);
    reloadCodeMirror();
    updateEditorIcons();
}

function markLastClicked(el) {
    document.querySelectorAll('.key_to_click').forEach((link) => {
        link.classList.remove('selected');
    });
    el.classList.add('selected');
}
