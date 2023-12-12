/* exported setCalendarMenuValues*/
const settings_divs = ['#cal-gradeable-div', '#cal-date-div'];

function setCalendarMenuValues(div_class) {
    //First sets the proper values
    $(`.${div_class} #show-menu`).each(function () {
        settings_divs.forEach(cur_div => {
            if (!cur_div.includes($(this).val())) {
                $(`.${div_class} ${cur_div}`).css('display','none');
            }
            else {
                $(`.${div_class} ${cur_div}`).css('display','block');
            }
        });
    });

    //jquery function to update all calendar menu boxes
    $(`.${div_class} #show-menu`).on('change', function () {
        settings_divs.forEach(cur_div => {
            if (!cur_div.includes($(this).val())) {
                $(`.${div_class} ${cur_div}`).css('display','none');
            }
            else {
                $(`.${div_class} ${cur_div}`).css('display','block');
            }
        });
    });
}
