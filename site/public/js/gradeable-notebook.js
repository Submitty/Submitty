const NOTEBOOK_DEFER_KEY = 'notebook-autosave';
const NOTEBOOK_AUTOSAVE_KEY = `${window.location.pathname}-notebook-autosave`;

/**
 * Saves the current state of the notebook gradeable to localstorage.
 */
function saveNotebookToLocal() {
    if (autosaveEnabled) {
        localStorage.setItem(NOTEBOOK_AUTOSAVE_KEY, JSON.stringify({
            timestamp: Date.now(),
            multiple_choice: gatherInputAnswersByType("multiple_choice"),
            short_answer: gatherInputAnswersByType("short_answer"),
        }));
    }
}

/**
 * Restores the state of the notebook gradeable from localstorage. If no
 * autosave data exists yet, then this function does nothing.
 */
function restoreNotebookFromLocal() {
    if (autosaveEnabled) {
        const state = JSON.parse(localStorage.getItem(NOTEBOOK_AUTOSAVE_KEY));
        
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

/**
 * Checks all radio buttons or checkboxes are given in the checked array
 *
 * @param mc_field_id The id of the multiple choice fieldset
 */
function setMultipleChoices(mc_field_id, checked) {
    checked = checked.split("\n");

    // For each input inside the fieldset see if its value is inside the prev checked array
    $("#" + mc_field_id + " :input").each(function(index,element) {

        var value = element.getAttribute("value");

        if(checked.includes(value))
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
function clearMultipleChoices(mc_field_id) {
    // For each input inside the fieldset remove the 'checked' attribute
    $("#" + mc_field_id + " :input").each(function(index,element) {

        $(element).prop("checked", false);

    });
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
        localStorage.removeItem(NOTEBOOK_AUTOSAVE_KEY);
        // Changes have been submitted; we don't need to warn the user anymore
        window.onbeforeunload = null;
    });
});

document.addEventListener('DOMContentLoaded', () => {
    window.codemirrors = {};

    document.querySelectorAll('.short-answer').forEach(sa => {
        const config = {};
        let height;
        let width;

        if (sa.dataset.rows > 0) {
            config.lineNumbers = true;
            config.mode = sa.dataset.language;

            height = rowsToPixels(sa.dataset.rows);
        }
        else {
            config.scrollbarStyle = null;

            height = 30;
            width = 150;
        }

        config.value = sa.dataset.initial;

        codemirrors[sa.dataset.index] = CodeMirror(sa, config);
        codemirrors[sa.dataset.index].setSize(width, height);
    });

    document.querySelector('.notebook').onclick = event => {
        if (event.target.classList.contains('notebook-btn')) {
            const button = event.target;
            const cell_type = button.dataset.cell_type;
            const index = button.dataset.index;
            const operation = button.dataset.operation;

            if (cell_type === 'multiple_choice') {
                mcButtonClickAction(index, operation);
            }
            else if (cell_type === 'short_answer') {
                const sa = document.querySelector(`#short-answer-${index}`);
                codemirrors[index].setValue(sa.dataset[operation]);
            }
            else {
                console.error('Invalid notebook cell type');
            }

            saveNotebookToLocal();
        }
    }
});

function mcButtonClickAction(index, operation) {
    const mc_field_id = `mc_field_${index}`;
    const fieldset = document.querySelector(`#mc_field_${index}`);

    if (operation === 'initial') {
        clearMultipleChoices(mc_field_id);
    }
    else if (operation === 'recent') {
        setMultipleChoices(mc_field_id, fieldset.dataset.prev_checked);
    }
    else {
        console.error('Invalid button click operation');
    }
}