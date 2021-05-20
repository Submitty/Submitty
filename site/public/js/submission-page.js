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
    let url  = '';
    let days, hours, mins, seconds = 0;
    let deadline = 0;
    if (document.getElementById('time_remaining_text') !== null) {
        url = `${window.location}/time_remaining_data`;
        syncDeadline();
    }

    function syncDeadline() {
        $.ajax({
            url,
            type: 'GET',
            processData: false,
            contentType: false,
            success: function(res) {
                const response = JSON.parse(res);
                if (response.status === 'success') {
                    const { data } = response;
                    deadline = data.deadline;
                    updateTime();
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

    function updateTime() {
        if (Date.now() > deadline) {
            document.getElementById('time_remaining_text').textContent = 'Time Left until Due: Past Due';
        }
        else {
            const time = Math.floor((deadline - Date.now())/1000);
            seconds = time % 60;
            mins = Math.floor(time / 60) % 60;
            hours = Math.floor(time / 3600) % 24;
            days = Math.floor(time / (3600*24));
            if (days > 0) {
                document.getElementById('time_remaining_text').textContent = `Time Left until Due: ${days.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} days ${hours.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} hours`;
            }
            else if (hours > 0) {
                document.getElementById('time_remaining_text').textContent = `Time Left until Due: ${hours.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} hours ${mins.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} mins`;
            }
            else if (mins > 0) {
                document.getElementById('time_remaining_text').textContent = `Time Left until Due: ${mins.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} mins ${seconds.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} seconds`;
            }
            else {
                document.getElementById('time_remaining_text').textContent = `Time Left until Due: ${mins.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} mins ${seconds.toLocaleString('en-US', {minimumIntegerDigits: 2, useGrouping: false})} seconds`;
                document.getElementById('time_remaining_text').classList.add('timer-under-min');
            }
            setTimeout(updateTime, 1000);
        }
    }
});
