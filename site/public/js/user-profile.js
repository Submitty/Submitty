function showUpdatePrefNameForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-username-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    $('[name="user_name_change"]', form).val("");
    $("#user-firstname-change").focus();
}

function showUpdatePasswordForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#change-password-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    $('[name="new_password"]', form).val("");
    $('[name="confirm_new_password"]', form).val("");
    $("#new_password").focus();
}

function showUpdateProfilePhotoForm() {
  $('.popup-form').css('display', 'none');
  var form = $("#edit-profile-photo-form");
  form.css("display", "block");
  form.find('.form-body').scrollTop(0);
}

function showUpdateSecondaryEmailForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-secondary-email-form")
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
}

/**
 * Gets the list of all available time zones as an array
 * @returns {string[]}
 */
function getAvailableTimeZones() {
    return $('#time_zone_selector_label').data('available_time_zones').split(',')
}

function updateUserPreferredNames () {
  const first_name_field = $("#user-firstname-change");
  const last_name_field = $("#user-lastname-change");
  // If the names are not updated just display an error message and return without making any API call
  if (first_name_field.data('current-name') === first_name_field.val() && last_name_field.data('current-name') === last_name_field.val()) {
    displayErrorMessage('No changes detected to update preferred names!');
  }
  else {
    let data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('first_name', first_name_field.val());
    data.append('last_name', last_name_field.val());
    let url = buildUrl(['user_profile', 'change_preferred_names']);
    $.ajax({
      url,
      type: "POST",
      data,
      processData: false,
      contentType: false,
      success: function(res) {
        const response = JSON.parse(res);
        if (response.status === "success") {
          const {data} = response;
          displaySuccessMessage(data.message);
          //update the preferred names
          $("#firstname-row .value").text(data.first_name);
          $("#lastname-row .value").text(data.last_name);
          //update the data attributes
          first_name_field.data('current-name', data.first_name);
          last_name_field.data('current-name', data.last_name);
        } else {
          displayErrorMessage(response.message);
        }
      },
      error: function() {
        // display error message
        displayErrorMessage("Some went wrong while updating preferred names!");
      }
    });
  }
  // hide the form form view
  $('.popup-form').css('display', 'none');
  return false;
}

function updateUserProfilePhoto () {
  let data = new FormData();
  data.append('csrf_token', $("#user-profile-photo-csrf").val());
  data.append('user_image', $("#user-image-button").prop('files')[0]);
  let url = buildUrl(['user_profile', 'change_profile_photo']);

  $.ajax({
    url,
    type: "POST",
    data,
    processData: false,
    contentType: false,
    success: function(res) {
      //display success message
      const response = JSON.parse(res);

      if (response.status === "success") {
        const { data } = response;
        displaySuccessMessage(data.message);
        let updated_element = '<span class="center-img-tag">N/A</span>';
        // create a new image node
        if (data.image_data && data.image_mime_type) {
          updated_element = `<img src="data:${data.image_mime_type};base64,${data.image_data}" alt="${data.image_alt_data}"/>`;
        }
        // check whether the image flag status is updated
        data.image_flagged_state === "flagged" ?
          $("#flagged-message").addClass("show")
          : $("#flagged-message").removeClass("show");
        $(".user-img-cont").html(updated_element);
      } else {
        displayErrorMessage(response.message);
      }
    },
    error: function() {
      // display error message
      displayErrorMessage("Some went wrong while updating profile photo!");
    }
  });
  // hide the form from view
  $('.popup-form').css('display', 'none');
  $('#user-image-button').val(null);
  return false;
}

function updateUserSecondaryEmail () {
    const second_email = $('#user-secondary-email-change');
    const second_email_notify = $('#user-secondary-email-notify-change');
    if (second_email.data('current-second-email') === second_email.val() && second_email_notify.get(0).checked === (second_email_notify.data('current-second-email-notify') === 1)) {
        displayErrorMessage('No changes detected to secondary email');
    }
    else {
        let data = new FormData();
        data.append('csrf_token', csrfToken);
        data.append('secondary_email', second_email.val());
        data.append('secondary_email_notify', second_email_notify.get(0).checked);
        let url = buildUrl(['user_profile', 'change_secondary_email']);
        $.ajax({
            url,
            type: "POST",
            data,
            processData: false,
            contentType: false,
            success: function(res) {
                const response = JSON.parse(res);
                if (response.status === "success") {
                    const { data } = response;
                    displaySuccessMessage(data.message);
                    $('#secondary-email-row .value').text(data.secondary_email);
                    $('#secondary-email-notify-row .value').text(data.secondary_email_notify);
                    second_email.data('current-second-email', data.secondary_email);
                    second_email_notify.data('current-second-email-notify', data.secondary_email_notify === "True" ? 1 : 0);
                }
                else if (response.status === "error") {
                    displayErrorMessage("Some went wrong while updating secondary email address!");
                    second_email.val(second_email.data('current-second-email'));
                    second_email_notify.prop('checked', second_email_notify.data('current-second-email-notify'));
                }
            },
            error: function() {
                // display error message
                displayErrorMessage("Some went wrong while updating secondary email address!");
            }
        });
    }
    $('.popup-form').css('display', 'none');
    return false;
}

$(document).ready(function() {

    $('#theme_change_select').change(function() {
        updateTheme();
    });

    if ($('#flagged-message').data('flagged') === "flagged") {
      $('#flagged-message').addClass('show');
    }

    // Populate the time zone selector box with options
    let availableTimeZones = getAvailableTimeZones();
    availableTimeZones.forEach(function(elem) {
        $('#time_zone_drop_down').append(`<option value="${elem}">${elem}</option>`);
    });

    $('#time_zone_drop_down').change(function() {
        let timeZoneWithOffset = $(this).children('option:selected').val();
        // extract out the time_zone from the timezone with utc offset
        let time_zone = timeZoneWithOffset === "NOT_SET/NOT_SET" ? timeZoneWithOffset : timeZoneWithOffset.split(') ')[1];

        $.getJSON({
            type: "POST",
            url: buildUrl(['user_profile', 'change_time_zone']),
            data: {
                csrf_token: csrfToken,
                time_zone
            },
            success: function (response) {
                // Update page elements if the data was successfully saved server-side
                if (response.status === 'success') {
                    $('#user_utc_offset').text(response.data.utc_offset);
                    $('#time_zone_selector_label').attr('data-user_time_zone', response.data.user_time_zone_with_offset);
                    displaySuccessMessage("Time-zone updated succesfully!");
                }
                else {
                    console.log(response);
                    displayErrorMessage("Time-zone is not updated!");
                }
            },
            error: function (response) {
                console.error('Failed to parse response from server!');
                displayErrorMessage('Failed to parse response from server!');
                console.log(response);
            }
        });
    });

    // Set time zone drop down boxes to the user's time zone (only after other JS has finished loading)
    let user_time_zone =  $('#time_zone_selector_label').data('user_time_zone');
    $('[value="' + user_time_zone + '"]').prop('selected', true);
});
