$("#edit-user-form").ready(function() {
    var form = $("#edit-user-form");
    var url = buildCourseUrl(['user_information']);
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data)['data'];
            $('[name="user_id"]').change(function() {
                autoCompleteOnUserId(json);
            });

            $('[name="user_id"]', form).autocomplete({
                appendTo: form,
                source: Object.keys(json),
                change: () => $('[name="user_id"]').change()
            });

            $(":text",$("#edit-user-form")).change(checkValidEntries);
        },
        error: function() {
            alert("Could not load user data, please refresh the page and try again.");
        }
    })
});

//opens modal with initial settings for new student
function newStudentForm() {
    $('[name="user_group"] option[value="4"]').prop('selected', true);
    $('#user-form-assigned-sections').hide();
    $('#user-form-student-error-message').show();
    $("#new-student-modal-title").css('display','block');
    $("#new-grader-modal-title").css('display','none');
    newUserForm();
}

//opens modal with initial settings for new grader
function newGraderForm() {
    $('[name="user_group"] option[value="3"]').prop('selected', true);
    $('#user-form-student-error-message').hide();
    $('#user-form-assigned-sections').show();
    $("#new-student-modal-title").css('display','none');
    $("#new-grader-modal-title").css('display','block');
    newUserForm();
}

//opens modal with initial settings for new user (student and grader)
function newUserForm() {
    $('.popup-form').css('display', 'none');
    var form = $("#edit-user-form");
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
    $("#edit-student-modal-title").css('display','none');
    $("#edit-grader-modal-title").css('display','none');
    $("#user-form-already-exists-error-message").css('display','none');
    $('[name="edit_user"]', form).val("false");
    $('[name="user_id"]', form).removeClass('readonly').prop('readonly', false);
    $('[name="manual_registration"]', form).prop('checked', true);

    if ($("#user_id").val() == "") {
        $("#user_id")[0].setCustomValidity("user_id is required");
    }
    if ($("#user_firstname").val() == "") {
        $("#user_firstname")[0].setCustomValidity("user_firstname is required");
    }
    if ($("#user_lastname").val() == "") {
        $("#user_lastname")[0].setCustomValidity("user_lastname is required");
    }
    checkValidEntries();
    captureTabInModal("edit-user-form");
}

//opens modal with initial settings for edit user
function editUserForm(user_id) {
    var url = buildCourseUrl(['users', 'details']) + `?user_id=${user_id}`;
    $.ajax({
        url: url,
        success: function(data) {
            var json = JSON.parse(data)['data'];
            var form = $("#edit-user-form");
            // will help to check whether the userForm is edited or not
            $('[name="edit_user"]', form).attr('data-user', data);

            form.css("display", "block");
            form.find('.form-body').scrollTop(0);
            if (json['user_group'] == 4) {
                $("#edit-student-modal-title").css('display','block');
                $("#edit-grader-modal-title").css('display','none');
            }
            else {
                $("#edit-student-modal-title").css('display','none');
                $("#edit-grader-modal-title").css('display','block');
            }
            $("#new-student-modal-title").css('display','none');
            $("#new-grader-modal-title").css('display','none');
            $("#user-form-already-exists-error-message").css('display','none');
            $('[name="edit_user"]', form).val("true");
            var user = $('[name="user_id"]', form);
            user.val(json['user_id']);
            user.attr('readonly', 'readonly');
            if (!user.hasClass('readonly')) {
                user.addClass('readonly');
            }
            completeUserFormInformation(json);
            clearValidityWarnings();
            captureTabInModal("edit-user-form");
        },
        error: function() {
            alert("Could not load user data, please refresh the page and try again.");
        }
    })
}

function deleteUserForm(user_id, firstname, lastname) {
    $('.popup-form').css('display', 'none');
    const form = $("#delete-user-form");
    $('[name="user_id"]', form).val(user_id);
    $('[name="displayed_fullname"]', form).val(firstname + " " + lastname);
    $('#user-fullname', form).html(firstname + " " + lastname);
    form.css("display", "block");
}

function userFormChange() {
    var user_elem = $("select[name='user_group']")[0];
    var is_student = user_elem.options[user_elem.selectedIndex].text === "Student";

    var regis_elem = $("select[name='registered_section']")[0];
    var is_no_regis = regis_elem.options[regis_elem.selectedIndex].text === "Not Registered";

    if(is_student && is_no_regis) {
        $("#user-form-student-error-message").show();
    }
    else {
        $("#user-form-student-error-message").hide();
    }
    if(is_student) {
        $("#user-form-assigned-sections").hide();
    }
    else {
        $("#user-form-assigned-sections").show();
    }
}

