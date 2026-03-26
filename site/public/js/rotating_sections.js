let existingCourseIds = new Set();

$(function () {
    $("[name='rotating_assignment_type']").change(function () {
        const val = $(this).val();

        if (val !== "redo") {
            $("#redo-data").hide();
        } else {
            $("#redo-data").show();
        }

        if (val !== "fewest") {
            $("#fewest-exclude").hide();
        } else {
            $("#fewest-exclude").show();
        }
    });

    $("#redo-data").hide();
    $("#fewest-exclude").hide();

    // Populate existingCourseIds from the DOM
    document.querySelectorAll(".course-id-text").forEach((span) => {
        const text = span.textContent.trim();
        if (text) {
            existingCourseIds.add(text);
        }
    });
});

function deleteRegistrationSection(button) {
    const sectionId = button.dataset.sectionId;

    if (!confirm(`Delete registration section ${sectionId}?`)) {
        return;
    }

    const deleteInput = document.getElementById("delete_reg_section");
    deleteInput.value = sectionId;
    deleteInput.closest("form").submit();
}

function showEditCourseIdPopup(button) {
    $(".popup-form").css("display", "none");

    const form = $("#edit-courseid-form");
    showPopup("#edit-courseid-form");

    const sectionId = button.getAttribute("data-section-id");
    const courseId = button.getAttribute("data-course-id");

    form.find("input[name='section_id']").val(sectionId);
    form.find("#new-course-id").val(courseId);
    form.data("original-course-id", courseId);

    form.find(".form-body").scrollTop(0);
    $("#new-course-id").focus();
}

function updateCourseID() {
    const form = $("#edit-courseid-form");

    const courseId = $("#new-course-id").val();
    const sectionId = form.find("input[name='section_id']").val();
    const originalCourseId = form.data("original-course-id");

    if (!courseId || !sectionId) {
        displayErrorMessage("Missing course ID or section ID");
        return false;
    }

    if (courseId !== originalCourseId && existingCourseIds.has(courseId)) {
        displayErrorMessage("That Course ID already exists. Please choose a unique one.");
        return false;
    }

    const data = new FormData();
    data.append("csrf_token", csrfToken);
    data.append("course_id", courseId);
    data.append("section_id", sectionId);

    $.ajax({
        url: updateCourseIdUrl,
        type: "POST",
        data: data,
        processData: false,
        contentType: false,
        dataType: "json",
        success(response) {
            if (response.status === "success") {
                const { data: responseData } = response;

                displaySuccessMessage(responseData.message);

                document.getElementById(
                    "course-id-" + sectionId
                ).textContent = responseData.course_id;

                existingCourseIds.clear();

                document.querySelectorAll(".course-id-text").forEach((span) => {
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
        error(xhr) {
            console.error(xhr.responseText);
            displayErrorMessage("Unexpected server error");
        }
    });

    $(".popup-form").css("display", "none");
    return false;
}