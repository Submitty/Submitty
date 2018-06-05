
function fillAjaxErrorBox(data, statusCode) {
    if(statusCode !== 204) { //NO CONTENT
        $('#ajax_debug').html(data);
    }
    else {
        $('#ajax_debug').html('');
    }
}

$(document).ready(function () {
    $('input').change(function () {
        ajaxUpdateGradeableProperty($('#g_id').val(), this.name, $(this).val(),
            fillAjaxErrorBox, fillAjaxErrorBox);
    });
});

// TODO: move all js from the twig files to this file when moving to dynamic interface
function ajaxUpdateGradeableProperty(gradeable_id, p_name, p_val, successCallback, errorCallback) {
    // Workaround since you can't use 'p_name' as a key in the body of anonymous objects
    var data = {};
    data[p_name] = p_val;

    $.ajax({
        type: "POST",
        url: buildUrl({
            'component': 'admin',
            'page': 'admin_gradeable',
            'action': 'update_gradeable',
            'id': gradeable_id
        }),
        data: data,
        success: function(data, textStatus, xhr) {
            console.log('Request for "' + p_name + '" with value "' + p_val + '" returned status code ' + xhr.status);
            if (typeof(successCallback) === "function") {
                successCallback(data, xhr.status);
            }
        },
        error: function(data) {
            console.log('[Error]: Request for "' + p_name + '" with value "' + p_val + '" returned status code ' + data.status);
            if (typeof(errorCallback) === "function") {
                errorCallback(data.responseText, data.status);
            }
        }
    });
}