/* global previewMarkdown */
/* exported previewPollMarkdown */

function previewPollMarkdown() {
    const markdown_textarea = $('#poll-question');
    const preview_element = $('[id^="poll_preview_"]');
    const preview_button = $('[title="Preview Markdown"]');
    const content = markdown_textarea.val();

    previewMarkdown(markdown_textarea, preview_element, preview_button, {content: content});
}
