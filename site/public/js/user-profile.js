/* exported updateUserPronouns, showUpdatePrefNameForm, showUpdateLastInitialFormatForm,
showUpdatePronounsForm, showDisplayNameOrderForm, showUpdatePasswordForm, showUpdateProfilePhotoForm, showUpdateSecondaryEmailForm,
updateUserPreferredNames, updateUserLastInitialFormat, updateUserProfilePhoto, updateUserSecondaryEmail, updateDisplayNameOrder,
changeSecondaryEmail, previewUserLastInitialFormat, clearPronounsBox
 */
/* global displaySuccessMessage, displayErrorMessage, buildUrl, showPopup */

// This variable is to store changes to the pronouns form that have not been submitted
let pronounsLastVal = null;
let displayNameOrderLast = 'GIVEN_F';

function showUpdatePrefNameForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-username-form');
    showPopup('#edit-username-form');
    form.find('.form-body').scrollTop(0);
    $('[name="user_name_change"]', form).val('');
    $('#user-givenname-change').focus();
}

function showUpdateLastInitialFormatForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-last-initial-format-form');
    showPopup('#edit-last-initial-format-form');
    form.find('.form-body').scrollTop(0);
    const dropdown = $('#user-last-initial-format-change');
    dropdown.val(dropdown.data().default || 0);
}

function showUpdatePronounsForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-pronouns-form');
    showPopup('#edit-pronouns-form');
    form.find('.form-body').scrollTop(0);
    if (pronounsLastVal !== null && document.getElementById('user-pronouns-change').value === '') {
        document.getElementById('user-pronouns-change').value = pronounsLastVal;
    }
}

function showDisplayNameOrderForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-display-name-order-form');
    form.css('display', 'block');
    form.find('.form-body').scrollTop(0);
    if (displayNameOrderLast !== null && document.getElementById('display-name-order-change').value === '') {
        document.getElementById('display-name-order-change').value = displayNameOrderLast;
    }
}

function showUpdatePasswordForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#change-password-form');
    showPopup('#change-password-form');
    form.find('.form-body').scrollTop(0);
    $('[name="new_password"]', form).val('');
    $('[name="confirm_new_password"]', form).val('');
    $('#new_password').focus();
}

function showUpdateProfilePhotoForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-profile-photo-form');
    showPopup('#edit-profile-photo-form');
    form.find('.form-body').scrollTop(0);
}

function showUpdateSecondaryEmailForm() {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-secondary-email-form');
    showPopup('#edit-secondary-email-form');
    form.find('.form-body').scrollTop(0);
}

/**
 * Gets the list of all available time zones as an array
 * Referenced from https://stackoverflow.com/questions/9149556/how-to-get-utc-offset-in-javascript-analog-of-timezoneinfo-getutcoffset-in-c
 * @returns {string[]}
 */
function getAvailableTimeZones() {
    return $('#time_zone_selector_label').data('available_time_zones').split(',');
}

/**
 * Get the UTC offset of the user's local time zone
 *
 * @return {string} of the user's local time zone UTC offset, for example for example '+9:30' or '-4:00'
 */
function getCurrentUTCOffset() {
    const date = new Date();
    const sign = (date.getTimezoneOffset() > 0) ? '-' : '+';
    const offset = Math.abs(date.getTimezoneOffset());
    let hours = Math.floor(offset / 60);
    hours = (hours < 10 ? `0${hours}` : hours);
    return `${sign + hours}:00`;
}

function clearPronounsBox() {
    const pronounsInput = document.getElementById('user-pronouns-change');
    if (pronounsLastVal === null || pronounsLastVal !== pronounsInput.value) {
        pronounsLastVal = pronounsInput.value;
    }
    pronounsInput.value = '';
}

