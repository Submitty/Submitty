/* global previewMarkdown */
/* exported previewQueueMarkdown */

function previewQueueMarkdown(mode){
    const announcement_textarea = $('#queue-announcement-message');
    const preview = $('#queue_announcement_message_preview');
    const announcement_content = announcement_textarea.val();

    previewMarkdown(mode, announcement_textarea, preview, { content: announcement_content });
}
