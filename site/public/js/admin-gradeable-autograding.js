/* global csrfToken, buildCourseUrl, getGradeableId, displayErrorMessage, displaySuccessMessage, displayWarningMessage, CodeMirror, originalConfigContent, codeMirrorInstance, isConfigEdited, ajaxCheckBuildStatus */

function showBuildLog() {
    ajaxGetBuildLogs(getGradeableId());
}

function hideBuildLog() {
    $('.log-container').hide();
    $('#open-build-log').show();
    $('#close-build-log').hide();
}

function ajaxRebuildGradeableButton() {
    const gradeable_id = getGradeableId();
    $.ajax({
        url: buildCourseUrl(['gradeable', gradeable_id, 'rebuild']),
        success: function () {
            rebuild_triggered = true;
            ajaxCheckBuildStatus();
        },
        error: function (response) {
            console.error(response);
        },
    });
}

function ajaxGetBuildLogs(gradeable_id, rebuilt = false) {
    $.getJSON({
        type: 'GET',
        url: buildCourseUrl(['gradeable', gradeable_id, 'build_log']),
        success: function (response) {
            let alerted = false;
            const build_info = response['data'][0];
            const cmake_info = response['data'][1];
            const make_info = response['data'][2];

            if (build_info !== null) {
                $('#build-log-body').html(build_info);
                for (const line of build_info.split('\n')) {
                    if (line.includes('WARNING:')) {
                        alerted = true;
                        displayWarningMessage(line.split('WARNING:')[1].trim());
                    }
                    else if (line.includes('ERROR:')) {
                        alerted = true;
                        displayErrorMessage(line.split('ERROR:')[1].trim());
                    }
                }
            }
            else {
                $('#build-log-body').text('There is currently no build output.');
            }
            if (cmake_info !== null) {
                $('#cmake-log-body').html(cmake_info);
            }
            else {
                $('#cmake-log-body').text('There is currently no cmake output.');
            }
            if (make_info !== null) {
                $('#make-log-body').html(make_info);
            }
            else {
                $('#make-log-body').html('There is currently no make output.');
            }

            if (alerted || !rebuilt) {
                $('.log-container').show();
                $('#open-build-log').hide();
                $('#close-build-log').show();
            }
        },
        error: function (response) {
            console.error(`Failed to parse response from server: ${response}`);
        },
    });
}

function ajaxCheckBuildStatus() {
    const gradeable_id = getGradeableId();
    if (!gradeable_id) { return; }
    $('#rebuild-log-button').css('display', 'none');
    hideBuildLog();
    $.getJSON({
        type: 'GET',
        url: buildCourseUrl(['gradeable', gradeable_id, 'build_status']),
        success: function (response) {
            $('#rebuild-log-button').css('display', 'block');
            if (response['data'] === 'queued') {
                $('#rebuild-status').text(gradeable_id.concat(' is in the rebuild queue...'));
                $('#rebuild-log-button').css('display', 'none');
                setTimeout(ajaxCheckBuildStatus, 1000);
            }
            else if (response['data'] === 'processing') {
                $('#rebuild-status').text(gradeable_id.concat(' is being rebuilt...'));
                $('#rebuild-log-button').css('display', 'none');
                setTimeout(ajaxCheckBuildStatus, 1000);
            }
            else if (response['data'] === 'warnings') {
                $('#rebuild-status').text('Gradeable built with warnings');
            }
            else if (response['data'] == true) {
                $('.config_search_error').hide();
                $('#rebuild-status').text('Gradeable build complete');
            }
            else if (response['data'] == false) {
                $('#rebuild-status').text('Gradeable build failed');
                $('#autograding_config_error').text('The current configuration is not valid, please check the build log for details.');
                $('.config_search_error').show();
            }
            else {
                $('#rebuild-status').text('Error');
                console.error('Internal server error, please try again.');
            }

            if (typeof rebuild_triggered !== 'undefined' && rebuild_triggered) {
                rebuild_triggered = false;
                ajaxGetBuildLogs(gradeable_id, true);
            }
        },
        error: function (response) {
            console.error(`Failed to parse response from server: ${response}`);
        },
    });
}

function loadCodeMirror() {
    codeMirrorInstance = CodeMirror.fromTextArea(
        document.getElementById('gradeable-config-edit'),
        {
            mode: { name: 'json', json: true },
            theme: localStorage.theme === 'light' ? 'eclipse' : 'monokai',
            lineNumbers: localStorage.getItem('enableLineNums') === 'true',
            tabSize: Number(localStorage.getItem('setTabLength')) === 2 ? 2 : 4,
            indentUnit: Number(localStorage.getItem('setTabLength')) === 2 ? 2 : 4,
            lineWrapping: true,
        },
    );
    updateEditorIcons();
    codeMirrorInstance.on('change', () => {
        const currentContent = codeMirrorInstance.getValue();
        isConfigEdited = currentContent !== originalConfigContent;
    });
}

function saveGradeableConfigEdit(g_id) {
    const content = codeMirrorInstance?.getValue() || $('textarea#gradeable-config-edit').val();
    $.ajax({
        url: buildCourseUrl(['gradeable', 'edit', 'save']),
        type: 'POST',
        data: {
            gradeable_id: g_id,
            file_path: $('textarea#gradeable-config-edit').data('file-path'),
            write_content: content,
            csrf_token: csrfToken,
        },
        success: function (data) {
            try {
                const json = JSON.parse(data);
                if (json['status'] === 'fail') {
                    displayErrorMessage(json['message']);
                    return;
                }
                originalConfigContent = $('#gradeable-config-edit').val();
                $('#gradeable-config-edit').data('edited', false);
                cancelGradeableConfigEdit();
                ajaxCheckBuildStatus();
                displaySuccessMessage('Autograding configuration successfully updated.');
            }
            catch (err) {
                displayErrorMessage('Error parsing data. Please try again');
            }
        },
        error: function () {
            window.alert('Something went wrong while saving the gradeable config. Please try again.');
        },
    });
}

function reloadCodeMirror() {
    if (codeMirrorInstance) {
        const currentContent = codeMirrorInstance.getValue();
        codeMirrorInstance.toTextArea();
        document.getElementById('gradeable-config-edit').value = currentContent;
    }
    loadCodeMirror();
}

function updateEditorIcons() {
    const enableLineNumsIcon = document.getElementById('toggle-line-nums');
    const lineNums = localStorage.getItem('enableLineNums');
    if (lineNums === 'true') {
        enableLineNumsIcon.classList.add('line-nums-selected');
    }
    else {
        enableLineNumsIcon.classList.remove('line-nums-selected');
    }

    const tabLengthIcon = document.getElementById('toggle-tab-length');
    const tabLength = localStorage.getItem('setTabLength') || '2';
    if (tabLengthIcon) {
        tabLengthIcon.classList.remove('fa-2', 'fa-4');
        tabLengthIcon.classList.add(`fa-${tabLength}`);
    }
}
