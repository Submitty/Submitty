/* global previewMarkdown buildCourseUrl */
/* exported previewQueueMarkdown */

function previewQueueMarkdown(){
    const announcement_textarea = $('#queue-announcement-message');
    const preview = $('#queue_announcement_message_preview');
    const preview_button = $('button[title="Preview Markdown"]');
    const announcement_content = announcement_textarea.val();

    previewMarkdown(announcement_textarea, preview, preview_button, { content: announcement_content });
}
