
let awaitingChanges = false;
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
        $('#ajax_raw').html(data);
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

    window.onbeforeunload = function(event) {
        if(Object.keys(errors).length !== 0) {
            event.returnValue = 1;
        }
    };
    $('input,select,textarea').change(function () {
        // If its rubric-related, then make different request
        if($('#gradeable_rubric').find('[name="' + this.name + '"]').length > 0) {
            //saveRubric();
            return;
        }

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
    while(awaitingChanges);
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

function serializeRubric() {
    return function () {
        let o = {};
        let a = this.serializeArray();
        let ignore = ["numeric_label_0", "max_score_0", "numeric_extra_0", "numeric_extra_0",
            "text_label_0", "checkpoint_label_0", "num_numeric_items", "num_text_items"];

        // Ignore all properties not on rubric
        $.each(a, function() {
            if($('#gradeable_rubric').find('[name="' + this.name + '"]').length === 0) {
                ignore.push(this.name);
            }
        });

        // Ignore all properties marked to be ignored
        $('.ignore').each(function () {
            ignore.push($(this).attr('name'));
        });

        //parse checkpoints

        $('.checkpoints-table').find('.multi-field').each(function () {
            var label = '';
            var extra_credit = false;
            var skip = false;

            $(this).find('.checkpoint_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) {
                return;
            }

            $(this).find('.checkpoint_extra').each(function () {
                extra_credit = $(this).is(':checked');
                ignore.push($(this).attr('name'));
            });

            if (o['checkpoints'] === undefined) {
                o['checkpoints'] = [];
            }
            o['checkpoints'].push({"label": label, "extra_credit": extra_credit});
        });


        // parse text items

        $('.text-table').find('.multi-field').each(function () {
            var label = '';
            var skip = false;

            $(this).find('.text_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) {
                return;
            }

            if (o['text'] === undefined) {
                o['text'] = [];
            }
            o['text'].push({'label': label});
        });

        // parse numeric items

        $('.numerics-table').find('.multi-field').each(function () {
            var label = '';
            var max_score = 0;
            var extra_credit = false;
            var skip = false;

            $(this).find('.numeric_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) {
                return;
            }

            $(this).find('.max_score').each(function () {
                max_score = parseFloat($(this).val());
                ignore.push($(this).attr('name'));
            });

            $(this).find('.numeric_extra').each(function () {
                extra_credit = $(this).is(':checked');
                ignore.push($(this).attr('name'));
            });

            if (o['numeric'] === undefined) {
                o['numeric'] = [];
            }
            o['numeric'].push({"label": label, "max_score": max_score, "extra_credit": extra_credit});

        });


        $.each(a, function () {
            if ($.inArray(this.name, ignore) !== -1) {
                return;
            }
            var val = this.value;
            if ($("[name=" + this.name + "]").hasClass('int_val')) {
                val = parseInt(val);
            }
            else if ($("[name=" + this.name + "]").hasClass('float_val')) {
                val = parseFloat(val);
            }

            else if ($("[name=" + this.name + "]").hasClass('bool_val')) {
                val = (this.value === 'true');
            }

            if ($("[name=" + this.name + "]").hasClass('grader')) {
                var tmp = this.name.split('_');
                var grader = tmp[1];
                if (o['grader'] === undefined) {
                    o['grader'] = [];
                }
                var arr = {};
                arr[grader] = this.value.trim();
                o['grader'].push(arr);
            }
            else if ($("[name=" + this.name + "]").hasClass('points')) {
                if (o['points'] === undefined) {
                    o['points'] = [];
                }
                o['points'].push(parseFloat(this.value));
            }
            else if ($("[name=" + this.name + "]").hasClass('complex_type')) {
                var classes = $("[name=" + this.name + "]").closest('.complex_type').prop('class').split(" ");
                classes.splice(classes.indexOf('complex_type'), 1);
                var complex_type = classes[0];

                if (o[complex_type] === undefined) {
                    o[complex_type] = [];
                }
                o[complex_type].push(val);
            }
            else if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(val || '');
            } else {
                o[this.name] = val || '';
            }
        });
        return o;
    }.call($('form'));
}

function saveRubric() {
    let values = serializeRubric();

    // Dont't swarm the server with updates if its getting
    //  backed up
    while(awaitingChanges);

    $('#save_status').html('Saving Rubric...');
    awaitingChanges = true;
    $.ajax({
        type: "POST",
        url: buildUrl({
            'component': 'admin',
            'page': 'admin_gradeable',
            'action': 'update_gradeable_rubric',
            'id': $('#g_id').val()
        }),
        data: values,
        success: function(data, textStatus, xhr) {
            console.log('Request returned status code ' + xhr.status);
            updateErrors();
            awaitingChanges = false;
        },
        error: function(data) {
            console.log('[Error]: Request returned status code ' + data.status);
            errors['rubric'] = 'Rubric failed to update!';
            updateErrors();
            awaitingChanges = false;
        }
    });
}

function serializeGraders() {


    // export appropriate users
    if ($('[name="minimum_grading_group"]').prop('value') == 1) {
        $('#full-access-graders').find('.grader').each(function () {
            ignore.push($(this).attr('name'));
        });
    }

    if ($('[name="minimum_grading_group"]').prop('value') <= 2) {
        $('#limited-access-graders').find('.grader').each(function () {
            ignore.push($(this).attr('name'));
        });
    }
}

function saveGraders() {

}