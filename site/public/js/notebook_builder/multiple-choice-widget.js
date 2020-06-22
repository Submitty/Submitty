class MultipleChoiceWidget extends Widget {
    constructor() {
        super();

        this.choices_table = document.createElement('table');
        this.choices_table.innerHTML = this.getMultipleChoiceTableTemplate();

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
        const results = [];
        const values = this.choices_table.getElementsByClassName('value');
        const descriptions = this.choices_table.getElementsByClassName('description');

        let i;
        for (i = 0; i < values.length; i++) {
            results.push({
                value: values[i].innerText,
                description: descriptions[i].innerText
            });
        }

        return results;
    }

    getMultipleChoice() {
        const add_button = this.getButton('Add');
        add_button.addEventListener('click', () => {
            // Collect value and description from input boxes
            const value = this.choices_table.getElementsByClassName('value-input')[0];
            const description = this.choices_table.getElementsByClassName('description-input')[0];

            if (value.value === '' || description.value === '') {
                alert('You must enter both a value and description.');
                return;
            }

            // Append to the table body
            const table_body = this.choices_table.getElementsByTagName('tbody')[0];
            const new_row = table_body.insertRow();

            const cell0 = new_row.insertCell(0);
            cell0.classList.add('value');
            cell0.innerText = value.value;

            const cell1 = new_row.insertCell(1);
            cell1.classList.add('description');
            cell1.innerText = description.value;

            // Clear input boxes
            value.value = '';
            description.value = '';
        });

        const container = document.createElement('div');
        container.classList.add('multiple-choice-options')
        container.appendChild(this.choices_table);
        container.appendChild(add_button);

        return container;
    }

    getMultipleChoiceTableTemplate() {
        return `
        <thead>
            <tr>
                <th>Value</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
            <tr>
                <td><input type="text" class="value-input"></td>
                <td><input type="text" class="description-input"></td>
            </tr>
        </tfoot>`;
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
