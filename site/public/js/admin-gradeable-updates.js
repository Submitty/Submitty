function resetStyle(name) {
    $('input[name="' + name + '"]').each(function (i, elem) {
        elem.title = '';
        elem.style.backgroundColor = '';
    });
}
function getErrorCallback(props) {
    return function(data) {
        let arr = JSON.parse(data);
        $('#ajax_debug').html(data);
        props.forEach(function (name) {
            if(name in arr['errors']) {
                $('input[name="' + name + '"]').each(function (i, elem) {
                    elem.title = arr['errors'][name];
                    elem.style.backgroundColor = '#FDD';
                });
            }
            else {
                resetStyle(name);
            }
        })
    }
}
function getSuccessCallback(props) {
    return function() {
        $('#ajax_debug').html('');
        props.forEach(resetStyle);
    }
}

$(document).ready(function () {
    $('textarea').change(function () {
        var data = {};
        data[this.name] = $(this).val();

        ajaxUpdateGradeableProperty($('#g_id').val(), data,
            getSuccessCallback(Object.keys(data)), getErrorCallback(Object.keys(data)));
    });
    $('input').change(function () {
        var data = {};
        data[this.name] = $(this).val();

        // If its date-related, then submit all date data
        if($('#gradeable-dates').find('input[name="' + this.name + '"]').length > 0) {
            $('#gradeable-dates :input').each(function (i, val) {
                data[val.name] = $(val).val();
            });
        };
        ajaxUpdateGradeableProperty($('#g_id').val(), data,
            getSuccessCallback(Object.keys(data)), getErrorCallback(Object.keys(data)));
    });
});

// TODO: move all js from the twig files to this file when moving to dynamic interface
function ajaxUpdateGradeableProperty(gradeable_id, p_values, successCallback, errorCallback) {
    $.ajax({
        type: "POST",
        url: buildUrl({
            'component': 'admin',
            'page': 'admin_gradeable',
            'action': 'update_gradeable',
            'id': gradeable_id
        }),
        data: p_values,
        success: function(data, textStatus, xhr) {
            console.log('Request returned status code ' + xhr.status);
            if (typeof(successCallback) === "function") {
                successCallback();
            }
        },
        error: function(data) {
            console.log('[Error]: Request returned status code ' + data.status);
            if (typeof(errorCallback) === "function") {
                errorCallback(data.responseText);
            }
        }
    });
}