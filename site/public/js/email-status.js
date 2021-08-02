/* exported loadPage */
const page_window = 5;
function loadPage(page, load_page_url) {
    $(`#${page}`).addClass('selected');
    $(`#${page}`).attr('disabled', 'disabled');
    $('.page-btn').each(function() {
        $id = parseInt($(this).attr('id'));
        // To avoid race condition of what gets displayed first when buttons are spammed
        $(this).attr('disabled', 'disabled');
        $('#page-num').attr('disabled', 'disabled');
        // To avoid cluttering of buttons
        if ($id < parseInt(page) - page_window || parseInt(page) + page_window < $id) {
            $(this).hide();
        }
        else {
            $(this).show();
        }
    });
    $('#email-statuses').html('<div class="loading-animation"></div>');
    $.ajax({
        type: 'GET',
        url: load_page_url,
        data: {
            // eslint-disable-next-line no-undef
            'csrf_token': csrfToken,
            'page': page
        },
        success: function(data) {
            $('#email-statuses').html(data);
            $('.page-btn').each(function() {
                $id = parseInt($(this).attr('id'));
                if (parseInt(page) - page_window <= $id && $id <= parseInt(page) + page_window && $id != parseInt(page)) {
                    $(this).removeAttr('disabled');
                    $('#page-num').removeAttr('disabled');
                    $('#page-num').val(page);
                }
            });
            $('#pagination-nav').show();
        },
        error: function (err) {
            displayAjaxError(err);
            reject(err);
        }
    });
}

function textPageChange(){
    let page = parseInt($('#page-num').val());
    if (page < 1) {
        page = 1;
    }
    else if (page > parseInt($('#page-num').attr('max'))) {
        page = $('#page-num').attr('max');
    }
    $(`#${page}`).trigger('click');
}

$(document).ready(function () {
    $('#1').trigger('click');
    $('#page-num').on('change', textPageChange);
});