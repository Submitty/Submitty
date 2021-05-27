/* exported adminTeamForm importTeamForm randomizeRotatingGroupsButton addTeamMemberInput*/
/* global captureTabInModal */

function adminTeamForm(new_team, who_id, reg_section, rot_section, user_assignment_setting_json, members,
    pending_members, multiple_invite_members, max_members, lock_date) {
    $('.popup-form').css('display', 'none');
    const form = $('#admin-team-form');
    form.css('display', 'block');
    captureTabInModal('admin-team-form');

    form.find('.form-body').scrollTop(0);
    $('#admin-team-form-submit').prop('disabled',false);
    $('[name="new_team"]', form).val(new_team);
    $(`[name="reg_section"] option[value="${reg_section}"]`, form).prop('selected', true);
    $(`[name="rot_section"] option[value="${rot_section}"]`, form).prop('selected', true);
    if (new_team) {
        $('[name="num_users"]', form).val(3);
    }
    else if (!new_team) {
        $('[name="num_users"]', form).val(members.length+pending_members.length+2);
    }

    const title_div = $('#admin-team-title');
    title_div.empty();
    const members_div = $('#admin-team-members');
    members_div.empty();

    const team_history_tbody = $('#admin_team_history_table > tbody');
    team_history_tbody.empty();

    //add nav button to skip to submit button
    members_div.append('<a id="skip-nav" class="skip-btn" href="#admin-team-form-submit">Skip to Submit Button</a>');
    members_div.append('Team Member IDs:<br />');

    const student_full = JSON.parse($('#student_full_id').val());
    let exists_multiple_invite_member = false;

    //if a new team is being created
    if (new_team) {
        $('[name="new_team_user_id"]', form).val(who_id);
        $('[name="edit_team_team_id"]', form).val('');

        title_div.append(`Create New Team: ${who_id}`);
        members_div.append(getTeamFormLabelString('user_id_', 'user_id_', 0));
        members_div.append(getTeamFormReadOnlyInputString('user_id_', 'user_id_', who_id, 0));

        //add 2 more inputs
        for (let i = 1; i < 3; i++) {
            members_div.append(getTeamFormLabelString('user_id_', 'Team Member', i));
            members_div.append(getTeamFormInputString('user_id_', 'user_id_', i));
            members_div.append('<br/>');
            $(`#user_id_${i}`).autocomplete({
                source: student_full,
            });
            $(`#user_id_${i}`).autocomplete('option', 'appendTo', form);
        }
        members_div.find('[name="reg_section"]').val(reg_section);
        members_div.find('[name="rot_section"]').val(rot_section);
    }
    //a team is being edited
    else {
        $('[name="new_team_user_id"]', form).val('');
        $('[name="edit_team_team_id"]', form).val(who_id);

        title_div.append(`Edit Team: ${who_id}`);

        //append current members in the team to members_div
        for (let i = 0; i < members.length; i++) {
            members_div.append(getTeamFormLabelString('user_id_', 'Team Member', i));
            members_div.append(getTeamFormReadOnlyInputString('user_id_', 'user_id_', members[i], i));
            members_div.append(getTeamFormLabelString('remove_member_', 'Remove Member', i));
            members_div.append(getTeamFormButtonString('remove_member_', 'btn-danger', 'Remove', i, removeTeamMemberInput, i));
            members_div.append('<br/>');
        }

        //append pending members to members_div
        for (let i = members.length; i < members.length + pending_members.length; i++) {
            //if this member has been invited to multiple teams, set flag
            if (multiple_invite_members[i-members.length]) {
                exists_multiple_invite_member = true;
            }
            members_div.append(getTeamFormLabelString('pending_user_id_', 'Pending Team Member', i));
            members_div.append(
                getTeamFormReadOnlyInputString(
                    'pending_user_id_',
                    'pending_user_id_',
                    `Pending: ${pending_members[i-members.length]}`,
                    i,
                    `admin-team-form-pending ${multiple_invite_members[i-members.length] ? 'admin-team-form-pending-conflict' : ''}`,
                ),
            );
            members_div.append(getTeamFormLabelString('approve_member_', 'approve_member_', i));
            members_div.append(getTeamFormButtonString('approve_member_', 'btn-success', 'Accept', i, approveTeamMemberInput, i));
            members_div.append('<br/>');
        }

        //add 2 extra inputs at the bottom of the list
        for (let i = members.length+pending_members.length; i < (members.length+pending_members.length+2); i++) {
            members_div.append(getTeamFormLabelString('user_id_', 'Team Member', i));
            members_div.append(getTeamFormInputString('user_id_', 'user_id_', i));
            members_div.append('<br/>');
            $(`#user_id_${i}`, form).autocomplete({
                source: student_full,
            });
            $(`#user_id_${i}`).autocomplete('option', 'appendTo', form);
        }

        if (user_assignment_setting_json != false) {
            const team_history_len = user_assignment_setting_json.team_history.length;
            team_history_tbody.append(getTeamHistoryTableRowString('', user_assignment_setting_json.team_history[0].time, 'N/A', 'Team Formed'));
            team_history_tbody.append(getTeamHistoryTableRowString('', user_assignment_setting_json.team_history[team_history_len-1].time, 'N/A', 'Last Edited'));
            let past_lock_date = false;
            for (let j = 0; j <=team_history_len-1; j++) {
                const curr_json_entry = user_assignment_setting_json.team_history[j];
                if (!past_lock_date && curr_json_entry.time > lock_date) {
                    past_lock_date = true;
                }

                const getRowBound = getTeamHistoryTableRowString.bind(null, past_lock_date, curr_json_entry.time);

                if (curr_json_entry.action == 'admin_create' && curr_json_entry.first_user != undefined) {
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, 'Created Team'));
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, `Added ${curr_json_entry.first_user}`));
                }
                else if (curr_json_entry.action == 'admin_create' || curr_json_entry.action == 'admin_add_user'){
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, `Added ${curr_json_entry.added_user}`));
                }
                else if (user_assignment_setting_json.team_history[j].action == 'create') {
                    team_history_tbody.append(getRowBound(curr_json_entry.user, 'Created Team'));
                }
                else if (user_assignment_setting_json.team_history[j].action == 'admin_remove_user'){
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, `Removed ${curr_json_entry.removed_user}`));
                }
                else if (user_assignment_setting_json.team_history[j].action == 'leave') {
                    team_history_tbody.append(getRowBound(curr_json_entry.user, 'Left Team'));
                }
                else if (user_assignment_setting_json.team_history[j].action == 'send_invitation') {
                    team_history_tbody.append(getRowBound(curr_json_entry.sent_by_user, `Invited ${curr_json_entry.sent_to_user}`));
                }
                else if (user_assignment_setting_json.team_history[j].action == 'accept_invitation') {
                    team_history_tbody.append(getRowBound(curr_json_entry.user, 'Accepted Invite'));
                }
                else if (user_assignment_setting_json.team_history[j].action == 'cancel_invitation') {
                    team_history_tbody.append(getRowBound(curr_json_entry.canceled_by_user, `Uninvited ${curr_json_entry.canceled_user}`));
                }
            }
            if (past_lock_date) {
                $('#admin_team_history_table > tfoot').css('display', 'table-footer-group');
            }
        }
    }

    $(':text',form).change(function() {
        let found = false;
        for (let i = 0; i < student_full.length; i++) {
            if (student_full[i]['value'] === $(this).val()) {
                found = true;
                break;
            }
        }
        if (found || $(this).val() == '') {
            $(this)[0].setCustomValidity('');
        }
        else {
            $(this)[0].setCustomValidity('Invalid user_id');
        }
    });

    const next_user_num = (new_team ? 3 : members.length+2);
    members_div.append(getTeamFormAddMoreUsersButtonString(next_user_num));
    if (exists_multiple_invite_member) {
        members_div.append(getTeamFormMultipleInvitesWarningString());
    }
}

