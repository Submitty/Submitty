$(document).ready(function() {
  $("#gradeable-select").change(function () {
    var g_id = $('#gradeable-select').val();
    var expiration_date = new Date(Date.now());
    expiration_date.setDate(expiration_date.getDate() + 1);
    document.cookie = "exception_gid=" + g_id + "; expires=" + expiration_date.toUTCString();
    window.location = window.location; // pseudo post/redirect/get pattern
  });
});

function updateHomeworkExtension() {
    var fd = new FormData($('#extensions-form').get(0));
    var url = buildCourseUrl(['extensions', 'update']);
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
            }
            else {
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

function setLateDays() {
    let new_date = new Date($("#late-calendar").val());
    let old_date = new Date($("#due-date").data("date"));
    var diff = (new_date.getTime() - old_date.getTime()) / (1000 * 3600 * 24);
    document.getElementById("late-days").value = diff;
}

function confirmExtension(option){
    $('.popup-form').css('display', 'none');
    $('input[name="option"]').val(option);
    updateHomeworkExtension();
    $('input[name="option"]').val(-1);
}

function extensionPopup(json){
    $('.popup-form').css('display', 'none');
    var form = $('#more_extension_popup');
    form[0].outerHTML = json['data']['popup'];
    $('#more_extension_popup').css('display', 'block');
    $("#team-extension-cancel").focus();
}
