$(function() {
    $("input,textarea,select").on("change", function() {
        var elem = this;
        let formData = new FormData();
        formData.append('csrf_token', csrfToken);
        let entry;
        if(this.type === "checkbox") {
            entry = $(elem).is(":checked");
        }
        else {
            entry = elem.value;
        }
        formData.append("name", elem.name);
        formData.append("entry", entry);

        $.ajax({
            url: buildUrl({
                'component': 'admin',
                'page': 'configuration',
                'action': 'update'
            }),
            data: formData,
            type: "POST",
            processData: false,
            contentType: false,
            success: function(response) {
                response = JSON.parse(response);
                if(response['status'] === 'fail') {
                    alert(response['message']);
                    $(elem).focus();
                    elem.value = $(elem).attr("value");
                }
                $(elem).attr("value", elem.value);
            }
        });
    });
});