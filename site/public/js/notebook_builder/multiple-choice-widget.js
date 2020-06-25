class MultipleChoiceWidget extends Widget {
    constructor() {
        super();

        this.allow_multiple_toggle = document.createElement('input');
        this.allow_multiple_toggle.setAttribute('type', 'checkbox');

        this.randomize_order_toggle = document.createElement('input');
        this.randomize_order_toggle.setAttribute('type', 'checkbox');

        this.filename = document.createElement('input');
        this.filename.setAttribute('type', 'text');
    }

    render() {
        // Setup container
        const container = this.getContainer('Multiple Choice');
        container.classList.add('multiple-choice-widget');

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(this.getMultipleChoice());
        interactive_area.appendChild(this.getConfig());

        return container;
    }

    getJSON() {
        const choices = this.getChoicesArrayJSON();

        // If there were no choices created then return nothing
        if (choices.length === 0) {
            return;
        }

        const result = {};

        result.type = 'multiple_choice';
        result.choices = choices;

        if (this.randomize_order_toggle.checked) {
            result.randomize_order = true;
        }

        if (this.allow_multiple_toggle.checked) {
            result.allow_multiple = true;
        }

        if (this.filename.value) {
            result.filename = this.filename.value;
        }

        return result;
    }

    getChoicesArrayJSON() {

    }

    getMultipleChoice() {
        const table = document.createElement('div');
        table.setAttribute('id', 'mc-table');
        table.innerHTML = this.getMultipleChoiceTemplate();

        // Add newly entered data to form when add button is clicked
        const add_button = table.querySelector('#add_button');
        add_button.addEventListener('click', () => {
            const value = table.querySelector('#value-input');
            const description = table.querySelector('#description-input');

            table.insertBefore(this.getMultipleChoiceOption(value.value, description.value), table.querySelector('.mc-inputs'));

            value.value = '';
            description.value = '';
        });

        return table;
    }

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
            <div class="mc-col">
                <input type="text" id="value-input" placeholder="Add new value">    
            </div>
            <div class="mc-col-center">
                <textarea id="description-input" placeholder="Add new description"></textarea>
            </div>
            <div class="mc-col mc-buttons">
                <input type="button" id="add_button" value="Add">
            </div>
        </div>`;
    }

    getMultipleChoiceOption(value, description) {
        const mc_option = document.createElement('div');
        mc_option.classList.add('mc-entered-option');
        mc_option.classList.add('mc-row');
        mc_option.innerHTML = this.getMultipleChoiceOptionTemplate(value, description);

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

    getConfig() {
        const allow_multiple_label = document.createElement('label');
        allow_multiple_label.innerText = 'Select multiple: ';
        allow_multiple_label.appendChild(this.allow_multiple_toggle);

        const randomize_order_label = document.createElement('label');
        randomize_order_label.innerText = 'Randomize order: ';
        randomize_order_label.appendChild(this.randomize_order_toggle);

        const filename_label = document.createElement('label');
        filename_label.innerText = 'Filename: ';
        filename_label.appendChild(this.filename);

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
