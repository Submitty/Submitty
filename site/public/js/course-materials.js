/* exported setCalendarMenuValues */
const settings_divs = ['#cal-gradeable-div', '#cal-date-div'];

function setCalendarMenuValues(div_class, tag, number) {
    const set_visibility = function () {
        settings_divs.forEach((cur_div) => {
            if (!cur_div.includes($(this).val())) {
                $(`.${div_class} ${cur_div}`).hide();
            }
            else {
                $(`.${div_class} ${cur_div}`).show();
            }
        });
    };


const associated_date = $(tag).data('associated-date') ?? null;
const on_calendar = $(tag).data('is-on-calendar') ?? null;
const gradeable = $(tag).data('gradeable') ?? null;



if (associated_date !== null && on_calendar !== null && gradeable !== null) {
    console.log("Is On Calendar:", "date");
    console.log("Associated Date:", associated_date);
    console.log("Gradeable:", gradeable);
}

console.log(number);
let container = document.getElementById(number);
if (container) {
    let dateInput = container.querySelector('#associated-date');
    if (dateInput) {
        dateInput.value = associated_date;
    }

    let gradeableSelect = container.querySelector('#gradeable-select');
    if (gradeableSelect) {
        gradeableSelect.value = gradeable;
    }


    let showOnCalendar = container.querySelector('#show-menu');
    if (showOnCalendar) {
        showOnCalendar.value = "date";
    }


}


    // First sets the proper values
    $(`.${div_class} #show-menu`).each(set_visibility);

    // jQuery function to update all calendar menu boxes
    $(`.${div_class} #show-menu`).on('change', set_visibility);
}
