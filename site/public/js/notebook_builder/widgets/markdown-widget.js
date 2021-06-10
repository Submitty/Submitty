class MarkdownWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'markdown',
            markdown_string: ''
        };
    }

    render() {
        // Setup container
        const container = this.getContainer('Markdown');
        container.classList.add('markdown-widget');

        // Add instructional link to the widget title area
        const info_link = document.createElement('a');
        info_link.setAttribute('href', 'https://submitty.org/student/communication/markdown');
        info_link.setAttribute('title', 'Help using markdown');
        info_link.setAttribute('target', '_blank');
        info_link.innerHTML = '<i class="far fa-question-circle"></i>';
        container.querySelector('.heading-container').appendChild(info_link);

        //Add hidden label for screen reader
        const label = document.createElement('label');
        label.setAttribute('for', `notebook-builder-markdown-${NUM_MARKDOWN}`);
        label.style.display = 'none';
        label.innerHTML =  `Markdown Input #${NUM_MARKDOWN}`;

        // Setup text area
        const text_area = document.createElement('textarea');
        text_area.classList.add('markdown-input');
        text_area.setAttribute('placeholder', 'Enter text or markdown');
        text_area.setAttribute('id', `notebook-builder-markdown-${NUM_MARKDOWN}`);
        text_area.value = this.state.markdown_string;
        NUM_MARKDOWN++;

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(label);
        interactive_area.appendChild(text_area);

        this.dom_pointer = container;
        return container;
    }

    commitState() {
        const text_area = this.dom_pointer.querySelector('.markdown-input');
        this.state.markdown_string = text_area.value;
    }

    getJSON() {
        this.commitState();

        if (this.state.markdown_string !== '') {
            return this.state;
        }
    }

    load(data) {
        this.state = data;
    }
}

