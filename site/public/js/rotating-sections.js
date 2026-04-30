/* global showPopup, closePopup, displayErrorMessage, displaySuccessMessage */

$(() => {
    $('[name=\'rotating_assignment_type\']').change(function () {
        const val = $(this).val();

        if (val !== 'redo') {
            $('#redo-data').hide();
        }
        else {
            $('#redo-data').show();
        }

        if (val !== 'fewest') {
            $('#fewest-exclude').hide();
        }
        else {
            $('#fewest-exclude').show();
        }
    });
});

$(document).ready(() => {
    $('#redo-data').hide();
    $('#fewest-exclude').hide();
});

function deleteRegistrationSection(button) {
    const sectionId = button.dataset.sectionId;
    if (!confirm(`Delete registration section ${sectionId}?`)) {
        return;
    }
    const deleteInput = document.getElementById('delete_reg_section');
    deleteInput.value = sectionId;
    deleteInput.closest('form').submit();
}

function showEditCourseIdPopup(button) {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-courseid-form');
    showPopup('#edit-courseid-form');

    const section_id = button.getAttribute('data-section-id');
    const course_id = button.getAttribute('data-course-id');

    form.find('input[name="section_id"]').val(section_id);
    form.find('#new-course-id').val(course_id);
    form.data('original-course-id', course_id);

    form.find('.form-body').scrollTop(0);
    $('#new-course-id').focus();
}

function updateCourseID() {
    const form = $('#edit-courseid-form');

    const course_id = $('#new-course-id').val();
    const section_id = form.find('input[name="section_id"]').val();
    const originalCourseId = form.data('original-course-id');

    if (!course_id || !section_id) {
        displayErrorMessage('Missing course ID or section ID');
        return false;
    }

    if (course_id !== originalCourseId && window.existingCourseIds.has(course_id)) {
        displayErrorMessage('That Course ID already exists. Please choose a unique one.');
        return false;
    }

    const data = new FormData();
    data.append('csrf_token', window.csrfToken);
    data.append('course_id', course_id);
    data.append('section_id', section_id);

    $.ajax({
        url: window.updateCourseIdUrl,
        type: 'POST',
        data: data,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                const { data } = response;

                displaySuccessMessage(data.message);

                document.getElementById(`course-id-${section_id}`).textContent = data.course_id;

                window.existingCourseIds.clear();

                document.querySelectorAll('.course-id-text').forEach((span) => {
                    window.existingCourseIds.add(span.textContent.trim());
                });

                closePopup();
            }
            else {
                displayErrorMessage(response.message);
            }
        },
        error: function (xhr) {
            console.error(xhr.responseText);
            displayErrorMessage('Unexpected server error');
        },
    });

    $('.popup-form').css('display', 'none');
    return false;
}

function addRegistrationSection() {
    const form = $('#add-registration-section-form');

    const sectionInput = document.getElementById('new-section-id');
    const courseInput = form.find('#new-course-id-num')[0];

    const section = sectionInput.value.trim();
    const course = courseInput.value.trim();

    const sectionRegex = /^[A-Za-z0-9_-]+$/;

    if (!section || !sectionRegex.test(section)) {
        sectionInput.setCustomValidity('Please match the requested format.');
        sectionInput.reportValidity();
        return false;
    }

    sectionInput.setCustomValidity('');

    if (window.existingCourseIds.has(course)) {
        displayErrorMessage('That Course ID already exists.');
        return false;
    }

    if (course) {
        $('#add_course_id_hidden').val(course);
    }

    $('#add_reg_section').val(section);
    $('#add_reg_section_submit').click();

    return false;
}

function showAddSectionPopup() {
    $('.popup-form').css('display', 'none');

    const form = $('#add-registration-section-form');

    showPopup('#add-registration-section-form');

    form.find('#new-section-id').val('');
    form.find('#new-course-id-num').val('');
    form.find('.form-body').scrollTop(0);

    $('#new-section-id').focus();
}

$(document).on('input', '#new-section-id', function () {
    this.setCustomValidity('');
});
