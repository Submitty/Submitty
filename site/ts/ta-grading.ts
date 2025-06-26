import { loadTAGradingSettingData, registerKeyHandler, settingsData } from './ta-grading-keymap';
import { gotoNextStudent, gotoPrevStudent } from './ta-grading-navigation';
import {
    closeComponent,
    CUSTOM_MARK_ID,
    getAnonId,
    getComponentIdByOrder,
    getFirstOpenComponentId,
    getGradeableId,
    getMarkIdFromOrder,
    getNextComponentId,
    getPrevComponentId,
    isSilentEditModeEnabled,
    NO_COMPONENT_ID,
    toggleComponent as oldToggleComponent,
    onToggleEditMode,
    scrollToComponent,
    scrollToOverallComment,
    toggleCommonMark,
} from './ta-grading-rubric';

declare global {
    interface Window {
        deleteAttachment(target: string, file_name: string): void;
        openAll (click_class: string, class_modifier: string): void;
        changeCurrentPeer(): void;
        clearPeerMarks (submitter_id: string, gradeable_id: string, csrf_token: string): void;
        newEditPeerComponentsForm(): void;
        imageRotateIcons (iframe: string): void;
        collapseFile (panel: string): void;
        uploadAttachment(): void;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-function-type
        registerKeyHandler(parameters: object, fn: Function): void;
        updateCookies (): void;
        openFrame(html_file: string, url_file: string, num: string, pdf_full_panel: boolean, panel: string): void;
        rotateImage(url: string | undefined, rotateBy: string): void;
        loadPDF(name: string, path: string, page_num: number, panelStr: string): JQueryXHR | undefined;
        viewFileFullPanel(name: string, path: string, page_num: number, panelStr: string): JQueryXHR | undefined;
    }
    interface JQueryStatic {
        active: number;
    }
}

// Used to reset users cookies
const cookie_version = 1;

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

