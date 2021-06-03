
function previewQueueMarkdown(){
    const announcement_textarea = $(`#queue-announcement-message`);
    const preview = $(`#queue_announcement_message_preview`);
    const preview_button = $(`button[title="Preview Markdown"]`);
    const url = buildCourseUrl(['office_hours_queue', 'preview']);
    const announcement_content = announcement_textarea.val();

    previewMarkdown(announcement_textarea, preview, preview_button, url, { content: announcement_content });
}