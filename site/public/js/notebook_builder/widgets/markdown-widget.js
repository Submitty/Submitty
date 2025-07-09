/* global Widget, NUM_MARKDOWN, buildUrl, csrfToken, displayErrorMessage */
/* exported MarkdownWidget */

class MarkdownWidget extends Widget {
    constructor() {
        super();

        this.dom_pointer;

        this.state = {
            type: 'markdown',
            markdown_string: '',
        };
    }

    render() {
        // Setup container
        const container = this.getContainer('Markdown');
        container.classList.add('markdown-widget');

        // Add hidden label for screen reader
        const label = document.createElement('label');
        label.setAttribute('for', `notebook-builder-markdown-${NUM_MARKDOWN}`);
        label.style.display = 'none';

        label.innerText = `Markdown Input #${NUM_MARKDOWN}`;

        // Setup interactive area
        const interactive_area = container.getElementsByClassName('interactive-container')[0];
        interactive_area.appendChild(label);

        $.ajax({
            url: buildUrl(['markdown', 'area']),
            type: 'POST',
            data: {
                data: {
                    markdown_area_id: `notebook-builder-markdown-${NUM_MARKDOWN}`,
                    markdown_area_name: '',
                    markdown_area_value: this.state.markdown_string,
                    class: 'markdown-input',
                    placeholder: 'Enter text or markdown...',
                    preview_div_id: `notebook-builder-markdown-preview-${NUM_MARKDOWN}`,
                    preview_div_name: `notebook-builder-markdown-preview-${NUM_MARKDOWN}`,
                    preview_button_id: `notebook-builder-preview-button-${NUM_MARKDOWN}`,
                    render_header: true,
                    min_height: '100px',
                },
                csrf_token: csrfToken,
            },
            success: function (data) {
                $(interactive_area).append(data);
            },
            error: function () {
                displayErrorMessage('Something went wrong while trying to preview markdown. Please try again.');
            },
        });

        // eslint-disable-next-line no-global-assign
        NUM_MARKDOWN++;
        this.dom_pointer = container;
        return container;
    }

    commitState() {
        const text_area = this.dom_pointer.querySelector('.markdown-input');
        text_area.value ? this.state.markdown_string = text_area.value : delete this.state.markdown_string;
    }

    getJSON() {
        this.commitState();

        if (this.state) {
            return this.state;
        }
    }

    load(data) {
        this.state = data;
    }
}
