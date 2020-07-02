class SelectorWidget extends Widget {
    constructor() {
        super();

        this.options = ['Multiple Choice', 'Markdown', 'Short Answer'];
        this.drop_down = document.createElement('select');

        // Setup drop down
        this.options.forEach((option_string) => {
            const option_node = document.createElement('option');
            option_node.innerText = option_string;
            this.drop_down.appendChild(option_node);
        })
    }

    render() {
        // Setup button
        const add_button = this.getButton('Add');
        add_button.addEventListener('click', () => {
            const selected = this.options[this.drop_down.selectedIndex];

            switch (selected) {
                case 'Multiple Choice':
                    notebook_builder.widgetAdd(new MultipleChoiceWidget());
                    break;
                case 'Markdown':
                    notebook_builder.widgetAdd(new MarkdownWidget());
                    break;
                case 'Short Answer':
                    notebook_builder.widgetAdd(new ShortAnswerWidget());
                    break;
                default:
                    console.error('Not a valid option for selector.');
            }
        });

        const interactive_container = this.getInteractiveContainer();
        interactive_container.appendChild(this.drop_down);
        interactive_container.appendChild(add_button);

        const heading_container = this.getHeadingContainer('Add New Notebook Cell');

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('selector-widget');
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        return container;
    }
}
