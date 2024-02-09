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
        success: function(data) {
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
                alert('Error parsing response from server. Please copy the contents of your Javascript Console and ' +
                    'send it to an administrator, as well as what you were doing and what files you were uploading.');
                console.log(data);
            }
        },
        error: function() {
            window.location.href = buildUrl(['banner']);
        },
    });
}


function imageSelectionUpdate() {
    if (image_banner === false) {
        $('#extra_name').show();
        image_banner = true;
    }
    else {
        image_banner = false;
        $('#extra_name').hide();
    }
}


function urlSelectionUpdate() {
    if (url_banner === false) {
        url_banner = true;
        $('#url_link_address').show();
    }
    else {
        url_banner = false;
        $('#url_link_address').hide();
    }
} 