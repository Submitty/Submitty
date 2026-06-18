import { changeStudentArrowTooltips } from './ta-grading';
import { loadTAGradingSettingData, settingsData } from './ta-grading-keymap';

const settingsCallbacks = {
    'general-setting-arrow-function': changeStudentArrowTooltips,
    'general-setting-navigate-assigned-students-only': function (
        value: string,
    ) {
        // eslint-disable-next-line eqeqeq
        if (value == 'true') {
            window.Cookies.set('view', 'assigned', { path: '/' });
        }
        else {
            window.Cookies.set('view', 'all', { path: '/' });
        }
    },
};

window.Twig.twig({
    id: 'Attachments',
    href: '/templates/grading/Attachments.twig',
    async: true,
});

$(() => {
    loadTAGradingSettingData();

    for (let i = 0; i < settingsData.length; i++) {
        for (let x = 0; x < settingsData[i].values.length; x++) {
            const storageCode = settingsData[i].values[x].storageCode;
            const item = localStorage.getItem(storageCode);
            if (
                item
                && Object.prototype.hasOwnProperty.call(
                    settingsCallbacks,
                    storageCode,
                )
            ) {
                if (storageCode in settingsCallbacks) {
                    settingsCallbacks[storageCode as keyof typeof settingsCallbacks](item);
                }
            }
        }
    }

    $('#settings-popup').on(
        'change',
        '.ta-grading-setting-option',
        function () {
            const storageCode = $(this).attr('data-storage-code');
            if (storageCode) {
                localStorage.setItem(storageCode, (this as HTMLSelectElement).value);
                if (
                    settingsCallbacks
                    && Object.prototype.hasOwnProperty.call(
                        settingsCallbacks,
                        storageCode,
                    )
                ) {
                    settingsCallbacks[storageCode as keyof typeof settingsCallbacks]((this as HTMLSelectElement).value);
                    if ((this as HTMLSelectElement).value !== 'active-inquiry') {
                        // if user change setting to non-grade inquiry option, change the inquiry_status to off and set inquiry_status to off in grading index page
                        window.Cookies.set('inquiry_status', 'off');
                    }
                    else {
                        window.Cookies.set('inquiry_status', 'on');
                    }
                }
            }
        },
    );

    // Progress bar value
    const value = $('.progressbar').val() ?? 0;
    // eslint-disable-next-line no-restricted-syntax
    $('.progress-value').html(`<b>${String(value)}%</b>`);

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