// update user pronouns and display pronouns option
function updateUserPronouns(e) {
    // update user pronouns
    e.preventDefault();
    const pronouns = $('#user-pronouns-change');
    const forumDisplay = $('#pronouns-forum-display');
    pronounsLastVal = pronouns.val();
    if (pronouns.data('current-pronouns') === pronouns.val() && pronouns.data('pronouns-forum-display') === forumDisplay.val()) {
        // eslint-disable-next-line no-undef
        displayErrorMessage('No changes detected to update pronouns!');
        $('#edit-pronouns-form').hide();
    }
    else {
        const data = new FormData();
        const isForumDisplayChecked = forumDisplay.prop('checked');
        // eslint-disable-next-line no-undef
        data.append('csrf_token', csrfToken);
        data.append('pronouns', pronouns.val());
        data.append('pronouns-forum-display', isForumDisplayChecked);
        // eslint-disable-next-line no-undef
        const url = buildUrl(['user_profile', 'change_pronouns']);
        $.ajax({
            url,
            type: 'POST',
            data,
            processData: false,
            contentType: false,
            success: function (res) {
                console.log(res);
                const response = JSON.parse(res);
                if (response.status === 'success') {
                    const { data } = response;
                    // eslint-disable-next-line no-undef
                    displaySuccessMessage(data.message);
                    const icon = '<i class="fas fa-pencil-alt"></i>';
                    // update the pronouns and display (true or false)
                    $('#pronouns_val').html(`${icon} ${data.pronouns}`);
                    const isForumDisplayCheckedString = String(isForumDisplayChecked);
                    const capitalizedString = isForumDisplayCheckedString.charAt(0).toUpperCase() + isForumDisplayCheckedString.slice(1);
                    $('#display_pronouns_val').html(`${icon} ${capitalizedString}`);

                    // update the data attributes
                    pronouns.data('current-pronouns', data.pronouns);
                    $('#edit-pronouns-form').hide();
                }
                else {
                    // eslint-disable-next-line no-undef
                    displayErrorMessage(response.message);
                }
            },
            error: function () {
                // eslint-disable-next-line no-undef
                displayErrorMessage('Some went wrong while updating pronouns!');
            },
        });
    }
}

// update user name order and display name order option
function updateDisplayNameOrder(e) {
    // update user name order
    e.preventDefault();
    const displayNameOrder = $('#display-name-order-change');
    displayNameOrderLast = displayNameOrder.val();

    const data = new FormData();
    // eslint-disable-next-line no-undef
    data.append('csrf_token', csrfToken);
    data.append('display-name-order', displayNameOrder.val());
    const url = buildUrl(['user_profile', 'change_display_name_order']);
    $.ajax({
        url: url,
        type: 'POST',
        data: data,
        processData: false,
        contentType: false,
        success: function (res) {
            const response = JSON.parse(res);
            if (response.status === 'success') {
                const { data } = response;
                displaySuccessMessage(data.message);
                const icon = '<i class="fas fa-pencil-alt"></i>';
                if (data['display-name-order'] === 'GIVEN_F') {
                    $('#display_name_order_val').html(`${icon} Given Name First`);
                }
                else {
                    $('#display_name_order_val').html(`${icon} Family Name First`);
                }

                // update the data attributes
                displayNameOrder.data('current-display-name-order', data['display-name-order']);
                $('#edit-display-name-order-form').hide();
            }
            else {
                displayErrorMessage(response.message);
            }
        },
        error: function () {
            displayErrorMessage('Some went wrong while updating name order!');
        },
    });
}

