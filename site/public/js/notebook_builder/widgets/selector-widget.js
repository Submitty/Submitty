class SelectorWidget extends Widget {
    /**
     * A widget which facilitates the adding of other widgets to the form
     *
     * @param {RootBuilder|ItempoolBuilder} instantiator
     * @param {String} heading_text Heading text to show in the selector widget
     */
    constructor(instantiator, heading_text) {
        super();
        
        this.instantiator = instantiator;

        this.heading_text = heading_text;

        this.options = ['Multiple Choice', 'Markdown', 'Short Answer', 'Image'];

        if (instantiator.constructor.name === 'RootBuilder') {
            this.options.push('Itempool');
        }
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
                    this.instantiator.widgetAdd(new MultipleChoiceWidget());
                    break;
                case 'Markdown':
                    this.instantiator.widgetAdd(new MarkdownWidget());
                    break;
                case 'Short Answer':
                    this.instantiator.widgetAdd(new ShortAnswerWidget());
                    break;
                case 'Image':
                    this.instantiator.widgetAdd(new ImageWidget());
                    break;
                case 'Itempool':
                    this.instantiator.widgetAdd(new ItempoolWidget());
                    break;
                default:
                    break;
            }
        });

        const heading_container = this.getHeadingContainer(this.heading_text);

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('selector-widget');
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        return container;
    }
}
