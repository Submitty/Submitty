function previewPollMarkdown() {
    const markdown_textarea = $('#poll-question');
    const preview_element = $('[id^="poll_preview_"]');
    const preview_button = $('[title="Preview Markdown"]');
    const url = buildCourseUrl(['polls', 'preview']);
    const content = markdown_textarea.val();

    previewMarkdown(markdown_textarea, preview_element, preview_button, url, {content: content});
}