function updateUserPreferredNames() {
    const given_name_field = $('#user-givenname-change');
    const family_name_field = $('#user-familyname-change');
    // If the names are not updated just display an error message and return without making any API call
    if (given_name_field.data('current-name') === given_name_field.val() && family_name_field.data('current-name') === family_name_field.val()) {
        // eslint-disable-next-line no-undef
        displayErrorMessage('No changes detected to update preferred names!');
    }
    else {
        const data = new FormData();
        // eslint-disable-next-line no-undef
        data.append('csrf_token', csrfToken);
        data.append('given_name', given_name_field.val());
        data.append('family_name', family_name_field.val());
        // eslint-disable-next-line no-undef
        const url = buildUrl(['user_profile', 'change_preferred_names']);
        $.ajax({
            url,
            type: 'POST',
            data,
            processData: false,
            contentType: false,
            success: function (res) {
                const response = JSON.parse(res);
                if (response.status === 'success') {
                    const { data } = response;
                    // eslint-disable-next-line no-undef
                    displaySuccessMessage(data.message);
                    // update the preferred names
                    const icon = '<i class="fas fa-pencil-alt"></i>';
                    $('#givenname-row .icon').html(`${icon} ${data.displayed_given_name}`);
                    $('#familyname-row .icon').html(`${icon} ${data.displayed_family_name}`);
                    // update the data attributes
                    given_name_field.data('current-name', data.preferred_given_name);
                    family_name_field.data('current-name', data.preferred_family_name);
                    // Update abbreviated name
                    $('#user-last-initial-format-preview').data('options', data.abbreviation_options);
                    $('#user-last-initial-format-preview').text(data.current_abbreviation);
                    $('#last-initial-format-row .icon').html(`${icon} ${data.current_abbreviation}`);
                }
                else {
                    // eslint-disable-next-line no-undef
                    displayErrorMessage(response.message);
                }
            },
            error: function () {
                // display error message
                // eslint-disable-next-line no-undef
                displayErrorMessage('Some went wrong while updating preferred names!');
            },
        });
    }
    // hide the form form view
    $('.popup-form').css('display', 'none');
    return false;
}

function updateUserLastInitialFormat() {
    const newVal = Number($('#user-last-initial-format-change').val());
    if (isNaN(newVal)) {
        return displayErrorMessage('Invalid option for last initial format!');
    }
    const data = new FormData();
    data.append('csrf_token', $('#user-last-initial-format-csrf').val());
    data.append('format', newVal);
    const url = buildUrl(['user_profile', 'update_last_initial_format']);
    $.ajax({
        url,
        type: 'POST',
        data,
        processData: false,
        contentType: false,
        success: function (res) {
            const response = JSON.parse(res);
            if (response.status === 'success') {
                const { data } = response;
                displaySuccessMessage(data.message);
                const icon = '<i class="fas fa-pencil-alt"></i>';
                $('#last-initial-format-row .icon').html(`${icon} ${data.new_abbreviated_name}`);
                $('#user-last-initial-format-change').data('default', data.format);
            }
            else {
                displayErrorMessage(response.message);
            }
        },
        error: function () {
            displayErrorMessage('Something went wrong!');
        },
    });
    // hide the form form view
    $('.popup-form').css('display', 'none');
    return false;
}

function updateUserProfilePhoto() {
    const data = new FormData();
    data.append('csrf_token', $('#user-profile-photo-csrf').val());
    data.append('user_image', $('#user-image-button').prop('files')[0]);
    // eslint-disable-next-line no-undef
    const url = buildUrl(['user_profile', 'change_profile_photo']);

    $.ajax({
        url,
        type: 'POST',
        data,
        processData: false,
        contentType: false,
        success: function (res) {
            // display success message
            const response = JSON.parse(res);

            if (response.status === 'success') {
                const { data } = response;
                // eslint-disable-next-line no-undef
                displaySuccessMessage(data.message);
                let updated_element = '<span class="center-img-tag">N/A</span>';
                // create a new image node
                if (data.image_data && data.image_mime_type) {
                    updated_element = `<img src="data:${data.image_mime_type};base64,${data.image_data}" alt="${data.image_alt_data}"/>`;
                }
                // check whether the image flag status is updated
                data.image_flagged_state === 'flagged'
                    ? $('#flagged-message').addClass('show')
                    : $('#flagged-message').removeClass('show');
                $('.user-img-cont').html(updated_element);
            }
            else {
                // eslint-disable-next-line no-undef
                displayErrorMessage(response.message);
            }
        },
        error: function () {
            // display error message
            // eslint-disable-next-line no-undef
            displayErrorMessage('Some went wrong while updating profile photo!');
        },
    });
    // hide the form from view
    $('.popup-form').css('display', 'none');
    $('#user-image-button').val(null);
    return false;
}

