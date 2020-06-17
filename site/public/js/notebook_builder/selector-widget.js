class SelectorWidget extends Widget {
    constructor() {
        super();
        this.options = ['Markdown', 'Multiple Choice', 'Short Answer'];
        this.drop_down = document.createElement('select');

        // Setup drop down
        this.options.forEach((option_string) => {
            const option_node = document.createElement('option');
            option_node.innerText = option_string;
            this.drop_down.appendChild(option_node);
        })
    }

    render() {
        // Setup containers
        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('selector-widget');
        const heading_container = this.getHeadingContainer('Selector', 'Select a new item to add:');

        // Setup button
        const add_button = this.getButton('Add');

        add_button.addEventListener('click', () => {
            const selected = this.options[this.drop_down.selectedIndex];

            switch (selected) {
                case 'Markdown':
                    notebook_builder.addWidget(new MarkdownWidget());
                    break;
                case 'Multiple Choice':
                    break;
                case 'Short Answer':
                    break;
                default:
                    console.error('Not a valid option for selector add button.');
            }
        });

        // Setup the interactive container
        const interactive_container = this.getInteractiveContainer();
        interactive_container.appendChild(this.drop_down);
        interactive_container.appendChild(add_button);

        // Place dropdown and button in container
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        return container;
    }
}