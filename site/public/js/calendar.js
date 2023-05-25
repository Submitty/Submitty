/* exported prevMonth, nextMonth, loadCalendar, loadFullCalendar, editCalendarItemForm, deleteCalendarItem, openNewItemModal */
/* global curr_day, curr_month, curr_year, gradeables_by_date */
/* global csrfToken, buildCourseUrl */

// List of names of months in English
const monthNames = ['December', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const monthNamesShort = ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

/**
 * Gets the previous month of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @returns {number[]} : array<int> {previous_month, year_of_previous_month}
 */
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
 *
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @returns {number[]} : array<int> {next_month, year_of_next_month}
 */
function nextMonth(month, year) {
    month = month + 1;
    if (month > 12) {
        month = month - 12;
        year = year + 1;
    }
    return [month, year];
}


/**
 * This function creates a Date object based on a string.
 *
 * @param datestr : string a string representing a date in the format of YYYY-mm-dd
 * @returns {Date} a Date object containing the specified date
 */
function parseDate(datestr) {
    const temp = datestr.split('-');
    return new Date(parseInt(temp[0]), parseInt(temp[1])-1, parseInt(temp[2]));
}

/**
 * This function creates a string in the format of YYYY-mm-dd.
 *
 * @param year : int the year
 * @param month : int the month
 * @param day : int the date
 * @returns {string}
 */
function dateToStr(year, month, day) {
    return `${year.toString()}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
}

/**
 * Create a HTML element that contains the calendar item (button/link/text).
 *
 * @param item : array the calendar item
 * @returns {HTMLElement} the HTML Element for the calendar item
 */
function generateCalendarItem(item) {
    // When hovering over an item, shows the name and due date
    // Due date information
    let due_string = '';
    if (item['submission'] !== '') {
        due_string = `Due: ${item['submission']}`;
    }

    // Put detail in the tooltip
    let tooltip = `Course: ${item['course']}&#10;` +
        `Title: ${item['title']}&#10;`;
    if (item['status_note'] !== '') {
        tooltip += `Status: ${item['status_note']}&#10;`;
    }
    if (due_string !== '') {
        tooltip += `${due_string}`;
    }
    // Put the item in the day cell
    const link = (!item['disabled']) ? item['url'] : '';
    const onclick = item['onclick'];
    const icon = item['icon'];
    const element = document.createElement('a');
    element.classList.add('btn', item['class'], `cal-gradeable-status-${item['status']}`, 'cal-gradeable-item');
    if (item['show_due']) {
        element.style.setProperty('background-color', item['color']);
    }
    if (item['status'] === 'ann') {
        element.style.setProperty('border-color', item['color']);
    }
    element.title = tooltip;
    if (link !== '') {
        element.href = link;
    }
    if (onclick !== '') {
        element.onclick = onclick;
    }
    element.disabled = item['disabled'];
    if (icon !== '') {
        const iconElement = document.createElement('i');
        iconElement.classList.add('fas', icon, 'cal-icon');
        element.appendChild(iconElement);
    }
    element.append(item['title']);
    return element;
}

/**
 * The form for editing calendar items.
 *
 * @param itemType : string the calendar item type
 * @param itemText : string the text the item shoukd contain
 * @param itemId : (Not sure, possibly string or int) the item ID
 * @param date : string the item date
 * @returns {void} : only has to update existing variables
 */
function editCalendarItemForm(itemType, itemText, itemId, date) {
    $('#calendar-item-type-edit').val(itemType);
    $('#calendar-item-text-edit').val(itemText);
    $('#edit-picker-edit').val(date);
    $('#calendar-item-id').val(itemId);

    $('#edit-calendar-item-form').show();
}

/**
 * Deletes the selected calendar item.
 *
 * @returns {void} : Just deleting.
 */
function deleteCalendarItem() {
    const id = $('#calendar-item-id').val();
    if (id !== '') {
        const data = new FormData();
        data.append('id', id);
        data.append('csrf_token', csrfToken);
        $.ajax({
            url: buildCourseUrl(['calendar', 'deleteItem']),
            type: 'POST',
            processData: false,
            contentType: false,
            data: data,
            success: function (res) {
                const response = JSON.parse(res);
                if (response.status === 'success') {
                    location.reload();
                }
                else {
                    alert(response.message);
                }
            },
        });
    }
}

/**
 * Creates a HTML table cell that contains a date.
 *
 * @param year : int the year of the date
 * @param month : int the month of the date (1 as January and 12 as December)
 * @param day : int the date of the date (1 - 31)
 * @param curr_view_month : int the current month that the calendar is viewing
 * @param view_semester : boolean if the calendar is viewing the entire semester. If so, the day cell would show both the month and date
 * @returns {HTMLElement} the HTML Element containing the cell
 */
function generateDayCell(year, month, day, curr_view_month, view_semester=false) {
    const cell_date_str = dateToStr(year, month ,day);

    const content = document.createElement('td');
    content.classList.add('cal-day-cell');
    content.id = `cell-${cell_date_str}`;
    if (view_semester) {
        content.classList.add('cal-cell-expand');
    }
    const div = document.createElement('div');
    div.classList.add('cal-cell-title-panel');
    const span = document.createElement('span');
    span.classList.add('cal-day-title');
    if (view_semester) {
        span.classList.add('cal-curr-month-date');
        span.textContent = `${monthNamesShort[month]} ${day}`;
    }
    else if (month === curr_view_month) {
        span.classList.add('cal-curr-month-date');
        if (day === curr_day && month === curr_month && year === curr_year) {
            span.classList.add('cal-today-title');
        }
        span.textContent = `${day}`;
    }
    else {
        span.classList.add('cal-next-month-date');
        if (month > 12) {
            month = month % 12;
        }
        else if (month <= 0) {
            month = month + 12;
        }
        span.textContent = `${month}/${day}`;
    }
    div.appendChild(span);
    content.appendChild(div);
    const itemList = document.createElement('div');
    itemList.classList.add('cal-cell-items-panel');
    for (const i in gradeables_by_date[cell_date_str]) {
        itemList.appendChild(generateCalendarItem(gradeables_by_date[cell_date_str][i]));
    }
    content.appendChild(itemList);
    return content;
}

/**
 * Generates the title area for the calendar.
 *
 * @param title_area the title of the calendar (month+year/semester/...)
 * @returns {HTMLElement} the HTML Element for the table with the header filled in
 */
function generateCalendarHeader(title_area) {
    const table = document.createElement('table');
    table.classList.add('table', 'table-striped', 'table-bordered', 'persist-area', 'table-calendar');
    const tableHead = document.createElement('thead');
    const navRow = document.createElement('tr');
    navRow.classList.add('cal-navigation');

    navRow.appendChild(title_area);

    const weekTitleRow = document.createElement('tr');
    weekTitleRow.classList.add('cal-week-title-row');
    const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const shortDaysOfWeek = ['sun', 'mon', 'tue', 'wed', 'thr', 'fri', 'sat'];
    for (let i = 0; i < daysOfWeek.length; i++) {
        const th = document.createElement('th');
        th.classList.add('cal-week-title', `cal-week-title-${shortDaysOfWeek[i]}`);
        th.textContent = daysOfWeek[i];
        weekTitleRow.appendChild(th);
    }

    tableHead.appendChild(navRow);
    tableHead.appendChild(weekTitleRow);

    table.appendChild(tableHead);
    return table;
}

/**
 * Builds the title/header for a regular month switching calendar
 *
 * @param view_year : int the year currently in view
 * @param view_month : int the month currently in view
 * @returns {DocumentFragment} the HTML element containing the title/header
 */
function buildSwitchingHeader(view_year, view_month) {
    const fragment = document.createDocumentFragment();

    // Build first header
    const th1 = document.createElement('th');
    th1.colSpan = 3;
    let div = document.createElement('div');
    div.classList.add('cal-switch');
    div.id = 'prev-month-switch';
    let a = document.createElement('a');
    a.classList.add('cal-btn', 'cal-prev-btn');
    const prev = prevMonth(view_month, view_year);
    a.onclick = () => loadCalendar.apply(this, prev);
    a.innerHTML = '<i class="fas fa-angle-left"></i>';
    div.appendChild(a);
    th1.appendChild(div);

    // Build second header
    const th2 = document.createElement('th');
    th2.colSpan = 1;
    div = document.createElement('div');
    div.classList.add('cal-title');
    const h2 = document.createElement('h2');
    h2.classList.add('cal-month-title');
    h2.textContent = monthNames[view_month];
    div.appendChild(h2);
    const h3 = document.createElement('h3');
    h3.classList.add('cal-year-title');
    h3.textContent = `${view_year}`;
    div.appendChild(h3);
    th2.appendChild(div);

    // Build third header
    const th3 = document.createElement('th');
    th3.colSpan = 3;
    div = document.createElement('div');
    div.classList.add('cal-switch');
    div.id = 'next-month-switch';
    a = document.createElement('a');
    a.classList.add('cal-btn', 'cal-next-btn');
    const next = nextMonth(view_month, view_year);
    a.onclick = () => loadCalendar.apply(this, next);
    a.innerHTML = '<i class="fas fa-angle-right"></i>';
    div.appendChild(a);
    th3.appendChild(div);

    fragment.appendChild(th1);
    fragment.appendChild(th2);
    fragment.appendChild(th3);
    return fragment;
}

/**
 * Builds a title/header for semester calendar
 *
 * @param semester_name the name of the semester
 * @returns {DocumentFragment} the HTML element containing the title/header
 */
function buildSemesterHeader(semester_name) {
    const fragment = document.createDocumentFragment();
    const th1 = document.createElement('th');
    th1.colSpan = 3;
    const th2 = document.createElement('th');
    th2.colSpan = 1;
    const div = document.createElement('div');
    div.classList.add('cal-title');
    const h2 = document.createElement('h2');
    h2.classList.add('cal-month-title');
    h2.textContent = semester_name.split(' ')[0];
    div.appendChild(h2);
    const h3 = document.createElement('h3');
    h3.classList.add('cal-year-title');
    h3.textContent = semester_name.split(' ')[1];
    const th3 = document.createElement('th');
    th3.colSpan = 3;
    div.appendChild(h3);
    th2.appendChild(div);
    fragment.appendChild(th1);
    fragment.appendChild(th2);
    fragment.appendChild(th3);
    return fragment;
}

/**
 * This function creates a table that shows the calendar.
 *
 * @param view_year : int year that the calendar is viewing
 * @param view_month : int month that the calendar is viewing (1 as January and 12 as December)
 * @returns {HTMLElement} the HTML Element with the entire calendar
 */
function generateCalendarOfMonth(view_year, view_month) {
    const startWeekday = new Date(view_year, view_month - 1, 1).getDay();
    const title = buildSwitchingHeader(view_year, view_month);
    const table = generateCalendarHeader(title);
    const tableBody = document.createElement('tbody');
    let curRow = document.createElement('tr');

    // Show days at the end of last month that belongs to the first week of current month
    if (startWeekday !== 0) {
        const lastMonthEnd = new Date(view_year, view_month - 1, 0).getDate();
        const lastMonthStart = lastMonthEnd + 1 - startWeekday;
        for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
            curRow.appendChild(generateDayCell(view_year, view_month - 1, day, view_month));
        }
    }

    // Shows each day of current month
    const daysInMonth = new Date(view_year, view_month, 0).getDate();
    let weekday = startWeekday;
    for (let day = 1; day <= daysInMonth; day++) {
        curRow.appendChild(generateDayCell(view_year, view_month, day, view_month));
        if (weekday === 6) {
            weekday = 0;
            // Next week should show on next line
            tableBody.appendChild(curRow);
            curRow = document.createElement('tr');
        }
        else {
            weekday = weekday + 1;
        }
    }

    // Show the start of next month that belongs to the last week of current month
    if (weekday !== 0) {
        const remain = 7 - weekday;
        for (let day = 1; day <= remain; day++) {
            curRow.appendChild(generateDayCell(view_year, view_month + 1, day, view_month));
            if (weekday === 6) {
                weekday = 0;
            }
            else {
                weekday = weekday + 1;
            }
        }
    }
    tableBody.appendChild(curRow);
    table.appendChild(tableBody);
    return table;
}