function getTeamHistoryTableRowString(isAfterLockDate, date, user, action){
    return `<tr ${isAfterLockDate ? 'class="admin-team-history-after-lock"' : ''} tabIndex="-1">
        <td class="admin-team-history-td" tabIndex="0">${user}</td>
        <td class="admin-team-history-td" tabIndex="0">${action}</td>
        <td class="admin-team-history-td" tabIndex="0">${date}</td>
    </tr>`;
}

function getTeamFormLabelString(for_prefix, text, user_num ){
    return `<label tabIndex="0" for="${for_prefix + user_num}" style="display:none;">${`${text} ${user_num}`}</label>`;
}

function getTeamFormReadOnlyInputString(id_prefix, name_prefix, value, user_num, class_string=''){
    return `<input 
                tabIndex="0" 
                id="${id_prefix + user_num}" 
                class="readonly ${class_string}" 
                type="text" 
                name="${name_prefix + user_num}"
                readonly="readonly" 
                value="${value}"
            />`;
}

function getTeamFormInputString(id_prefix, name_prefix, user_num){
    return `<input 
                tabIndex="0" 
                id="${id_prefix + user_num}" 
                type="text" 
                name="${name_prefix + user_num}"
            />`;
}

function getTeamFormButtonString(id_prefix, button_class, text, user_num, onclick, ...onclickArgs){
    return `<button 
                tabIndex="0"
                id="${id_prefix + user_num}"
                class="btn ${button_class} admin-team-form-button" 
                onclick="${onclick.name}(${onclickArgs.join(', ')})" 
            >
                ${text}
            </button>`;
}

