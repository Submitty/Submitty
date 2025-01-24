/* exported toggleSort, displayStudentHistory */
/* global buildCourseUrl, displayErrorMessage */
// this helps update the frontend when the page refreshes because without this the sort icon would reset and the sort state would not
document.addEventListener('DOMContentLoaded', () => {
    const sortIndicator = document.getElementById('sort-indicator-oh-queue');
    let sortState = localStorage.getItem('sort-indicator-oh-queue');

    if (sortState === null) {
        sortState = 'off';
        localStorage.setItem('sort-indicator-oh-queue', sortState);
    }

    if (sortState === 'up') {
        sortIndicator.classList.add('fas', 'fa-sort-up');
    }
    else if (sortState === 'off') {
        sortIndicator.classList.add('fa-solid', 'fa-sort');
    }
    else if (sortState === 'down') {
        sortIndicator.classList.add('fas', 'fa-sort-down');
    }

    adjustRows();
});

function displayStudentHistory() {
    $.ajax({
        url: buildCourseUrl(['queue', 'student_search']),
        type: 'POST',
        data: {
            student_id: $('#search-student-queue-input').val(),
            // eslint-disable-next-line no-undef
            csrf_token: csrfToken,
        },
        success: function (response_str) {
            const table_body = $('#search-student-tbody').empty();
            const response = JSON.parse(response_str);
            if (response.status === 'fail') {
                displayErrorMessage(response.message);
            }

            const data = response.data;
            const student_data = JSON.parse(data);
            $('#student-queue-table caption').text(`${student_data[0].name} - (ID:${student_data[0].user_id}) - Contact: ${student_data[0].contact_info}`);

            let help_counter = 0;
            student_data.forEach((student, i) => {
                if (student.removal_type === 'helped') {
                    help_counter++;
                }
                const time_start = student.time_in;
                const time_end = student.time_out === null ? '-' : student.time_out;
                const removed_by = student.removed_by === null ? '-' : student.removed_by;
                const helper = student.help_started_by === null ? '-' : student.help_started_by;
                const removal_method = student.removal_type === null ? '-' : student.removal_type;

                table_body.append($('<tr></tr>').attr('data-testid', `student-row-${i + 1}`)
                    .append($('<td></td>').attr('data-testid', 'row-label').text(i + 1))
                    .append($('<td></td>').attr('data-testid', 'current-state').text(student.current_state))
                    .append($('<td></td>').attr('data-testid', 'queue').text(student.queue_code))
                    .append($('<td></td>').attr('data-testid', 'time-entered').text(time_start))
                    .append($('<td></td>').attr('data-testid', 'time-removed').text(time_end))
                    .append($('<td></td>').attr('data-testid', 'helped-by').text(helper))
                    .append($('<td></td>').attr('data-testid', 'removed-by').text(removed_by))
                    .append($('<td></td>').attr('data-testid', 'removal-method').text(removal_method)));
            });

            table_body.append($('<tr></tr>')
                .attr('id', 'times-helped-row')
                .append($('<td></td>')
                    .attr('id', 'times-helped-cell')
                    .attr('colspan', '8')
                    .text(`${help_counter} times helped.`)));
        },
        error: function () {
            window.alert('Something went wrong while searching for students!');
        },
    });
}

function toggleSort(column) {
    const sortIndicator = $('#sort-indicator-oh-queue');
    if (column === 'HelpStartedBy') {
        if (localStorage.getItem('sort-indicator-oh-queue') === 'off') {
            localStorage.setItem('sort-indicator-oh-queue', 'up');
            sortIndicator.attr('class', 'fas fa-sort-up');
        }
        else if (localStorage.getItem('sort-indicator-oh-queue') === 'up') {
            localStorage.setItem('sort-indicator-oh-queue', 'down');
            sortIndicator.attr('class', 'fas fa-sort-down');
        }
        else if (localStorage.getItem('sort-indicator-oh-queue') === 'down') {
            localStorage.setItem('sort-indicator-oh-queue', 'off');
            sortIndicator.attr('class', 'fa-solid fa-sort');
        }
    }
    adjustRows();
}
function adjustRows() {
    const rows = $('.queue_history_row').toArray();
    rows.sort((a, b) => {
        if (localStorage.getItem('sort-indicator-oh-queue') === 'up') {
            return $(a).find('.help-started').text() > $(b).find('.help-started').text() ? -1 : 1;
        }
        else if (localStorage.getItem('sort-indicator-oh-queue') === 'down') {
            return $(a).find('.help-started').text() > $(b).find('.help-started').text() ? 1 : -1;
        }
        else {
            return parseInt($(a).find('.number-count').text()) < parseInt($(b).find('.number-count').text()) ? -1 : 1;
        }
    });
    $('#queue-history-table').empty();
    for (let i = 0; i < rows.length; i++) {
        $('#queue-history-table').append($(rows[i]));
    }
}
