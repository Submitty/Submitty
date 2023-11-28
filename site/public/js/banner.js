/* exported deleteBannerImage, handleUploadBanner */
/* global buildUrl */


/**
 * @param csrf_token
 */

function deleteBannerImage(csrf_token, imageName, imagePath, description, releaseDate, closeDate) {
    const formData = new FormData();
    formData.append('csrf_token', csrf_token);
    formData.append('name', imageName);
    formData.append('path', imagePath);
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
                    'send it to an administrator, as well as what you were doing and what files you were uploading. - [handleUploadCourseMaterials]');
                console.log(data);
            }
        },
        error: function() {
            window.location.href = buildUrl(['banner']);
        },
    });
}
