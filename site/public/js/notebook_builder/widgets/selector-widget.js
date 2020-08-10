class SelectorWidget extends Widget {
    /**
     * A widget which facilitates the adding of other widgets to the form.
     *
     * @param {RootBuilder|ItempoolBuilder} builder The builder which is instantiating this widget.
     * @param {String} heading_text Heading text to show in the selector widget.
     */
    constructor(builder, heading_text) {
        super(builder);
        
        this.builder = builder;

        this.heading_text = heading_text;

        this.options = ['Multiple Choice', 'Markdown', 'Short Answer', 'Image'];

        if (builder.constructor.name === 'RootBuilder') {
            this.options.push('Itempool');
            this.options.push('Item');
        }
    }

    render() {
        const interactive_container = this.getInteractiveContainer();
        this.options.forEach(option => {
            const button = this.getButton(option);
            interactive_container.appendChild(button);
        });

        interactive_container.onclick = event => {
            switch (event.target.value) {
                case 'Multiple Choice':
                    this.builder.widgetAdd(new MultipleChoiceWidget(this.builder));
                    break;
                case 'Markdown':
                    this.builder.widgetAdd(new MarkdownWidget(this.builder));
                    break;
                case 'Short Answer':
                    this.builder.widgetAdd(new ShortAnswerWidget(this.builder));
                    break;
                case 'Image':
                    this.builder.widgetAdd(new ImageWidget(this.builder));
                    break;
                case 'Itempool':
                    this.builder.widgetAdd(new ItempoolWidget(this.builder));
                    break;
                case 'Item':
                    break;
                default:
                    break;
            }
        };

        const heading_container = this.getHeadingContainer(this.heading_text);

        const container = document.createElement('div');
        container.classList.add('notebook-builder-widget');
        container.classList.add('selector-widget');
        container.appendChild(heading_container);
        container.appendChild(interactive_container);

        return container;
    }
}
