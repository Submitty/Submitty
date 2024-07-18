/* exported setCalendarMenuValues */
const settings_divs = ['#cal-gradeable-div', '#cal-date-div'];

function setCalendarMenuValues(div_class) {
    let set_visiblity = function () {
        settings_divs.forEach((cur_div) => {
            if (!cur_div.includes($(this).val())) {
                $(`.${div_class} ${cur_div}`).hide();
            }
            else {
                $(`.${div_class} ${cur_div}`).show();
            }
        });
    }

    // First sets the proper values
    $(`.${div_class} #show-menu`).each(set_visibility);

    // jQuery function to update all calendar menu boxes
    $(`.${div_class} #show-menu`).on('change', set_visibility);
}
