/* global disableElementChildren, onManualGradingChange, onVCSTypeChange, validate, updateDateDisplay, buildCourseUrl */

function textAreaAdjust(o) {
    o.style.height = "1px";
    o.style.height = (15 + o.scrollHeight) + "px";
}

function updateDateDisplay() {
    let elecDateBoxes = $('.electronic_file_dates input');
    elecDateBoxes.prop('disabled', true);
    if ($('#radio_electronic_file').is(':checked')) {
        elecDateBoxes.prop('disabled', false);
        $('.manual_grading_dates input').prop('disabled', $('#no_ta_grade').is(':checked'));
        const teamLock = $('#date_team_lock');
        if (teamLock.length) {
            teamLock.prop('disabled', teamLock.data('team-assignment') === 'false');
        }
        $('#late_days').prop('disabled', $('#no_late_submission').is(':checked'));

        const [gradeInqOpenDate, gradeInqDueDate] = [$('#date_grade_inquiry_start'), $('#date_grade_inquiry_due')];

        gradeInqOpenDate.prop('disabled', true);
        gradeInqDueDate.prop('disabled', true);
        $('#no_grade_inquiry_allowed').each(function () {
            gradeInqOpenDate.prop('disabled', $(this).is(':checked'));
            gradeInqDueDate.prop('disabled', $(this).is(':checked'));
        });

        $('#date_released').prop('disabled', !$('#has_release_date_yes').is(':checked'));

        const has_due_date = $('#has_due_date_yes').is(':checked');

        $('#date_due').prop('disabled', !has_due_date);
    }
    $('#hide_dates').hide();
    $('#show_all_dates').show();
}

function onGradeableTypeChange() {
    if ($('#radio_electronic_file').is(':checked') ||
       $('#radio_checkpoints').is(':checked') ||
       $('#radio_numeric').is(':checked')) {
        $('.required_type').hide();
    }

    if (!$('#radio_electronic_file').is(':checked')) {
        $('input[name=bulk_upload]').prop('checked', false);
    }

    disableElementChildren('.electronic_file');
    disableElementChildren('.checkpoints');
    disableElementChildren('.numeric');

    if ($('#radio_electronic_file').is(':checked')) {
        disableElementChildren('.electronic_file', false);
        $('#page_1_nav').show();
        onManualGradingChange();
        if ($('input[name=electronic_gradeable_presets]:checked').length === 0) {
            $('input[name=electronic_gradeable_presets][value=normal]').prop('checked', true);
        }
    }
    else {
        $('input[name=electronic_gradeable_presets]').prop('checked', false);
        if ($('#radio_checkpoints').is(':checked')) {
            disableElementChildren('.checkpoints', false);
        }
        else if ($('#radio_numeric').is(':checked')) {
            disableElementChildren('.numeric', false);
        }
    }
}

function onTeamAssignmentChange() {
    if ($('#team_yes_radio').is(':checked')) {
        disableElementChildren('.team_yes', false);
        disableElementChildren('.team_no', true);

        if ($('#vcs_radio_submitty_hosted_set_url').is(':checked')) {
            $('#vcs_radio_submitty_hosted').prop('checked', true);
            onVCSTypeChange();
        }
    }
    else {
        disableElementChildren('.team_yes', true);
        disableElementChildren('.team_no', false);
    }
}

function onSubdirectoryChange() {
    if ($('#subdir_yes_radio').is(':checked')) {
        disableElementChildren('.subdirectory_yes', false);
        $('#vcs_subdirectory_input').prop('required', true);
    }
    else {
        disableElementChildren('.subdirectory_yes', true);
        $('#vcs_subdirectory_input').prop('required', false);
    }
}

function onDiscussionChange() {
    if ($('#yes_discussion').is(':checked')) {
        disableElementChildren('.discussion_id_wrapper', false);
        $("#discussion_thread_id").attr('required', '');
    }
    else {
        disableElementChildren('.discussion_id_wrapper', true);
        $("#discussion_thread_id").removeAttr('required');
    }
}

