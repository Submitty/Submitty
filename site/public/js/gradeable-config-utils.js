// This file contains utility functions that both UploadConfigForm.twig and AdminGradeableAuto.twig use.

function downloadConfig(file_path) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = buildCourseUrl(['autograding_config', 'download_zip']);
    form.style.display = 'none';

    const pathInput = document.createElement('input');
    pathInput.type = 'hidden';
    pathInput.name = 'curr_config_name';
    pathInput.value = file_path;
    form.appendChild(pathInput);

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit();
}