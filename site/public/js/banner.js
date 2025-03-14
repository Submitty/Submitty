/* exported deleteBannerImage, imageSelectionUpdate, urlSelectionUpdate */
/* global buildUrl */

/**
 * @param csrf_token
 */

function deleteBannerImage(csrf_token, id, imageName, imagePath, description, releaseDate, closeDate) {
    const formData = new FormData();
    formData.append('csrf_token', csrf_token);
    formData.append('name', imageName);
    formData.append('path', imagePath);
    formData.append('id', id);
    formData.append('description', description);
    formData.append('release_date', releaseDate);
    formData.append('close_date', closeDate);
    $.ajax({
        url: buildUrl(['banner', 'delete']),
        data: formData,
        processData: false,
        contentType: false,
        type: 'POST',
        success: function (data) {
            try {
                const jsondata = JSON.parse(data);

                if (jsondata['status'] === 'success') {
                    window.location.href = buildUrl(['banner']);
                }
                else {
                    alert(jsondata['message']);
                }
            }
            catch (e) {
                alert('Failed to delete banner image!');
                console.log(data);
            }
        },
        error: function () {
            window.location.href = buildUrl(['banner']);
        },
    });
}

function imageSelectionUpdate() {
    if ($('#extra_name').is(':visible')) {
        $('#extra_name').hide();
    }
    else {
        $('#extra_name').show();
    }
}

function urlSelectionUpdate() {
    if ($('#url_link_address').is(':visible')) {
        $('#url_link_address').hide();
    }
    else {
        $('#url_link_address').show();
    }
}
