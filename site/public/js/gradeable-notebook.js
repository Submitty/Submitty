/* global USER_ID, autosaveEnabled, cleanupAutosaveHistory, deferredSave, saveAndWarnUnsubmitted */

/**
 * Checks all radio buttons or checkboxes that were previously checked in the recent submission
 *
 * @param mc_field_id The id of the multiple choice fieldset
 */
function setMultipleChoices(mc_field_id, viewing_inactive_version) {
    let prev_checked = $(`#${mc_field_id}`).attr('data-prev_checked');
    prev_checked = prev_checked.split('\n');
    // For each input inside the fieldset see if its value is inside the prev checked array
    $(`#${mc_field_id} :input`).each((index,element) => {

        const value = element.getAttribute('value');

        if (prev_checked.includes(value)) {
            $(element).prop('checked', true);
        }
        else {
            if (viewing_inactive_version) {
                $(element).prop('disabled', true);
            }
            $(element).prop('checked', false);
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
    $(`#${mc_field_id} :input`).each((index,element) => {

        $(element).prop('checked', false);

    });
}

/**
 * Sets the contents of the codebox
 *
 * @param codebox_id The id of the codebox div
 * @param state May be either clear (set to initial_value) or recent (set to recent submission)
 */
function setCodeBox(codebox_id, state) {
    // Get initial and previous submission values
    const initial_value = $(`#${codebox_id}`).attr('data-initial_value');
    const version_submission =  $(`#${codebox_id}`).attr('data-version_submission');
    // Get the codebox
    const codebox = $(`#${codebox_id} .CodeMirror`).get(0).CodeMirror;

    if (state === 'clear') {
        codebox.setValue(initial_value);
    }
    else {
        codebox.setValue(version_submission);
    }
}

const NOTEBOOK_DEFER_KEY = 'notebook-autosave';

const NOTEBOOK_AUTOSAVE_KEY_SUFFIX = `${window.location.pathname}-notebook-autosave`;

/**
 * Get the autosave key for the notebook.
 *
 * This is a function because USER_ID is defined *after* this script is
 * loaded -- thus, simply defining the constant w/ USER_ID results in an
 * error since USER_ID is not yet defined.
 */
function notebookAutosaveKey() {
    return `${USER_ID}-${NOTEBOOK_AUTOSAVE_KEY_SUFFIX}`;
}

/**
 * Saves the current state of the notebook gradeable to localstorage.
 */
function saveNotebookToLocal() {
    const mc_inputs=[];
    const codebox_inputs = [];

    //loop through multiple choice questions and save answers
    $('.multiple_choice').each(function() {
        let file_name='';
        $(this).children('fieldset').each(function() {
            file_name = $(this).data('filename');
            if (file_name) {
                const answers = [];
                //grab selected answers
                $(this).find('input').each(function() {
                    if ($(this)[0].checked) {
                        answers.push($(this)[0].defaultValue);
                    }
                });
                mc_inputs.push({file_name, answers});
            }
        });
    });

    //save short answers
    $('.short_answer').each(function() {
        $(this).find('div[name^="codebox_"]').each(function() {
            const file_name = ($(this).data('filename') || '').trim();
            if (file_name) {
                //grab input
                const editor = ($(this)[0]).querySelector('.CodeMirror').CodeMirror;
                if (editor) {
                    const value = editor.getValue();
                    codebox_inputs.push({file_name, value});
                }
            }
        });
    });

    localStorage.setItem(notebookAutosaveKey(), JSON.stringify({
        timestamp: Date.now(),
        multiple_choice: mc_inputs,
        codebox: codebox_inputs,
    }));
}

/**
 * Restores the state of the notebook gradeable from localstorage. If no
 * autosave data exists yet, then this function does nothing.
 */
function restoreNotebookFromLocal() {
    if (typeof autosaveEnabled !== 'undefined' && autosaveEnabled) {
        const inputs = JSON.parse(localStorage.getItem(notebookAutosaveKey()));
        if (inputs === null) {
            return;
        }
        //to prevent data loss when these changes get installed on production
        const not_found = [];

        //restore multiple choice
        for (const id in inputs.multiple_choice) {
            const {file_name, answers} = inputs.multiple_choice[id];
            let found = false;
            $(`fieldset.mc[data-filename="${file_name}"] input`).each(function() {
                found = true;
                //check off proper inputs
                for (let i = 0; i < answers.length; i++) {
                    if ($(this)[0].defaultValue === answers[i]) {
                        $(this).prop('checked', true);
                        $(this).trigger('change');
                    }
                }
            });
            if (!found) {
                not_found.push(inputs.multiple_choice[id]);
            }
        }

        //restore short answers
        for (const id in inputs.codebox) {
            const {file_name, value} = inputs.codebox[id];
            const question = $(`.short_answer > div[data-filename="${file_name}"]`);
            //fill in proper values if question is found
            if (question.length > 0) {
                const editor = question[0].querySelector('.CodeMirror').CodeMirror;
                editor.setValue(value);
            }
            else {
                not_found.push(inputs.codebox[id][0]);
            }
        }

        //if there are answers that could not be placed anywhere
        if (not_found.length > 0) {
            const old_answers_div = document.createElement('div');
            old_answers_div.id = 'old-answers';
            old_answers_div.classList.add('box');
            old_answers_div.style.backgroundColor = 'var(--alert-background-danger-red)';

            //create header text to warn user
            const old_answers_header = document.createElement('h4');
            old_answers_header.style.color = 'var(--focus-danger-red)';
            old_answers_header.innerHTML = 'Answer(s) could not be restored. You will have to copy and paste them in the proper place.';
            //add header to container
            old_answers_div.appendChild(old_answers_header);

            // for ... of loop loops through all elements in array, while for ... in loop loops through
            // all indexes/keys in arrays/objects
            const old_answers_list = document.createElement('ol');
            old_answers_list.style.marginLeft = '3em';
            for (const i in not_found) {
                const answer_text = document.createElement('li');
                answer_text.innerHTML = not_found[i];
                old_answers_list.appendChild(answer_text);
            }
            old_answers_div.appendChild(old_answers_list);
            $(old_answers_div).insertAfter('#gradeable-info');
        }
    }
}

$(document).ready(() => {

    // If any button inside the notebook has been clicked then enable the submission button
    $('.notebook button').click(() => {

        // Set global javascript variable to allow submission for notebook
        window.is_notebook = true;
    });

    $('#submit').click(() => {
        localStorage.removeItem(notebookAutosaveKey());
        // Changes have been submitted; we don't need to warn the user anymore
        window.onbeforeunload = null;
    });

    // Register click handler for codebox clear and recent buttons
    $('.codebox-clear-reset').click(function() {

        // Collect the id of the button and split it apart to find out which field it is bound to
        const items = this.id.split('_');
        const index = items[1];
        const action = items[2];
        const button_selector = `#codebox_${index}_`;

        setCodeBox(`codebox_${index}`, action);

        if (action === 'clear') {
            $(`${button_selector}clear_button`).attr('disabled', true);
            $(`${button_selector}recent_button`).attr('disabled', false);
        }
        else {
            $(`${button_selector}clear_button`).attr('disabled', false);
            $(`${button_selector}recent_button`).attr('disabled', true);
        }

        saveNotebookToLocal();
    });

    // Register handler to detect changes inside codeboxes and then enable buttons
    $('.CodeMirror').each((_index, cm) => cm.CodeMirror.on('changes', codebox => {
        // Select the <div> that wraps the actual codebox element and contains
        // the data-initial_value and data-recent_submission attributes.
        const codeboxWrapper = codebox.getWrapperElement().parentElement;

        const initial_value = codeboxWrapper.getAttribute('data-initial_value');
        const recent_submission = codeboxWrapper.getAttribute('data-recent_submission');

        const code = codebox.getValue();
        const clear_button_id = `#${codeboxWrapper.id}_clear_button`;
        const recent_button_id = `#${codeboxWrapper.id}_recent_button`;

        if (code === initial_value) {
            $(clear_button_id).attr('disabled', true);
        }
        else {
            $(clear_button_id).attr('disabled', !!$(clear_button_id).attr('data-older_version'));
        }

        if (code === recent_submission) {
            $(recent_button_id).attr('disabled', true);
        }
        else {
            $(recent_button_id).attr('disabled', !!$(clear_button_id).attr('data-older_version'));
            window.onbeforeunload = saveAndWarnUnsubmitted;
        }
    }));

    $('.CodeMirror').each((_index, cm) => cm.CodeMirror.on('changes', () => deferredSave(NOTEBOOK_DEFER_KEY, saveNotebookToLocal)));

    // Register click handler for multiple choice buttons
    $('.mc-clear, .mc-recent').click(function() {

        // Collect the id of the button and split it apart to find out which field it is bound to
        const items = this.id.split('_');
        const index = items[1];
        const action = items[2];
        const field_set_id = `mc_field_${index}`;

        if (action === 'clear') {
            clearMultipleChoices(field_set_id);
            $(`#mc_${index}_clear_button`).attr('disabled', true);
            $(`#mc_${index}_recent_button`).attr('disabled', false);
        }
        else if (action === 'recent') {
            setMultipleChoices(field_set_id);
            $(`#mc_${index}_clear_button`).attr('disabled', true);
            $(`#mc_${index}_recent_button`).attr('disabled', true);
        }

        saveNotebookToLocal();
    });

    // Register change handler to enable buttons when multiple choice inputs change
    $('.mc').change(function() {

        const items = this.id.split('_');
        const index = items[2];

        // Enable recent button
        $(`#mc_${index}_clear_button`).attr('disabled', false);
        $(`#mc_${index}_recent_button`).attr('disabled', false);
        const prev_checked_items = this.getAttribute('data-prev_checked');
        const curr_checked_items = $(this).serializeArray().map(v => v.value).join('\n');
        if (curr_checked_items !== prev_checked_items) {
            window.onbeforeunload = saveAndWarnUnsubmitted;
            $(`#mc_${index}_recent_button`).attr('disabled', false);
        }
        else {
            $(`#mc_${index}_recent_button`).attr('disabled', true);
        }

        saveNotebookToLocal();
    });

    // Setup click events for short answer buttons
    $('.sa-clear-reset').click(function() {

        // Collect the id of the button and split it apart to find out which short answer it is bound to
        // and which action it preforms
        const items = this.id.split('_');

        const index_num = items[2];
        const button_action = items[3];
        const field_id = `#short_answer_${index_num}`;

        let data_to_set = '';

        // Collect data from the data-* attribute of the text box
        if (button_action === 'clear') {
            data_to_set = $(field_id).attr('data-initial_value');
            $(`${field_id}_clear_button`).attr('disabled', true);
            $(`${field_id}_recent_button`).attr('disabled', false);
        }
        else {
            data_to_set = $(field_id).attr('data-recent_submission');
            $(`${field_id}_clear_button`).attr('disabled', false);
            $(`${field_id}_recent_button`).attr('disabled', true);
        }

        // Set the data into the textbox
        $(field_id).val(data_to_set);

        saveNotebookToLocal();
    });

    // Setup keyup event for short answer boxes
    $('.sa-box').on('input', function() {

        const index_num = this.id.split('_')[2];

        const initial_value = this.getAttribute('data-initial_value');
        const recent_submission = this.getAttribute('data-recent_submission');

        const text_box_id = `#short_answer_${index_num}`;
        const clear_button_id = `#short_answer_${index_num}_clear_button`;
        const recent_button_id = `#short_answer_${index_num}_recent_button`;

        // eslint-disable-next-line eqeqeq
        if ($(text_box_id).val() == initial_value) {
            $(clear_button_id).attr('disabled', true);
        }
        else {
            $(clear_button_id).attr('disabled', false);
        }

        // eslint-disable-next-line eqeqeq
        if ($(text_box_id).val() == recent_submission) {
            $(recent_button_id).attr('disabled', true);
        }
        else {
            $(recent_button_id).attr('disabled', false);
            window.onbeforeunload = saveAndWarnUnsubmitted;
        }
    });

    $('.sa-box').on('input', () => deferredSave(NOTEBOOK_DEFER_KEY, saveNotebookToLocal));

    restoreNotebookFromLocal();

    if (typeof cleanupAutosaveHistory === 'function') {
        cleanupAutosaveHistory('-notebook-autosave');
    }
});