function onManualGradingChange() {
    if ($('#yes_ta_grade').is(':checked') || !$('#radio_electronic_file').is(':checked')) {
        disableElementChildren('.manual_grading', false);
        disableElementChildren('.no_manual_grading', true);
        $('#grade_inquiry_enable_container').show();
        $('#discussion_grading_enable_container').show();
    }
    else {
        disableElementChildren('.manual_grading', true);
        disableElementChildren('.no_manual_grading', false);
        $("#yes_discussion").prop('checked', false);
        $("#no_discussion").prop('checked', true);
        $('#grade_inquiry_enable_container').hide();
        $('#discussion_grading_enable_container').hide();
        const noDiscussion = document.getElementById("no_discussion");
        if (noDiscussion) {
            noDiscussion.checked = true;
        }
    }
    updateDateDisplay();
}

function onVCSTypeChange() {
    disableElementChildren('[id=vcs_submitty_set_url_content]', true);
    disableElementChildren('[id=vcs_submitty_url_content]', true);
    disableElementChildren('[id=vcs_self_set_url_content]', true);

    if ($('#vcs_radio_submitty_hosted').is(':checked')) {
        disableElementChildren('#vcs_submitty_url_content', false);
    }

    if ($('#vcs_radio_submitty_hosted_set_url').is(':checked')) {
        disableElementChildren('#vcs_submitty_set_url_content', false);
        $('#vcs_path').prop('required', true);
    }
    else {
        $('#vcs_path').prop('required', false);
    }

    if ($('#vcs_radio_self_hosted_set_url').is(':checked')) {
        disableElementChildren('#vcs_self_set_url_content', false);
        $('#vcs_self_url').prop('required', true);
    }
    else {
        $('#vcs_self_url').prop('required', false);
    }
}

function onIsGradeInquiryAllowedChange() {
    if ($('#yes_grade_inquiry_allowed').is(':checked')) {
        disableElementChildren('.grade_inquiry_date', false);
        disableElementChildren('.grade_inquiry_per_component_allowed', false);
    }
    else {
        disableElementChildren('.grade_inquiry_date', true);
        $('#no_grade_inquiry_per_component_allowed').prop('checked', true);
        disableElementChildren('.grade_inquiry_per_component_allowed', true);
    }
    updateDateDisplay();
}

function onLateSubmissionAllowedChanged() {
    let lateSubmissionParts = $('.yes-late-submission');
    if ($('#yes_late_submission').is(':checked')) {
        lateSubmissionParts.show();
    }
    else {
        lateSubmissionParts.hide();
    }
    updateDateDisplay();
}

function onHasReleaseDate() {
    if ($('#has_release_date_yes').is(':checked')) {
        disableElementChildren('.release_date_date', false);
    }
    else {
        disableElementChildren('.release_date_date', true);
    }
    updateDateDisplay();
}

function onHasDueDate() {
    if ($('#has_due_date_yes').is(':checked')) {
        disableElementChildren('.due_date_date', false);
    }
    else {
        disableElementChildren('.due_date_date', true);
    }
    updateDateDisplay();
}

function validate() {
    const sub1 = $('input[type]');
    const sub2 = $('input[electronic_gradeable_presets]');

    if ($(sub1).is(':checked') || $(sub2).is(':checked')) {
        $(".btn#create-gradeable-btn").removeAttr("disabled");
    }
    else {
        $(".btn#create-gradeable-btn").attr("disabled", true);
    }
}

