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

        // Setup markdown preview button
        const preview_button = document.createElement('button');
        preview_button.setAttribute('title', 'Preview Markdown');
        preview_button.setAttribute('type', 'button');
        preview_button.setAttribute('class', 'btn btn-default btn-markdown key_to_click');
        preview_button.setAttribute('tabIndex', '0');
        preview_button.onclick = previewNotebookBuilderMarkdown.bind(preview_button);
        preview_button.innerHTML = 'Preview <i class="fas fa-eye fa-1x"></i>';
        container.querySelector('.heading-container').appendChild(preview_button);

        // Add instructional link to the widget title area
        const info_link = document.createElement('a');
        info_link.setAttribute('href', 'https://submitty.org/student/communication/markdown');
        info_link.setAttribute('title', 'Help using markdown');
        info_link.setAttribute('target', '_blank');
        info_link.innerHTML = '<i class="far fa-question-circle"></i>';
        container.querySelector('.heading-container').appendChild(info_link);

        // Setup text area
        const text_area = document.createElement('textarea');
        text_area.classList.add('markdown-input');
        text_area.setAttribute('placeholder', 'Enter text or markdown');
        text_area.value = this.state.markdown_string;

        // Setup markdown preview
        const preview_element = document.createElement('pre');
        //  <pre id="queue_announcement_message_preview" name="queue_announcement_message_preview" class="fill-available markdown-preview" style="resize:none;min-height:{{ min_height }};max-height:300px;" hidden></pre>
        //TODO: NEED TO HAVE PR #6548 merged first to be able to fill out this id
        preview_element.setAttribute('id', '');

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
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

function previewNotebookBuilderMarkdown() {

}