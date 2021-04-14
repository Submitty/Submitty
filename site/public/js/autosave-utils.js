/* global saveNotebookToLocal */
/* exported cancelAllDeferredSaves, cleanupAutosaveHistory, deferredSave, saveAndWarnUnsubmitted */
/**
 * This value will be true if autosave is enabled for this session.
 *
 * Specifically, we try to set a test value in localStorage and immediately
 * remove the test value. If all goes well, then localStorage is available and
 * we can auto-save to there. Otherwise, we simply disable auto-saving.
 *
 * In particular, setting an item can fail in one of two scenarios:
 * * The local storage quota is full
 * * The `localStorage` variable simply doesn't exist and therefore the browser doesn't support it.
 *
 * The first point isn't too relevant as we don't expect to be storing a ton of
 * data; however, Safari on iOS in Private Mode sets the quota to zero, meaning
 * that every attempt to write to localStorage will throw an exception.
 * Throwing an exception every keypress isn't great, hence the primary
 * motivation for this block here.
 *
 * @see {@link https://developer.mozilla.org/en-US/docs/Web/API/Web_Storage_API/Using_the_Web_Storage_API#Feature-detecting_localStorage|MDN} on detecting localStorage.
 * @see {@link https://developer.mozilla.org/en-US/docs/Web/API/Storage/setItem|Storage.setItem()} documentation.
 */
const autosaveEnabled = (() => {
    try {
        localStorage.setItem('TEST', 'TEST');
        localStorage.removeItem('TEST');
        return true;
    }
    catch (e) {
        return false;
    }
})();

function msToDays(ms) {
    return ms / (1000 * 60 * 60 * 24);
}

/**
 * Scans localStorage data and deletes any autosave entries older than thirty days.
 *
 * Data is classified as autosave if its key ends in the provided suffix. Each
 * autosave entry is expected to be a JSON object with a "timestamp" key. If
 * an autosave entry could not be parsed, or if it does not have a "timestamp"
 * key, then it is assumed to be corrupted and will therefore be deleted.
 *
 * @param {String} suffix Suffix of autosave entries to delete.
 */
function cleanupAutosaveHistory(suffix) {
    if (autosaveEnabled) {
        const toDelete = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (!key.endsWith(suffix)) {
                continue;
            }

            try {
                const { timestamp } = JSON.parse(localStorage.getItem(key));

                if (msToDays(Date.now() - timestamp) > 30) {
                    toDelete.push(key);
                }
            }
            catch (e) {
                // This item has gotten corrupted somehow; let's delete it
                // instead of letting it linger around and taking up space.
                toDelete.push(key);
            }
        }
        toDelete.forEach(localStorage.removeItem, localStorage);
    }
}

/**
 * Event handler for the `onbeforeunload` event; saves unsaved changes to
 * localStorage (if enabled) and prompts the "Are you sure you want to exit?"
 * dialog box.
 * @param {Event} e Event object for this event
 */
function saveAndWarnUnsubmitted(e) {
    saveNotebookToLocal();
    // For Firefox
    e.preventDefault();
    // For Chrome
    e.returnValue = '';
    return true;
}

const scheduledSaves = {};
/**
 * Schedules a save action for some time from now if no save has been
 * scheduled yet. This way we don't re-write minor changes when modifying a
 * text box answer.
 *
 * The key parameter allows for multiple save actions to be queued up in
 * parallel, e.g. for multiple forum reply boxes to be able to queue their own
 * save actions independent of one another. This also allows canceling a
 * deferred save.
 *
 * @param {String} key A key to keep track of a single deferred save action.
 * @param {() => void} saveCallback Callback that triggers the save action.
 * @param {Number} timeout The number of seconds to delay. Default 10.
 */
function deferredSave(key, saveCallback, timeout) {
    if (!timeout) {
        timeout = 10;
    }
    if (autosaveEnabled && !scheduledSaves[key]) {
        scheduledSaves[key] = setTimeout(() => {
            saveCallback();
            scheduledSaves[key] = false;
        }, timeout * 1000);
    }
}

/**
 * Cancels a previously-deferred save action.
 *
 * @param {String} key A key to keep track of a single deferred save action.
 */
function cancelDeferredSave(key) {
    if (autosaveEnabled && scheduledSaves[key]) {
        clearTimeout(scheduledSaves[key]);
    }
    scheduledSaves[key] = false;
}

/**
 * Cancels all deferred save actions.
 */
function cancelAllDeferredSaves() {
    for (const key in scheduledSaves) {
        cancelDeferredSave(key);
    }
}
