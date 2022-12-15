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

// once the homepage loads, does a timezone check against the user's local timezone and
//if the user set a timezone,  warning will pop up if there is a timezone mismatch
$(document).ready(() => {
    console.log('home page loaded');

    $.getJSON({
        type: 'GET',
        // eslint-disable-next-line no-undef
        url: buildUrl(['home', 'get_user_time_zone']),
        success: function (response) {
            // Update page elements if the data was successfully saved server-side
            if (response.status === 'success') {
                const users_utc = response.data.utc_offset;
                const current_offset = getCurrentUTCOffset();
                // Check user's current time zone, give a warning message if the user's current time zone differs from systems' time-zone
                if (users_utc !== current_offset && users_utc !== 'NOT SET') {
                // eslint-disable-next-line no-undef
                    displayWarningMessage('Set time-zone on your profile does not match system time-zone. Please update to prevent any issues!');
                }
            }
            else {
                console.log(response);
            }
        },
        error: function (response) {
            console.log(response);
        },
    });
});
