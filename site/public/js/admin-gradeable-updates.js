

let errors = {};
function updateErrors() {
    $('#ajax_debug').html(errors);
    if(Object.keys(errors).length !== 0) {
        $('#save_status').html('<span style="color: red">Some Changes Failed!</span>');
    }
    else {
        $('#save_status').html('All Changes Saved');
    }
}

function setError(name, err) {
    $('input[name="' + name + '"]').each(function (i, elem) {
        elem.title = err;
        elem.style.backgroundColor = '#FDD';
    });
    errors[name] = err;
}
function clearError(name) {
    $('input[name="' + name + '"]').each(function (i, elem) {
        elem.title = '';
        elem.style.backgroundColor = '';
    });
    // remove the error for this property
    delete errors[name];
}
function getErrorCallback(props) {
    return function(data) {
        let arr = JSON.parse(data);
        props.forEach(function (name) {
            if(name in arr['errors']) {
                setError(name, arr['errors'][name]);
            }
            else {
                clearError(name);
            }
        });
        updateErrors();
    }
}
function getSuccessCallback(props) {
    return function() {
        props.forEach(clearError);
        updateErrors();
    }
}

$(document).ready(function () {
    updateErrors();
    let onSimpleChange = function () {
        let data = {};
        data[this.name] = $(this).val();

        ajaxUpdateGradeableProperty($('#g_id').val(), data,
            getSuccessCallback(Object.keys(data)), getErrorCallback(Object.keys(data)));
    };
    $('textarea').change(onSimpleChange);
    $('select').change(onSimpleChange);
    $('input').change(function () {
        let data = {};
        data[this.name] = $(this).val();
        let addDataToRequest = function (i, val) {
            data[val.name] = $(val).val();
        };

        // If its date-related, then submit all date data
        if($('#gradeable-dates').find('input[name="' + this.name + '"]').length > 0) {
            $('#gradeable-dates :input').each(addDataToRequest);
        }
        ajaxUpdateGradeableProperty($('#g_id').val(), data,
            getSuccessCallback(Object.keys(data)), getErrorCallback(Object.keys(data)));
    });
});

// TODO: move all js from the twig files to this file when moving to dynamic interface
function ajaxUpdateGradeableProperty(gradeable_id, p_values, successCallback, errorCallback) {
    $('#save_status').html('Saving...');
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