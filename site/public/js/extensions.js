function updateGradeableForExtensions() {
    var g_id = $('#gradeable-select').val();
    document.cookie = "exception_gid=" + g_id;
    window.location=window.location; // pseudo post/redirect/get pattern
}

function updateHomeworkExtension() {
    var fd = new FormData($('#extensions-form').get(0));
    var url = buildNewCourseUrl(['extensions', 'update']);
    $.ajax({
        url: url,
        type: "POST",
        data: fd,
        processData: false,
        cache: false,
        contentType: false,
        success: function(data) {
            try {
                var json = JSON.parse(data);
            } catch(err) {
                window.alert("Error parsing data. Please try again.");
            }
            if (json['data'] && json['data']['is_team']) {
                extensionPopup(json);
            } else {
                window.location=window.location; // pseudo post/redirect/get pattern
            }
        },
        error: function() {
            window.alert("Something went wrong. Please try again.");
        }
    });
}

function deleteHomeworkExtension(user) {
    $("#user_id").val(user);
    $("#late-days").val(0);
    updateHomeworkExtension();
}

function clearDate() {
    document.getElementById("late-calendar").value = "";
}

function clearLateDays() {
    document.getElementById("late-days").value = "";
}

function confirmExtension(option){
    $('.popup-form').css('display', 'none');
    $('input[name="option"]').val(option);
    $('#excusedAbsenceForm').submit();
    $('input[name="option"]').val(-1);
}

function extensionPopup(json){
    $('.popup-form').css('display', 'none');
    var form = $('#more_extension_popup');
    form[0].outerHTML = json['data']['popup'];
    $('#more_extension_popup').css('display', 'block');
}