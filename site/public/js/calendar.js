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
function prevMonth(month, year, day) {
    month = month - 1;
    if (month <= 0) {
        month = 12 + month;
        year = year - 1;
    }
    return [month, year, day, "month"];
}

/**
 * Gets the next month of a given month
 *
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @returns {number[]} : array<int> {next_month, year_of_next_month}
 */
function nextMonth(month, year, day) {
    month = month + 1;
    if (month > 12) {
        month = month - 12;
        year = year + 1;
    }
    return [month, year, day, "month"];
}

/**
 * Gets the previous two weeks of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @param day : int the current day
 * @returns {number[]} : array<int> {previous_month, year_of_previous_month}
 */
function prevTwoWeeks(month, year, day) {
    day = day - 14;
    if (day <= 0) {
        month = month - 1;
        if (month <= 0) {
            month = 12 + month;
            year = year - 1;
        }
        day = 31 + day;
    }
    return [month, year, day, "two_week"];
}

/**
 * Gets the next two weeks of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @param day : int the current day
 * @returns {number[]} : array<int> {next_month, year_of_next_month}
 */
function nextTwoWeeks(month, year, day) {
    day = day + 14;
    if (day > 31) {
        month = month + 1;
        if (month > 12) {
            month = month - 12;
            year = year + 1;
        }
        day = day - 31;
    }
    return [month, year, day, "two_week"];
}

/**
 * Gets the previous week of a given month
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @param day : int the current day
 * @returns {number[]} : array<int> {previous_month, year_of_previous_month}
 */
 function prevWeek(month, year, day) {
    day = day - 7;
    if (day <= 0) {
        month = month - 1;
        if (month <= 0) {
            month = 12 + month;
            year = year - 1;
        }
        day = 31 + day;
    }
    return [month, year, day, "one_week"];
}

/** 
 * Gets the next week of a given week
 * @param month : int the current month (1 as January and 12 as December)
 * @param year : int the current year
 * @param day : int the current day
 * @returns {number[]} : array<int> {next_month, year_of_next_month}
 */
