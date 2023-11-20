function imageSelectionUpdate(checked) {
    if (checked === true) {
        $('#extra_name').show();

    }
    else {
        $('#extra_name').show();
    }
}

function urlSelectionUpdate(checked) {
    if (checkred === true) {
        $('#url_link_address').show();

    }
    else {
        $('#url_link_address').hide();
    }
}  



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

    $.post(buildUrl(['banner', 'delete']), formData
    ).done(function(res) {
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
    }).fail(function() {
        window.location.href = buildUrl(['banner']);
    })


}


/**
 * @param csrf_token
 */

function handleUploadBanner(csrf_token, closeTime, releaseTime, extraName, linkName) {
    const formData = new FormData();
    formData.append('csrf_token', csrf_token);
    formData.append('close_time', closeTime);
    formData.append('release_time', releaseTime);
    formData.append('extra_name', extraName);
    formData.append('link_name', linkName);
    for (let i = 0; i < file_array.length; i++) {
        for (let j = 0; j < file_array[i].length; j++) {
            if (file_array[i][j].name.indexOf("'") !== -1 ||
              file_array[i][j].name.indexOf('"') !== -1) {
                alert(`ERROR! You may not use quotes in your filename: ${file_array[i][j].name}`);
                return;
            }
            else if (file_array[i][j].name.indexOf('\\') !== -1 ||
              file_array[i][j].name.indexOf('/') !== -1) {
                alert(`ERROR! You may not use a slash in your filename: ${file_array[i][j].name}`);
                return;
            }
            else if (file_array[i][j].name.indexOf('<') !== -1 ||
              file_array[i][j].name.indexOf('>') !== -1) {
                alert(`ERROR! You may not use angle brackets in your filename: ${file_array[i][j].name}`);
                return;
            }
            const k = fileExists(`/${file_array[i][j].name}`, 1);
            // Check conflict here
            if ( k[0] === 1 ) {
                if (!confirm(`Note: ${file_array[i][j].name} already exists. Do you want to replace it?`)) {
                    continue;
                }
            }
            formData.append(`files${i + 1}[]`, file_array[i][j], file_array[i][j].name);
        }
    }
    $.ajax({
        url: buildUrl(['banner', 'upload']),
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
