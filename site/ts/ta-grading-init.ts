window.Twig.twig({
    id: 'Attachments',
    href: '/templates/grading/Attachments.twig',
    async: true,
});

$(() => {
    if (
        localStorage.getItem('notebook-setting-file-submission-expand')
        === 'true'
    ) {
        const notebookPanel = $('#notebook-view');
        if (notebookPanel.length !== 0) {
            const notebookItems = notebookPanel.find('.openAllFilesubmissions');
            for (let i = 0; i < notebookItems.length; i++) {
                $(notebookItems[i]).trigger('click');
            }
        }
    }
});
