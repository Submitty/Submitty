class MultipleChoiceWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'multiple_choice',
            choices: [],
            filename: 'default.txt'
        };
    }

    render() {
        // Setup container
        const container = this.getContainer('Multiple Choice');
        container.classList.add('multiple-choice-widget');

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(this.getMultipleChoice());
        interactive_area.appendChild(this.getConfig());

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        // MC Options
        const values = this.dom_pointer.querySelectorAll('.entered-value-input');
        const descriptions = this.dom_pointer.querySelectorAll('.entered-description-input');

        this.state.choices = [];
        for (let i = 0; i < values.length; i++) {
            this.state.choices.push({value: values[i].value, description: descriptions[i].value});
        }

        // Others
        const allow_multiple_toggle = this.dom_pointer.querySelector('.allow-multiple-toggle');
        allow_multiple_toggle.checked ? this.state.allow_multiple = true : delete this.state.allow_multiple;

        const randomize_order_toggle = this.dom_pointer.querySelector('.randomize-order-toggle');
        randomize_order_toggle.checked ? this.state.randomize_order = true : delete this.state.randomize_order;

        const filename = this.dom_pointer.querySelector('.filename-input');
        this.state.filename = filename.value;
    }

    getJSON() {
        this.commitState();

        if (this.state.choices.length) {
            return this.state;
        }
    }

    load(data) {
        this.state = data;
    }

    /**
     * Get and setup the area of the multiple choice widget which allows the user to add a new multiple choice
     * option.
     *
     * @returns {HTMLDivElement}
     */
    getMultipleChoice() {
        const table = document.createElement('div');
        table.classList.add('mc-table');
        table.innerHTML = this.getMultipleChoiceTemplate();

        const insertOption = (value, description) => {
            table.insertBefore(this.getMultipleChoiceOption(value, description), table.querySelector('.mc-inputs'));
        };

        if (this.state.choices.length === 0) {
            insertOption('', '');
        }
        else {
            this.state.choices.forEach(option_json => {
                insertOption(option_json.value, option_json.description);
            });
        }

        // Add newly entered data to form when add button is clicked
        const add_button = table.querySelector('.add-button');
        add_button.addEventListener('click', () => {
            insertOption('', '');
        });

        return table;
    }

    /**
     * Get the html template used with this.getMultipleChoice().
     *
     * @returns {string}
     */
    getMultipleChoiceTemplate() {
        return `
        <div class="mc-row">
            <div class="mc-header mc-col">
                Value
            </div>
            <div class="mc-header mc-col-center">
                Description
            </div>
            <div class="mc-header mc-col">
                Controls
            </div>
        </div>
        <div class="mc-inputs mc-row">
            <div class="mc-col"></div>
            <div class="mc-col-center"></div>
            <div class="mc-col mc-buttons">
                <input type="button" class="add-button" value="Add Option">
            </div>
        </div>`;
    }

    /**
     * Get a div which contains populated inputs for a single multiple choice option, along with their up/down/remove
     * controls.
     *
     * @param {string} value The value which should be populated in the value box.
     * @param {string} description The description which should be populated in the description box.
     * @returns {HTMLDivElement}
     */
    getMultipleChoiceOption(value, description) {
        const mc_option = document.createElement('div');
        mc_option.classList.add('mc-entered-option');
        mc_option.classList.add('mc-row');
        mc_option.innerHTML = this.getMultipleChoiceOptionTemplate(value, description);

        // Handle when an up, down, or remove mc option button is clicked
        mc_option.addEventListener('click', (event) => {
            const trigger = event.target;
            const current = event.currentTarget;

            if (trigger.classList.contains('up-button')) {
                const arr = Array.from(current.parentNode.children);
                const index = arr.indexOf(current);
                if (index > 1) {
                    current.parentNode.insertBefore(current, current.previousSibling);
                }
            }
            else if (trigger.classList.contains('down-button')) {
                const arr = Array.from(current.parentNode.children);
                const index = arr.indexOf(current);
                if (index < arr.length - 2) {
                    current.parentNode.insertBefore(current, current.nextSibling.nextSibling);
                }
            }
            else if (trigger.classList.contains('remove-button')) {
                current.remove();
            }
        });

        return mc_option;
    }

    /**
     * Get the html template used with this.getMultipleChoiceOption().
     *
     * @param {string} value The value which should be populated in the value box.
     * @param {string} description The description which should be populated in the description box.
     * @returns {string}
     */
    getMultipleChoiceOptionTemplate(value, description) {
        return `
        <div class="mc-col">
            <input type="text" class="entered-value-input" value="${value}">    
        </div>
        <div class="mc-col-center">
            <textarea class="entered-description-input">${description}</textarea>
        </div>
        <div class="mc-col mc-buttons">
            <input type="button" class="up-button" value="Up">
            <input type="button" class="down-button" value="Down">
            <input type="button" class="remove-button" value="Remove">
        </div>`;
    }

    /**
     * Get the configuration area of the multiple choice widget.  These are the inputs related to
     * allow_multiple, randomize_order, and filename.
     *
     * @returns {HTMLDivElement}
     */
    getConfig() {
        const allow_multiple_toggle = document.createElement('input');
        allow_multiple_toggle.classList.add('allow-multiple-toggle');
        allow_multiple_toggle.setAttribute('type', 'checkbox');
        allow_multiple_toggle.checked = this.state.allow_multiple;

        const randomize_order_toggle = document.createElement('input');
        randomize_order_toggle.classList.add('randomize-order-toggle');
        randomize_order_toggle.setAttribute('type', 'checkbox');
        randomize_order_toggle.checked = this.state.randomize_order;

        const filename = document.createElement('input');
        filename.classList.add('filename-input');
        filename.setAttribute('type', 'text');
        filename.value = this.state.filename;

        const allow_multiple_label = document.createElement('label');
        allow_multiple_label.innerText = 'Select multiple: ';
        allow_multiple_label.appendChild(allow_multiple_toggle);

        const randomize_order_label = document.createElement('label');
        randomize_order_label.innerText = 'Randomize order: ';
        randomize_order_label.appendChild(randomize_order_toggle);

        const filename_label = document.createElement('label');
        filename_label.innerText = 'Filename: ';
        filename_label.appendChild(filename);

        const fieldset = document.createElement('fieldset');
        fieldset.appendChild(allow_multiple_label);
        fieldset.appendChild(randomize_order_label);
        fieldset.appendChild(document.createElement('hr'));
        fieldset.appendChild(filename_label);

        const container = document.createElement('div');
        container.classList.add('multiple-choice-config');
        container.appendChild(fieldset);

        return container;
    }
}