function getTeamFormAddMoreUsersButtonString(user_num){
    return `<button 
                id="admin_team_form_add_more_users_button"
                onclick="addTeamMemberInput(this, ${user_num});" 
                aria-label="Add More Users"
                class="btn btn-primary"
            >
                <i class="fas fa-plus-square"></i>
                Add More Users
            </button>`;
}

function getTeamFormMultipleInvitesWarningString(){
    return `<div id="multiple-invites-warning" class="red-message" style="margin-top:3px;width:90%">
                *Pending members highlighted in pink/red have invites to multiple teams.
            </div>`;
}

function removeTeamMemberInput(i) {
    const removed_member_element = $(`#user_id_${i}`);
    //remove the Remove button associated with this member;
    $(`#remove_member_${i}`).remove();

    //clear removed_member's input field and change it to writeable
    removed_member_element.removeClass('readonly').prop('readonly', false).val('');

    //update autocomplete
    const student_full = JSON.parse($('#student_full_id').val());
    removed_member_element.autocomplete({
        source: student_full,
    });
}

function approveTeamMemberInput(i) {
    const new_member_element = $(`#pending_user_id_${i}`);

    //remove accept button associated with pending member
    $(`#approve_member_${i}`).remove();

    //remove pending from the name of the readonly input
    new_member_element.attr('name', `user_id_${i}`);

    //remove pending classes from the associated input
    new_member_element.removeClass('admin-team-form-pending');
    new_member_element.removeClass('admin-team-form-pending-conflict');

    //remove "Pending: "" from input value
    const user_id = (new_member_element.val()).substring(9);
    new_member_element.attr('value', user_id);

    //update autocomplete
    const student_full = JSON.parse($('#student_full_id').val());
    new_member_element.autocomplete({
        source: student_full,
    });
}

function addTeamMemberInput(old, i) {
    //insert new input before "Add New Users" button
    $(getTeamFormLabelString('user_id_', 'Team Member', i)).insertBefore(old);
    $(getTeamFormInputString('user_id_', 'user_id_', i)).insertBefore(old);
    $('<br/>').insertBefore(old);

    //remove old "Add More Users" button so we can update it
    old.remove();
    //remove the multiple invites warning so we can add it back at the bottom later
    $('#multiple-invites-warning').remove();

    //increment num_users
    const form = $('#admin-team-form');
    $('[name="num_users"]', form).val( parseInt($('[name="num_users"]', form).val()) + 1);

    //put button and warning back
    const members_div = $('#admin-team-members');
    members_div.append(getTeamFormAddMoreUsersButtonString(i+1));
    members_div.append(getTeamFormMultipleInvitesWarningString());

    //update autocomplete
    const student_full = JSON.parse($('#student_full_id').val());
    $(`#user_id_${i}`).autocomplete({
        source: student_full,
    });
}

function importTeamForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#import-team-form');
    form.css('display', 'block');
    captureTabInModal('import-team-form');
    form.find('.form-body').scrollTop(0);
    $('[name="upload_team"]', form).val(null);
}


function randomizeRotatingGroupsButton() {
    $('.popup-form').css('display', 'none');
    const form = $('#randomize-button-warning');
    form.css('display', 'block');
    captureTabInModal('randomize-button-warning');
    form.find('.form-body').scrollTop(0);
}
