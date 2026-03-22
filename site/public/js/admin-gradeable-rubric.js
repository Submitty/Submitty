/* global csrfToken, buildCourseUrl, getGradeableId, setError, clearError, updateErrorMessage, isItempoolAvailable, getItempoolOptions, reloadInstructorEditRubric, closeAllComponents, ajaxUpdateGradeableProperty, updateGradeableErrorCallback */

function onItemPoolOptionChange(componentId) {
    const linkItemPool = $(`#yes-link-item-pool-${componentId}`);
    if (linkItemPool.is(':checked')) {
        $(`#component-itempool-${componentId}-cont`).removeClass('hide');
    }
    else {
        $(`#component-itempool-${componentId}-cont`).addClass('hide');
    }
}

function onPrecisionChange() {
    ajaxUpdateGradeableProperty(getGradeableId(), {
        precision: $('#point_precision_id').val(),
        csrf_token: csrfToken,
    }, () => {
        clearError('precision');
        updateErrorMessage();

        closeAllComponents(true)
            .then(() => {
                return reloadInstructorEditRubric(getGradeableId(), isItempoolAvailable(), getItempoolOptions());
            })
            .catch((err) => {
                alert(`Failed to reload the gradeable rubric! ${err.message}`);
            });
    }, updateGradeableErrorCallback);
}

function serializeRubric() {
    return (function () {
        const o = {};
        const a = this.serializeArray();
        const ignore = ['numeric_label_0', 'max_score_0', 'numeric_extra_0', 'numeric_extra_0',
            'text_label_0', 'checkpoint_label_0', 'num_numeric_items', 'num_text_items'];

        $.each(a, function () {
            if ($('#gradeable_rubric').find(`[name="${this.name}"]`).length === 0) {
                ignore.push(this.name);
            }
        });

        $('.ignore').each(function () {
            ignore.push($(this).attr('name'));
        });

        $('.checkpoints-table').find('.multi-field').each(function () {
            let label = '';
            let extra_credit = false;
            let skip = false;

            $(this).find('.checkpoint_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) { return; }

            $(this).find('.checkpoint_extra').each(function () {
                extra_credit = $(this).is(':checked');
                ignore.push($(this).attr('name'));
            });

            if (o['checkpoints'] === undefined) { o['checkpoints'] = []; }
            o['checkpoints'].push({ label: label, extra_credit: extra_credit });
        });

        $('.text-table').find('.multi-field').each(function () {
            let label = '';
            let skip = false;

            $(this).find('.text_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) { return; }

            if (o['text'] === undefined) { o['text'] = []; }
            o['text'].push({ label: label });
        });

        $('.numerics-table').find('.multi-field').each(function () {
            let label = '';
            let max_score = 0;
            let extra_credit = false;
            let skip = false;

            $(this).find('.numeric_label').each(function () {
                label = $(this).val();
                if ($.inArray($(this).attr('name'), ignore) !== -1) {
                    skip = true;
                }
                ignore.push($(this).attr('name'));
            });

            if (skip) { return; }

            $(this).find('.max_score').each(function () {
                max_score = parseFloat($(this).val());
                ignore.push($(this).attr('name'));
            });

            $(this).find('.numeric_extra').each(function () {
                extra_credit = $(this).is(':checked');
                ignore.push($(this).attr('name'));
            });

            if (o['numeric'] === undefined) { o['numeric'] = []; }
            o['numeric'].push({ label: label, max_score: max_score, extra_credit: extra_credit });
        });

        $.each(a, function () {
            if ($.inArray(this.name, ignore) !== -1) { return; }
            o[this.name] = this.value || '';
        });
        return o;
    }.call($('form')));
}

function saveRubric(redirect = true) {
    const values = serializeRubric();

    $('#save_status').text('Saving Rubric...').css('color', 'var(--text-black)');
    $.getJSON({
        type: 'POST',
        url: buildCourseUrl(['gradeable', getGradeableId(), 'rubric']),
        data: {
            values: values,
            csrf_token: csrfToken,
        },
        success: function (response) {
            if (response.status === 'success') {
                delete errors['rubric'];
                updateErrorMessage();
                if (redirect) {
                    window.location.replace(`${buildCourseUrl(['gradeable', getGradeableId(), 'update'])}?nav_tab=2`);
                }
            }
            else {
                errors['rubric'] = response.message;
                updateErrorMessage();
                alert('Error saving rubric, you may have tried to delete a component with grades.  Refresh the page');
            }
        },
        error: function (response) {
            alert('Error saving rubric.  Refresh the page');
            console.error(`Failed to parse response from server: ${response}`);
        },
    });
}

function initAdminGradeableRubric() {
    if ($('#no_custom_marks').is(':checked')) {
        $('#custom_marks_warning').show();
    }
    // toggle warning message if custom marks have already been used and the settings are changed
    $('#no_custom_marks').click(() => {
        $('#custom_marks_warning').show();
    });
    $('#yes_custom_marks').click(() => {
        $('#custom_marks_warning').hide();
    });

    loadTemplates().then(() => {
        const rubric_container = $('#gradeable_rubric');
        reloadInstructorEditRubric($('#g_id').val(), rubric_container.attr('data-notebook'), JSON.parse(rubric_container.attr('data-itempool-options'))).then(() => {
            const componentContainer = document.querySelector('.component-container');
            if (componentContainer) {
                const observer = new MutationObserver(() => {
                    const yesPeer = ($('.peer-component-container').length !== 0);
                    if (yesPeer) {
                        $('#page_4_nav').show();
                        $('#page_4_nav').removeClass('hidden-no-peers');
                    }
                    else {
                        $('#page_4_nav').hide();
                        $('#page_4_nav').addClass('hidden-no-peers');
                    }
                });

                observer.observe(componentContainer, {
                    childList: true,
                    subtree: true,
                });
            }
        }).catch((error) => {
            console.error('Failed to call reloadInstructorEditRubric', error);
        });
    });
}
