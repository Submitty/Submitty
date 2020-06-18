class MultipleChoiceWidget extends Widget {
    constructor() {
        super();
        this.allow_multiple = false;
        this.randomize_order = false;

        this.choices_table = document.createElement('table');
        this.choices_table.innerHTML = this.getMultipleChoiceTableTemplate();
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

    getMultipleChoice() {
        const container = document.createElement('div');
        container.classList.add('multiple-choice-options')

        const add_button = this.getButton('Add');
        add_button.addEventListener('click', () => {
            // Collect value and description from input boxes
            const inputs = this.choices_table.getElementsByTagName('input');
            const value = inputs[0].value;
            const description = inputs[1].value;

            // Clear input boxes
            inputs[0].value = '';
            inputs[1].value = '';

            // Append to the table body
            const table_body = this.choices_table.getElementsByTagName('tbody')[0];
            const new_row = table_body.insertRow();
            const cell0 = new_row.insertCell(0);
            const cell1 = new_row.insertCell(1);
            cell0.innerHTML = value;
            cell1.innerHTML = description;
        });


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
                <td><input type="text"></td>
                <td><input type="text"></td>
            </tr>
        </tfoot>`;
    }

    getConfig() {
        const container = document.createElement('div');
        container.classList.add('multiple-choice-config');

        return container;
    }
}