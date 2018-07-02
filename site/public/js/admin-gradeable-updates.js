
let errors = {};
function updateErrors(data) {
    $('#ajax_raw').html(data);
    if(Object.keys(errors).length !== 0) {
        $('#save_status').html('<span style="color: red">Some Changes Failed!</span>');
        $('#ajax_debug').html(errors);
    }
    else {
        $('#save_status').html('All Changes Saved');
        $('#ajax_debug').html('');
    }
}

function setError(name, err) {
    $('input[name="' + name + '"]').each(function (i, elem) {
        elem.title = (err[0] !== 0 ? '[Warning] ' : '') + err[1];
        elem.style.backgroundColor = '#FDD';
    });
    if(err[0] === 0)
        errors[name] = err[1];
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
        updateErrors(data);
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
            // ... but don't automatically save electronic rubric data
            if(!$('#radio_electronic_file').is(':checked')) {
                saveRubric(false);
            }
            return;
        }
        if($('#grader_assignment').find('[name="' + this.name + '"]').length > 0) {
            saveGraders();
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
        success: function (data, textStatus, xhr) {
            console.log('Request returned status code ' + xhr.status);
            if (typeof(successCallback) === "function") {
                successCallback();
            }
        },
        error: function (data) {
            console.log('[Error]: Request returned status code ' + data.status);
            if (typeof(errorCallback) === "function") {
                errorCallback(data.responseText);
            }
        }
    });
}

function serializeRubric() {
    return function () {

        // make pdf pages reflect the pdf page setting
        if ($('#yes_pdf_page').is(':checked')) {
            if ($('#yes_pdf_page_student').is(':checked')) {
                $("input[name^='page_component']").each(function () {
                    this.value = -1;
                });
            } else {
                $("input[name^='page_component']").each(function () {
                    if (this.value < 1) {
                        this.value = 1;
                    }
                });
            }
        } else {
            $("input[name^='page_component']").each(function () {
                this.value = 0;
            });
        }

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
            o[this.name] = this.value || '';
        });
        return o;
    }.call($('form'));
}

function saveRubric(redirect = true) {
    let values = serializeRubric();

    $('#save_status').html('Saving Rubric...');
    $.ajax({
        type: "POST",
        url: buildUrl({
            'component': 'admin',
            'page': 'admin_gradeable',
            'action': 'update_gradeable_rubric',
            'id': $('#g_id').val()
        }),
        data: values,
        success: function (data, textStatus, xhr) {
            console.log('Request returned status code ' + xhr.status);
            updateErrors();
            if(redirect) {
                window.location.replace(buildUrl({
                    'component': 'admin',
                    'page': 'admin_gradeable',
                    'action': 'edit_gradeable_page',
                    'id': $('#g_id').val(),
                    'nav_tab': '2'
                }));
            }
        },
        error: function (data) {
            console.log('[Error]: Request returned status code ' + data.status);
            updateErrors(data.responseText);
        }
    });
}

function serializeGraders() {
    // Setup graders with an array for each privilege level
    let graders = {};
    let minLevel = parseInt($('#minimum_grading_group').val());

    $('#grader_assignment').find('input').each(function () {
        let parts = this.name.split('_');

        // Ignore everything but checkboxes ('grader' prefix)
        if (parts[0] !== 'grader') return;

        // Ignore if we aren't at the right access level
        let level = parts[1].substr(1);
        if (level > minLevel) return;

        if ($(this).is(':checked')) {
            if (!(parts[3] in graders)) {
                graders[parts[3]] = [];
            }
            graders[parts[3]].push(parts[2]);
        }
    });

    return graders;
}

function saveGraders() {
    let values = serializeGraders();

    $('#save_status').html('Saving Graders...');
    $.ajax({
        type: "POST",
        url: buildUrl({
            'component': 'admin',
            'page': 'admin_gradeable',
            'action': 'update_gradeable_graders',
            'id': $('#g_id').val()
        }),
        data: {
            graders: values
        },
        success: function (data, textStatus, xhr) {
            console.log('Request returned status code ' + xhr.status);
            updateErrors();
        },
        error: function (data) {
            console.log('[Error]: Request returned status code ' + data.status);
            updateErrors(data.responseText);
        }
    });
}