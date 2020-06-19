class MarkdownWidget extends Widget {
    constructor() {
        super();
        this.text_area = document.createElement('textarea');

        // Setup text area
        this.text_area.setAttribute('placeholder', 'Enter text or markdown');
    }

    render() {
        // Setup container
        const container = this.getContainer('Markdown');
        container.classList.add('markdown-widget');

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(this.text_area);

        return container;
    }

    getJSON() {
        // If there was no value entered then return nothing
        if (this.text_area.value === '') {
            return;
        }

        return {
            type: 'markdown',
            markdown_string: this.text_area.value
        };
    }
}

