/* exported openActionsPopup */
/* exported initializeTimer */
function openActionsPopup(popup_css, element_id) {
    let elem_html = `<link rel="stylesheet" type="text/css" href="${popup_css}" />`;
    elem_html += document.getElementById(element_id).innerHTML;
    const my_window = window.open('', '_blank', 'status=1,width=750,height=500');
    my_window.document.write(elem_html);
    my_window.document.close();
    my_window.focus();
}

let days, hours, mins, seconds = 0;
let curTime = 0;
let lastTime = 0;
let deadline = 0;
let user_deadline = 0;
let startTime = 0;
let width = 0;
let allowedTime = 0;
let gradeable_id = '';
let ticks_till_update = 600000;
let popUpTimerStarted = false;
let isTimed = false;

function initializeTimer(gradeableID, is_timed) {
    gradeable_id = gradeableID;
    isTimed = is_timed;
    syncWithServer(true);
}

function syncWithServer(criticalSync) {
    // eslint-disable-next-line no-undef
    const url = buildCourseUrl(['gradeable', gradeable_id, 'time_remaining_data']);
    $.ajax({
        url,
        type: 'GET',
        processData: false,
        contentType: false,
        success: function(res) {
            lastTime = Date.now();
            const response = JSON.parse(res);
            ticks_till_update = 600000;
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
                curTime = data.current_time;
                deadline = data.deadline;
                updateTime();
                if (!popUpTimerStarted && isTimed && allowedTime > 25) {
                    // eslint-disable-next-line no-undef
                    initializePopupTimer();
                    popUpTimerStarted = true;
                }
            }
            else {
                // eslint-disable-next-line no-undef
                displayErrorMessage('Something went wrong while starting the timer');
            }
        },
        error: function (err) {
            ticks_till_update = 600000;
            console.log(err);
            if (!criticalSync) {
                updateTime();
            }
            else {
                if (document.getElementById('gradeable-time-remaining-text') !== null) {
                    document.getElementById('gradeable-time-remaining-text').textContent = 'Timer Error. Please refresh to restart.';
                }
                if (user_deadline !== 0) {
                    if (document.getElementById('time-remaining-text') !== null) {
                        document.getElementById('time-remaining-text').textContent = 'Timer Error. Please refresh to restart.';
                    }
                }
            }
        },
    });
}

function updateTime() {
    if (Math.abs(Date.now() - lastTime) > 5000) {
        //we need to sync back up
        syncWithServer(true);
    }
    if (ticks_till_update <= 0) {
        syncWithServer(false);
    }
    else {
        curTime += (Date.now()-lastTime);
        ticks_till_update -= (Date.now()-lastTime);
        lastTime = Date.now();
        if (document.getElementById('gradeable-time-remaining-text') !== null) {
            if (curTime > deadline) {
                document.getElementById('gradeable-time-remaining-text').textContent = 'Gradeable Time Remaining: Past Due';
            }
            else {
                const time = Math.floor((deadline - curTime)/1000);
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
        }
        if (user_deadline !== 0) {
            if (document.getElementById('time-remaining-text') !== null) {
                if (curTime > user_deadline) {
                    document.getElementById('time-remaining-text').textContent = 'Your Time Remaining: Past Due';
                    document.getElementById('gradeable-progress-bar').style.backgroundColor = 'var(--alert-danger-red)';
                    document.getElementById('gradeable-progress-bar').style.width = '100%';
                }
                else {
                    const time = Math.floor((user_deadline - curTime)/1000);
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
                }
            }
        }
        setTimeout(updateTime, 100);
    }
}