function initAdminGradeableCreate() {
    onManualGradingChange();
    $('[name="ta_grading"]').change(onManualGradingChange);
    $('[name="ta_grading"]').change(onDiscussionChange);

    $('[name="gradeable_template"]').change(function () {
        window.location.href = buildCourseUrl(['gradeable']) + `?template_id=${this.value}`;
    });

    $("input[type='radio']").change(validate);

    onGradeableTypeChange();
    $('[name="type"]').change(onGradeableTypeChange);

    onTeamAssignmentChange();
    $('[name="team_assignment"]').change(onTeamAssignmentChange);

    onSubdirectoryChange();
    $('[name="using_subdirectory"]').change(onSubdirectoryChange);

    onVCSTypeChange();
    $('[name="vcs_radio_buttons"]').change(onVCSTypeChange);

    onDiscussionChange();
    $('[name="discussion_based"]').change(onDiscussionChange);

    disableElementChildren('#repository', $('input[name=vcs]').val() === 'false');
    disableElementChildren('#subdirectory-settings-container', $('input[name=vcs]').val() === 'false');

    $('input[name=electronic_gradeable_presets]').change(function () {
        $('#radio_electronic_file').prop('checked', true);
        onGradeableTypeChange();
        const vcs = $('input[name=vcs]');
        const bulkUpload = $('input[name=bulk_upload]');

        vcs.val('false');
        $('#repository').hide();
        $('#subdirectory-settings-container').hide();
        if ($(this).val() === 'bulk_upload') {
            bulkUpload.val('true');
        }
        else {
            bulkUpload.val('false');
            if ($(this).val() === 'vcs') {
                vcs.val('true');
                $('#repository').show();
                $('#subdirectory-settings-container').show();
            }
        }
        vcs.change();
        bulkUpload.change();
    });

    onIsGradeInquiryAllowedChange();
    $('[name="grade_inquiry_allowed"]').change(onIsGradeInquiryAllowedChange);
    $('[name="grade_inquiry_per_component_allowed"]').click(function () {
        if ($('#no_grade_inquiry_per_component_allowed').prop('checked') && !confirm('* WARNING *\nThere are already grade inquiries, continuing with this action will permanently convert all component inquiries to general inquiries.  Do you want to proceed?')) {
            return false;
        }
    });

    onLateSubmissionAllowedChanged();
    $('input[name=late_submission_allowed]').change(onLateSubmissionAllowedChanged);
}

function showAllDates() {
    const isElectronic = $('#radio_electronic_file').is(':checked');
    $('#gradeable-dates').find('div,input').show();
    if (!isElectronic) {
        $('#release_toggle_container').hide();
    }
    $('#gray_date_warning').show();
    $('#hide_dates').show();
    $('#show_all_dates').hide();
}

function hideDates() {
    $('#gray_date_warning').hide();
    onGradeableTypeChange();
    onTeamAssignmentChange();
    onManualGradingChange();
    onIsGradeInquiryAllowedChange();
    onLateSubmissionAllowedChanged();
    const isElectronic = $('#radio_electronic_file').is(':checked');
    if (isElectronic) {
        onHasReleaseDate();
        onHasDueDate();
    }
}

function initAdminGradeableDates() {
    flatpickr('.date_picker', {
        plugins: [ShortcutButtonsPlugin(
            {
                button: [
                    {
                        label: 'Now',
                    },
                    {
                        label: 'End of time',
                    },
                ],
                label: 'or',
                onClick: (index, fp) => {
                    let date;
                    switch (index) {
                        case 0:
                            date = new Date();
                            break;
                        case 1:
                            date = new Date('9998-01-01T00:00:00');
                            break;
                    }
                    fp.setDate(date, true);
                },
            },
        )],
        allowInput: true,
        enableTime: true,
        enableSeconds: true,
        time_24hr: true,
        dateFormat: 'Y-m-d H:i:S',
        onReady: (a, b, fp) => {
            fp.calendarContainer.firstChild.childNodes[1].firstChild.firstChild.setAttribute('aria-label', 'Month');
            fp.calendarContainer.childNodes[2].childNodes[4].firstChild.setAttribute('aria-label', 'Seconds');
        },
    });
}
