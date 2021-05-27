function adminTeamForm(new_team, who_id, reg_section, rot_section, user_assignment_setting_json, members, 
    pending_members, multiple_invite_members, max_members, lock_date) {
    $('.popup-form').css('display', 'none');
    const form = $("#admin-team-form");
    form.css("display", "block");
    captureTabInModal("admin-team-form");

    form.find('.form-body').scrollTop(0);
    $("#admin-team-form-submit").prop('disabled',false);
    $('[name="new_team"]', form).val(new_team);
    $('[name="reg_section"] option[value="' + reg_section + '"]', form).prop('selected', true);
    $('[name="rot_section"] option[value="' + rot_section + '"]', form).prop('selected', true);
    if(new_team) {
        $('[name="num_users"]', form).val(3);
    }
    else if (!new_team) {
        $('[name="num_users"]', form).val(members.length+pending_members.length+2);
    }

    const title_div = $("#admin-team-title");
    title_div.empty();
    const members_div = $("#admin-team-members");
    members_div.empty();

    const team_history_tbody = $("#admin_team_history_table > tbody");
    team_history_tbody.empty();

    //add nav button to skip to submit button
    members_div.append('<a id="skip-nav" class="skip-btn" href="#admin-team-form-submit">Skip to Submit Button</a>');
    members_div.append('Team Member IDs:<br />');

    const student_full = JSON.parse($('#student_full_id').val());
    let exists_multiple_invite_member = false;
    if (new_team) {
        $('[name="new_team_user_id"]', form).val(who_id);
        $('[name="edit_team_team_id"]', form).val("");

        title_div.append('Create New Team: ' + who_id);
        members_div.append('<label tabIndex="0" for="user_id_0" style="display:none;">Team Member 1</label>');
        members_div.append('<input tabIndex="0" id="user_id_0" class="readonly" type="text" name="user_id_0" readonly="readonly" value="' + who_id + '" />');
        for (let i = 1; i < 3; i++) {
            members_div.append('<label tabIndex="0" for="user_id_' + i + '" style="display:none;">Team Member ' + (i+1) + '</label>');
            members_div.append('<input tabIndex="0" id="user_id_' + i + '" type="text" name="user_id_' + i + '" /><br />');
            $('[name="user_id_'+i+'"]', form).autocomplete({
                source: student_full
            });
            $('[name="user_id_'+i+'"]').autocomplete( "option", "appendTo", form );
        }
        members_div.find('[name="reg_section"]').val(reg_section);
        members_div.find('[name="rot_section"]').val(rot_section);
    }
    else {
        $('[name="new_team_user_id"]', form).val("");
        $('[name="edit_team_team_id"]', form).val(who_id);

        title_div.append('Edit Team: ' + who_id);
        for (let i = 0; i < members.length; i++) {
            members_div.append('<label tabIndex="0" for="user_id_' + i + '" style="display:none;">Team Member ' + (i+1) + '</label>');
            members_div.append('<input tabIndex="0" id="user_id_' + i + '" class="readonly" type="text" name="user_id_' + i + '" readonly="readonly" value="' + members[i] + '" /> \
                <label tabIndex="0" for="remove_member_' + i + '" style="display:none;">Remove Member ' + (i+1) + '</label>\
                <button tabIndex="0" id="remove_member_'+i+'" class = "btn btn-danger" value="Remove" onclick="removeTeamMemberInput('+i+');" \
                style="cursor:pointer; width:80px; padding-top:3px; padding-bottom:3px;">Remove</button><br />');
        }
        for (let i = members.length; i < members.length+pending_members.length; i++) {
            if (multiple_invite_members[i-members.length]) exists_multiple_invite_member = true;
            members_div.append('<label tabIndex="0" for="pending_user_id_' + i + '" style="display:none;">Pending Team Member ' + (i+1) + '</label>');
            members_div.append('<input tabIndex="0" id="pending_user_id_' + i + '" class="readonly" type="text" style= "font-style: italic; color: var(--standard-medium-dark-gray);'+ (multiple_invite_members[i-members.length] ? " background-color:var(--alert-invalid-entry-pink);" : "") + '" \
                name="pending_user_id_' + i + '" readonly="readonly" value="Pending: ' + pending_members[i-members.length] + '" />\
                <input tabIndex="0" id="approve_member_'+i+'" class = "btn btn-success" type="submit" value="Accept" onclick="approveTeamMemberInput(this,'+i+');" \
                style="cursor:pointer; width:80px; padding-top:3px; padding-bottom:3px;"></input><br />');
        }
        for (let i = members.length+pending_members.length; i < (members.length+pending_members.length+2); i++) {
            members_div.append('<label tabIndex="0" for="user_id_' + i + '" style="display:none;">Team Member ' + (i+1) + '</label>');
            members_div.append('<input tabIndex="0" id="user_id_' + i + '" type="text" name="user_id_' + i + '" /><br />');
            $('[name="user_id_'+i+'"]', form).autocomplete({
                source: student_full
            });
            $('[name="user_id_'+i+'"]').autocomplete( "option", "appendTo", form );
        }
        if (user_assignment_setting_json != false) {
            const team_history_len = user_assignment_setting_json.team_history.length;
            team_history_tbody.append(getTeamHistoryTableRowString("", user_assignment_setting_json.team_history[0].time, "N/A", "Team Formed"));
            team_history_tbody.append(getTeamHistoryTableRowString("", user_assignment_setting_json.team_history[team_history_len-1].time, "N/A", "Last Edited"));
            let past_lock_date = false;
            for (let j = 0; j <=team_history_len-1; j++) {
                const curr_json_entry = user_assignment_setting_json.team_history[j];
                if (!past_lock_date && curr_json_entry.time > lock_date) {
                    past_lock_date = true;
                }

                const getRowBound = getTeamHistoryTableRowString.bind(null, past_lock_date, curr_json_entry.time);

                if(curr_json_entry.action == "admin_create" && curr_json_entry.first_user != undefined) {
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, "Created Team"));
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, 'Added ' + curr_json_entry.first_user));
                } 
                else if(curr_json_entry.action == "admin_create" || curr_json_entry.action == "admin_add_user"){
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, "Added " + curr_json_entry.added_user))
                } 
                else if (user_assignment_setting_json.team_history[j].action == "create") {
                    team_history_tbody.append(getRowBound(curr_json_entry.user, "Created Team"));
                } 
                else if(user_assignment_setting_json.team_history[j].action == "admin_remove_user"){
                    team_history_tbody.append(getRowBound(curr_json_entry.admin_user, 'Removed ' + curr_json_entry.removed_user));
                }
                else if (user_assignment_setting_json.team_history[j].action == "leave") {
                    team_history_tbody.append(getRowBound(curr_json_entry.user, "Left Team"));
                }
                else if (user_assignment_setting_json.team_history[j].action == "send_invitation") {
                    team_history_tbody.append(getRowBound(curr_json_entry.sent_by_user, "Invited " + curr_json_entry.sent_to_user));
                }
                else if (user_assignment_setting_json.team_history[j].action == "accept_invitation") {
                    team_history_tbody.append(getRowBound(curr_json_entry.user, "Accepted Invite"));
                }
                else if (user_assignment_setting_json.team_history[j].action == "cancel_invitation") {
                    team_history_tbody.append(getRowBound(curr_json_entry.canceled_by_user, "Uninvited " + curr_json_entry.canceled_user));
                }
            }
            if (past_lock_date) {
                $("#admin_team_history_table > tfoot").css("display", "table-footer-group");
            }
        }
    }

    $(":text",form).change(function() {
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
            $(this)[0].setCustomValidity("Invalid user_id");
        }
    });

    const param = (new_team ? 3 : members.length+2);
    members_div.append('<button onclick="addTeamMemberInput(this, '+param+');" aria-label="Add More Users"><i class="fas fa-plus-square"></i> \
        Add More Users</button>');
    if (exists_multiple_invite_member) {
        members_div.append('<div id="multiple-invites-warning" class="red-message" style="margin-top:3px;width:75%">\
        *Pending members highlighted in red have invites to multiple teams.');
    }
}

