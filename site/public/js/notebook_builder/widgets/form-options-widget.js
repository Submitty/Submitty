class FormOptionsWidget extends Widget {
    constructor() {
        super();

        // Input boxes which fail validation will have their background color changed to this color
        this.failed_validation_color = getComputedStyle(document.documentElement).getPropertyValue('--alert-invalid-entry-pink');
    }

    render() {
        const heading_container = this.getHeadingContainer('Form Options');

        const interactive_container = this.getInteractiveContainer();
        interactive_container.innerHTML = this.getFormOptionsTemplate();

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('form-options-widget');
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        const save_button = container.querySelector('.save-button');
        save_button.addEventListener('click', event => {
            this.saveButtonAction();
        })

        return container;
    }

    /**
     * Get the template used in the form options widget
     *
     * @returns {string}
     */
    getFormOptionsTemplate() {
        return `
        <div class="buttons">
            <input type="button" class="save-button" value="Save">
        </div>
        <div class="status"></div>`;
    }

    /**
     * Pack up all the configuration data from the GUI into a nice config.json and submit it to the server.
     * Additionally perform validation on inputs and prevent saving if validation errors were found.
     */
    saveButtonAction() {
        // Clear any previous validation errors
        this.clearStatusMessages();
        this.resetInputColors();

        // Only continue if validation passed
        if(!this.validate()) {
            return;
        }

        const config_json_file = new File([JSON.stringify(notebook_builder.getJSON(), null, 2)], 'config.json', {type: "text/plain"});
        const url = buildCourseUrl(['notebook_builder', 'save']);

        const form_data = new FormData();
        form_data.append('config_upload', config_json_file, 'config.json');
        form_data.append('csrf_token', csrfToken);
        form_data.append('g_id', builder_data.g_id);

        const makeRequest = async () => {
            this.appendStatusMessage('Saving...');

            // Currently only deals with uploading images, will rework later to accommodate other files/directories as needed
            // Wait for all files to be uploaded before continuing
            const file_selectors = document.querySelectorAll('input[type=file]');
            await uploadFiles(file_selectors, builder_data.g_id, 'test_input');

            const response = await fetch(url, {method: 'POST', body: form_data});
            const result = await response.json();

            this.clearStatusMessages();

            if (result.status === 'success') {
                const gradeable_submission_url = buildCourseUrl(['gradeable', builder_data.g_id]);
                const edit_gradeable_url = buildCourseUrl(['gradeable', builder_data.g_id, 'update']);

                this.appendStatusMessage(`Your gradeable is being installed.  To view it visit the <a href="${gradeable_submission_url}">submission page</a>.`);
                this.appendStatusMessage(`To make other changes to the gradeable configuration visit the <a href="${edit_gradeable_url}">edit gradeable page</a>.`);
            }
            else {
                this.appendStatusMessage(result.message);

                if (result.data && Array.isArray(result.data)) {
                    result.data.forEach(msg => this.appendStatusMessage(msg));
                }
            }
        };

        makeRequest().catch(err => console.error(err));
    }

    /**
     * Validate current form configuration/data
     *
     * @returns {boolean}
     */
    validate() {
        return this.validateFileNames() && this.validateItemNames();
    }

    /**
     * Append a message to the status div
     *
     * @param {string} message Text to be displayed
     */
    appendStatusMessage(message) {
        const status_div = document.querySelector('.form-options-widget .status');
        const p = document.createElement('p');
        p.innerHTML = message;
        status_div.appendChild(p);
    }

    /**
     * Remove all messages from the status div
     */
    clearStatusMessages() {
        const status_div = document.querySelector('.form-options-widget .status');
        status_div.innerHTML = '';
    }

    /**
     * Reset the all input boxes to have a white background
     */
    resetInputColors() {
        document.querySelectorAll('input').forEach(elem => {
            elem.style.backgroundColor = '';
        });
    }

    /**
     * Validates all itempool item_name inputs.  For any duplicated or blank inputs a failed validation message will be
     * displayed and that input will have it's background colored red.
     *
     * @returns {Boolean} True if all item_name inputs are valid, false otherwise.
     */
    validateItemNames() {
        const bad_item_names = getBadItemNames();
        bad_item_names.forEach(bad_item_name => {
            if (bad_item_name === '') {
                this.appendStatusMessage(`An itempool item name was found to be blank.  Ensure all item names are non-blank.`);
            }
            else {
                this.appendStatusMessage(`Itempool item name '${bad_item_name}' was found to be duplicated.  Ensure all item names are unique.`);
            }
        });

        this.colorFailedInputs(bad_item_names, '.item-name-input');

        return bad_item_names.length === 0;
    }

    /**
     * Validates to see if all filename boxes contain unique values.  If validation errors are found then a message
     * is added to the status div and the offending input boxes will have their background color changed to indicate
     * they are the ones that failed validation.
     *
     * @returns {Boolean} True if validation was successful, false otherwise
     */
    validateFileNames() {
        const filename_inputs = Array.from(document.querySelectorAll('.filename-input'));
        const filenames = filename_inputs.map(input => input.value);

        // Duplicated filename check
        const duplicated_filenames = this.getDuplicatedFileNames(filenames);
        duplicated_filenames.forEach(filename => {
            this.appendStatusMessage(`Filename: '${filename}' was found to be duplicated.  All filenames must be unique.`);
        });

        // Invalid substrings check
        const illegal_substrings = {
            '..': '..',
            '/': '/',
            ' ': 'spaces',
        };

        const illegal_filenames = this.getFileNamesWithIllegalSubstrings(filenames, Object.keys(illegal_substrings));
        illegal_filenames.forEach(filename => {
            this.appendStatusMessage(`Filename: '${filename}' was found to contain illegal substrings. Filenames may not contain ${Object.values(illegal_substrings).join(' or ')}.`);
        });

        this.colorFailedInputs(duplicated_filenames.concat(illegal_filenames), '.filename-input');

        return duplicated_filenames.length === 0 && illegal_filenames.length === 0;
    }

    /**
     * Color the selected input boxes red to indicate they failed validation.
     *
     * @param {String[]} failed_values The set of values that were found to have failed validation.
     * @param {String} selector_string A CSS selector string used to select the inputs.
     */
    colorFailedInputs(failed_values, selector_string) {
        document.querySelectorAll(selector_string).forEach(elem => {
            if (failed_values.includes(elem.value)) {
                elem.style.backgroundColor = this.failed_validation_color;
            }
        });
    }

    /**
     * Determine if any of the form's filenames contain illegal substrings.
     *
     * @param {String[]} filenames Array of all the filenames found in filename inputs
     * @param {String[]} illegal_substrings An array of substrings filenames must not contain.
     * @returns {String[]} An array of filenames that do contain illegal substrings.
     */
    getFileNamesWithIllegalSubstrings(filenames, illegal_substrings) {
        const illegal_filenames = new Set();

        filenames.forEach(filename => {
            illegal_substrings.forEach(substring => {
                if (filename.includes(substring)) {
                    illegal_filenames.add(filename);
                }
            });
        });

        return Array.from(illegal_filenames);
    }

    /**
     * Collects and returns the set of strings which were found to be duplicated in more than one filename input box.
     *
     * @param {String[]} filenames Array of all the filenames found in filename inputs
     * @returns {String[]}
     */
    getDuplicatedFileNames(filenames) {
        const used_filenames = new Set();
        const duplicated_filenames = new Set();

        filenames.forEach(filename => {
            !used_filenames.has(filename) ? used_filenames.add(filename) : duplicated_filenames.add(filename);
        });

        return Array.from(duplicated_filenames);
    }
}
