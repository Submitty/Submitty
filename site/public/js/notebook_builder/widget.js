class Widget {
    /**
     * Gets a widget container div
     *
     * @returns {HTMLDivElement}
     */
    getContainer(heading_text, instruction_text = null) {
        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');

        const widget_main = document.createElement('div');
        widget_main.classList.add('widget-main');
        widget_main.appendChild(this.getHeadingContainer(heading_text, instruction_text))
        widget_main.appendChild(this.getInteractiveContainer());

        const widget_controls = this.getControls();

        container.appendChild(widget_main);
        container.appendChild(widget_controls);

        return container;
    }

    /**
     * Gets a div which contains a heading and instructional text about how to use a widget
     *
     * @param heading_text The heading
     * @param instruction_text Optional text indicating how to use the widget's interactive features
     * @returns {HTMLDivElement}
     */
    getHeadingContainer(heading_text, instruction_text = null) {
        const container = document.createElement('div');
        container.classList.add('heading-container');

        const heading = document.createElement('h3');
        heading.innerText = heading_text;

        container.appendChild(heading);

        if (instruction_text) {
            const instructions = document.createElement('p');
            instructions.innerHTML = instruction_text;
            container.appendChild(instructions);
        }

        return container;
    }

    /**
     * Gets a div which will contain the interactive elements of the widget
     *
     * @returns {HTMLDivElement}
     */
    getInteractiveContainer() {
        const container = document.createElement('div');
        container.classList.add('interactive-container');

        return container;
    }

    /**
     * Get a button node
     *
     * @param value The text shown inside the button
     * @returns {HTMLInputElement}
     */
    getButton(value) {
        const button = document.createElement('input');
        button.setAttribute('type', 'button');
        button.setAttribute('value', value);

        return button;
    }

    /**
     * Gets a div which contains all the buttons needed to handle the reorder or removal of a widget
     *
     * @returns {HTMLDivElement}
     */
    getControls() {
        const container = document.createElement('div');
        container.classList.add('widget-controls');

        // Setup move up button
        const up_button = this.getButton('Up');
        up_button.addEventListener('click', () => {
            notebook_builder.widgetUp(this);
        });
        container.appendChild(up_button);

        // Setup down button
        const down_button = this.getButton('Down');
        down_button.addEventListener('click', () => {
            notebook_builder.widgetDown(this);
        });
        container.appendChild(down_button);

        // Setup remove button
        const remove_button = this.getButton('Remove');
        remove_button.addEventListener('click', () => {
            notebook_builder.widgetRemove(this);
        })
        container.appendChild(remove_button);

        return container;
    }
}