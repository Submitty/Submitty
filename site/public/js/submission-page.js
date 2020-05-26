function showActions(form_id) {
    var form = $("#" + form_id);
    form.css("display", "block");
    form.find('.form-body').scrollTop(0);
}
