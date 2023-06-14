/* global IS_INSTRUCTOR, toggleCMFolders, toggleCMFolder */

// eslint-disable-next-line no-unused-vars
function setFolderCookie(folderPath, id, open) {
    const folderData = JSON.parse(Cookies.get('cm_folder_data') || '{}');
    folderData[folderPath] = { id, open };
    Cookies.set('cm_folder_data', JSON.stringify(folderData));
}

// eslint-disable-next-line no-unused-vars
function toggleFoldersOpen() {
    Cookies.remove('cm_folder_data');
    Cookies.set('foldersOpen', toggleCMFolders());
}

function handleHideMaterialsCheckboxChange(clicked) {
    const warningIds = {
        'hide-materials-checkbox': 'upload-form',
        'hide-materials-checkbox-edit': 'edit-form',
        'hide-folder-materials-checkbox-edit': 'edit-folder-form',
    };
    const callerId = clicked.target ? clicked.target.id : clicked; //can be an event or id itself
    const caller = $(`#${callerId}`);
    const warningMessage = $(`#${callerId} ~ #${warningIds[callerId]}-hide-warning`);
    if (caller.hasClass('partial-checkbox')) {
        caller.removeClass('partial-checkbox');
        caller.siblings()[0].remove();
        if (caller.attr('class') === '') {
            caller.removeAttr('class');
        }
        caller.prop('checked', true);
    }
    if (caller.is(':checked') && warningMessage.length === 0) {
        const callerParent = caller.parent().get(0);
        callerParent.insertAdjacentHTML(
            'beforeend',
            `<span id="${warningIds[callerId]}-hide-warning" class="red-message full-width">\nWarning:
            Students can view the hidden course material by guessing the corresponding
            course material ID (a simple number). It is recommended to instead use the release date feature
            if it is necessary to prevent students from accessing course materials.\n</span>`,
        );
    }
    else if (caller.is(':not(:checked)')) {
        warningMessage.remove();
    }
}

window.onload = function () {
    //determine if folders have been left open or closed
    const foldersOpen = Boolean(JSON.parse(Cookies.get('foldersOpen') || 'false'));
    const folderData = JSON.parse(Cookies.get('cm_folder_data') || '{}');
    //open/close folders on screen as directed
    toggleCMFolders(foldersOpen);
    for (const data of Object.values(folderData)) {
        if (!data.open === foldersOpen) {
            toggleCMFolder(data.id, !foldersOpen);
        }
    }
    // loop thru each div_viewer_xxx
    const jumpToScrollPosition = Cookies.get('jumpToScrollPosition') || '';
    const topFolderMatcher = new RegExp('^div_viewer_sd[0-9]{0,}$');
    const partiallyHidden = 2;

    // accepts an element (editButton) having required data attributes; other parameters represent the properties known to be common until now
    // returns an updated set of common props by comparing with the props of the given element
    function folderCommonProps(editButton, commonSections, releaseTime, hiddenState, first, hiddenStateMismatch, releaseTimeMismatch) {
        let residualSections = [];
        if (first) {
            commonSections = editButton.data('sections');
            releaseTime = editButton.data('release-time');
            hiddenState = editButton.data('hidden-state');
            first = false;
        }
        else {
            const combinedSections = [...(new Set([...commonSections, ...editButton.data('sections')]))];
            commonSections = commonSections.filter(x => {
                return editButton.data('sections').includes(x);
            });
            residualSections = combinedSections.filter(x => {
                return !commonSections.includes(x);
            });
            if (!releaseTimeMismatch) {
                if (editButton.data('release-time') !== releaseTime) {
                    releaseTimeMismatch = true;
                    releaseTime = '';
                }
            }
            if (!hiddenStateMismatch) {
                if (editButton.data('hidden-state') !== hiddenState) {
                    hiddenStateMismatch = true;
                    hiddenState = '';
                }
            }
        }
        return [commonSections, residualSections, releaseTime, hiddenState, first, hiddenStateMismatch, releaseTimeMismatch];
    }

    // sets the common properties of a given folder
    function folderSetter(elem) {
        const insideFileMatcher = new RegExp(`^${elem.attr('id').replace('div', 'file')}f[0-9]{0,}$`);
        const insideFolderMatcher = new RegExp(`^${elem.attr('id')}d[0-9]{0,}$`);
        let first = true;
        let commonSections = [];
        let residualSections = [];
        const partialSections = [];
        let releaseTime = '';
        let hiddenState = '';
        let hiddenStateMismatch = false;
        let releaseTimeMismatch = false;
        $(`.folder-container > [id^=${elem.attr('id')}d]`, elem).each(function() {
            if (insideFolderMatcher.test($(this).attr('id'))) {
                folderSetter($(this));
                const fEditButton = $(this).siblings('.div-viewer').children('a[onclick^=newEditCourseMaterialsFolderForm]');
                partialSections.push(...fEditButton.data('partial-sections'));
                [commonSections, residualSections, releaseTime, hiddenState, first, hiddenStateMismatch, releaseTimeMismatch] = folderCommonProps(fEditButton, commonSections, releaseTime, hiddenState, first, hiddenStateMismatch, releaseTimeMismatch);
                partialSections.push(...residualSections);
            }
        });
        $('.file-container > .file-viewer > a[onclick^=newEditCourseMaterialsForm]', elem).each(function() {
            if (insideFileMatcher.test($(this).parent('.file-viewer').siblings('.file-viewer-data').attr('id'))) {
                [commonSections, residualSections, releaseTime, hiddenState, first, hiddenStateMismatch, releaseTimeMismatch] = folderCommonProps($(this), commonSections, releaseTime, hiddenState, first, hiddenStateMismatch, releaseTimeMismatch);
                partialSections.push(...residualSections);
            }
        });
        const editButton = $(elem.siblings('.div-viewer').children('a[onclick^=newEditCourseMaterialsFolderForm]'));
        editButton.attr('data-sections', JSON.stringify(commonSections));
        editButton.attr('data-partial-sections', JSON.stringify(partialSections));
        editButton.attr('data-release-time', releaseTime);
        if (hiddenStateMismatch) {
            hiddenState = partiallyHidden;
        }
        editButton.attr('data-hidden-state', hiddenState);
    }

    function callFolderSetter(elem) {
        if (topFolderMatcher.test(elem.id)) {
            folderSetter($(elem));
        }
    }

    // set folder data for instructor
    if (IS_INSTRUCTOR) {
        $('[id^=div_viewer_]').each(function() {
            callFolderSetter(this);
        });
    }

    if (jumpToScrollPosition.length > 0 && jumpToScrollPosition !== '-1' && jumpToScrollPosition !== -1) {
        const cm_ids = (Cookies.get('cm_data') || '').split('|').filter(n => n.length);
        for (const cm_id of cm_ids) {
            toggleCMFolder(cm_id);
        }
        // jump to last location if scroll is enabled.
        window.scrollTo(0, jumpToScrollPosition);
        Cookies.set('jumpToScrollPosition', -1);
    }

    // clean up cm data cookie
    Cookies.remove('cm_data');

    if (IS_INSTRUCTOR) {
        $('#hide-materials-checkbox').on('change', handleHideMaterialsCheckboxChange);
        $('#hide-materials-checkbox-edit').on('change', handleHideMaterialsCheckboxChange);
        $('[id^="section-folder-edit"]').on('change', function() {
            if ($(this).hasClass('partial-checkbox')) {
                $(this).removeClass('partial-checkbox');
                if ($(this).attr('class') === '') {
                    $(this).removeAttr('class');
                }
                $(this).prop('checked', true);
            }
        });
    }
};