function checkValidEntries() {
    var form = $("#edit-user-form");
    var input = $(this);
    switch(input.prop("id")) {
        case "user_id":
            if (input.val() == "") {
                input[0].setCustomValidity(input.prop('id') + " is required");
                break;
            }
            if (!$('#user-form-already-exists-error-message').is(':hidden')) {
                input[0].setCustomValidity(input.prop('id') + " already exists");
                break;
            }
            var valid_expression = /^[a-z0-9_\-]*$/;
            setRedOrTransparent(input, valid_expression);
            break;
        case "user_numeric_id":
            break;
        case "user_firstname":
        case "user_lastname":
            if (input.val() == "") {
                input[0].setCustomValidity(input.prop('id') + " is required");
                break;
            }
            var valid_expression = /^[a-zA-Z'`\-\.\(\) ]*$/;
            setRedOrTransparent(input, valid_expression);
            break;
        case "user_preferred_firstname":
        case "user_preferred_lastname":
            var valid_expression = /^[a-zA-Z'`\-\.\(\) ]{0,30}$/;
            setRedOrTransparent(input, valid_expression);
            break;
        case "user_email":
            if (input.val() == '') {
                input.css("background-color", "transparent");
                break;
            }
            var valid_expression = /^[a-zA-Z0-9.!#$%&’*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,253}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,253}[a-zA-Z0-9])?)*$/;
            setRedOrTransparent(input, valid_expression);
            break;
        case "user_email_secondary":
            if (input.val() == '') {
                input.css("background-color", "transparent");
                break;
            }
            var valid_expression = /^[a-zA-Z0-9.!#$%&’*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,253}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,253}[a-zA-Z0-9])?)*$/;
            setRedOrTransparent(input, valid_expression);
            break;
    }

    //disable submit button if anything is invalid
    var has_invalid_entry = false;
    $(":text",$("#edit-user-form")).each( function() {
        if (!this.checkValidity()) {
            has_invalid_entry = true;
        }
    });
    if (has_invalid_entry) {
        $("#user-form-submit").prop('disabled',true);
    }
    else {
        $("#user-form-submit").prop('disabled',false);
    }
    captureTabInModal("edit-user-form", false);
}

function setRedOrTransparent(input,reg_expression) {
    if (!reg_expression.test(input.val())) {
        input[0].setCustomValidity(input.val()+" is not a valid "+input.prop('id'));
    }
    else {
        input[0].setCustomValidity("");
    }
}

function autoCompleteOnUserId(user_information) {
    if ($('#user_id').val() in user_information) {
        var user = user_information[$('#user_id').val()];
        var user_already_exists = user['already_in_course'] ? 'block' : 'none';
        $("#user-form-already-exists-error-message").css('display',user_already_exists);
        completeUserFormInformation(user);
    }
    else {
        $("#user-form-already-exists-error-message").css('display','none');
    }
}

function completeUserFormInformation(user) {
    var form = $("#edit-user-form");

    $('[name="user_numeric_id"]', form).val(user['user_numeric_id']);
    $('[name="user_firstname"]', form).val(user['user_firstname']);
    $('[name="user_firstname"]').change();
    $('[name="user_preferred_firstname"]', form).val(user['user_preferred_firstname']);
    $('[name="user_preferred_firstname"]').change();
    $('[name="user_lastname"]', form).val(user['user_lastname']);
    $('[name="user_lastname"]').change();
    $('[name="user_preferred_lastname"]', form).val(user['user_preferred_lastname']);
    $('[name="user_preferred_lastname"]').change();
    $('[name="user_email"]', form).val(user['user_email']);
    $('[name="user_email_secondary"]', form).val(user['user_email_secondary']);
    $('[name="user_email"]').change();
    $('[name="registration_subsection"]', form).val(user['registration_subsection']);
    $('[name="registration_subsection"]').change();

    var registration_section;
    if (user['registration_section'] === null) {
        registration_section = "null";
    }
    else {
        registration_section = user['registration_section'].toString();
    }
    var rotating_section;
    if (user['rotating_section'] === null) {
        rotating_section = "null";
    }
    else {
        rotating_section = user['rotating_section'].toString();
    }
    $('[name="registered_section"] option[value="' + registration_section + '"]', form).prop('selected', true);
    $('[name="rotating_section"] option[value="' + rotating_section + '"]', form).prop('selected', true);
    if (user['already_in_course']) {
        $('[name="user_group"] option[value="' + user['user_group'] + '"]', form).prop('selected', true);
        $('[name="manual_registration"]', form).prop('checked', user['manual_registration']);
    }
    $("[name='grading_registration_section[]']").prop('checked', false);
    if (user['grading_registration_sections'] !== null && user['grading_registration_sections'] !== undefined) {
        user['grading_registration_sections'].forEach(function(val) {
            $('#grs_' + val).prop('checked', true);
        });
    }
    if (registration_section === 'null' && $('[name="user_group"] option[value="4"]', form).prop('selected')) {
        $('#user-form-student-error-message').css('display', 'block');
    }
    else {
        $('#user-form-student-error-message').css('display', 'none');
    }
    if ($('[name="user_group"] option[value="4"]', form).prop('selected')) {
        $('#user-form-assigned-sections').hide();
    }
    else {
        $('#user-form-assigned-sections').show();
    }
}

function isUserFormEmpty() {
  let form = document.querySelectorAll('form')[0];
  let isFormEmpty = true;
  for (const formElement of form.elements) {
    const {name, value, type, checked} = formElement;
    // skipping all the hidden fields, user-group and manual-registration
    if (type === 'hidden' || name === 'user_group' || name === 'manual_registration') {
      continue;
    }
    else if ((type === "text" && value.length) || (type === "select-one" && value !=='null') || checked){
      isFormEmpty = false;
      break;
    }
  }
  return isFormEmpty;
}

function isUserFormEdited() {
  let form = document.querySelectorAll('form')[0];

  let data = $('[name="edit_user"]').attr('data-user');
  let user = JSON.parse(data)['data'];
  let rotating_section = user['rotating_section'] === null ? "null" : user['rotating_section'].toString();
  let registration_section = user['registration_section'] === null ? "null" : user['registration_section'].toString();

  let user_grading_sections = user['grading_registration_sections'] ? user['grading_registration_sections'].sort() : [];

  let current_grading_sections = [];
  $('[name="grading_registration_section[]"]').each(function () {
    if ($(this).prop('checked')) {
      current_grading_sections.push(+$(this).val());
    }
  })
  // check if there is any changes made in the grading checkboxes.
  let grading_sections_edited = !(current_grading_sections.length === current_grading_sections.length && current_grading_sections.every((num, idx) => num === user_grading_sections[idx]));

  return (
    $('[name="user_numeric_id"]', form).val() !== user['user_numeric_id'] ||
    $('[name="user_firstname"]', form).val() !== user['user_firstname'] ||
    $('[name="user_lastname"]', form).val() !== user['user_lastname'] ||
    $('[name="user_preferred_firstname"]', form).val() !== user['user_preferred_firstname'] ||
    $('[name="user_preferred_lastname"]', form).val() !== user['user_preferred_lastname'] ||
    $('[name="user_email"]', form).val() !== user['user_email'] ||
    ! $('[name="user_group"]  option[value="' + user['user_group'] + '"]').prop('selected')  ||
    $('[name="manual_registration"]', form).prop('checked') !==  user['manual_registration'] ||
    ! $('[name="registered_section"] option[value="' + registration_section + '"]').prop('selected') ||
    ! $('[name="rotating_section"] option[value="' + rotating_section + '"]').prop('selected') ||
    $('[name="registration_subsection"]', form).val() !== user['registration_subsection'] ||
    grading_sections_edited
  )
}

function clearUserFormInformation() {
    let form = document.querySelectorAll('form')[0];
    for (const formElement of form.elements) {
      const {name, value, type, checked} = formElement;
      if (type === "text") {
        formElement.value = "";
      } else if (type === "select-one") {
        formElement.selectedIndex = 0;
      } else if (type === "checkbox") {
          // clear the checkbox for all the checkboxes other than manual_registration
          formElement.checked = name === "manual_registration";
      }
    }
    clearValidityWarnings();
}

function clearValidityWarnings() {
    $(":text",$("#edit-user-form")).each( function() {
        $(this)[0].setCustomValidity("");
    });
    $("#user-form-submit").prop('disabled',false);
    captureTabInModal("edit-user-form", false);
}

function closeButton() {
  let form = document.querySelectorAll('form')[0];
  let closeForm = false;
  // edit from manage page
  if($('[name="edit_user"]', form).val() === "true") {
    if (isUserFormEdited()) {
       if (confirm('Changes to the form will be lost!')) {
         $('[name="edit_user"]', form).attr('data-user', '');
         closeForm = true;
       }
    } else {
      closeForm = true;
    }
  } else {
    // If form contain some data, prompt the user for data loss
    if (!isUserFormEmpty()) {
      if (confirm('Changes to the form will be lost!')) {
        closeForm = true;
      }
    } else {
      closeForm = true;
    }
  }
  if (closeForm) {
    clearUserFormInformation();
    $('#edit-user-form').css('display', 'none');
  }
}

function redirectToEdit() {
    editUserForm($('#user_id').val());
}
