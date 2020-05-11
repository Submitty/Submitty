/**
 * Checks all radio buttons or checkboxes that were previously checked in the recent submission
 *
 * @param mc_field_id The id of the multiple choice fieldset
 */
function setMultipleChoices(mc_field_id)
{
    var prev_checked = $("#" + mc_field_id).attr("data-prev_checked");

    prev_checked = prev_checked.split("\n");

    // For each input inside the fieldset see if its value is inside the prev checked array
    $("#" + mc_field_id + " :input").each(function(index,element) {

        var value = element.getAttribute("value");

        if(prev_checked.includes(value))
        {
            $(element).prop("checked", true);
        }
        else
        {
            $(element).prop("checked", false);
        }
    });
}

/**
 * Sets all checks or radio buttons in a multiple choice question to unchecked
 *
 * @param mc_field_id The id of the multiple choice fieldset
 */
function clearMultipleChoices(mc_field_id)
{
    // For each input inside the fieldset remove the 'checked' attribute
    $("#" + mc_field_id + " :input").each(function(index,element) {

        $(element).prop("checked", false);

    });
}

/**
 * Sets the contents of the codebox
 *
 * @param codebox_id The id of the codebox div
 * @param state May be either clear (set to initial_value) or recent (set to recent submission)
 */
function setCodeBox(codebox_id, state)
{
    // Get initial and previous submission values
    var initial_value = $("#" + codebox_id).attr("data-initial_value");
    var recent_submission = $("#" + codebox_id).attr("data-recent_submission");

    // Get the codebox
    var codebox = $("#" + codebox_id + " .CodeMirror").get(0).CodeMirror;

    if(state == "clear")
    {
        codebox.setValue(initial_value);
    }
    else
    {
        codebox.setValue(recent_submission);
    }
}

const AUTOSAVE_KEY = `${window.location.pathname}-notebook-autosave`;

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
    } catch (e) {
        return false;
    }
})();

function msToDays(ms) {
    return ms / (1000 * 60 * 60 * 24);
}

/**
 * Scans all autosaved data and deletes any entries older than thirty days.
 */
function cleanupAutosaveHistory() {
    if (autosaveEnabled) {
        const toDelete = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (!key.endsWith("-notebook-autosave")) {
                continue;
            }

            try {
                const { timestamp } = JSON.parse(localStorage.getItem(key));
                
                if (msToDays(Date.now() - timestamp) > 30) {
                    toDelete.push(key);
                }
            } catch (e) {
                // This item has gotten corrupted somehow; let's delete it 
                // instead of letting it linger around and taking up space.
                toDelete.push(key);
            }
        }
        toDelete.forEach(localStorage.removeItem, localStorage);
    }
}

/**
 * Saves the current state of the notebook gradeable to localstorage.
 */
function saveToLocal() {
    if (autosaveEnabled) {
        localStorage.setItem(AUTOSAVE_KEY, JSON.stringify({
            timestamp: Date.now(),
            multiple_choice: gatherInputAnswersByType("multiple_choice"),
            short_answer: gatherInputAnswersByType("short_answer"),
            codebox: gatherInputAnswersByType("codebox")
        }));
    }
}

/**
 * Restores the state of the notebook gradeable from localstorage. If no
 * autosave data exists yet, then this function does nothing.
 */
function restoreFromLocal() {
    if (autosaveEnabled) {
        const state = JSON.parse(localStorage.getItem(AUTOSAVE_KEY));
        
        if (state === null) {
            return;
        }

        // First, we restore multiple choice answers
        for (const id in state.multiple_choice) {
            const values = state.multiple_choice[id];
            const index = /multiple_choice_([0-9])+/.exec(id)[1];
            $(`#mc_field_${index} :input`).each((_index, element) => {
                $(element).prop('checked', values.includes(element.value)).change();
            });
        }
        // Next, we restore short-answer boxes
        for (const id in state.short_answer) {
            const answer = state.short_answer[id][0];
            // Restore the answer and trigger change events (for setting button states)
            // (see https://stackoverflow.com/questions/4672505/why-does-the-jquery-change-event-not-trigger-when-i-set-the-value-of-a-select-us)
            $(`#${id}`).val(answer).trigger('input');
        }
        // Finally, we restore codeboxes
        for (const id in state.codebox) {
            const answer = state.codebox[id][0];
            const codebox = $(`#${id} .CodeMirror`).get(0);
            // If this box no longer exists, then don't attempt to update the
            // answer. The autosave data is probably for an older version of
            // the gradeable at this point, see issue #5351.
            if (!codebox) {
                continue;
            }
            const cm = codebox.CodeMirror;
            // This automatically triggers the event handler for the clear and
            // recent buttons.
            cm.setValue(answer);
        }
    }
}

let isSaveScheduled = false;
/**
 * Schedules a save action for ten seconds from now if no save has been 
 * scheduled yet. This way we don't re-write minor changes when modifying a
 * text box answer.
 */
function deferredSave() {
    if (autosaveEnabled && !isSaveScheduled) {
        setTimeout(() => {
            saveToLocal();
            isSaveScheduled = false;
        }, 10 * 1000);
        isSaveScheduled = true;
    }
}

/**
 * Event handler for the `onbeforeunload` event; saves unsaved changes to 
 * localStorage (if enabled) and prompts the "Are you sure you want to exit?" 
 * dialog box.
 * @param {Event} e Event object for this event
 */
function saveAndWarnUnsubmitted(e) {
    saveToLocal();
    // For Firefox
    e.preventDefault();
    // For Chrome
    e.returnValue = '';
    return true;
}

