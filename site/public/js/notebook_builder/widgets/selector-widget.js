class SelectorWidget extends Widget {
    /**
     * Instantiate a selector widget.
     *
     * @param {String[]} options Array of options which will be turned into buttons.
     * @param {String} heading_text
     */
    constructor(options, heading_text) {
        super();

        this.dom_pointer;

        this.options = options;
        this.heading_text = heading_text;
    }

    render() {
        const interactive_container = this.getInteractiveContainer();
        this.options.forEach(option => {
            const button = this.getButton(option);
            interactive_container.appendChild(button);
        });

        const heading_container = this.getHeadingContainer(this.heading_text);

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('selector-widget');
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        this.dom_pointer = container;
        return container;
    }
}
