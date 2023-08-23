


function newCourseMaterialCalendarForm(id, file_name, str_id = null) {
    console.log(id);
    console.log(file_name);
    console.log(str_id);

    const form = $('#course-material-calendar-form');
    form.css('display', 'block');
    
    const settings_divs = ['#cal-gradeable-div', '#cal-date-div'];
    settings_divs.forEach(cur_div => {
        if (!cur_div.includes($('#show-menu').val())) {
            $(cur_div).css('display','none');
        }
        else {
            $(cur_div).css('display','block');
        }
    });
    $('#show-menu').on('change', function () {
        settings_divs.forEach(cur_div => {
            if (!cur_div.includes($(this).val())) {
                $(cur_div).css('display','none');
            }
            else {
                $(cur_div).css('display','block');
            }
        });
    });
}

function showSettingsDivs() {
    console.log($(this));
    const settings_divs = ['#cal-gradeable-div', '#cal-date-div'];
    settings_divs.forEach(cur_div => {
        if (!cur_div.includes($(this).val())) {
            $(cur_div).css('display','none');
        }
    });
}