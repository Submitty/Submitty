$(document).ready(() => {
    $('input').on('change', function () {
        const elem = this;
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        let entry;
        if (this.type === 'checkbox') {
            entry = $(elem).is(':checked');
        }
        else {
            entry = elem.value;
        }
        formData.append('name', elem.name);
        formData.append('entry', entry);
        $.ajax({
            url: buildCourseUrl(['sections']),
            data: formData,
            type: 'POST',
            processData: false,
            contentType: false,
            success: function (response) {
                try {
                    response = JSON.parse(response);
                }
                catch (exc) {
                    console.log(response);
                    response = {
                        status: 'fail',
                        message: 'invalid response received from server',
                    };
                }
                if (response['status'] === 'fail') {
                    alert(response['message']);
                    $(elem).focus();
                    elem.value = $(elem).attr('value');
                }
                $(elem).attr('value', elem.value);
            },
        });
    });
});