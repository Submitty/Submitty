const page_window = 5;
function loadPage(page, load_page_url) {
    $(`#${page}`).addClass('selected');
    $(`#${page}`).attr('disabled', 'disabled');
    $('.page-btn').each(function() {
        $id = parseInt($(this).attr('id'));
        // To avoid race condition of what gets displayed first when buttons are spammed
        $(this).attr('disabled', 'disabled');
        // To avoid cluttering of buttons
        if ($id < parseInt(page) - page_window || parseInt(page) + page_window < $id) {
            $(this).hide();
        }
        else {
            $(this).show();
        }
    });
    $.ajax({
        type: 'GET',
        url: load_page_url,
        data: {
            'csrf_token': csrfToken,
            'page': page
        },
        success: function(data) {
            console.log(data);
            response = JSON.parse(data);
            if (response.status !== 'success') {
                displayErrorMessage(response.message);
            }
            else {
                $('#email-statuses').html(response.data);
            }
            $('.page-btn').each(function() {
                $id = parseInt($(this).attr('id'));
                if (parseInt(page) - page_window <= $id && $id <= parseInt(page) + page_window && $id != parseInt(page)) {
                    $(this).removeAttr('disabled');
                }
            });
        },
        error: function (err) {
            displayAjaxError(err);
            reject(err);
        }
    });
}

$(document).ready(function () {
    $('#1').trigger('click');
});