function getTeamHistoryTableRowString(isAfterLockDate, date, user, action){
    return `<tr ${isAfterLockDate ? 'class="admin-team-history-after-lock"' : ''} tabIndex="-1">
        <td class="admin-team-history-td" tabIndex="0">${user}</td>
        <td class="admin-team-history-td" tabIndex="0">${action}</td>
        <td class="admin-team-history-td" tabIndex="0">${date}</td>
    </tr>`;
}

function removeTeamMemberInput(i) {
    const form = $("#admin-team-form");
    $('[name="user_id_'+i+'"]', form).removeClass('readonly').prop('readonly', false).val("");
    $("#remove_member_"+i).remove();
    const student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function approveTeamMemberInput(old, i) {
    const form = $("#admin-team-form");
    $("#approve_member_"+i).remove();
    $('[name="pending_user_id_'+i+'"]', form).attr("name", "user_id_"+i);
    $('[name="user_id_'+i+'"]', form).attr("style", "font-style: normal;");
    const user_id = ($('[name="user_id_'+i+'"]', form).val()).substring(9);
    $('[name="user_id_'+i+'"]', form).attr("value", user_id);
    const student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function addTeamMemberInput(old, i) {
    old.remove()
    $('#multiple-invites-warning').remove();
    const form = $("#admin-team-form");
    $('[name="num_users"]', form).val( parseInt($('[name="num_users"]', form).val()) + 1);
    const members_div = $("#admin-team-members");
    members_div.append('<input type="text" name="user_id_' + i + '" /><br /> \
        <span style="cursor: pointer;" onclick="addTeamMemberInput(this, '+ (i+1) +');" aria-label="Add More Users"><i class="fas fa-plus-square"></i> \
        Add More Users</span>');
    const student_full = JSON.parse($('#student_full_id').val());
    $('[name="user_id_'+i+'"]', form).autocomplete({
        source: student_full
    });
}

function importTeamForm() {
    $('.popup-form').css('display', 'none');
    const form = $("#import-team-form");
    form.css("display", "block");
    captureTabInModal("import-team-form");
    form.find('.form-body').scrollTop(0);
    $('[name="upload_team"]', form).val(null);
}


function randomizeRotatingGroupsButton() {
    $('.popup-form').css('display', 'none');
    const form = $("#randomize-button-warning");
    form.css("display", "block");
    captureTabInModal("randomize-button-warning");
    form.find('.form-body').scrollTop(0);
}