function changeStudentArrowTooltips(data: string) {
    const inquiry_status = window.Cookies.get('inquiry_status');
    if (inquiry_status === 'on') {
        data = 'active-inquiry';
    }
    else {
        // if inquiry_status is off, and data equals active inquiry means the user set setting to active-inquiry manually
        // and need to set back to default since user also manually changed inquiry_status to off.
        if (data === 'active-inquiry') {
            data = 'default';
        }
    }
    let component_id = NO_COMPONENT_ID;
    switch (data) {
        case 'ungraded':
            component_id = getFirstOpenComponentId(false);
            if (component_id === NO_COMPONENT_ID) {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Previous ungraded student');
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Next ungraded student');
            }
            else {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Previous ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Next ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
            }
            break;
        case 'itempool':
            component_id = getFirstOpenComponentId(true);
            if (component_id === NO_COMPONENT_ID) {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Previous student');
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Next student');
            }
            else {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Previous student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Next student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
            }
            break;
        case 'ungraded-itempool':
            component_id = getFirstOpenComponentId(true);
            if (component_id === NO_COMPONENT_ID) {
                component_id = getFirstOpenComponentId();
                if (component_id === NO_COMPONENT_ID) {
                    $('#prev-student-navlink')
                        .find('i')
                        .first()
                        .attr('title', 'Previous ungraded student');
                    $('#next-student-navlink')
                        .find('i')
                        .first()
                        .attr('title', 'Next ungraded student');
                }
                else {
                    $('#prev-student-navlink')
                        .find('i')
                        .first()
                        .attr(
                            'title',
                            `Previous ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                        );
                    $('#next-student-navlink')
                        .find('i')
                        .first()
                        .attr(
                            'title',
                            `Next ungraded student (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                        );
                }
            }
            else {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Previous ungraded student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Next ungraded student (item ${$(`#component-${component_id}`).attr('data-itempool_id')}; ${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
            }
            break;
        case 'inquiry':
            component_id = getFirstOpenComponentId();
            if (component_id === NO_COMPONENT_ID) {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Previous student with inquiry');
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Next student with inquiry');
            }
            else {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Previous student with inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Next student with inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
            }
            break;
        case 'active-inquiry':
            component_id = getFirstOpenComponentId();
            if (component_id === NO_COMPONENT_ID) {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Previous student with active inquiry');
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr('title', 'Next student with active inquiry');
            }
            else {
                $('#prev-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Previous student with active inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
                $('#next-student-navlink')
                    .find('i')
                    .first()
                    .attr(
                        'title',
                        `Next student with active inquiry (${$(`#component-${component_id}`).find('.component-title').text().trim()})`,
                    );
            }
            break;
        default:
            $('#prev-student-navlink')
                .find('i')
                .first()
                .attr('title', 'Previous student');
            $('#next-student-navlink')
                .find('i')
                .first()
                .attr('title', 'Next student');
            break;
    }
}

async function toggleComponent(component_id: number, saveChanges: boolean, edit_mode: boolean): Promise<void> {
    void edit_mode; // to avoid unused variable warning
    await oldToggleComponent(component_id, saveChanges);
    changeStudentArrowTooltips(
        localStorage.getItem('general-setting-arrow-function') || 'default',
    );
}

window.updateCookies = function () {
    window.Cookies.set('silent_edit_enabled', String(isSilentEditModeEnabled()), {
        path: '/',
    });
    const autoscroll = $('#autoscroll_id').is(':checked') ? 'on' : 'off';
    window.Cookies.set('autoscroll', autoscroll, { path: '/' });

    let files: string[] = [];
    $('#file-container')
        .children()
        .each(function () {
            $(this)
                .children('div[id^=div_viewer_]')
                .each(function () {
                    files = files.concat(
                        findAllOpenedFiles(
                            $(this),
                            '',
                            $(this)[0].dataset.file_name!,
                            [],
                            true,
                        ),
                    );
                });
        });

    window.Cookies.set('files', JSON.stringify(files), { path: '/' });
    window.Cookies.set('cookie_version', String(cookie_version), { path: '/' });
};

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
// -----------------------------------------------------------------------------
// Show/hide components

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

// expand all files in Submissions and Results section
window.openAll = function (click_class: string, class_modifier: string) {
    const toClose = $(
        `#div_viewer_${$(`.${click_class}${class_modifier}`).attr('data-viewer_id')}`,
    ).hasClass('open');

    $('#submission_browser')
        .find(`.${click_class}${class_modifier}`)
        .each(function () {
            // Check that the file is not a PDF before clicking on it
            const viewerID = $(this).attr('data-viewer_id');
            if (
                ($(this).parent().hasClass('file-viewer')
                    && $(`#file_viewer_${viewerID}`).hasClass('shown')
                    === toClose)
                || ($(this).parent().hasClass('div-viewer')
                    && $(`#div_viewer_${viewerID}`).hasClass('open') === toClose)
            ) {
                const innerText = Object.values($(this))[0].innerText;
                if (innerText.slice(-4) !== '.pdf') {
                    $(this).click();
                }
            }
        });
};

function openDiv(num: string) {
    const elem = $(`#div_viewer_${num}`);
    if (elem.hasClass('open')) {
        elem.hide();
        elem.removeClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0])
            .removeClass('fa-folder-open')
            .addClass('fa-folder');
    }
    else {
        elem.show();
        elem.addClass('open');
        $($($(elem.parent().children()[0]).children()[0]).children()[0])
            .removeClass('fa-folder')
            .addClass('fa-folder-open');
    }
    return false;
}
window.openDiv = openDiv;

// finds all the open files and folder and stores them in stored_paths
function findAllOpenedFiles(elem: JQuery<HTMLElement>, current_path: string, path: string, stored_paths: string[], first: boolean) {
    if (first === true) {
        current_path += path;
        if ($(elem)[0].classList.contains('open')) {
            stored_paths.push(path);
        }
        else {
            return [];
        }
    }
    else {
        current_path += `#$SPLIT#$${path}`;
    }

    $(elem)
        .children()
        .each(function () {
            $(this)
                .children('div[id^=file_viewer_]')
                .each(function () {
                    if ($(this)[0].classList.contains('shown')) {
                        stored_paths.push(
                            `${current_path}#$SPLIT#$${$(this)[0].dataset.file_name}`,
                        );
                    }
                });
        });

    $(elem)
        .children()
        .each(function () {
            $(this)
                .children('div[id^=div_viewer_]')
                .each(function () {
                    if ($(this)[0].classList.contains('open')) {
                        stored_paths.push(
                            `${current_path}#$SPLIT#$${$(this)[0].dataset.file_name}`,
                        );
                        stored_paths = findAllOpenedFiles(
                            $(this),
                            current_path,
                            $(this)[0].dataset.file_name!,
                            stored_paths,
                            false,
                        );
                    }
                });
        });

    return stored_paths;
}

window.changeCurrentPeer = function () {
    const peer = $('#edit-peer-select').val() as string;
    $('.edit-peer-components-block').hide();
    $(`#edit-peer-components-form-${peer}`).show();
};

window.clearPeerMarks = function (submitter_id: string, gradeable_id: string, csrf_token: string) {
    const peer_id = $('#edit-peer-select').val();
    const url = buildCourseUrl([
        'gradeable',
        gradeable_id,
        'grading',
        'clear_peer_marks',
    ]);
    $.ajax({
        url,
        data: {
            csrf_token,
            peer_id,
            submitter_id,
        },
        type: 'POST',
        success: function () {
            console.log('Successfully deleted peer marks');
            window.location.reload();
        },
        error: function () {
            console.log('Failed to delete');
        },
    });
};

window.newEditPeerComponentsForm = function () {
    $('.popup-form').css('display', 'none');
    const form = $('#edit-peer-components-form');
    form.css('display', 'block');
    captureTabInModal('edit-peer-components-form');
};

function rotateImage(url: string | undefined, rotateBy: string) {
    let rotate: number | string | null = sessionStorage.getItem(`image-rotate-${url}`);
    if (rotate) {
        rotate = parseInt(rotate);
        if (isNaN(rotate)) {
            rotate = 0;
        }
    }
    else {
        rotate = 0;
    }
    if (rotateBy === 'cw') {
        rotate = (rotate + 90) % 360;
    }
    else if (rotateBy === 'ccw') {
        rotate = (rotate - 90) % 360;
    }
    $(`iframe[src="${url}"]`).each(function () {
        const img = $(this).contents().find('img');
        if (img && $(this).data('rotate') !== rotate) {
            $(this).data('rotate', rotate);
            if ($(this).data('observingImageResize') === undefined) {
                $(this).data('observingImageResize', false);
            }
            resizeImageIFrame(img, $(this));
            if ($(this).data('observingImageResize') === false) {
                const iFrameTarget = $(this);
                const observer = new ResizeObserver(() => {
                    resizeImageIFrame(img, iFrameTarget);
                });
                observer.observe($(this)[0]);
                $(this).data('observingImageResize', true);
            }
        }
    });
    sessionStorage.setItem(`image-rotate-${url}`, rotate.toString());
}
window.rotateImage = rotateImage;

function resizeImageIFrame(imageTarget: JQuery<HTMLImageElement>, iFrameTarget: JQuery<HTMLElement>) {
    if (imageTarget.parent().is(':visible')) {
        const rotateAngle = iFrameTarget.data('rotate') as number;
        if (rotateAngle === 0) {
            imageTarget.css('transform', '');
        }
        else {
            imageTarget.css('transform', `rotate(${rotateAngle}deg)`);
        }
        imageTarget.css(
            'transform',
            `translateY(${-imageTarget.get(0)!.getBoundingClientRect().top}px) rotate(${rotateAngle}deg)`,
        );
        const iFrameBody = iFrameTarget.contents().find('body').first();
        const boundsHeight = iFrameBody[0].scrollHeight;
        let height = 500;
        if (
            iFrameTarget.css('max-height').length !== 0
            && parseInt(iFrameTarget.css('max-height')) >= 0
        ) {
            height = parseInt(iFrameTarget.css('max-height'));
        }
        if (boundsHeight > height) {
            iFrameBody.css('overflow-y', '');
            iFrameTarget.height(height);
        }
        else {
            iFrameBody.css('overflow-y', 'hidden');
            iFrameTarget.height(boundsHeight);
        }
    }
}

window.imageRotateIcons = function (iframe: string) {
    const iframeTarget = $(`iframe#${iframe}`);
    const contentType = (iframeTarget.contents().get(0) as Document).contentType;

    // eslint-disable-next-line eqeqeq
    if (contentType != undefined && contentType.startsWith('image')) {
        if (iframeTarget.attr('id')!.endsWith('_full_panel_iframe')) {
            const imageRotateBar = iframeTarget
                .parent()
                .parent()
                .parent()
                .find('.image-rotate-bar')
                .first();
            imageRotateBar.show();
            imageRotateBar
                .find('.image-rotate-icon-ccw')
                .first()
                .attr(
                    'onclick',
                    `rotateImage('${iframeTarget.attr('src')}', 'ccw')`,
                );
            imageRotateBar
                .find('.image-rotate-icon-cw')
                .first()
                .attr(
                    'onclick',
                    `rotateImage('${iframeTarget.attr('src')}', 'cw')`,
                );
            if (
                sessionStorage.getItem(
                    `image-rotate-${iframeTarget.attr('src')}`,
                )
            ) {
                rotateImage(iframeTarget.attr('src'), 'none');
            }
        }
        else if (iframeTarget.parent().data('image-rotate-icons') !== true) {
            iframeTarget.parent().data('image-rotate-icons', true);
            iframeTarget.before(`<div class="image-rotate-bar">
                              <a class="image-rotate-icon-ccw" onclick="rotateImage('${iframeTarget.attr('src')}', 'ccw')">
                              <i class="fas fa-undo" title="Rotate image counterclockwise"></i></a>
                              <a class="image-rotate-icon-cw" onclick="rotateImage('${iframeTarget.attr('src')}', 'cw')">
                              <i class="fas fa-redo" title="Rotate image clockwise"></i></a>
                              </div>`);

            if (
                sessionStorage.getItem(
                    `image-rotate-${iframeTarget.attr('src')}`,
                )
            ) {
                rotateImage(iframeTarget.attr('src'), 'none');
            }
        }
    }
};

function openFrame(
    html_file: string,
    url_file: string,
    num: string,
    pdf_full_panel = true,
    panel: string = 'submission',
) {
    const iframe = $(`#file_viewer_${num}`);
    const display_file_url = buildCourseUrl(['display_file']);
    if (!iframe.hasClass('open') || iframe.hasClass('full_panel')) {
        const iframeId = `file_viewer_${num}_iframe`;
        let directory = '';
        if (url_file.includes('user_assignment_settings.json')) {
            directory = 'submission_versions';
        }
        else if (url_file.includes('submissions')) {
            directory = 'submissions';
        }
        else if (url_file.includes('results_public')) {
            directory = 'results_public';
        }
        else if (url_file.includes('results')) {
            directory = 'results';
        }
        else if (url_file.includes('checkout')) {
            directory = 'checkout';
        }
        else if (url_file.includes('attachments')) {
            directory = 'attachments';
        }
        // handle pdf
        if (
            pdf_full_panel
            && url_file.substring(url_file.length - 3) === 'pdf'
        ) {
            viewFileFullPanel(html_file, url_file, 0, panel as FileFullPanelOptions)?.then(() => {
                loadPDFToolbar();
            });
        }
        else {
            const forceFull
                = url_file.substring(url_file.length - 3) === 'pdf' ? 500 : -1;
            const targetHeight = iframe.hasClass('full_panel') ? 1200 : 500;
            const frameHtml = `
        <iframe id="${iframeId}" onload="resizeFrame('${iframeId}', ${targetHeight}, ${forceFull}); imageRotateIcons('${iframeId}');"
                src="${display_file_url}?dir=${encodeURIComponent(directory)}&file=${encodeURIComponent(html_file)}&path=${encodeURIComponent(url_file)}&ta_grading=true&gradeable_id=${$('#submission_browser').data('gradeable-id')}"
                width="95%">
        </iframe>
      `;
            iframe.html(frameHtml);
            iframe.addClass('open');
        }
    }
    if (
        !iframe.hasClass('full_panel')
        && (!pdf_full_panel || url_file.substring(url_file.length - 3) !== 'pdf')
    ) {
        if (!iframe.hasClass('shown')) {
            iframe.show();
            iframe.addClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0])
                .removeClass('fa-plus-circle')
                .addClass('fa-minus-circle');
        }
        else {
            iframe.hide();
            iframe.removeClass('shown');
            $($($(iframe.parent().children()[0]).children()[0]).children()[0])
                .removeClass('fa-minus-circle')
                .addClass('fa-plus-circle');
        }
    }
    return false;
}
window.openFrame = openFrame;

