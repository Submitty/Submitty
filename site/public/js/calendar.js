/**
 * Gets the previous month of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @returns {number[]} : array<int> {previous_month, year_of_previous_month}
 */
// eslint-disable-next-line no-unused-vars
function prevMonth(month, year) {
    month = month - 1;
    if (month <= 0) {
        month = 12 + month;
        year = year - 1;
    }
    return [month, year];
}

/**
 * Gets the next month of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @returns {number[]} : array<int> {next_month, year_of_next_month}
 */
// eslint-disable-next-line no-unused-vars
function nextMonth(month, year) {
    month = month + 1;
    if (month > 12) {
        month = month - 12;
        year = year + 1;
    }
    return [month, year];
}

/**
 * Creates a HTML table cell that contains a date.
 *
 * @param year : int the year of the date
 * @param month : int the month of the date (1 as January and 12 as December)
 * @param day : int the date of the date (1 - 31)
 * @param curr_view_month : int the current month that the calendar is viewing
 * @returns {string} the HTML string containing the cell
 */
function displayDayCell(year, month, day, curr_view_month) {
    const cell_date_str = `${year.toString()}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
    let content = `<td class="cal-day-cell" id=${cell_date_str}>
    <div>`;
    // Title of the day cell
    content += '<div>';
    if (month === curr_view_month) {
        // eslint-disable-next-line no-undef
        if (day === curr_day && month === curr_month && year === curr_year) {
            content += `<span class="cal-curr-month-date cal-day-title cal-today-title">${day}</span>`;
        }
        else {
            content += `<span class="cal-curr-month-date cal-day-title">${day}</span>`;
        }
    }
    else {
        if (month > 12) {
            month = month % 12;
        }
        else if (month <= 0) {
            month = month + 12;
        }
        content += `<span class="cal-next-month-date cal-day-title">${month}/${day}</span>`;
    }
    content += '</div>';
    // List all gradeables of other items
    content += '<div class="cal-cell-items-panel">';
    // eslint-disable-next-line no-undef
    for (const i in gradeables_by_date[cell_date_str]) {
        // eslint-disable-next-line no-undef
        const gradeable = gradeables_by_date[cell_date_str][i];
        const due_time = gradeable['submission'] !== '' ? new Date(gradeable['submission']['date']) : '';
        let due_string = '';
        if (due_time !== '') {
            due_string = `Due ${(due_time.getMonth() + 1)}/${(due_time.getDate())}/${due_time.getFullYear()} @ ${due_time.getHours()}:${due_time.getMinutes()} ${gradeable['submission']['timezone']}`;
        }
        content += `
      <a class="cal-gradeable-item cal-gradeable-status-${gradeable['status']}"
         title="Course: ${gradeable['course']}&#10;${gradeable['title']}&#10;${due_string}"
         href="${gradeable['url']}">
        ${gradeable['title']}
      </a>`;
    }
    content += `
    </div>
  </div>
</td>
    `;
    return content;
}

// List of names of months in English
const monthNames = ['December', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/**
 * This function creates a table that shows the calendar.
 *
 * @param view_year : int year that the calendar is viewing
 * @param view_month : int month that the calendar is viewing (1 as January and 12 as December)
 * @returns {string} the HTML string contains the entire calendar table displaying view_month/view_year
 */
function showCalendar(view_year, view_month) {
    const startWeekday = new Date(view_year, view_month - 1, 1).getDay();
    // Header area: two buttons to move, and month
    let content = `  
    <table class='table table-striped table-bordered persist-area table-calendar'>
        <thead>
        
        <tr class="navigation">
            <th style='text-align: center'>
                <a class="prev" onclick="loadCalendar.apply(this, prevMonth(${view_month}, ${view_year}))">&#60;</a>
            </th>
            <th colspan="5" class="cal-month-title">
                <div class="title" >${monthNames[view_month]}, ${view_year}</div>
            </th>
            <th style='text-align: center'>
                <a class="next" onclick="loadCalendar.apply(this, nextMonth(${view_month}, ${view_year}))">&#62;</a>
            </th>
        </tr>
        <tr class='cal-week-title-row'>
            <th width="12%">Sunday</th>
            <th width="15%">Monday</th>
            <th width="15%">Tuesday</th>
            <th width="16%">Wednesday</th>
            <th width="15%">Thursday</th>
            <th width="15%">Friday</th>
            <th width="12%">Saturday</th>
        </tr>
        </thead>
        <tbody>
        <tr>`;

    // Show days at the end of last month that belongs to the first week of current month
    if (startWeekday !== 0) {
        const lastMonthEnd = new Date(view_year, view_month - 1, 0).getDate();
        const lastMonthStart = lastMonthEnd + 1 - startWeekday;
        for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
            content += displayDayCell(view_year, view_month - 1, day, view_month);
        }
    }

    // Shows each day of current month
    const daysInMonth = new Date(view_year, view_month, 0).getDate();
    let weekday = startWeekday;
    for (let day = 1; day <= daysInMonth; day++) {
        content += displayDayCell(view_year, view_month, day, view_month);
        if (weekday === 6) {
            weekday = 0;
            // Next week should show on next line
            content += '</tr><tr>';
        }
        else {
            weekday = weekday + 1;
        }
    }

    // Show the start of next month that belongs to the last week of current month
    if (weekday !== 0) {
        const remain = 7 - weekday;
        for (let day = 1; day <= remain; day++) {
            content += displayDayCell(view_year, view_month + 1, day, view_month);
            if (weekday === 6) {
                weekday = 0;
            }
            else {
                weekday = weekday + 1;
            }
        }
    }
    content += `
        </tr>
        </tbody>
    </table> 
    `;
    return content;
}

/**
 * Changes the calendar div to the required month and year.
 *
 * @param month_ : int month that the calendar will show (1 as January and 12 as December)
 * @param year_ : int year that the calendar will show
 */
// eslint-disable-next-line no-unused-vars
function loadCalendar(month_, year_) {
    $('#full_calendar').html(showCalendar(year_, month_));
}