function updateUserSecondaryEmail() {
    const second_email = $('#user-secondary-email-change');
    const second_email_notify = $('#user-secondary-email-notify-change');
    if (second_email.data('current-second-email') === second_email.val() && second_email_notify.get(0).checked === (second_email_notify.data('current-second-email-notify') === 1)) {
        // eslint-disable-next-line no-undef
        displayErrorMessage('No changes detected to secondary email');
    }
    else {
        if (second_email.val() === '' && second_email_notify.get(0).checked) {
            // eslint-disable-next-line no-undef
            displayErrorMessage('Please disable second email notifications or add a valid second email');
        }
        else {
            const data = new FormData();
            // eslint-disable-next-line no-undef
            data.append('csrf_token', csrfToken);
            data.append('secondary_email', second_email.val());
            data.append('secondary_email_notify', second_email_notify.get(0).checked);
            // eslint-disable-next-line no-undef
            const url = buildUrl(['user_profile', 'change_secondary_email']);
            $.ajax({
                url,
                type: 'POST',
                data,
                processData: false,
                contentType: false,
                success: function (res) {
                    const response = JSON.parse(res);
                    if (response.status === 'success') {
                        const { data } = response;
                        // eslint-disable-next-line no-undef
                        displaySuccessMessage(data.message);
                        const icon = '<i class="fas fa-pencil-alt"></i>';
                        $('#secondary-email-row .icon').html(`${icon} ${data.secondary_email}`);
                        $('#secondary-email-notify-row .icon').html(`${icon} ${data.secondary_email_notify}`);
                        second_email.data('current-second-email', data.secondary_email);
                        second_email_notify.data('current-second-email-notify', data.secondary_email_notify === 'True' ? 1 : 0);
                    }
                    else if (response.status === 'error') {
                        // eslint-disable-next-line no-undef
                        displayErrorMessage(response.message);
                    }
                },
                error: function () {
                    // display error message
                    // eslint-disable-next-line no-undef
                    displayErrorMessage('Something went wrong while updating secondary email address!');
                },
            });
        }
    }
    $('.popup-form').css('display', 'none');
    return false;
}

function changeSecondaryEmail() {
    const email = $('#user-secondary-email-change').val();
    const checkbox = $('#user-secondary-email-notify-change');

    if (email.length > 0) {
        checkbox.prop('disabled', false);
    }
    else {
        checkbox.prop('disabled', true);
        checkbox.prop('checked', false);
    }
}

function previewUserLastInitialFormat() {
    const format = Number($('#user-last-initial-format-change').val());
    const preview = $('#user-last-initial-format-preview');
    const options = preview.data('options').split('|');
    preview.text(options[format]);
}

