/* global previewMarkdown */
/* exported previewPollMarkdown */

function previewPollMarkdown(mode) {
    const markdown_textarea = $('#poll-question');
    const preview_element = $('[id^="poll_preview_"]');
    const content = markdown_textarea.val();

    previewMarkdown(mode, markdown_textarea, preview_element, {content: content});
}
