import { changeStudentArrowTooltips } from './ta-grading';
import { loadTAGradingSettingData, registerKeyHandler, settingsData } from './ta-grading-keymap';
import { gotoNextStudent, gotoPrevStudent } from './ta-grading-navigation';
import {
    closeComponent,
    CUSTOM_MARK_ID,
    getComponentIdByOrder,
    getFirstOpenComponentId,
    getMarkIdFromOrder,
    getNextComponentId,
    getPrevComponentId,
    NO_COMPONENT_ID,
    onToggleEditMode,
    scrollToComponent,
    scrollToOverallComment,
    toggleCommonMark,
    toggleComponent as oldToggleComponent,
} from './ta-grading-rubric';

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

function checkOpenComponentMark(index: number) {
    const component_id = getFirstOpenComponentId();
    if (component_id !== NO_COMPONENT_ID) {
        const mark_id = getMarkIdFromOrder(component_id, index);
        // TODO: Custom mark id is zero as well, should use something unique
        if (mark_id === CUSTOM_MARK_ID || mark_id === 0) {
            return;
        }
        void toggleCommonMark(component_id, mark_id).catch((err: { message: string }) => {
            console.error(err);
            alert(`Error toggling mark! ${err.message}`);
        });
    }
}

async function toggleComponent(component_id: number, saveChanges: boolean, edit_mode: boolean): Promise<void> {
    void edit_mode; // to avoid unused variable warning
    await oldToggleComponent(component_id, saveChanges);
    changeStudentArrowTooltips(
        localStorage.getItem('general-setting-arrow-function') || 'default',
    );
}

// Navigate to the prev / next student buttons
registerKeyHandler({ name: 'Previous Student', code: 'ArrowLeft' }, () => {
    gotoPrevStudent();
});
registerKeyHandler({ name: 'Next Student', code: 'ArrowRight' }, () => {
    gotoNextStudent();
});

// Key handler / shorthand for toggling in between panels
registerKeyHandler({ name: 'Toggle Autograding Panel', code: 'KeyA' }, () => {
    $('#autograding_results_btn button').trigger('click');
    window.updateCookies();
});
registerKeyHandler({ name: 'Toggle Rubric Panel', code: 'KeyG' }, () => {
    $('#grading_rubric_btn button').trigger('click');
    window.updateCookies();
});
registerKeyHandler({ name: 'Toggle Submissions Panel', code: 'KeyO' }, () => {
    $('#submission_browser_btn button').trigger('click');
    window.updateCookies();
});
registerKeyHandler(
    { name: 'Toggle Student Information Panel', code: 'KeyS' },
    () => {
        $('#student_info_btn button').trigger('click');
        window.updateCookies();
    },
);
registerKeyHandler({ name: 'Toggle Grade Inquiry Panel', code: 'KeyX' }, () => {
    $('#grade_inquiry_info_btn button').trigger('click');
    window.updateCookies();
});
registerKeyHandler({ name: 'Toggle Discussion Panel', code: 'KeyD' }, () => {
    $('#discussion_browser_btn button').trigger('click');
    window.updateCookies();
});
registerKeyHandler({ name: 'Toggle Peer Panel', code: 'KeyP' }, () => {
    $('#peer_info_btn button').trigger('click');
    window.updateCookies();
});

registerKeyHandler({ name: 'Toggle Notebook Panel', code: 'KeyN' }, () => {
    $('#notebook-view-btn button').trigger('click');
    window.updateCookies();
});
registerKeyHandler(
    { name: 'Toggle Solution/TA-Notes Panel', code: 'KeyT' },
    () => {
        $('#solution_ta_notes_btn button').trigger('click');
        window.updateCookies();
    },
);

