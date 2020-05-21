function userNameChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-username-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    $('[name="user_name_change"]', form).val("");
    $("#user-firstname-change").focus();
}

function passwordChange() {
    $('.popup-form').css('display', 'none');
    var form = $("#change-password-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    $('[name="new_password"]', form).val("");
    $('[name="confirm_new_password"]', form).val("");
    $("#new_password").focus();
}

function getAvailableTimeZones() {
    return $('#time_zone_selector_label').data('available_time_zones').split(',')
}

function getGeneralTimeZoneOptions() {
    let available_time_zones = getAvailableTimeZones();

    // Only interested in the 'general' area (the part before the first /)
    available_time_zones = available_time_zones.map(x => x.split('/', 1)[0]);

    return new Set(available_time_zones);
}

function getSpecificTimeZoneOptions(general_option) {
    let available_time_zones = getAvailableTimeZones();

    available_time_zones = available_time_zones.filter(
        x => x.split('/', 1)[0] === general_option
    )

    let str_to_strip = general_option + '/';
    available_time_zones = available_time_zones.map(x => x.replace(str_to_strip, ''));

    return new Set(available_time_zones);
}

function populateSpecificTimeZoneDropDown(general_selection, selected_option = null) {
    $('#time_zone_specific_drop_down').empty();

    let specific_area_set = getSpecificTimeZoneOptions(general_selection);

    $('#time_zone_specific_drop_down').append('<option></option>');

    specific_area_set.forEach(function(elem) {
        $('#time_zone_specific_drop_down').append('<option value='+elem+'>'+elem+'</option>');
    });

    if(selected_option !== null) {
        $('[value=' + selected_option + ']').prop('selected', true);
    }
}

function updateUTCOffsetText(text) {
    if(text === 'NOT_SET/NOT_SET') {
        $('#utc_offset_stub').hide();
    } else {
        $('#utc_offset_stub').show();
        $('#user_utc_offset').text(text);
    }
}

$(document).ready(function() {

    // Populate the general time zone selector box with options
    let general_area_set = getGeneralTimeZoneOptions();
    general_area_set.forEach(function(elem) {
        $('#time_zone_general_drop_down').append('<option value='+elem+'>'+elem+'</option>');
    });

    // Populate specific area time zone selector box when the general one has detected a change
    $('#time_zone_general_drop_down').change(function() {
        let selected_elem = $(this).children(':selected')[0].innerHTML
        populateSpecificTimeZoneDropDown(selected_elem);
    });

    // Any changes to the specific time zone dropdown should be saved server-side
    $('#time_zone_specific_drop_down').change(function() {

        let general_area = $('#time_zone_general_drop_down').children(':selected')[0].innerHTML;
        let specific_area = $(this).children(':selected')[0].innerHTML;
        let time_zone = general_area + '/' + specific_area

        $.getJSON({
            type: "POST",
            url: buildUrl(['current_user', 'change_time_zone']),
            data: {
                csrf_token: csrfToken,
                time_zone: time_zone
            },
            success: function (response) {
                if (response.status === 'success') {
                    updateUTCOffsetText(response.data.utc_offset);
                }
                else {
                    console.log(response);
                }
            },
            error: function (response) {
                console.error('Failed to parse response from server!');
                console.log(response);
            }
        });
    });

    // Set time zone drop down boxes to the user's time zone (only after other JS has finished loading)
    $(document).ready(function() {
        let user_time_zone =  $('#time_zone_selector_label').data('user_time_zone');
        let general_area = user_time_zone.split('/', 1)[0];
        let specific_area = user_time_zone.replace(general_area + '/', '');

        $('[value=' + general_area + ']').prop('selected', true);
        populateSpecificTimeZoneDropDown(general_area, specific_area);
    });
});