$(document).ready(() => {
    $('#desktop_sidebar_preference').change(() => {
        // eslint-disable-next-line no-undef
        updateSidebarPreference();
        location.reload();
    });
    if (localStorage.getItem('desktop-sidebar-preference')) {
        if (localStorage.getItem('desktop-sidebar-preference') === 'automatic') {
            // eslint-disable-next-line no-undef
            CollapseSidebarOnNarrowView();
        }
        else {
            // eslint-disable-next-line no-undef
            DisableAutomaticCollapse();
        }
    }

    $('#theme_change_select').change(() => {
        // eslint-disable-next-line no-undef
        updateTheme();
        if (JSON.parse(localStorage.getItem('rainbow-mode')) === true) {
            $(document.body).find('#rainbow-mode').remove();
            localStorage.removeItem('rainbow-mode');
            const theme_picker = $('#theme_change_select');
            theme_picker.find('[value="rainbow"]').remove();
        }
    });

    if (JSON.parse(localStorage.getItem('rainbow-mode')) === true) {
        const theme_picker = $('#theme_change_select');
        theme_picker.append('<option value="rainbow" selected="selected">Rainbow Mode</option>');
    }

    if ($('#flagged-message').data('flagged') === 'flagged') {
        $('#flagged-message').addClass('show');
    }

    /* adding select2 to the time_zone_drop_down element */
    $('#time_zone_drop_down').select2({ theme: 'bootstrap-5' });

    // Populate the time zone selector box with options
    const availableTimeZones = getAvailableTimeZones();
    availableTimeZones.forEach((elem) => {
        $('#time_zone_drop_down').append(`<option value="${elem}">${elem}</option>`);
    });

    $('#time_zone_drop_down').change(function () {
        const timeZoneWithOffset = $(this).children('option:selected').val();
        // extract out the time_zone from the timezone with utc offset
        const time_zone = timeZoneWithOffset === 'NOT_SET/NOT_SET' ? timeZoneWithOffset : timeZoneWithOffset.split(') ')[1];

        $.getJSON({
            type: 'POST',
            // eslint-disable-next-line no-undef
            url: buildUrl(['user_profile', 'change_time_zone']),
            data: {
                // eslint-disable-next-line no-undef
                csrf_token: csrfToken,
                time_zone,
            },
            success: function (response) {
                // Update page elements if the data was successfully saved server-side
                if (response.status === 'success') {
                    $('#user_utc_offset').text(response.data.utc_offset);
                    $('#time_zone_selector_label').attr('data-user_time_zone', response.data.user_time_zone_with_offset);
                    // eslint-disable-next-line no-undef
                    displaySuccessMessage('Time-zone updated successfully!');

                    // Check user's current time zone, give a warning message if the user's current time zone differs from systems' time-zone
                    const offset = getCurrentUTCOffset();
                    if (response.data.utc_offset !== offset) {
                        // eslint-disable-next-line no-undef
                        displayWarningMessage('Selected time-zone does not match system time-zone.');
                    }
                }
                else {
                    console.log(response);
                    // eslint-disable-next-line no-undef
                    displayErrorMessage('Time-zone is not updated!');
                }
            },
            error: function (response) {
                console.error('Failed to parse response from server!');
                // eslint-disable-next-line no-undef
                displayErrorMessage('Failed to parse response from server!');
                console.log(response);
            },
        });
    });

    $('#user-image-button').bind('change', function () {
        if ((this.files[0].size / 1048576) > 5.0) {
            alert("Selected file's size exceeds 5 MB");
            $('#user-image-button').val('');
        }
    });

    // Set time zone drop down boxes to the user's time zone (only after other JS has finished loading)
    const user_time_zone = $('#time_zone_selector_label').data('user_time_zone');
    $(`[value="${user_time_zone}"]`).prop('selected', true);

    $('#pref_locale_select').on('change', function () {
        $.getJSON({
            type: 'POST',
            url: buildUrl(['user_profile', 'set_pref_locale']),
            data: {
                // eslint-disable-next-line no-undef
                csrf_token: csrfToken,
                locale: $(this).val(),
            },
            success: function (response) {
                if (response.status === 'success') {
                    displaySuccessMessage('User locale updated successfully!');
                    location.reload();
                }
                else {
                    console.log(response);
                    displayErrorMessage('Failed to update user locale!');
                }
            },
            error: function (response) {
                console.error('Failed to parse response from server!');
                displayErrorMessage('Failed to parse response from server!');
                console.log(response);
            },
        });
    });
    document.addEventListener('scroll', () => {
        const dropdown = document.querySelector('.select2-container--open .select2-dropdown');
        if (dropdown) {
            // Close the dropdown menu
            $('#time_zone_drop_down').select2('close');
        }
    });
});
