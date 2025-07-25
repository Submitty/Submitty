/* exported setCalendarMenuValues */
const settings_divs = ['#cal-gradeable-div', '#cal-date-div'];

function setCalendarMenuValues(div_class, tag, number) {
    function updateCalendarMenuVisibility(container) {
        if (!container) {
            return;
        }

        const dateInput = container.querySelector('#cal-date-div');
        const gradeableSelect = container.querySelector('#cal-gradeable-div');
        const showOnCalendar = container.querySelector('#show-menu');

        if (!dateInput || !gradeableSelect || !showOnCalendar) {
            return;
        }

        if (showOnCalendar.value === 'gradeable') {
            dateInput.style.display = 'none';
            gradeableSelect.style.display = 'block';
        }
        else if (showOnCalendar.value === 'date') {
            dateInput.style.display = 'block';
            gradeableSelect.style.display = 'none';
        }
        else {
            dateInput.style.display = 'none';
            gradeableSelect.style.display = 'none';
        }
    }

    const associated_date = $(tag).data('associated-date') ?? 'none';
    const on_calendar = $(tag).data('is-on-calendar') ?? false;
    const gradeable = $(tag).data('gradeable') ?? 'none';

    const container = document.getElementById(number);
    if (container) {
        const dateInput = container.querySelector('#associated-date');
        if (dateInput) {
            dateInput.value = associated_date;
        }

        const gradeableSelect = container.querySelector('#gradeable-select');
        if (gradeableSelect) {
            gradeableSelect.value = gradeable;
        }

        const showOnCalendar = container.querySelector('#show-menu');
        if (showOnCalendar) {
            if (on_calendar && gradeable !== 'none') {
                showOnCalendar.value = 'gradeable';

                dateInput.display = 'none';
            }
            else if (on_calendar && associated_date !== 'none') {
                showOnCalendar.value = 'date';
                gradeableSelect.display = 'none';
            }
            else {
                showOnCalendar.value = 'none';
                gradeableSelect.display = 'none';
                dateInput.display = 'none';
            }
        }
        showOnCalendar.addEventListener('change', () => {
            updateCalendarMenuVisibility(container);
        });
    }
    updateCalendarMenuVisibility(container);
}