type FileFullPanelOptions = keyof typeof fileFullPanelOptions;
const fileFullPanelOptions = {
    submission: {
        // Main viewer (submission panel)
        viewer: '#viewer',
        fileView: '#file-view',
        gradingFileName: '#grading_file_name',
        panel: '#submission_browser',
        innerPanel: '#directory_view',
        pdfAnnotationBar: '#pdf_annotation_bar',
        saveStatus: '#save_status',
        fileContent: '#file-content',
        fullPanel: 'full_panel',
        imageRotateBar: '#image-rotate-icons-bar',
        pdf: true,
    },
    notebook: {
        // Notebook panel
        viewer: '#notebook-viewer',
        fileView: '#notebook-file-view',
        gradingFileName: '#notebook_grading_file_name',
        panel: '#notebook_view',
        innerPanel: '#notebook-main-view',
        pdfAnnotationBar: '#notebook_pdf_annotation_bar', // TODO
        saveStatus: '#notebook_save_status', // TODO
        fileContent: '#notebook-file-content',
        fullPanel: 'notebook_full_panel',
        imageRotateBar: '#notebook-image-rotate-icons-bar',
        pdf: false,
    },
};

export function viewFileFullPanel(name: string, path: string, page_num = 0, panelStr: string = 'submission') {
    const panel = panelStr as FileFullPanelOptions;
    if ($(fileFullPanelOptions[panel]['viewer']).length !== 0) {
        $(fileFullPanelOptions[panel]['viewer']).remove();
    }

    $(fileFullPanelOptions[panel]['imageRotateBar']).hide();

    const promise = loadPDF(name, path, page_num, panel);
    $(fileFullPanelOptions[panel]['fileView']).show();
    $(fileFullPanelOptions[panel]['gradingFileName']).text(name);
    const precision
        = $(fileFullPanelOptions[panel]['panel']).width()!
            - $(fileFullPanelOptions[panel]['innerPanel']).width()!;
    const offset = $(fileFullPanelOptions[panel]['panel']).width()! - precision;
    $(fileFullPanelOptions[panel]['innerPanel']).animate(
        { left: `+=${-offset}px` },
        200,
    );
    $(fileFullPanelOptions[panel]['innerPanel']).hide();
    $(fileFullPanelOptions[panel]['fileView'])
        .animate({ left: `+=${-offset}px` }, 200)
        .promise();
    return promise;
}
window.viewFileFullPanel = viewFileFullPanel;

