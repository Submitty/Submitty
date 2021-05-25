/* exported openActionsPopup */
function openActionsPopup(popup_css, element_id) {
    let elem_html = `<link rel="stylesheet" type="text/css" href="${popup_css}" />`;
    elem_html += document.getElementById(element_id).innerHTML;
    const my_window = window.open('', '_blank', 'status=1,width=750,height=500');
    my_window.document.write(elem_html);
    my_window.document.close();
    my_window.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    let days, hours, mins, seconds = 0;
    let deadline = 0;
    let user_deadline = 0;
    let startTime = 0;
    let width = 0;
    let allowedTime = 0;
    syncDeadline();

    function syncDeadline() {
        const url = `${window.location}/time_remaining_data`;
        $.ajax({
            url,
            type: 'GET',
            processData: false,
            contentType: false,
            success: function(res) {
                const response = JSON.parse(res);
                if (response.status === 'success') {
                    const { data } = response;
                    if (Object.prototype.hasOwnProperty.call(data, 'user_allowed_time_deadline')) {
                        user_deadline = data.user_allowed_time_deadline;
                    }
                    if (Object.prototype.hasOwnProperty.call(data, 'user_allowed_time')) {
                        allowedTime = data.user_allowed_time;
                    }
                    if (Object.prototype.hasOwnProperty.call(data, 'user_start_time')) {
                        startTime = data.user_start_time;
                    }
                    if (user_deadline !== 0) {
                        if (document.getElementById('time-remaining-text') !== null) {
                            updateUserTime();
                        }
                    }
                    deadline = data.deadline;
                    if (document.getElementById('gradeable-time-remaining-text') !== null) {
                        updateGradeableTime();
                    }
                }
                else {
                    // eslint-disable-next-line no-undef
                    displayErrorMessage('Something went wrong while starting the timer');
                }
            },
            error: function(err) {
                console.log(err);
            },
        });
    }

    function updateUserTime() {
        if (Date.now() > user_deadline) {
            document.getElementById('time-remaining-text').textContent = 'Your Time Remaining: Past Due';
            document.getElementById('gradeable-progress-bar').style.backgroundColor = 'var(--alert-danger-red)';
            document.getElementById('gradeable-progress-bar').style.width = '100%';
        }
        else {
            const time = Math.floor((user_deadline - Date.now())/1000);
            seconds = time % 60;
            mins = Math.floor(time / 60) % 60;
            hours = Math.floor(time / 3600) % 24;
            days = Math.floor(time / (3600*24));
            width = ((Date.now() - startTime) / 1000 / 60 / allowedTime * 100) * 0.95 + 5;
            if (width > 75 && width < 90) {
                document.getElementById('gradeable-progress-bar').style.backgroundColor = 'var(--standard-vibrant-yellow)';
            }
            else if (width > 90) {
                document.getElementById('gradeable-progress-bar').style.backgroundColor = 'var(--alert-danger-red)';
            }
            document.getElementById('gradeable-progress-bar').style.width = `${width}%`;
            if (days > 0) {
                document.getElementById('time-remaining-text').textContent = `Your Time Remaining: ${days.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} days ${hours.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} hours`;
            }
            else if (hours > 0) {
                document.getElementById('time-remaining-text').textContent = `Your Time Remaining: ${hours.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} hours ${mins.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} mins`;
            }
            else {
                document.getElementById('time-remaining-text').textContent = `Your Time Remaining: ${mins.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} mins ${seconds.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} seconds`;
            }
            setTimeout(updateUserTime, 100);
        }
    }

    function updateGradeableTime() {
        if (Date.now() > deadline) {
            document.getElementById('gradeable-time-remaining-text').textContent = 'Gradeable Time Remaining: Past Due';
        }
        else {
            const time = Math.floor((deadline - Date.now())/1000);
            seconds = time % 60;
            mins = Math.floor(time / 60) % 60;
            hours = Math.floor(time / 3600) % 24;
            days = Math.floor(time / (3600*24));
            if (days > 0) {
                document.getElementById('gradeable-time-remaining-text').textContent = `Gradeable Time Remaining: ${days.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} days ${hours.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} hours`;
            }
            else if (hours > 0) {
                document.getElementById('gradeable-time-remaining-text').textContent = `Gradeable Time Remaining: ${hours.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} hours ${mins.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} mins`;
            }
            else {
                document.getElementById('gradeable-time-remaining-text').textContent = `Gradeable Time Remaining: ${mins.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} mins ${seconds.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} seconds`;
            }
        }
        setTimeout(updateGradeableTime, 100);
    }
});