registerKeyHandler({ name: 'Open Next Component', code: 'ArrowDown' }, (e: { preventDefault: () => void }) => {
    const openComponentId = getFirstOpenComponentId();
    const numComponents = $('#component-list').find(
        '.component-container',
    ).length;

    // Note: we use the 'toggle' functions instead of the 'open' functions
    //  Since the 'open' functions don't close any components
    if (openComponentId === NO_COMPONENT_ID) {
        // No component is open, so open the first one
        const componentId = getComponentIdByOrder(0);
        void toggleComponent(componentId, true, false).then(() => {
            scrollToComponent(componentId);
        });
    }
    else if (openComponentId === getComponentIdByOrder(numComponents - 1)) {
        // Last component is open, close it and then open and scroll to first component
        void closeComponent(openComponentId, true).then(() => {
            const componentId = getComponentIdByOrder(0);
            void toggleComponent(componentId, true, false).then(() => {
                scrollToComponent(componentId);
            });
        });
    }
    else {
        // Any other case, open the next one
        const nextComponentId = getNextComponentId(openComponentId);
        void toggleComponent(nextComponentId, true, false).then(() => {
            scrollToComponent(nextComponentId);
        });
    }
    e.preventDefault();
});

registerKeyHandler(
    { name: 'Open Previous Component', code: 'ArrowUp' },
    (e: { preventDefault: () => void }) => {
        const openComponentId = getFirstOpenComponentId();
        const numComponents = $('#component-list').find(
            '.component-container',
        ).length;

        // Note: we use the 'toggle' functions instead of the 'open' functions
        //  Since the 'open' functions don't close any components
        if (openComponentId === NO_COMPONENT_ID) {
            // No Component is open, so open the overall comment
            // Targets the box outside of the container, can use tab to focus comment
            // TODO: Add "Overall Comment" focusing, control
            scrollToOverallComment();
        }
        else if (openComponentId === getComponentIdByOrder(0)) {
            // First component is open, close it and then open and scroll to the last one
            void closeComponent(openComponentId, true).then(() => {
                const componentId = getComponentIdByOrder(numComponents - 1);
                void toggleComponent(componentId, true, false).then(() => {
                    scrollToComponent(componentId);
                });
            });
        }
        else {
            // Any other case, open the previous one
            const prevComponentId = getPrevComponentId(openComponentId);
            void toggleComponent(prevComponentId, true, false).then(() => {
                scrollToComponent(prevComponentId);
            });
        }
        e.preventDefault();
    },
);

// -----------------------------------------------------------------------------
// Misc rubric options
registerKeyHandler({ name: 'Toggle Rubric Edit Mode', code: 'KeyE' }, () => {
    const editBox = $('#edit-mode-enabled');
    editBox.prop('checked', !editBox.prop('checked'));
    void onToggleEditMode();
    window.updateCookies();
});

// -----------------------------------------------------------------------------
// Selecting marks

registerKeyHandler(
    { name: 'Select Full/No Credit Mark', code: 'Digit0' },
    () => {
        checkOpenComponentMark(0);
    },
);
registerKeyHandler({ name: 'Select Mark 1', code: 'Digit1' }, () => {
    checkOpenComponentMark(1);
});
registerKeyHandler({ name: 'Select Mark 2', code: 'Digit2' }, () => {
    checkOpenComponentMark(2);
});
registerKeyHandler({ name: 'Select Mark 3', code: 'Digit3' }, () => {
    checkOpenComponentMark(3);
});
registerKeyHandler({ name: 'Select Mark 4', code: 'Digit4' }, () => {
    checkOpenComponentMark(4);
});
registerKeyHandler({ name: 'Select Mark 5', code: 'Digit5' }, () => {
    checkOpenComponentMark(5);
});
registerKeyHandler({ name: 'Select Mark 6', code: 'Digit6' }, () => {
    checkOpenComponentMark(6);
});
registerKeyHandler({ name: 'Select Mark 7', code: 'Digit7' }, () => {
    checkOpenComponentMark(7);
});
registerKeyHandler({ name: 'Select Mark 8', code: 'Digit8' }, () => {
    checkOpenComponentMark(8);
});
registerKeyHandler({ name: 'Select Mark 9', code: 'Digit9' }, () => {
    checkOpenComponentMark(9);
});

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