function loadPDF(name: string, path: string, page_num: number, panelStr: string = 'submission') {
    const panel = panelStr as FileFullPanelOptions;
    // Store the file name of the last opened file for scrolling when switching between students
    localStorage.setItem('ta-grading-files-full-view-last-opened', name);
    const extension = name.split('.').pop();
    if (fileFullPanelOptions[panel]['pdf'] && extension === 'pdf') {
        const gradeable_id = document.getElementById(
            fileFullPanelOptions[panel]['panel'].substring(1),
        )!.dataset.gradeableId;
        const anon_submitter_id = document.getElementById(
            fileFullPanelOptions[panel]['panel'].substring(1),
        )!.dataset.anonSubmitterId;
        $('#pdf_annotation_bar').show();
        $('#save_status').show();
        return $.ajax({
            type: 'POST',
            url: buildCourseUrl(['gradeable', gradeable_id, 'grading', 'pdf']),
            data: {
                user_id: anon_submitter_id,
                filename: name,
                file_path: path,
                page_num: page_num,
                is_anon: true,
                csrf_token: window.csrfToken,
            },
            success: function (data: string) {
                $('#file-content').append(data);
            },
        });
    }
    else {
        $(fileFullPanelOptions[panel]['pdfAnnotationBar']).hide();
        $(fileFullPanelOptions[panel]['saveStatus']).hide();
        $(fileFullPanelOptions[panel]['fileContent']).append(
            `<div id="file_viewer_${fileFullPanelOptions[panel]['fullPanel']}" class="full_panel" data-file_name="" data-file_url=""></div>`,
        );
        $(`#file_viewer_${fileFullPanelOptions[panel]['fullPanel']}`).empty();
        $(`#file_viewer_${fileFullPanelOptions[panel]['fullPanel']}`).attr(
            'data-file_name',
            '',
        );
        $(`#file_viewer_${fileFullPanelOptions[panel]['fullPanel']}`).attr(
            'data-file_url',
            '',
        );
        $(`#file_viewer_${fileFullPanelOptions[panel]['fullPanel']}`).attr(
            'data-file_name',
            name,
        );
        $(`#file_viewer_${fileFullPanelOptions[panel]['fullPanel']}`).attr(
            'data-file_url',
            path,
        );
        openFrame(name, path, fileFullPanelOptions[panel]['fullPanel'], false);
        $(
            `#file_viewer_${fileFullPanelOptions[panel]['fullPanel']}_iframe`,
        ).css('max-height', '1200px');
        // $("#file_viewer_" + fileFullPanelOptions[panel]["fullPanel"] + "_iframe").height("100%");
    }
}
window.loadPDF = loadPDF;