/**
 * Creates a calendar of the entire semester.
 *
 * @param start the start date of the semester in the format of YYYY-mm-dd
 * @param end the end date of the semester in the format of YYYY-mm-dd
 * @param semester_name the name of the semester
 * @returns {HTMLElement} the HTML Element containing the calendar
 */
function generateFullCalendar(start, end, semester_name) {
    // Header area: two buttons to move, and month
    const table = generateCalendarHeader(buildSemesterHeader(semester_name));
    const tableBody = document.createElement('tbody');

    const startDate = parseDate(start);
    const endDate = parseDate(end);
    const currDate = startDate;


    const startWeekday = startDate.getDay();
    // Skip days at the end of last month that belongs to the first week of current month
    if (startWeekday !== 0) {
        const td = document.createElement('td');
        td.classList.add('cal-day-cell');
        td.colSpan = startWeekday;
        tableBody.appendChild(td);
    }

    let curRow = document.createElement('tr');

    let weekday = startWeekday;
    while ((endDate.getTime() - startDate.getTime()) >= 0) {
        // Shows each day of current month
        curRow.appendChild(generateDayCell(currDate.getFullYear(), currDate.getMonth()+1, currDate.getDate(), 0, true));
        if (weekday === 6) {
            weekday = 0;
            // Next week should show on next line
            tableBody.appendChild(curRow);
            curRow = document.createElement('tr');
        }
        else {
            weekday = weekday + 1;
        }

        currDate.setDate(currDate.getDate() + 1);
    }
    tableBody.appendChild(curRow);

    if (weekday !== 0) {
        const remain = 7 - weekday;
        const td = document.createElement('td');
        td.classList.add('cal-day-cell');
        td.colSpan = remain;
        tableBody.appendChild(td);
    }
    table.appendChild(tableBody);

    return table;
}

/**
 * Changes the calendar div to the required month and year.
 *
 * @param month_ : int month that the calendar will show (1 as January and 12 as December)
 * @param year_ : int year that the calendar will show
 */
function loadCalendar(month_, year_) {
    const calendar = document.getElementById('full-calendar');
    calendar.innerHTML = '';
    calendar.appendChild(generateCalendarOfMonth(year_, month_));
}

/**
 * Changes the calendar div to the required semester.
 *
 * @param start : string the start date of the semester in the format of YYYY-mm-dd
 * @param end the end date of the semester in the format of YYYY-mm-dd
 * @param semester_name the name of the semester
 */
function loadFullCalendar(start, end, semester_name) {
    const calendar = document.getElementById('full-calendar');
    calendar.innerHTML = '';
    calendar.appendChild(generateFullCalendar(start, end, semester_name));
}

function openNewItemModal() {
    $('#new-calendar-item-form').css('display', 'block');
}
