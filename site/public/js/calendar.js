/* exported prevMonth, nextMonth, loadCalendar, loadFullCalendar, editCalendarItemForm, deleteCalendarItem, deleteGlobalCalendarItem, openNewItemModal, openNewGlobalEventModal, openOptionsModal, updateCalendarOptions, colorLegend, setDateToToday, filter_course, changeView */
/* global curr_day, curr_month, curr_year, gradeables_by_date, global_items_by_date, instructor_courses, is_superuser, buildUrl */
/* global csrfToken */
/* global luxon */

// List of names of months in English
const monthNames = ['December', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const monthNamesShort = ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'];

const DateTime = luxon.DateTime;

/**
 * Changes the view and updates cookies and loads the calendar
 * @param view_type : string the value of the view to change to
 * @param view_year : int year that is currently being viewed
 * @param view_month : int month that is currently being viewed
 * @param view_day : int day that is currently being viewed
 * @returns {void} : loads the updated calendar
 */
function changeView(view_type, view_year, view_month, view_day) {
    Cookies.set('view', view_type);

    let cookie_year = parseInt(Cookies.get('calendar_year'));
    let cookie_month = parseInt(Cookies.get('calendar_month'));
    let cookie_day = parseInt(Cookies.get('calendar_day'));
    if (isNaN(cookie_year)) {
        cookie_year = view_year;
    }
    if (isNaN(cookie_month)) {
        cookie_month = view_month;
    }
    if (isNaN(cookie_day)) {
        cookie_day = view_day;
    }
    // Load the calendar to the correct day
    loadCalendar(cookie_month, cookie_year, cookie_day, view_type);
}

/**
 * Sets the current date to today and then changes the calendar
 * @returns {void} : only changes cookies and calendar date
 */
function setDateToToday() {
    const type = $('#calendar-item-type-edit').val();
    const currentDay = new Date();
    Cookies.set('calendar_year', currentDay.getFullYear());
    Cookies.set('calendar_month', currentDay.getMonth() + 1);
    Cookies.set('calendar_day', currentDay.getDate());

    const cookie_year = currentDay.getFullYear();
    const cookie_month = currentDay.getMonth() + 1;
    const cookie_day = currentDay.getDate();

    loadCalendar(cookie_month, cookie_year, cookie_day, type);
}

/**
 * Gets the previous month of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @returns {view_info[]} : array {previous_month, year_of_previous_month}
 */
function prevMonth(month, year, day) {
    month = month - 1;
    if (month <= 0) {
        month = 12 + month;
        year = year - 1;
    }
    return [month, year, day, 'month'];
}

/**
 * Gets the next month of a given month
 *
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @returns {view_info[]} : array {next_month, year_of_next_month}
 */
function nextMonth(month, year, day) {
    month = month + 1;
    if (month > 12) {
        month = month - 12;
        year = year + 1;
    }
    return [month, year, day, 'month'];
}

/**
 * Gets the previous week of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @param day : int the current day
 * @returns {view_info[]} : array {previous_month, year_of_previous_month}
 */
function prevWeek(month, year, day) {
    const currentDay = DateTime.local(year, month, day).minus({ days: 7 });
    const [newYear, newMonth, newDay] = [currentDay.year, currentDay.month, currentDay.day];
    return [newMonth, newYear, newDay];
}

/**
 * Gets the next week of a given week
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @param day : int the current day
 * @returns {view_info[]} : array {next_month, year_of_next_month}
 */
function nextWeek(month, year, day) {
    const currentDay = luxon.DateTime.local(year, month, day).plus({ days: 7 });
    const [newYear, newMonth, newDay] = [currentDay.year, currentDay.month, currentDay.day];
    return [newMonth, newYear, newDay];
}

/**
 * This function creates a Date object based on a string.
 *
 * @param datestr : string a string representing a date in the format of YYYY-mm-dd
 * @returns {Date} a Date object containing the specified date
 */
function parseDate(datestr) {
    return luxon.DateTime.fromFormat(datestr, 'yyyy-MM-dd', { zone: 'local' }).toJSDate();
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
 * This function returns a slightly darker color than the color variable name passed.
 *
 * @param colorstr : string the color to darken in the form "var(--color-name)"
 * @returns {string} a hex code for a slightly darker shade
 */
function darken(colorstr) {
    if (typeof colorstr !== 'string') {
        return colorstr;
    }
    else {
        const hexcodestr = window.getComputedStyle(document.documentElement).getPropertyValue(colorstr.slice(4, -1)).toLowerCase();
        const darkerstr = hexcodestr.split('');
        for (let i = 1; i < hexcodestr.length; i++) {
            if ((hexcodestr[i] > 'b' && hexcodestr[i] <= 'f') || (hexcodestr[i] > '1' && hexcodestr[i] <= '9')) {
                darkerstr[i] = String.fromCharCode(hexcodestr.charCodeAt(i) - 2);
            }
            else if (hexcodestr[i] === 'b') {
                darkerstr[i] = '9';
            }
            else if (hexcodestr[i] === 'a') {
                darkerstr[i] = '8';
            }
        }
        return darkerstr.join('');
    }
}

/**
 * This function returns a slightly lighter color than the color variable name passed.
 *
 * @param colorstr : string the color to lighten in the form "var(--color-name)"
 * @returns {string} a hex code for a slightly lighter shade
 */
function lighten(colorstr) {
    if (typeof colorstr !== 'string') {
        return colorstr;
    }
    else {
        const hexcodestr = window.getComputedStyle(document.documentElement).getPropertyValue(colorstr.slice(4, -1)).trim().toLowerCase();
        const hex = hexcodestr.slice(1);
        // Convert hex to RGB
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);
        // Adjusting the brightness for stripes to visible (used only for future gradeables as of now)
        const newR = Math.min(255, r + 40);
        const newG = Math.min(255, g + 40);
        const newB = Math.min(255, b + 40);
        // Convert RGB back to hex
        const lighterHex = `#${newR.toString(16).padStart(2, '0')}${newG.toString(16).padStart(2, '0')}${newB.toString(16).padStart(2, '0')}`;
        return lighterHex;
    }
}

/**
 * Create a HTML element that contains the calendar item (button/link/text).
 *
 * @param item : array the calendar item
 * @returns {HTMLElement} the HTML Element for the calendar item
 */
function generateCalendarItem(item) {
    let tooltip = '';
    if (!item['submission_open'] && item['is_student']) {
        // Student shouldn't be able to access this item
        // When hovering over an item, shows the below message
        tooltip = 'You can access this gradeable once the submission opens';
        item['disabled'] = true;
    }
    else {
        // When hovering over an item, shows the name and due date
        // Due-date information
        let due_string = '';
        if (item['submission'] !== '') {
            due_string = `Due: ${item['submission']}`;
        }

        // Put detail in the tooltip
        tooltip = `Course: ${item['course']}&#10;`
        + `Title: ${item['title']}&#10;`;
        if (item['status_note'] !== '') {
            tooltip += `Status: ${item['status_note']}&#10;`;
        }
        if (due_string !== '') {
            tooltip += `${due_string}`;
        }
    }
    // Put the item in the day cell
    const link = (!item['disabled']) ? item['url'] : '';
    const onclick = item['onclick'];
    let exists = false;
    if (!item['show_due']) {
        for (let course = 0; course < instructor_courses.length; course++) {
            if (instructor_courses[course].course === item['course'] && instructor_courses[course].semester === item['semester']) {
                exists = true;
            }
        }
    }
    const icon = item['icon'];
    const element = document.createElement('a');
    element.classList.add('btn', item['class'], `cal-gradeable-status-${item['status']}`, 'cal-gradeable-item');
    if (item['show_due']) {
        element.style.setProperty('background-color', item['color']);
    }
    if (item['status'] === 'text' || item['status'] === 'ann') {
        element.style.setProperty('background-color', item['color']);
    }
    // Displaying striped background if submission is not open irrespective of access level
    if (!item['submission_open']) {
        element.style.setProperty('background', `repeating-linear-gradient(45deg, ${item['color']}, ${item['color']} 10px, ${lighten(item['color'])} 10px, ${lighten(item['color'])} 15px)`);
    }
    if (!item['disabled'] || exists) {
        element.style.setProperty('cursor', 'pointer');
    }
    else {
        element.style.setProperty('cursor', 'default');
    }
    element.title = tooltip;
    if (link !== '') {
        element.href = link;
        element.addEventListener('mouseover', () => {
            element.style.setProperty('background-color', darken(item['color']));
        });
        element.addEventListener('mouseout', () => {
            element.style.setProperty('background-color', item['color']);
        });
    }

    if (onclick !== '' && instructor_courses.length > 0 && item['course'] !== 'Superuser') {
        if (!item['show_due']) {
            element.style.cursor = 'pointer';
            element.onclick = () => editCalendarItemForm(item['status'], item['title'], item['id'], item['date'], item['semester'], item['course']);
        }
        else {
            element.onclick = onclick;
        }
    }
    else if (onclick !== '' && is_superuser && item['course'] === 'Superuser') {
        if (!item['show_due']) {
            element.style.cursor = 'pointer';
            element.onclick = () => editGlobalCalendarItemForm(item['status'], item['title'], item['id'], item['date']);
        }
        else {
            element.onclick = onclick;
        }
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
function editCalendarItemForm(itemType, itemText, itemId, date, semester, course) {
    $(`#calendar-item-type-edit>option[value=${itemType}]`).attr('selected', true);
    $('#calendar-item-text-edit').val(itemText);
    $('#edit-picker-edit').val(date);
    $('#calendar-item-id').val(itemId);
    $('#calendar-item-semester-edit').val(semester);
    $('#calendar-item-course-edit').val(course);

    $('#edit-calendar-item-form').show();
}

/**
 * The form for editing Global calendar items.
 *
 * @param itemType : string the Global calendar item type
 * @param itemText : string the text the item shoukd contain
 * @param itemId : (Not sure, possibly string or int) the item ID
 * @param date : string the item date
 * @returns {void} : only has to update existing variables
 */
function editGlobalCalendarItemForm(itemType, itemText, itemId, date) {
    $(`#global-calendar-item-type-edit>option[value=${itemType}]`).attr('selected', true);
    $('#global-calendar-item-text-edit').val(itemText);
    $('#edit-global-picker').val(date);
    $('#global-calendar-item-id').val(itemId);

    $('#edit-global-item-form').show();
}

/**
 * Deletes the selected calendar item.
 *
 * @returns {void} : Just deleting.
 */
function deleteCalendarItem() {
    const id = $('#calendar-item-id').val();
    const course = $('#calendar-item-course-edit').val();
    const semester = $('#calendar-item-semester-edit').val();
    if (id !== '') {
        const data = new FormData();
        data.append('id', id);
        data.append('course', course);
        data.append('semester', semester);
        data.append('csrf_token', csrfToken);
        $.ajax({
            url: buildUrl(['calendar', 'items', 'delete']),
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
 * Deletes the selected global calendar item.
 *
 * @returns {void} : Just deleting.
 */
function deleteGlobalCalendarItem() {
    const id = $('#global-calendar-item-id').val();
    if (id !== '') {
        const data = new FormData();
        data.append('id', id);
        data.append('csrf_token', csrfToken);
        $.ajax({
            url: buildUrl(['calendar', 'global_items', 'delete']),
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
function generateDayCell(year, month, day, curr_view_month, view_mode, view_semester = false) {
    const cell_date_str = dateToStr(year, month, day);

    const content = document.createElement('td');
    // change the css of the cell based on the view mode:
    if (view_mode === 'month') {
        content.classList.add('cal-day-cell');
    }
    else if (view_mode === 'twoweek') {
        content.classList.add('cal-day-cell-twoweek');
    }
    else if (view_mode === 'week') {
        content.classList.add('cal-day-cell-week');
    }
    else {
        content.classList.add('cal-day-cell');
    }

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
    for (const i in global_items_by_date[cell_date_str]) {
        itemList.appendChild(generateCalendarItem(global_items_by_date[cell_date_str][i]));
    }
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
 * @param view_day : int, the day currently in view
 * @returns {DocumentFragment} the HTML element containing the title/header
 */
function buildSwitchingHeader(view_year, view_month, view_day, type) {
    const fragment = document.createDocumentFragment();

    // Build first header column
    const th1 = document.createElement('th');
    th1.colSpan = 3;
    let div = document.createElement('div');
    div.classList.add('cal-switch');
    div.id = 'prev-month-switch';
    let a = document.createElement('a');
    a.classList.add('cal-btn', 'cal-prev-btn');

    // Change onclick based on type
    let prev;
    if (type === 'month') {
        prev = prevMonth(view_month, view_year, view_day);
    }
    else {
        prev = prevWeek(view_month, view_year, view_day);
        prev.push(type);
    }
    a.onclick = () => loadCalendar.apply(this, prev);
    a.innerHTML = '<i class="fas fa-angle-left"></i>';

    // Append to header
    div.appendChild(a);
    th1.appendChild(div);

    // Build second header column
    const th2 = document.createElement('th');
    th2.colSpan = 1;
    div = document.createElement('div');
    div.classList.add('cal-title');

    // Create the month dropdown
    const monthSelect = $('<select>', {
        id: 'month-dropdown',
        class: 'dropdown-custom cal-month-title',
        change: function () {
            const type = $('#calendar-item-type-edit').val();
            const newMonth = parseInt(this.value);
            const newYear = parseInt($('#year-dropdown').val());
            loadCalendar(newMonth, newYear, view_day, type);
        },
    });

    for (let itermonth = 1; itermonth <= 12; itermonth++) {
        const monthOption = $('<option>', {
            value: itermonth,
            text: monthNames[itermonth],
        });
        monthSelect.append(monthOption);
    }
    monthSelect.val(view_month);

    // Create the year dropdown
    const currentYear = new Date().getFullYear();
    const yearSelect = $('<select>', {
        id: 'year-dropdown',
        class: 'dropdown-custom cal-year-title',
        change: function () {
            const type = $('#calendar-item-type-edit').val();
            const newYear = parseInt(this.value);
            const newMonth = parseInt($('#month-dropdown').val());
            loadCalendar(newMonth, newYear, view_day, type);
        },
    });

    for (let year = currentYear - 4; year <= currentYear + 1; year++) {
        const yearOption = $('<option>', {
            value: year,
            text: year,
        });
        yearSelect.append(yearOption);
    }
    yearSelect.val(view_year);

    // Add the month and year dropdowns side by side
    const dropdownContainer = $('<div>', {
        css: {
            display: 'flex',
            alignItems: 'center',
        },
    });
    dropdownContainer.append(monthSelect).append(yearSelect);
    div.appendChild(dropdownContainer[0]);
    th2.appendChild(div);

    // Build third header column
    const th3 = document.createElement('th');
    th3.colSpan = 3;
    div = document.createElement('div');
    div.classList.add('cal-switch');
    div.id = 'next-month-switch';
    a = document.createElement('a');
    a.classList.add('cal-btn', 'cal-next-btn');

    // Change onclick based on type
    let next;
    if (type === 'month') {
        next = nextMonth(view_month, view_year, view_day);
    }
    else {
        next = nextWeek(view_month, view_year, view_day);
        next.push(type);
    }
    a.onclick = () => loadCalendar.apply(this, next);
    a.innerHTML = '<i class="fas fa-angle-right"></i>';

    // Append to header
    div.appendChild(a);
    th3.appendChild(div);

    // Append all elements to fragment
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
 * @param view_day : int, the day currently in view
 * @returns {HTMLElement} the HTML Element with the entire calendar
 */
function generateCalendarOfMonth(view_year, view_month, view_day) {
    const startWeekday = DateTime.local(view_year, view_month, 1).weekday % 7;
    const title = buildSwitchingHeader(view_year, view_month, view_day, 'month');
    const table = generateCalendarHeader(title);
    const tableBody = document.createElement('tbody');
    let curRow = document.createElement('tr');

    // Show days at the end of last month that belongs to the first week of current month
    if (startWeekday !== 0) {
        const lastMonthEnd = DateTime.local(view_year, view_month, 1).minus({ days: 1 }).day;
        const lastMonthStart = lastMonthEnd + 1 - startWeekday;
        for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
            curRow.appendChild(generateDayCell(view_year, view_month - 1, day, view_month, 'month'));
        }
    }

    // Shows each day of current month
    const daysInMonth = luxon.DateTime.local(view_year, view_month).daysInMonth;
    let weekday = startWeekday;
    for (let day = 1; day <= daysInMonth; day++) {
        curRow.appendChild(generateDayCell(view_year, view_month, day, view_month, 'month'));
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
            curRow.appendChild(generateDayCell(view_year, view_month + 1, day, view_month, 'month'));
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
 * This function creates a table that shows the calendar for one week.
 *
 * @param view_year : int year that the calendar is viewing
 * @param view_month : int month that the calendar is viewing (1 as January and 12 as December)
 * @param view_day : int day that the calendar is viewing
 * @returns {HTMLElement} the HTML string contains the entire calendar table displaying view_month/view_year
 */
function generateCalendarOfMonthWeek(view_year, view_month, view_day) {
    // Header area: two buttons to move, and month
    const title = buildSwitchingHeader(view_year, view_month, view_day, 'one_week');

    // Body area: table
    const table = generateCalendarHeader(title);
    const tableBody = document.createElement('tbody');
    const curRow = document.createElement('tr');

    // Show days at the end of last month that belongs to the first week of current month
    const startWeekday = DateTime.local(view_year, view_month, 1).weekday % 7;
    const currentDay = DateTime.local(view_year, view_month, view_day).weekday % 7;
    const lastMonthEnd = DateTime.local(view_year, view_month, 1).minus({ days: 1 }).day;
    const lastMonthStart = lastMonthEnd + 1 - startWeekday;
    const daysInMonth = DateTime.local(view_year, view_month).daysInMonth;
    let print_day = 0;

    // Show days at the end of last month that belongs to the first week of current month
    if (view_day - currentDay <= 0) {
        for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
            curRow.appendChild(generateDayCell(view_year, view_month - 1, day, view_month, 'week'));
            print_day++;
        }
    }

    // Make the day cells before the "current" date
    if (print_day < currentDay) {
        for (let day = view_day - currentDay + print_day; print_day < currentDay; day++) {
            curRow.appendChild(generateDayCell(view_year, view_month, day, view_month, 'week'));
            print_day++;
        }
    }

    // Make the "current" day, and the days after in the month
    for (let day = view_day; print_day <= 6 && day <= daysInMonth; day++) {
        curRow.appendChild(generateDayCell(view_year, view_month, day, view_month, 'week'));
        print_day++;
    }

    // Makes any days that spill into the next month
    for (let day = 1; print_day <= 6; day++) {
        curRow.appendChild(generateDayCell(view_year, view_month + 1, day, view_month, 'week'));
        print_day++;
    }
    tableBody.appendChild(curRow);
    table.appendChild(tableBody);
    return table;
}

/**
 * This function creates a table that shows the calendar for two weeks.
 *
 * @param view_year : int year that the calendar is viewing
 * @param view_month : int month that the calendar is viewing (1 as January and 12 as December)
 * @param view_day : int day that the calendar is viewing
 * @returns {HTMLElement} the HTML string contains the entire calendar table displaying view_month/view_year
 */
function generateCalendarOfMonthTwoWeek(view_year, view_month, view_day) {
    // Header area: two buttons to move, and month
    const title = buildSwitchingHeader(view_year, view_month, view_day, 'two_week');

    // Body area: table
    const table = generateCalendarHeader(title);
    const tableBody = document.createElement('tbody');
    let curRow = document.createElement('tr');

    // Show days at the end of last month that belongs to the first week of current month
    const startWeekday = DateTime.local(view_year, view_month, 1).weekday % 7;
    const currentDay = DateTime.local(view_year, view_month, view_day).weekday % 7;
    const lastMonthEnd = DateTime.local(view_year, view_month, 1).minus({ days: 1 }).day;
    const lastMonthStart = lastMonthEnd + 1 - startWeekday;
    const daysInMonth = DateTime.local(view_year, view_month).daysInMonth;
    let print_day = 0;

    // Show days at the end of last month that belongs to the first week of current month
    if (view_day - currentDay <= 0) {
        for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
            curRow.appendChild(generateDayCell(view_year, view_month - 1, day, view_month, 'twoweek'));
            print_day++;
        }
    }

    // Make the day cells before the "current" date
    if (print_day < currentDay) {
        for (let day = view_day - currentDay + print_day; print_day < currentDay; day++) {
            curRow.appendChild(generateDayCell(view_year, view_month, day, view_month, 'twoweek'));
            print_day++;
        }
    }

    // Make the "current" day, and the days after in the month
    for (let day = view_day; print_day <= 13 && day <= daysInMonth; day++) {
        curRow.appendChild(generateDayCell(view_year, view_month, day, view_month, 'twoweek'));
        print_day++;
        // If the day is the last day of the week, then make a new row
        if (print_day === 7) {
            // Next week should show on next line
            tableBody.appendChild(curRow);
            curRow = document.createElement('tr');
        }
    }

    // Makes any days that spill into the next month
    for (let day = 1; print_day <= 13; day++) {
        curRow.appendChild(generateDayCell(view_year, view_month + 1, day, view_month, 'twoweek'));
        print_day++;
        // If the day is the last day of the week, then make a new row
        if (print_day === 7) {
            // Next week should show on next line
            tableBody.appendChild(curRow);
            curRow = document.createElement('tr');
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
    const table = generateCalendarHeader(semester_name);
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
        curRow.appendChild(generateDayCell(currDate.getFullYear(), currDate.getMonth() + 1, currDate.getDate(), 0, true));
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
 * @param view_day : int, the day currently in view
 * @param type : string type of the calendar
 */
function loadCalendar(month_, year_, day_, type) {
    const calendar = document.getElementById('full-calendar');
    calendar.innerHTML = '';
    if (type === 'month') {
        calendar.appendChild(generateCalendarOfMonth(year_, month_, day_));
    }
    else if (type === 'two_week') {
        calendar.appendChild(generateCalendarOfMonthTwoWeek(year_, month_, day_));
    }
    else {
        calendar.appendChild(generateCalendarOfMonthWeek(year_, month_, day_));
    }
    Cookies.set('calendar_year', year_);
    Cookies.set('calendar_month', month_);
    Cookies.set('calendar_day', day_);
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

function openNewGlobalEventModal() {
    $('#new-global-event-form').css('display', 'block');
}

function openOptionsModal() {
    $('#calendar-options-form').css('display', 'block');
    setOptionsValues();
    // Make color dropdowns change colors when values are changed
    $('.course-color-picker').on('change', function () {
        $(this).css('background-color', $(this).val());
    });
}

// checks proper tick marks in modal
function setOptionsValues() {
    // Courses filter
    const showAll = loadShowAllCoursesCookie();
    if (showAll) { // if show all is true, tick off show all
        document.getElementById('filter-courses-menu').value = 'show all';
    }
    else { // if show all if false, select a specific course
        document.getElementById('filter-courses-menu').value = loadCurrentCourseCookie();
    }
    // Course Colors
    $('.course-color-picker').each(function () {
        const selected_color = Cookies.get(`calendar_color_${$(this).attr('id').slice(6)}`);
        $(this).css('background-color', selected_color);
        $(this).val(selected_color);
    });
}

function loadShowAllCoursesCookie() {
    const cookie = Cookies.get('calendar_show_all');
    return cookie === '1' ? true : false;
}

function loadCurrentCourseCookie() {
    return Cookies.get('calendar_course');
}

function updateCalendarOptions() {
    saveOptions();
    location.reload();
}

function saveOptions() {
    // Courses Filter
    const courses_val = document.getElementById('filter-courses-menu').value;
    if (courses_val === 'show all') {
        Cookies.set('calendar_show_all', '1', { expires: 365 });
    }
    else {
        Cookies.set('calendar_show_all', '0', { expires: 365 });
        Cookies.set('calendar_course', courses_val, { expires: 365 });
    }
    // Course Colors
    $('.course-color-picker').each(function () {
        const cname = `calendar_color_${$(this).attr('id').slice(6)}`;
        Cookies.set(cname, $(this).val(), { expires: 365 });
    });
    // Legend
    const legend_val = document.getElementById('show-legend-box').checked;
    if (legend_val) {
        Cookies.set('show_legend', '1', { expires: 365 });
    }
    else {
        Cookies.set('show_legend', '0', { expires: 365 });
    }
}

// Adds Color to Legend
function colorLegend() {
    $('.legend-color').each(function () {
        $(this).css('background-color', Cookies.get(`calendar_color_${$(this).attr('name')}`));
    });
}

// Modifies cookies so the correct filtering of courses on the calendar is chosen.
// param string course_val Id of the course
// param string display_name display name of the course
function filter_course(course_val, display_name) {
    if (course_val === 'show all') {
        Cookies.set('calendar_show_all', '1', { expires: 365 });
    }
    else {
        Cookies.set('calendar_show_all', '0', { expires: 365 });
        if (display_name) {
            Cookies.set('display_name', display_name, { expires: 365 });
        }
        else {
            Cookies.set('display_name', course_val, { expires: 365 });
        }
        Cookies.set('calendar_course', course_val, { expires: 365 });
    }
    location.reload();
}