window.collapseFile = function (rawPanel: string = 'submission') {
    const panel: FileFullPanelOptions = rawPanel as FileFullPanelOptions;
    // Removing these two to reset the full panel viewer.
    $(`#file_viewer_${fileFullPanelOptions[panel]['fullPanel']}`).remove();
    if (fileFullPanelOptions[panel]['pdf']) {
        $('#content-wrapper').remove();
        if ($('#pdf_annotation_bar').is(':visible')) {
            $('#pdf_annotation_bar').hide();
        }
    }
    $(fileFullPanelOptions[panel]['innerPanel']).show();
    const offset1 = $(fileFullPanelOptions[panel]['innerPanel']).css('left');
    const offset2 = $(fileFullPanelOptions[panel]['innerPanel']).width();
    $(fileFullPanelOptions[panel]['innerPanel']).animate(
        { left: `-=${offset1}` },
        200,
    );
    $(fileFullPanelOptions[panel]['fileView']).animate(
        { left: `+=${offset2}px` },
        200,
        () => {
            $(fileFullPanelOptions[panel]['fileView']).css('left', '');
            $(fileFullPanelOptions[panel]['fileView']).hide();
        },
    );
};

window.Twig.twig({
    id: 'Attachments',
    href: '/templates/grading/Attachments.twig',
    async: true,
});

