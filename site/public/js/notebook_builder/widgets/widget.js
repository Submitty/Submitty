class Widget {
    /**
     * Get the html representation of the widget.
     */
    render() { throw 'Implement this method in the child class.'; }

    /**
     * Parse through any interactive elements inside the widget and save them into the widget's state object.
     */
    commitState() { throw 'Implement this method in the child class.'; }

    /**
     * Get the JSON representation of the widget.  This is usually just the state object.
     */
    getJSON() { throw 'Implement this method in the child class.'; }

    /**
     * Gets a widget container div.
     *
     * @param {string} heading_text Text that will be used as a heading for the div.
     * @returns {HTMLDivElement}
     */
    getContainer(heading_text) {
        const widget_main = document.createElement('div');
        widget_main.classList.add('widget-main');
        widget_main.appendChild(this.getHeadingContainer(heading_text))
        widget_main.appendChild(this.getInteractiveContainer());

        const widget_controls = this.getControls();

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.appendChild(widget_main);
        container.appendChild(widget_controls);

        return container;
    }

    /**
     * Gets a div which contains a heading text.
     *
     * @param {string} heading_text The heading
     * @returns {HTMLDivElement}
     */
    getHeadingContainer(heading_text) {
        const heading = document.createElement('h3');
        heading.innerText = heading_text;

        const container = document.createElement('div');
        container.classList.add('heading-container');
        container.appendChild(heading);

        return container;
    }

    /**
     * Gets a div which will contain the interactive elements of the widget.
     *
     * @returns {HTMLDivElement}
     */
    getInteractiveContainer() {
        const container = document.createElement('div');
        container.classList.add('interactive-container');

        return container;
    }

    /**
     * Get a button element.
     *
     * @param {string} value The text shown inside the button
     * @returns {HTMLInputElement}
     */
    getButton(value) {
        const button = document.createElement('input');
        button.setAttribute('type', 'button');
        button.setAttribute('value', value);

        return button;
    }

    /**
     * Gets a div which contains all the buttons needed to handle the reorder or removal of a widget.
     *
     * @returns {HTMLDivElement}
     */
    getControls() {
        const container = document.createElement('div');
        container.classList.add('widget-controls');

        ['Up', 'Down', 'Remove'].forEach(label => {
            const btn = this.getButton(label);
            btn.widget = this;
            container.appendChild(btn);
        });

        return container;
    }
}
