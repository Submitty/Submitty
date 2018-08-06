function ajaxGetGradeableRubric(gradeable_id, success_callback, error_callback) {
    $.getJSON({
        type: "GET",
        url: buildUrl({
            'component': 'grading',
            'page': 'electronic',
            'action': 'get_gradeable_rubric',
            'gradeable_id': gradeable_id
        }),
        async: true,
        success: function (response) {
            if (response.status !== 'success') {
                error_callback(response.message, response.data);
                return;
            }
            if (typeof(success_callback) === 'function') {
                success_callback(response.data);
            }
        },
        error: function (response) {
            alert("Irrecoverable error when fetching gradeable");
            console.error(response);
        }
    });
}

function ajaxGetGradedGradeable(gradeable_id, submitter_id, success_callback, error_callback) {
    $.getJSON({
        type: "GET",
        url: buildUrl({
            'component': 'grading',
            'page': 'electronic',
            'action': 'get_graded_gradeable',
            'gradeable_id': gradeable_id,
            'submitter_id': submitter_id
        }),
        async: true,
        success: function (response) {
            if (response.status !== 'success') {
                error_callback(response.message, response.data);
                return;
            }
            if (typeof(success_callback) === 'function') {
                success_callback(response.data);
            }
        },
        error: function (response) {
            alert("Irrecoverable error when fetching graded gradeable");
            console.error(response);
        }
    });
}