function nextWeek(month, year, day) {
    day = day + 7;
    if (day > 31) {
        month = month + 1;
        if (month > 12) {
            month = month - 12;
            year = year + 1;
        }
        day = day - 31;
    }
    return [month, year, day, "one_week"];
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
 * @param type : string the calendar item type
 * @returns {string} the HTML string containing the calendar item
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
    const disabled = item['disabled'] ? 'disabled' : '';
    return `<a class="btn ${item['class']} cal-gradeable-status-${item['status']} cal-gradeable-item"
           title="${tooltip}" 
           style="${(item['type'] === 'gradeable') ? `background-color: ${item['color']}` : `border-color: ${item['color']}`}"
           ${(link !== '') ? `href="${link}"` : ''} 
           ${(onclick !== '') ? `onclick="${onclick}"` : ''} 
           ${disabled}>
          ${(icon !== '') ? `<i class="fas ${icon} cal-icon"></i>` : ''} 
          ${item['title']}
        </a>`;
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
 * @returns {string} the HTML string containing the cell
 */
function generateDayCell(year, month, day, curr_view_month, view_semester=false) {
    const cell_date_str = dateToStr(year, month ,day);
    let content;
    if (view_semester) {
        content = `<td class="cal-day-cell cal-cell-expand" id="cell-${cell_date_str}">`;
    }
    else {
        content = `<td class="cal-day-cell" id="cell-${cell_date_str}">`;
    }
    // Title of the day cell
    content += '<div class="cal-cell-title-panel">';
    if (view_semester) {
        content += `<span class="cal-curr-month-date cal-day-title">${monthNamesShort[month]} ${day},</span>`;
    }
    else if (month === curr_view_month) {
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
    for (const i in gradeables_by_date[cell_date_str]) {
        content += generateCalendarItem(gradeables_by_date[cell_date_str][i]);
    }
    content += `
      </div>
</td>
    `;
    return content;
}

/**
 * Generates the title area for the calendar.
 *
 * @param title_area the title of the calendar (month+year/semester/...)
 * @returns {string} the HTML code for the title area
 */
function generateCalendarHeader(title_area) {
    return `<table class='table table-striped table-bordered persist-area table-calendar'>
        <thead>
        
        <tr class="cal-navigation">
            ${title_area}
        </tr>
        <tr class='cal-week-title-row'>
            <th class="cal-week-title cal-week-title-sun">Sunday</th>
            <th class="cal-week-title cal-week-title-mon">Monday</th>
            <th class="cal-week-title cal-week-title-tue">Tuesday</th>
            <th class="cal-week-title cal-week-title-wed">Wednesday</th>
            <th class="cal-week-title cal-week-title-thr">Thursday</th>
            <th class="cal-week-title cal-week-title-fri">Friday</th>
            <th class="cal-week-title cal-week-title-sat">Saturday</th>
        </tr>
        </thead>
        <tbody>
        <tr>`;
}

/**
 * This function creates a table that shows the calendar.
 *
 * @param view_year : int year that the calendar is viewing
 * @param view_month : int month that the calendar is viewing (1 as January and 12 as December)
 * @returns {string} the HTML string contains the entire calendar table displaying view_month/view_year
 */
function generateCalendarOfMonth(view_year, view_month, view_day) {
    const startWeekday = new Date(view_year, view_month - 1, 1).getDay();
    // Header area: two buttons to move, and month
    let content = generateCalendarHeader(
        `<th colspan="3">
                <div class="cal-switch" id="prev-month-switch">
                    <a class="cal-btn cal-prev-btn" onclick="loadCalendar.apply(this, prevMonth(${view_month}, ${view_year}, ${view_day}))"><i class="fas fa-angle-left"></i></a>
                </div>            
            </th>
            <th colspan="1">
                <div class="cal-title">
                    <h2 class="cal-month-title" >${monthNames[view_month]}</h2>
                    <h3 class="cal-year-title" >${view_year}</h3>
                </div>
            </th>
            <th colspan="3">
                <div class="cal-switch" id="next-month-switch">
                    <a class="cal-btn cal-next-btn" onclick="loadCalendar.apply(this, nextMonth(${view_month}, ${view_year}, ${view_day}))"><i class="fas fa-angle-right"></i></a>
                </div>            
            </th>`);

    // Show days at the end of last month that belongs to the first week of current month
    if (startWeekday !== 0) {
        const lastMonthEnd = new Date(view_year, view_month - 1, 0).getDate();
        const lastMonthStart = lastMonthEnd + 1 - startWeekday;
        for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
            content += generateDayCell(view_year, view_month - 1, day, view_month);
        }
    }

    // Shows each day of current month
    const daysInMonth = new Date(view_year, view_month, 0).getDate();
    let weekday = startWeekday;
    for (let day = 1; day <= daysInMonth; day++) {
        content += generateDayCell(view_year, view_month, day, view_month);
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
            content += generateDayCell(view_year, view_month + 1, day, view_month);
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
 * This function creates a table that shows the calendar for one week.
 *
 * @param view_year : int year that the calendar is viewing
 * @param view_month : int month that the calendar is viewing (1 as January and 12 as December)
 * @param view_day : int day that the calendar is viewing
 * @returns {string} the HTML string contains the entire calendar table displaying view_month/view_year
 */
function generateCalendarOfMonthWeek(view_year, view_month, view_day) {
    const startWeekday = new Date(view_year, view_month - 1, 1).getDay();
    const currentDay = new Date(view_year, view_month - 1, view_day).getDay();
    // Header area: two buttons to move, and month
    let content = generateCalendarHeader(
        `<th colspan="3">
                <div class="cal-switch" id="prev-month-switch">
                    <a class="cal-btn cal-prev-btn" onclick="loadCalendar.apply(this, prevWeek(${view_month}, ${view_year}, ${view_day}))"><i class="fas fa-angle-left"></i></a>
                </div>            
            </th>
            <th colspan="1">
                <div class="cal-title">
                    <h2 class="cal-month-title" >${monthNames[view_month]}</h2>
                    <h3 class="cal-year-title" >${view_year}</h3>
                </div>
            </th>
            <th colspan="3">
                <div class="cal-switch" id="next-month-switch">
                    <a class="cal-btn cal-next-btn" onclick="loadCalendar.apply(this, nextWeek(${view_month}, ${view_year}, ${view_day}))"><i class="fas fa-angle-right"></i></a>
                </div>            
            </th>`);
    console.log(view_day, startWeekday, currentDay);
    // Show days at the end of last month that belongs to the first week of current month
        const lastMonthEnd = new Date(view_year, view_month - 1, 0).getDate();
        const lastMonthStart = lastMonthEnd + 1 - startWeekday;
        const daysInMonth = new Date(view_year, view_month, 0).getDate();
        let print_day = 0;
        // Show days at the end of last month that belongs to the first week of current month
        if(view_day-currentDay <= 0 ){
            for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
                content += generateDayCell(view_year, view_month - 1, day, view_month);
                print_day++
            }
        }

        // Make the day cells before the "current" date
        if (print_day < currentDay) {
            for (let day = view_day - currentDay + print_day; print_day < currentDay; day++) {
                content += generateDayCell(view_year, view_month, day, view_month);
                print_day++
            }
        }

        // Make the "current" day, and the days after in the month 
        for (let day = view_day; print_day <= 6 && day <= daysInMonth; day++) {
            content += generateDayCell(view_year, view_month, day, view_month);
            print_day++
        }

        // Makes any days that spill into the next month
        for (let day = 1; print_day <= 6; day++) {
            content += generateDayCell(view_year, view_month + 1, day, view_month);
            print_day++
        }
    content += `
        </tr>
        </tbody>
    </table> 
    `;
    return content;
}

/**
 * This function creates a table that shows the calendar for two weeks.
 *
 * @param view_year : int year that the calendar is viewing
 * @param view_month : int month that the calendar is viewing (1 as January and 12 as December)
 * @param view_day : int day that the calendar is viewing
 * @returns {string} the HTML string contains the entire calendar table displaying view_month/view_year
 */
function generateCalendarOfMonthTwoWeek(view_year, view_month, view_day) {
    const startWeekday = new Date(view_year, view_month - 1, 1).getDay();
    const currentDay = new Date(view_year, view_month - 1, view_day).getDay();
    // Header area: two buttons to move, and month
    let content = generateCalendarHeader(
        `<th colspan="3">
                <div class="cal-switch" id="prev-month-switch">
                    <a class="cal-btn cal-prev-btn" onclick="loadCalendar.apply(this, prevTwoWeeks(${view_month}, ${view_year}, ${view_day}))"><i class="fas fa-angle-left"></i></a>
                </div>            
            </th>
            <th colspan="1">
                <div class="cal-title">
                    <h2 class="cal-month-title" >${monthNames[view_month]}</h2>
                    <h3 class="cal-year-title" >${view_year}</h3>
                </div>
            </th>
            <th colspan="3">
                <div class="cal-switch" id="next-month-switch">
                    <a class="cal-btn cal-next-btn" onclick="loadCalendar.apply(this, nextTwoWeeks(${view_month}, ${view_year}, ${view_day}))"><i class="fas fa-angle-right"></i></a>
                </div>            
            </th>`);
    console.log(view_day, startWeekday, currentDay);
    // Show days at the end of last month that belongs to the first week of current month
    const lastMonthEnd = new Date(view_year, view_month - 1, 0).getDate();
    const lastMonthStart = lastMonthEnd + 1 - startWeekday;
    const daysInMonth = new Date(view_year, view_month, 0).getDate();
    let print_day = 0;
    // If last months days should show, generate them.
    if(view_day-currentDay <= 0 ){
        for (let day = lastMonthStart; day <= lastMonthEnd; day++) {
            content += generateDayCell(view_year, view_month - 1, day, view_month);
            print_day++
        }
    }

    // Make the day cells before the "current" date
    if (print_day < currentDay) {
        for (let day = view_day - currentDay + print_day; print_day < currentDay; day++) {
            content += generateDayCell(view_year, view_month, day, view_month);
            print_day++
        }
    }

    // Make the "current" day, and the days after in the month 
    for (let day = view_day; print_day <= 13 && day <= daysInMonth; day++) {
        content += generateDayCell(view_year, view_month, day, view_month);
        print_day++
        // If the day is the last day of the week, make a new row
        if (print_day === 7) {
            content += `
                </tr>
                <tr>
            `;
        }
    }

    // Makes any days that spill into the next month
    for (let day = 1; print_day <= 13; day++) {
        content += generateDayCell(view_year, view_month + 1, day, view_month);
        print_day++
        // If the day is the last day of the week, make a new row
        if (print_day === 7) {
            content += `
                </tr>
                <tr>
            `;
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
 * Creates a calendar of the entire semester.
 *
 * @param start the start date of the semester in the format of YYYY-mm-dd
 * @param end the end date of the semester in the format of YYYY-mm-dd
 * @param semester_name the name of the semester
 * @returns {string} the HTML string containing the cell
 */
function generateFullCalendar(start, end, semester_name) {
    // Header area: two buttons to move, and month
    let content = generateCalendarHeader(
        `<th colspan="3">    
            </th>
            <th colspan="1">
                <div class="cal-title">
                    <h2 class="cal-month-title" >${semester_name.split(' ')[0]}</h2>
                    <h3 class="cal-year-title" >${semester_name.split(' ')[1]}</h3>
                </div>
            </th>
            <th colspan="3">          
            </th>`);

    const startDate = parseDate(start);
    const endDate = parseDate(end);
    const currDate = startDate;


    const startWeekday = start();
    // Skip days at the end of last month that belongs to the first week of current month
    if (startWeekday !== 0) {
        content += `<td class="cal-day-cell" colspan="${startWeekday}"></td>`;
    }

    let weekday = startWeekday;
    while ((endDate.getTime() - startDate.getTime()) >= 0) {
        // Shows each day of current month
        content += generateDayCell(currDate.getFullYear(), currDate.getMonth()+1, currDate.getDate(), 0, true);
        if (weekday === 6) {
            weekday = 0;
            // Next week should show on next line
            content += '</tr><tr>';
        }
        else {
            weekday = weekday + 1;
        }

        currDate.setDate(currDate.getDate() + 1);
    }

    if (weekday !== 0) {
        const remain = 7 - weekday;
        content += `<td class="cal-day-cell" colspan="${remain}"></td>`;
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
function loadCalendar(month_, year_, day_, type) {
    if (type === 'month') {
        $('#full-calendar').html(generateCalendarOfMonth(year_, month_));
    }
    else if (type === 'two_week') {
        $('#full-calendar').html(generateCalendarOfMonthTwoWeek(year_, month_, day_));
    }
    else if (type === 'one_week') {
        $('#full-calendar').html(generateCalendarOfMonthWeek(year_, month_, day_));
    }
    $('.calendar-item-edit-button').on('click', function () {
        const dataset = this.dataset;
        editCalendarItemForm(dataset.type, window.atob(dataset.text), dataset.id, dataset.date);
    });
}

/**
 * Changes the calendar div to the required semester.
 *
 * @param start : string the start date of the semester in the format of YYYY-mm-dd
 * @param end the end date of the semester in the format of YYYY-mm-dd
 * @param semester_name the name of the semester
 */
function loadFullCalendar(start, end, semester_name) {
    $('#full-calendar').html(generateFullCalendar(start, end, semester_name));
}

function openNewItemModal() {
    $('#new-calendar-item-form').css('display', 'block');
}