let uploadedAttachmentIndex = 1;

window.uploadAttachment = function () {
    const fileInput: JQuery<HTMLInputElement> = $('#attachment-upload');
    if (fileInput[0].files!.length === 1) {
        const formData = new FormData();
        formData.append('attachment', fileInput[0].files![0]);
        formData.append('anon_id', getAnonId());
        formData.append('csrf_token', window.csrfToken);
        let callAjax = true;
        const userAttachmentList = $('#attachments-list').children().first();
        const origAttachment = userAttachmentList.find(
            `[data-file_name='${CSS.escape(fileInput[0].files![0].name)}']`,
        );
        if (origAttachment.length !== 0) {
            callAjax = confirm(
                `The file '${fileInput[0].files![0].name}' already exists; do you want to overwrite it?`,
            );
        }
        if (callAjax) {
            fileInput.prop('disabled', true);
            $.ajax({
                url: buildCourseUrl([
                    'gradeable',
                    getGradeableId(),
                    'grading',
                    'attachments',
                    'upload',
                ]),
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function (data: Record<string, string>) {
                    if (!('status' in data) || data['status'] !== 'success') {
                        alert(
                            `An error has occurred trying to upload the attachment: ${data['message']}`,
                        );
                    }
                    else {
                        const renderedData = window.Twig.twig({
                            // @ts-expect-error @types/twig is not compatible with the current version of twig
                            ref: 'Attachments',
                        }).render({
                            file: data['data'],
                            id: `a-up-${uploadedAttachmentIndex}`,
                            is_grader_view: true,
                            can_modify: true,
                        });
                        uploadedAttachmentIndex++;
                        if (origAttachment.length === 0) {
                            userAttachmentList.append(renderedData);
                        }
                        else {
                            origAttachment
                                .first()
                                .parent()
                                .replaceWith(renderedData);
                        }
                        if (userAttachmentList.children().length === 0) {
                            userAttachmentList.css('display', 'none');
                            $('#attachments-header').css('display', 'none');
                        }
                        else {
                            userAttachmentList.css('display', '');
                            $('#attachments-header').css('display', '');
                        }
                    }
                    fileInput[0].value = '';
                    fileInput.prop('disabled', false);
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    alert(
                        `An error has occurred trying to upload the attachment: ${errorThrown}`,
                    );
                    fileInput[0].value = '';
                    fileInput.prop('disabled', false);
                },
            });
        }
        else {
            fileInput[0].value = '';
        }
    }
};

window.deleteAttachment = function (target: string, file_name: string) {
    const confirmation = confirm(
        `Are you sure you want to delete attachment '${decodeURIComponent(file_name)}'?`,
    );
    if (confirmation) {
        const formData = new FormData();
        formData.append('attachment', file_name);
        formData.append('anon_id', getAnonId());
        formData.append('csrf_token', window.csrfToken);
        $.ajax({
            url: buildCourseUrl([
                'gradeable',
                getGradeableId(),
                'grading',
                'attachments',
                'delete',
            ]),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            dataType: 'json',
            success: function (data: Record<string, string>) {
                if (!('status' in data) || data['status'] !== 'success') {
                    alert(
                        `An error has occurred trying to delete the attachment: ${data['message']}`,
                    );
                }
                else {
                    $(target).parent().parent().remove();
                    const userAttachmentList = $('#attachments-list')
                        .children()
                        .first();
                    if (userAttachmentList.children().length === 0) {
                        userAttachmentList.css('display', 'none');
                        $('#attachments-header').css('display', 'none');
                    }
                    else {
                        userAttachmentList.css('display', '');
                        $('#attachments-header').css('display', '');
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(
                    `An error has occurred trying to upload the attachment: ${errorThrown}`,
                );
            },
        });
    }
};
