$(function() {
    $("[name='rotating_assignment_type']").change(function() {
        var val = $(this).val();
        if (val !== "redo") {
            $('#redo-data').hide();
        } else {
            $('#redo-data').show();
        }
        if (val !== "fewest") {
            $('#fewest-exclude').hide();
        } else {
            $('#fewest-exclude').show();
        }
    });

    $('#redo-data').hide();
    $('#fewest-exclude').hide();

    // Populate existingCourseIds from the DOM
    document.querySelectorAll('.course-id-text').forEach(span => {
        const text = span.textContent.trim();
        if (text) {
            existingCourseIds.add(text);
        }
    });
});

let existingCourseIds = new Set();

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
    const course_id  = button.getAttribute('data-course-id');
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

    if (course_id !== originalCourseId && existingCourseIds.has(course_id)) {
        displayErrorMessage('That Course ID already exists. Please choose a unique one.');
        return false;
    }

    const data = new FormData();
    data.append('csrf_token', csrfToken);
    data.append('course_id', course_id);
    data.append('section_id', section_id);

    $.ajax({
        url: updateCourseIdUrl,
        type: 'POST',
        data: data,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                const { data } = response;
                displaySuccessMessage(data.message);
                document.getElementById("course-id-" + section_id).textContent = data.course_id;
                
                existingCourseIds.clear();
                document.querySelectorAll('.course-id-text').forEach(span => {
                    const text = span.textContent.trim();
                    if (text) {
                        existingCourseIds.add(text);
                    }
                });

                closePopup();
            } else {
                displayErrorMessage(response.message);
            }
        },
        error: function (xhr) {
            console.error(xhr.responseText);
            displayErrorMessage('Unexpected server error');
        }
    });

    $('.popup-form').css('display', 'none');
    return false;
}
