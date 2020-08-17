class SelectorWidget extends Widget {
    constructor() {
        super();

        this.options = ['Multiple Choice', 'Markdown', 'Short Answer', 'Image'];
    }

    render() {
        const interactive_container = this.getInteractiveContainer();
        this.options.forEach(option => {
            const button = this.getButton(option);
            interactive_container.appendChild(button);
        });

        interactive_container.addEventListener('click', event => {
            switch (event.target.value) {
                case 'Multiple Choice':
                    notebook_builder.widgetAdd(new MultipleChoiceWidget());
                    break;
                case 'Markdown':
                    notebook_builder.widgetAdd(new MarkdownWidget());
                    break;
                case 'Short Answer':
                    notebook_builder.widgetAdd(new ShortAnswerWidget());
                    break;
                case 'Image':
                    notebook_builder.widgetAdd(new ImageWidget());
                    break;
                default:
                    break;
            }
        });

        const heading_container = this.getHeadingContainer('Add New Notebook Cell');

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('selector-widget');
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        return container;
    }
}
