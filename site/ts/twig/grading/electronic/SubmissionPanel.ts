import { urlDecode } from '../../../utils/server';

// expand all files in Submissions and Results section
function openAll(click_class: string, class_modifier: string) {
    const toClose = $(`#div_viewer_${$(`.${click_class}${class_modifier}`).attr('data-viewer_id')}`).hasClass('open');
    $('#submission_browser').find(`.${click_class}${class_modifier}`).each(function () {
        // Check that the file is not a PDF before clicking on it
        const viewerID = $(this).attr('data-viewer_id');
        if (($(this).parent().hasClass('file-viewer') && $(`#file_viewer_${viewerID}`).hasClass('shown') === toClose) ||
            ($(this).parent().hasClass('div-viewer') && $(`#div_viewer_${viewerID}`).hasClass('open') === toClose)) {
            const innerText = Object.values($(this))[0].innerText;
            if (innerText.slice(-4) !== '.pdf') {
                $(this).click();
            }
        }
    });
}

$(() => {
    //note the commented out code here along with the code where files are displayed that is commented out
    //is intended to allow open and close to change dynamically on click
    //the problem is currently if you click the submissions folder then the text won't change b/c it's being double clicked effectively.
    $('.expand-button').on('click', function () {
        openAll('openable-element-', $(this).data('linked-type'));
    });

    $('#autoscroll_id').on('click', () => {
        updateCookies();
    });

    $('#submission-panel-download-zip').on('click', () => {
        const target = $('#submission-panel-download-zip');
        const gradeable_id = target.attr('data-gradeable-id')!;
        const anon_submitter_id = target.attr('data-anon-submitter-id')!;
        const active_version = parseInt(target.attr('data-active-version')!);

        downloadSubmissionZip(gradeable_id, anon_submitter_id, active_version, null, true);
    });

    // File viewer
    $('#file-container .file-viewer .file-viewer-open').on('click', (event: JQuery.TriggeredEvent) => {
        const target = $(event.currentTarget).parent();
        const dir = urlDecode(target.attr('data-dir')!);
        const path = urlDecode(target.attr('data-path')!);
        const id = target.attr('data-viewer-id');

        openFrame(dir, path, id);
        updateCookies();
    });

    $('#file-container .file-viewer .file-viewer-popup').on('click', (event: JQuery.TriggeredEvent) => {
        const target = $(event.currentTarget).parent();
        const dir = urlDecode(target.attr('data-dir')!);
        const path = urlDecode(target.attr('data-path')!);

        popOutSubmittedFile(dir, path);
    });

    $('#file-container .file-viewer .file-viewer-panel').on('click', (event: JQuery.TriggeredEvent) => {
        const target = $(event.currentTarget).parent();
        const dir = urlDecode(target.attr('data-dir')!);
        const path = urlDecode(target.attr('data-path')!);

        viewFileFullPanel(dir, path);
    });

    $('#file-container .file-viewer .file-viewer-download').on('click', (event: JQuery.TriggeredEvent) => {
        const target = $(event.currentTarget).parent();
        const path = urlDecode(target.attr('data-path')!);
        const title = urlDecode(target.attr('data-title')!);

        downloadFile(path, title);
    });

    // Dir viewer
    $('#file-container .div-viewer .file-viewer-dir').on('click', (event: JQuery.TriggeredEvent) => {
        const target = $(event.currentTarget).parent();
        const id = target.attr('data-viewer-id')!;

        openDiv(id);
        updateCookies();
    });

    const currentCodeStyle = localStorage.getItem('theme');
    const currentCodeStyleRadio = (currentCodeStyle == null || currentCodeStyle == 'light') ? 'style_light' : 'style_dark';
    $(`#${currentCodeStyleRadio}`).parent().addClass('active');
    $(`#${currentCodeStyleRadio}`).prop('checked', true);
});