$(document).ready(function () {

    // If any button inside the notebook has been clicked then enable the submission button
    $(".notebook button").click(function() {

        // Set global javascript variable to allow submission for notebook
        window.is_notebook = true;

        // Enable submit button
        $("#submit").attr("disabled", false);

    });

    $("#submit").click(() => {
        localStorage.removeItem(AUTOSAVE_KEY);
        // Changes have been submitted; we don't need to warn the user anymore
        window.onbeforeunload = null;
    });

    // Register click handler for codebox clear and recent buttons
    $(".codebox-clear-reset").click(function() {

        // Collect the id of the button and split it apart to find out which field it is bound to
        var items = this.id.split("_");
        var index = items[1];
        var action = items[2];
        var button_selector = "#codebox_" + index +  "_";

        setCodeBox("codebox_" + index, action);

        if(action == "clear")
        {
            $(button_selector + "clear_button").attr("disabled", true);
            $(button_selector + "recent_button").attr("disabled", false);
        }
        else
        {
            $(button_selector + "clear_button").attr("disabled", false);
            $(button_selector + "recent_button").attr("disabled", true);
        }

        saveToLocal();
    });

    // Register handler to detect changes inside codeboxes and then enable buttons
    $(".CodeMirror").each((_index, cm) => cm.CodeMirror.on("changes", codebox => {
        // Select the <div> that wraps the actual codebox element and contains
        // the data-initial_value and data-recent_submission attributes.
        const codeboxWrapper = codebox.getWrapperElement().parentElement;

        var initial_value = codeboxWrapper.getAttribute("data-initial_value");
        var recent_submission = codeboxWrapper.getAttribute("data-recent_submission");

        var code = codebox.getValue();
        var clear_button_id = `#${codeboxWrapper.id}_clear_button`;
        var recent_button_id = `#${codeboxWrapper.id}_recent_button`;

        if(code === initial_value)
        {
            $(clear_button_id).attr("disabled", true);
        }
        else
        {
            $(clear_button_id).attr("disabled", false);
        }

        if(code === recent_submission)
        {
            $(recent_button_id).attr("disabled", true);
        }
        else
        {
            $(recent_button_id).attr("disabled", false);
            $("#submit").attr("disabled", false);
            window.onbeforeunload = saveAndWarnUnsubmitted;
        }
    }));

    $(".CodeMirror").each((_index, cm) => cm.CodeMirror.on("changes", deferredSave));

    // Register click handler for multiple choice buttons
    $(".mc-clear, .mc-recent").click(function() {

        // Collect the id of the button and split it apart to find out which field it is bound to
        var items = this.id.split("_");
        var index = items[1];
        var action = items[2];
        var field_set_id = "mc_field_" + index;

        if(action == "clear")
        {
            clearMultipleChoices(field_set_id);
            $("#mc_" + index + "_clear_button").attr("disabled", true);
            $("#mc_" + index + "_recent_button").attr("disabled", false);
        }
        else if(action == "recent")
        {
            setMultipleChoices(field_set_id);
            $("#mc_" + index + "_clear_button").attr("disabled", false);
            $("#mc_" + index + "_recent_button").attr("disabled", true);
        }

        saveToLocal();
    });

    // Register change handler to enable buttons when multiple choice inputs change
    $(".mc").change(function() {

        var items = this.id.split("_");
        var index = items[2];

        // Enable recent button
        $("#mc_" + index + "_clear_button").attr("disabled", false);
        const prev_checked_items = this.getAttribute("data-prev_checked");
        const curr_checked_items = $(this).serializeArray().map(v => v.value).join("\n");
        if (curr_checked_items !== prev_checked_items) {
            window.onbeforeunload = saveAndWarnUnsubmitted;
            $("#submit").attr("disabled", false);
            $("#mc_" + index + "_recent_button").attr("disabled", false);
        } else {
            $("#mc_" + index + "_recent_button").attr("disabled", true);
        }

        saveToLocal();
    });

    // Setup click events for short answer buttons
    $(".sa-clear-reset").click(function() {

        // Collect the id of the button and split it apart to find out which short answer it is bound to
        // and which action it preforms
        var items = this.id.split("_");

        var index_num = items[2];
        var button_action = items[3];
        var field_id = "#short_answer_" + index_num;

        var data_to_set = "";

        // Collect data from the data-* attribute of the text box
        if(button_action == "clear")
        {
            var data_to_set = $(field_id).attr("data-initial_value");
            $(field_id + "_clear_button").attr("disabled", true);
            $(field_id + "_recent_button").attr("disabled", false);
        }
        else
        {
            var data_to_set = $(field_id).attr("data-recent_submission");
            $(field_id + "_clear_button").attr("disabled", false);
            $(field_id + "_recent_button").attr("disabled", true);
        }

        // Set the data into the textbox
        $(field_id).val(data_to_set);

        saveToLocal();
    });

    // Setup keyup event for short answer boxes
    $(".sa-box").on("input", function() {

        var index_num = this.id.split("_")[2];

        var initial_value = this.getAttribute("data-initial_value");
        var recent_submission = this.getAttribute("data-recent_submission");

        var text_box_id = "#short_answer_" + index_num;
        var clear_button_id = "#short_answer_" + index_num + "_clear_button";
        var recent_button_id = "#short_answer_" + index_num + "_recent_button";

        if($(text_box_id).val() == initial_value)
        {
            $(clear_button_id).attr("disabled", true);
        }
        else
        {
            $(clear_button_id).attr("disabled", false);
        }

        if($(text_box_id).val() == recent_submission)
        {
            $(recent_button_id).attr("disabled", true);
        }
        else
        {
            $(recent_button_id).attr("disabled", false);
            window.onbeforeunload = saveAndWarnUnsubmitted;
            $("#submit").attr("disabled", false);
        }
    });

    $(".sa-box").on('input', deferredSave);

    restoreFromLocal();

    cleanupAutosaveHistory();
});
