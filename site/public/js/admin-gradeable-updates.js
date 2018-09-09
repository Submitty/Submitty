
let errors = {};
function updateErrorMessage() {
    if (Object.keys(errors).length !== 0) {
        $('#save_status').html('<span style="color: red">Some Changes Failed!</span>');
    }
    else {
        $('#save_status').html('All Changes Saved');
    }
}

function setError(name, err) {
    $('[name="' + name + '"]').each(function (i, elem) {
        elem.title = err;
        elem.style.backgroundColor = '#FDD';
    });
    errors[name] = err;
}

function clearError(name, update) {
    $('[name="' + name + '"]').each(function (i, elem) {
        elem.title = '';
        elem.style.backgroundColor = '';

        // Update the value if provided
        if(update !== undefined) {
            $(elem).val(update);
        }
    });
    // remove the error for this property
    delete errors[name];
}

function updatePdfPageSettings() {
    let pdf_page = $('#yes_pdf_page').is(':checked');
    let pdf_page_student = $('#yes_pdf_page_student').is(':checked');
    if (pdf_page === false) {
        $('#no_pdf_page_student').prop('checked', true);
    }
    setPdfPageAssignment(pdf_page === false ? PDF_PAGE_NONE : (pdf_page_student === true ? PDF_PAGE_STUDENT : PDF_PAGE_INSTRUCTOR))
        .catch(function (err) {
            alert('Failed to update pdf page setting! ' + err.message);
        });
}

function onPrecisionChange() {
    ajaxUpdateGradeableProperty(getGradeableId(), {
        'precision': $('#point_precision_id').val()
    }, function () {
        // Clear errors by just removing red background
        clearError('precision');
        updateErrorMessage();

        closeAllComponents(true)
            .then(function () {
                return reloadInstructorEditRubric(getGradeableId());
            })
            .catch(function (err) {
                alert('Failed to reload the gradeable rubric! ' + err.message);
            });
    }, updateGradeableErrorCallback);
}

function updateGradeableErrorCallback(message, response_data) {
    for (let key in response_data) {
        if (response_data.hasOwnProperty(key)) {
            setError(key, response_data[key]);
        }
    }
    updateErrorMessage();
}

$(document).ready(function () {
    window.onbeforeunload = function (event) {
        if (Object.keys(errors).length !== 0) {
            event.returnValue = 1;
        }
    };
    $('input,select,textarea').change(function () {
        if ($(this).hasClass('ignore')) {
            return;
        }
        // If its rubric-related, then make different request
        if ($('#gradeable_rubric').find('[name="' + this.name + '"]').length > 0) {
            // ... but don't automatically save electronic rubric data
            if (!$('#radio_electronic_file').is(':checked')) {
                saveRubric(false);
            }
            return;
        }
        if ($('#grader_assignment').find('[name="' + this.name + '"]').length > 0) {
            saveGraders();
            return;
        }
        // Don't save if it we're ignoring it
        if ($(this).hasClass('ignore')) {
            return;
        }

        let data = {};
        data[this.name] = $(this).val();
        let addDataToRequest = function (i, val) {
            if (val.type === 'radio' && !$(val).is(':checked')) {
                return;
            }
            if($('#no_late_submission').is(':checked') && $(val).attr('name') === 'late_days') {
                $(val).val('0');
            }
            data[val.name] = $(val).val();
        };

        // If its date-related, then submit all date data
        if ($('#gradeable-dates').find('input[name="' + this.name + '"]').length > 0
            || $(this).hasClass('date-related')) {
            $('#gradeable-dates :input,.date-related').each(addDataToRequest);
        }
        ajaxUpdateGradeableProperty($('#g_id').val(), data,
            function (response_data) {
                // Clear errors by setting new values
                for (let key in response_data) {
                    if (response_data.hasOwnProperty(key)) {
                        clearError(key, response_data[key]);
                    }
                }
                // Clear errors by just removing red background
                for (let key in data) {
                    if (data.hasOwnProperty(key)) {
                        clearError(key);
                    }
                }
                updateErrorMessage();
            }, updateGradeableErrorCallback);
    });
});

function ajaxUpdateGradeableProperty(gradeable_id, p_values, successCallback, errorCallback) {
    $('#save_status').html('Saving...');
    $.getJSON({
        type: "POST",
        url: buildUrl({
            'component': 'admin',
            'page': 'admin_gradeable',
            'action': 'update_gradeable',
            'id': gradeable_id
        }),
        data: p_values,
        success: function (response) {
            if (response.status === 'success') {
                successCallback(response.data);
            } else if (response.status === 'fail') {
                errorCallback(response.message, response.data);
            } else {
                alert('Internal server error');
                console.error(response.message);
            }
        },
        error: function (response) {
            console.error('Failed to parse response from server: ' + response);
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
            o[this.name] = this.value || '';
        });
        return o;
    }.call($('form'));
}

function saveRubric(redirect = true) {
    let values = serializeRubric();

    $('#save_status').html('Saving Rubric...');
    $.getJSON({
        type: "POST",
        url: buildUrl({
            'component': 'admin',
            'page': 'admin_gradeable',
            'action': 'update_gradeable_rubric',
            'id': $('#g_id').val()
        }),
        data: values,
        success: function (response) {
            if (response.status === 'success') {
                delete errors['rubric'];
                updateErrorMessage();
                if (redirect) {
                    window.location.replace(buildUrl({
                        'component': 'admin',
                        'page': 'admin_gradeable',
                        'action': 'edit_gradeable_page',
                        'id': $('#g_id').val(),
                        'nav_tab': '2'
                    }));
                }
            } else {
                errors['rubric'] = response.message;
                updateErrorMessage();
                alert('Error saving rubric, you may have tried to delete a component with grades.  Refresh the page');
            }
        },
        error: function (response) {
            alert('Error saving rubric.  Refresh the page');
            console.error('Failed to parse response from server: ' + response);
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
    $.getJSON({
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
        success: function (response) {
            if (response.status !== 'success') {
                alert('Error saving graders!');
                console.error(response.message);
                errors['graders'] = '';
            } else {
                delete errors['graders'];
            }
            updateErrorMessage();
        },
        error: function (response) {
            alert('Error saving graders!');
            console.error('Failed to parse response from server: ' + response);
        }
    });
}